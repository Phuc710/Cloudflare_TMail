<?php
declare(strict_types=1);

namespace KaiMail\Core\Services;

use PDO;
use PDOException;
use KaiMail\Core\Http\ApiException;

/**
 * Clean, senior-grade Email management service.
 * Perfectly aligned with the active database schema (no legacy expiry fields).
 */
final class EmailService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Create emails in batch or single.
     *
     * @param array $options {
     *   name_type?: string,
     *   email?: string,
     *   domain?: string,
     *   quantity?: int,
     * }
     * @param int $maxAllowed Max quantity ceiling based on caller permission (e.g. 10 for Bot, 50 for Admin)
     */
    public function createEmails(array $options, int $maxAllowed = 50, string $createdBy = 'user', ?string $note = null): array
    {
        $nameType = strtolower(trim((string) ($options['name_type'] ?? 'en')));
        $customEmail = strtolower(trim((string) ($options['email'] ?? ($options['custom_email'] ?? ''))));
        $domain = strtolower(trim((string) ($options['domain'] ?? '')));
        $requestedQuantity = (int) ($options['quantity'] ?? ($options['count'] ?? 1));
        $quantity = min(max(1, $requestedQuantity), $maxAllowed);

        $noteToSave = isset($options['note']) ? trim((string) $options['note']) : ($note ? trim($note) : null);
        $source = !empty($options['created_by']) ? strtolower(trim((string) $options['created_by'])) : strtolower(trim($createdBy));
        if (!in_array($source, ['admin', 'user', 'api'], true)) {
            $source = 'user';
        }

        if (!in_array($nameType, ['vn', 'en', 'custom'], true)) {
            throw ApiException::badRequest('Loại tên không hợp lệ. Phải là: vn, en, hoặc custom');
        }

        if ($nameType === 'custom') {
            if ($customEmail === '') {
                throw ApiException::badRequest('Vui lòng nhập tên email tùy chỉnh');
            }
            if (!preg_match('/^[a-z0-9\-\._]+$/', $customEmail)) {
                throw ApiException::badRequest('Tên email chỉ được chứa chữ cái, chữ số, dấu gạch ngang, chấm và gạch dưới');
            }
        }

        // Resolve domain(s)
        if ($domain === '') {
            $stmt = $this->db->query("SELECT id, domain FROM domains WHERE is_active = 1");
            $activeDomainsList = $stmt->fetchAll();
            if (empty($activeDomainsList)) {
                throw ApiException::badRequest('Không có domain nào đang hoạt động trong hệ thống');
            }
        } else {
            $stmt = $this->db->prepare("SELECT id, domain FROM domains WHERE domain = ? AND is_active = 1");
            $stmt->execute([$domain]);
            $domainRow = $stmt->fetch();
            if (!$domainRow) {
                throw ApiException::badRequest("Domain không tồn tại hoặc đã bị tắt: {$domain}");
            }
            $activeDomainsList = [$domainRow];
        }

        $createdEmails = [];
        $errors = [];

        for ($i = 0; $i < $quantity; $i++) {
            $attempt = $i + 1;

            // Pick random active domain when not explicitly specified, or use the specified one
            $chosenDomainRow = $activeDomainsList[array_rand($activeDomainsList)];
            $chosenDomainId = (int) $chosenDomainRow['id'];
            $chosenDomainName = strtolower((string) $chosenDomainRow['domain']);

            if ($nameType === 'custom') {
                $username = $customEmail . ($quantity > 1 ? '_' . $attempt : '');
                $actualNameType = 'custom';
            } else {
                $username = NameGenerator::generateUsername($nameType);
                $actualNameType = $nameType;
            }

            $email = strtolower($username . '@' . $chosenDomainName);

            // Fast duplicate check
            $checkStmt = $this->db->prepare("SELECT id FROM emails WHERE email = ? LIMIT 1");
            $checkStmt->execute([$email]);
            if ($checkStmt->fetch()) {
                $errors[] = "[{$attempt}/{$quantity}] Email đã tồn tại: {$email}";
                continue;
            }

            try {
                $insertStmt = $this->db->prepare("
                    INSERT INTO emails (domain_id, email, name_type, is_done, created_by, note)
                    VALUES (?, ?, ?, 0, ?, ?)
                ");
                $insertStmt->execute([$chosenDomainId, $email, $actualNameType, $source, $noteToSave]);

                $createdEmails[] = [
                    'id' => (int) $this->db->lastInsertId(),
                    'email' => $email,
                    'name_type' => $actualNameType,
                    'created_by' => $source,
                    'note' => $noteToSave,
                ];
            } catch (PDOException $e) {
                $msg = strtolower($e->getMessage());
                if ((int) $e->getCode() === 23000 || str_contains($msg, 'duplicate') || str_contains($msg, 'unique')) {
                    $errors[] = "[{$attempt}/{$quantity}] Email đã tồn tại: {$email}";
                } else {
                    $errors[] = "[{$attempt}/{$quantity}] Tạo thất bại: {$email}";
                    error_log("EmailService::createEmails error: " . $e->getMessage());
                }
            }
        }

        return [
            'created' => $createdEmails,
            'count' => count($createdEmails),
            'errors' => $errors,
        ];
    }

    /**
     * Find email account by address.
     */
    public function findEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT id, domain_id, email, name_type, is_done, created_by, note, created_at
            FROM emails
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Find email account by ID.
     */
    public function findEmailById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT id, domain_id, email, name_type, is_done, created_by, note, created_at
            FROM emails
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Paginated list of emails with optional filtering.
     */
    public function listEmails(
        int $page = 1,
        int $limit = 13,
        ?string $search = null,
        ?string $domain = null,
        bool $noMessageOnly = false,
        ?string $createdBy = null,
        ?string $status = null
    ): array {
        $page = max(1, $page);
        $limit = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $where[] = '(e.email LIKE ? OR e.note LIKE ?)';
            $params[] = $term;
            $params[] = $term;
        }

        if ($domain !== null && trim($domain) !== '') {
            $where[] = 'e.email LIKE ?';
            $params[] = '%@' . trim($domain);
        }

        if ($noMessageOnly) {
            $where[] = 'NOT EXISTS (SELECT 1 FROM messages m WHERE m.email_id = e.id)';
        }

        if ($createdBy !== null && $createdBy !== '' && $createdBy !== 'all') {
            if ($createdBy === 'admin') {
                $where[] = "e.created_by = 'admin'";
            } elseif ($createdBy === 'user') {
                $where[] = "(e.created_by = 'user' OR e.created_by IS NULL OR e.created_by = '')";
            } elseif ($createdBy === 'api') {
                $where[] = "e.created_by = 'api'";
            }
        }

        if ($status !== null && $status !== '' && $status !== 'all') {
            if ($status === 'active' || $status === '0') {
                $where[] = "e.is_done = 0";
            } elseif ($status === 'done' || $status === '1') {
                $where[] = "e.is_done = 1";
            }
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total count
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM emails e {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Paginated rows
        $sql = "
            SELECT e.id, e.domain_id, e.email, e.name_type, e.is_done, e.created_by, e.note, e.created_at,
                   UNIX_TIMESTAMP(e.created_at) as created_ts,
                   (SELECT COUNT(*) FROM messages m WHERE m.email_id = e.id) as message_count,
                   (SELECT COUNT(*) FROM messages m WHERE m.email_id = e.id AND m.is_read = 0) as unread_count
            FROM emails e
            {$whereClause}
            ORDER BY e.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => (int) ceil($total / $limit),
            'emails' => $rows,
            'server_time' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Update note on an email.
     */
    public function updateNote(int $id, string $note): bool
    {
        $cleanNote = trim($note) !== '' ? trim($note) : null;
        $stmt = $this->db->prepare("UPDATE emails SET note = ? WHERE id = ?");
        return $stmt->execute([$cleanNote, $id]);
    }

    /**
     * Toggle is_done status flag.
     */
    public function toggleDone(int $id, int $isDone): bool
    {
        $stmt = $this->db->prepare("UPDATE emails SET is_done = ? WHERE id = ?");
        return $stmt->execute([$isDone ? 1 : 0, $id]);
    }

    /**
     * Delete emails by list of IDs.
     */
    public function deleteEmails(array $ids): int
    {
        $cleanIds = array_values(array_filter(array_map('intval', $ids), fn(int $id) => $id > 0));
        if (empty($cleanIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        $stmt = $this->db->prepare("DELETE FROM emails WHERE id IN ({$placeholders})");
        $stmt->execute($cleanIds);
        return $stmt->rowCount();
    }

    /**
     * Delete email by address.
     */
    public function deleteByAddress(string $email): int
    {
        $cleanEmail = strtolower(trim($email));
        $emailData = $this->findEmail($cleanEmail);
        if (!$emailData) {
            return 0;
        }

        $emailId = (int) $emailData['id'];
        try {
            $stmtMsg = $this->db->prepare("DELETE FROM messages WHERE email_id = ?");
            $stmtMsg->execute([$emailId]);
        } catch (\Throwable $e) {
            // Ignore if foreign key cascade handles it or table structure differs
        }

        $stmt = $this->db->prepare("DELETE FROM emails WHERE id = ?");
        $stmt->execute([$emailId]);
        return $stmt->rowCount();
    }
}
