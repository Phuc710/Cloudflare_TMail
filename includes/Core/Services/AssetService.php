<?php
declare(strict_types=1);

namespace KaiMail\Core\Services;

/**
 * Enterprise Asset Manager for Content Hashing & Cache Busting.
 * Automatically resolves hashed assets from static/manifest.json when built,
 * with zero-config fallback to query string versioning (?v=filemtime).
 */
final class AssetService
{
    private static ?array $manifest = null;
    private static ?string $rootDir = null;

    public static function setRootDir(string $rootDir): void
    {
        self::$rootDir = rtrim($rootDir, '/\\');
        self::$manifest = null; // reset cache if rootDir changes
    }

    public static function getRootDir(): string
    {
        if (self::$rootDir === null) {
            // Default: 3 levels up from includes/Core/Services -> project root
            self::$rootDir = dirname(__DIR__, 3);
        }
        return self::$rootDir;
    }

    /**
     * Resolve public URL for an asset with immutable content hash or fallback version.
     */
    public static function url(string $assetPath): string
    {
        $assetPath = trim($assetPath);
        if ($assetPath === '') {
            return rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/');
        }

        // Leave absolute URLs untouched
        if (str_starts_with($assetPath, 'http://') || str_starts_with($assetPath, 'https://') || str_starts_with($assetPath, '//')) {
            return $assetPath;
        }

        $base = rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/');
        $normalized = str_starts_with($assetPath, '/') ? $assetPath : '/' . $assetPath;

        // 1. Try loading manifest.json
        if (self::$manifest === null) {
            $manifestPath = self::getRootDir() . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'manifest.json';
            if (is_file($manifestPath)) {
                $raw = @file_get_contents($manifestPath);
                $decoded = $raw !== false ? json_decode($raw, true) : null;
                self::$manifest = is_array($decoded) ? $decoded : [];
            } else {
                self::$manifest = [];
            }
        }

        // 2. Check if asset exists in manifest
        if (isset(self::$manifest[$normalized])) {
            $hashed = (string) self::$manifest[$normalized];
            $hashedPath = str_starts_with($hashed, '/') ? $hashed : '/' . $hashed;
            return $base . $hashedPath;
        }

        // 3. Fallback: filemtime query param
        $relPath = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $normalized), DIRECTORY_SEPARATOR);
        $localPath = self::getRootDir() . DIRECTORY_SEPARATOR . $relPath;

        if (is_file($localPath)) {
            $version = (string) (@filemtime($localPath) ?: time());
            return $base . $normalized . '?v=' . rawurlencode($version);
        }

        return $base . $normalized;
    }
}
