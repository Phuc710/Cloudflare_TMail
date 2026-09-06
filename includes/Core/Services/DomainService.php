<?php
declare(strict_types=1);

namespace KaiMail\Core\Services;

use PDO;
use KaiMail\Core\Http\ApiException;

/**
 * Clean Domain management service.
 */
final class DomainService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listAll(bool $activeOnly = false): array
    {
        $sql = "SELECT id, domain, is_active, created_at FROM domains";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY is_active DESC, domain ASC";

        return $this->db->query($sql)->fetchAll() ?: [];
    }

    public function listActiveNames(): array
    {
        $stmt = $this->db->query("SELECT domain FROM domains WHERE is_active = 1 ORDER BY domain ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT id, domain, is_active, created_at FROM domains WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function findByDomain(string $domain): ?array
    {
        $stmt = $this->db->prepare("SELECT id, domain, is_active, created_at FROM domains WHERE domain = ?");
        $stmt->execute([strtolower(trim($domain))]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(string $domain, int $isActive = 1): int
    {
        $domain = strtolower(trim($domain));

        if ($domain === '') {
            throw ApiException::badRequest('Tên domain là bắt buộc');
        }

        if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
            throw ApiException::badRequest('Định dạng domain không hợp lệ');
        }

        if ($this->findByDomain($domain) !== null) {
            throw ApiException::badRequest('Domain đã tồn tại');
        }

        $stmt = $this->db->prepare("INSERT INTO domains (domain, is_active) VALUES (?, ?)");
        $stmt->execute([$domain, $isActive ? 1 : 0]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, ?int $isActive): bool
    {
        if ($id <= 0) {
            throw ApiException::badRequest('Thiếu ID domain');
        }

        if ($isActive !== null) {
            $stmt = $this->db->prepare("UPDATE domains SET is_active = ? WHERE id = ?");
            return $stmt->execute([$isActive ? 1 : 0, $id]);
        }

        return true;
    }

    public function delete(int $id): bool
    {
        if ($id <= 0) {
            throw ApiException::badRequest('Thiếu ID domain');
        }

        // Integrity check: do not delete domain if emails are attached
        $stmtDomain = $this->db->prepare("SELECT domain FROM domains WHERE id = ?");
        $stmtDomain->execute([$id]);
        $domain = $stmtDomain->fetchColumn();
        if (!$domain) {
            return false;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM emails WHERE email LIKE ?");
        $stmt->execute(['%@' . $domain]);
        $res = $stmt->fetch();
        $count = (int) ($res['count'] ?? 0);

        if ($count > 0) {
            throw ApiException::badRequest("Không thể xóa domain đang có {$count} email", ['email_count' => $count]);
        }

        $deleteStmt = $this->db->prepare("DELETE FROM domains WHERE id = ?");
        return $deleteStmt->execute([$id]);
    }
}
