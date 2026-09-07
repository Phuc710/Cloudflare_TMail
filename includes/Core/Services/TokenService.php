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
