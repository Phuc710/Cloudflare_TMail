<?php
declare(strict_types=1);

namespace KaiMail\Core\Services;

use PDO;
use KaiMail\Core\Http\ApiException;

/**
 * Multi-tenant API Token Service.
 * Manages dynamic client API keys, secrets, rate limits, and lifecycle.
 */
final class TokenService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->ensureTable();
    }

    public function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `api_tokens` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `key_id` VARCHAR(48) UNIQUE NOT NULL,
            `secret_key` VARCHAR(64) NOT NULL,
            `rate_limit_per_min` INT DEFAULT 120,
            `total_requests` BIGINT DEFAULT 0,
            `last_used_at` DATETIME NULL,
            `expires_at` DATETIME NULL,
            `status` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_key_id (`key_id`),
            INDEX idx_status (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        try {
            $this->db->exec($sql);
        } catch (\Throwable $e) {
            error_log('TokenService ensureTable error: ' . $e->getMessage());
        }
    }

    /**
     * List all API tokens for management.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listAll(): array
    {
        $sql = "SELECT id, name, key_id, secret_key, rate_limit_per_min, total_requests, 
                       last_used_at, expires_at, status, created_at 
                FROM api_tokens 
                ORDER BY id DESC";

        return $this->db->query($sql)->fetchAll() ?: [];
    }

    /**
     * Find a token by its primary ID.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, name, key_id, secret_key, rate_limit_per_min, total_requests, 
                    last_used_at, expires_at, status, created_at 
             FROM api_tokens 
             WHERE id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Find an active or lookup token by its public Key ID (used during HMAC verification).
     */
    public function findByKeyId(string $keyId): ?array
    {
        $keyId = trim($keyId);
        if ($keyId === '') {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT id, name, key_id, secret_key, rate_limit_per_min, total_requests, 
                    last_used_at, expires_at, status 
             FROM api_tokens 
             WHERE key_id = ?"
        );
        $stmt->execute([$keyId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Create a new API Token.
     *
     * @param string $name Token descriptive name (e.g. "Bot MMO TikTok #1")
     * @param int $rateLimit Custom requests per minute limit
     * @param string|null $expiresAt Expiration datetime (Y-m-d H:i:s) or null for permanent
     * @return array Newly created token record
     */
    public function create(string $name, int $rateLimit = 120, ?string $expiresAt = null): array
    {
        $name = trim($name);
        if ($name === '') {
            throw ApiException::badRequest('Tên token không được để trống');
        }

        if (mb_strlen($name) > 100) {
            throw ApiException::badRequest('Tên token tối đa 100 ký tự');
        }

        if ($rateLimit <= 0) {
            $rateLimit = 120;
        }

        // Validate expiration format if provided
        $normalizedExpiresAt = null;
        if ($expiresAt !== null && trim($expiresAt) !== '') {
            $ts = strtotime($expiresAt);
            if ($ts === false || $ts < time()) {
                throw ApiException::badRequest('Thời gian hết hạn không hợp lệ hoặc đã qua');
            }
            $normalizedExpiresAt = date('Y-m-d H:i:s', $ts);
        }

        // Generate cryptographically secure keys
        $keyId = 'km_live_' . bin2hex(random_bytes(16));
        $secretKey = 'km_sec_' . bin2hex(random_bytes(24));

        $stmt = $this->db->prepare(
            "INSERT INTO api_tokens (name, key_id, secret_key, rate_limit_per_min, expires_at, status) 
             VALUES (?, ?, ?, ?, ?, 1)"
        );
        $stmt->execute([$name, $keyId, $secretKey, $rateLimit, $normalizedExpiresAt]);

        $newId = (int) $this->db->lastInsertId();
        $token = $this->find($newId);

        if ($token === null) {
            throw ApiException::internal('Không thể lấy thông tin token vừa tạo');
        }

        return $token;
    }

    /**
     * Update active status (1: Active, 0: Disabled).
     */
    public function updateStatus(int $id, int $status): bool
    {
        $token = $this->find($id);
        if ($token === null) {
            throw ApiException::notFound('Không tìm thấy token');
        }

        $newStatus = $status === 1 ? 1 : 0;
        $stmt = $this->db->prepare("UPDATE api_tokens SET status = ? WHERE id = ?");
        return $stmt->execute([$newStatus, $id]);
    }

    /**
     * Update token details (name, rate_limit_per_min, expires_at, status).
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return array Updated token record
     */
    public function update(int $id, array $data): array
    {
        $token = $this->find($id);
        if ($token === null) {
            throw ApiException::notFound('Không tìm thấy token');
        }

        $fields = [];
        $params = [];

        if (isset($data['name'])) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw ApiException::badRequest('Tên token không được để trống');
            }
            if (mb_strlen($name) > 100) {
                throw ApiException::badRequest('Tên token tối đa 100 ký tự');
            }
            $fields[] = '`name` = ?';
            $params[] = $name;
        }

        if (isset($data['rate_limit_per_min'])) {
            $rateLimit = (int) $data['rate_limit_per_min'];
            if ($rateLimit <= 0) {
                $rateLimit = 120;
            }
            $fields[] = '`rate_limit_per_min` = ?';
            $params[] = $rateLimit;
        }

        if (array_key_exists('expires_at', $data)) {
            $expiresAt = $data['expires_at'];
            if ($expiresAt === null || trim((string) $expiresAt) === '' || $expiresAt === 'never') {
                $fields[] = '`expires_at` = NULL';
            } else {
                $ts = strtotime((string) $expiresAt);
                if ($ts === false || $ts < time()) {
                    throw ApiException::badRequest('Thời gian hết hạn không hợp lệ hoặc đã qua');
                }
                $fields[] = '`expires_at` = ?';
                $params[] = date('Y-m-d H:i:s', $ts);
            }
        }

        if (isset($data['status'])) {
            $status = (int) $data['status'] === 1 ? 1 : 0;
            $fields[] = '`status` = ?';
            $params[] = $status;
        }

        if (empty($fields)) {
            return $token;
        }

        $params[] = $id;
        $sql = "UPDATE api_tokens SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $updated = $this->find($id);
        if ($updated === null) {
            throw ApiException::internal('Không thể nạp thông tin token sau cập nhật');
        }

        return $updated;
    }

    /**
     * Regenerate / Rotate Secret Key for a token.
     *
     * @param int $id
     * @return array Updated token record with newly generated secret_key
     */
    public function regenerateSecret(int $id): array
    {
        $token = $this->find($id);
        if ($token === null) {
            throw ApiException::notFound('Không tìm thấy token');
        }

        $newSecret = 'km_sec_' . bin2hex(random_bytes(24));
        $stmt = $this->db->prepare("UPDATE api_tokens SET secret_key = ? WHERE id = ?");
        $stmt->execute([$newSecret, $id]);

        $updated = $this->find($id);
        if ($updated === null) {
            throw ApiException::internal('Không thể nạp thông tin token sau khi tạo lại secret');
        }

        return $updated;
    }

    /**
     * Revoke / Delete a token permanently.
     */
    public function delete(int $id): bool
    {
        $token = $this->find($id);
        if ($token === null) {
            throw ApiException::notFound('Không tìm thấy token');
        }

        $stmt = $this->db->prepare("DELETE FROM api_tokens WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Increment usage counter and record last access time.
     */
    public function recordUsage(int $id): void
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE api_tokens 
                 SET total_requests = total_requests + 1, last_used_at = NOW() 
                 WHERE id = ?"
            );
            $stmt->execute([$id]);
        } catch (\Throwable $e) {
            error_log('TokenService::recordUsage failed: ' . $e->getMessage());
        }
    }
}
