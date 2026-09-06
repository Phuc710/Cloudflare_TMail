<?php
declare(strict_types=1);

namespace KaiMail\Core\Security;

/**
 * Thread-safe file-based rate limiter with atomic flock and probabilistic GC.
 */
final class RateLimiter
{
    public static function enforce(
        string $scope,
        int $limit,
        int $windowSeconds,
        string $identifier
    ): array {
        $limit = max(1, $limit);
        $windowSeconds = max(1, $windowSeconds);

        $now = time();
        $scope = (string) preg_replace('/[^a-z0-9_\-]/i', '_', strtolower(trim($scope))) ?: 'default';
        $keyHash = hash('sha256', $scope . '|' . $identifier);
        $dir = self::ensureDir();
        $path = $dir . DIRECTORY_SEPARATOR . $scope . '_' . $keyHash . '.json';

        $fp = @fopen($path, 'c+');
        if ($fp === false) {
            return [
                'allowed' => true,
                'limit' => $limit,
                'remaining' => max(0, $limit - 1),
                'retry_after' => 0,
                'reset_at' => $now + $windowSeconds,
            ];
        }

        $windowStart = $now;
        $count = 0;

        try {
            if (!flock($fp, LOCK_EX)) {
                return [
                    'allowed' => true,
                    'limit' => $limit,
                    'remaining' => max(0, $limit - 1),
                    'retry_after' => 0,
                    'reset_at' => $now + $windowSeconds,
                ];
            }

            rewind($fp);
            $raw = stream_get_contents($fp);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($data)) {
                $windowStart = isset($data['window_start']) ? (int) $data['window_start'] : $now;
                $count = isset($data['count']) ? (int) $data['count'] : 0;
            }

            if (($windowStart + $windowSeconds) <= $now) {
                $windowStart = $now;
                $count = 0;
            }

            $count++;
            $allowed = $count <= $limit;
            $retryAfter = max(1, ($windowStart + $windowSeconds) - $now);
            $resetAt = $windowStart + $windowSeconds;
            $remaining = max(0, $limit - min($count, $limit));

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode([
                'window_start' => $windowStart,
                'count' => $count,
                'updated_at' => $now,
                'expires_at' => $resetAt,
            ], JSON_UNESCAPED_UNICODE));
            fflush($fp);
            flock($fp, LOCK_UN);

            self::gc($dir, $now);

            return [
                'allowed' => $allowed,
                'limit' => $limit,
                'remaining' => $remaining,
                'retry_after' => $allowed ? 0 : $retryAfter,
                'reset_at' => $resetAt,
            ];
        } finally {
            fclose($fp);
        }
    }

    private static function ensureDir(): string
    {
        $dir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'rate_limits';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private static function gc(string $dir, int $now): void
    {
        if (mt_rand(1, 100) !== 1) {
            return;
        }

        $files = @glob($dir . DIRECTORY_SEPARATOR . '*.json');
        if (!is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            $raw = @file_get_contents($file);
            if (!is_string($raw) || $raw === '') {
                continue;
            }
            $data = json_decode($raw, true);
            $expiresAt = is_array($data) ? (int) ($data['expires_at'] ?? 0) : 0;
            if ($expiresAt > 0 && $expiresAt < ($now - 30)) {
                @unlink($file);
            }
        }
    }
}
