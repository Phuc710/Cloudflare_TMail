<?php
declare(strict_types=1);

/**
 * PSR-4 Autoloader for KaiMail\Core
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'KaiMail\\Core\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

if (!function_exists('asset_url')) {
    function asset_url(string $path): string {
        return \KaiMail\Core\Services\AssetService::url($path);
    }
}

