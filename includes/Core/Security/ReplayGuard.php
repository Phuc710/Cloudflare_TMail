<?php
declare(strict_types=1);

namespace KaiMail\Core\Security;

/**
 * Replay attack prevention via atomic Nonce verification.
 */
final class ReplayGuard
{
    public static function verifyNonce(string $scope, string $nonce, int $ttlSeconds): bool
    {
        $nonce = trim($nonce);
        if (!preg_match('/^[A-Za-z0-9._-]{16,128}$/', $nonce)) {
            return false;
        }

        $ttlSeconds = max(30, $ttlSeconds);
        $now = time();
        $expiresAt = $now + $ttlSeconds;

        $scope = (string) preg_replace('/[^a-z0-9_\-]/i', '_', strtolower(trim($scope))) ?: 'default';
        $hash = hash('sha256', $scope . '|' . $nonce);
        $dir = self::ensureDir();
        $path = $dir . DIRECTORY_SEPARATOR . $hash . '.nonce';

        // Fast path: new nonce file creation
        $fp = @fopen($path, 'x');
        if ($fp !== false) {
            fwrite($fp, (string) $expiresAt);
            fclose($fp);
            self::gc($dir, $now);
            return true;
        }

        // Existing nonce file: allow reuse only if already expired
        $currentExpiryRaw = @file_get_contents($path);
        $currentExpiry = (int) trim((string) $currentExpiryRaw);
        if ($currentExpiry > $now) {
            return false;
        }

        @unlink($path);
        $fp = @fopen($path, 'x');
        if ($fp === false) {
            return false;
        }

        fwrite($fp, (string) $expiresAt);
        fclose($fp);
        self::gc($dir, $now);
        return true;
    }

    private static function ensureDir(): string
    {
        $dir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'nonces';
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

        $files = @glob($dir . DIRECTORY_SEPARATOR . '*.nonce');
        if (!is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            $expiry = (int) trim((string) @file_get_contents($file));
            if ($expiry > 0 && $expiry < ($now - 30)) {
                @unlink($file);
            }
        }
    }
}
