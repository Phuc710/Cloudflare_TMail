<?php

require_once __DIR__ . '/env.php';

loadEnvFile(dirname(__DIR__) . '/.env');

$defaultEnvironment = 'development';
$environment = env('APP_ENV', $defaultEnvironment);

if (!is_string($environment) || $environment === '') {
    $environment = $defaultEnvironment;
}

define('ENVIRONMENT', $environment);
define('IS_PRODUCTION', ENVIRONMENT === 'production');

$configuredBaseUrl = (string) env('APP_BASE_URL', 'http://localhost/kaiMail');
$requestHost = '';
$requestScheme = 'http';

if (PHP_SAPI !== 'cli') {
    $requestHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $https = $_SERVER['HTTPS'] ?? '';
    $serverPort = (string) ($_SERVER['SERVER_PORT'] ?? '');
    $requestScheme = (!empty($https) && strtolower((string) $https) !== 'off') || $serverPort === '443'
        ? 'https'
        : 'http';
}

if ($requestHost !== '') {
    $parsedBaseUrl = parse_url($configuredBaseUrl);
    $configuredHost = strtolower((string) ($parsedBaseUrl['host'] ?? ''));
    $configuredPath = rtrim((string) ($parsedBaseUrl['path'] ?? ''), '/');
    $requestHostWithoutPort = strtolower((string) preg_replace('/:\d+$/', '', $requestHost));

    // Keep links/API calls on the same host as the current request to avoid CORS/session issues.
    if ($configuredHost !== '' && $configuredHost !== $requestHostWithoutPort) {
        if ($configuredPath === '' && in_array($requestHostWithoutPort, ['localhost', '127.0.0.1', '::1'], true)) {
            $projectRoot = str_replace('\\', '/', dirname(__DIR__));
            $scriptFilename = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
            $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

            $relScript = substr($scriptFilename, strlen($projectRoot));
            if ($relScript !== false && $relScript !== '' && str_ends_with($scriptName, $relScript)) {
                $detectedPath = substr($scriptName, 0, -strlen($relScript));
            } else {
                $docRoot = str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
                if ($docRoot !== '' && stripos($projectRoot, $docRoot) === 0) {
                    $detectedPath = substr($projectRoot, strlen($docRoot));
                } else {
                    $detectedPath = '';
                }
            }
            $configuredPath = ($detectedPath === '/' || $detectedPath === false) ? '' : rtrim($detectedPath, '/');
        }
        $configuredBaseUrl = $requestScheme . '://' . $requestHost . $configuredPath;
    }
}

define('BASE_URL', rtrim($configuredBaseUrl, '/'));

define('DB_HOST', (string) env('DB_HOST', 'localhost'));
define('DB_NAME', (string) env('DB_NAME', 'kaimail'));
define('DB_USER', (string) env('DB_USER', 'root'));
define('DB_PASS', (string) env('DB_PASS', ''));
define('DB_CHARSET', (string) env('DB_CHARSET', 'utf8mb4'));
define('DB_TIMEZONE', (string) env('DB_TIMEZONE', '+07:00'));

define('WEBHOOK_SECRET', envRequired('WEBHOOK_SECRET'));
define('API_ACCESS_KEY', envRequired('API_ACCESS_KEY'));
define('API_SECRET_KEY', envRequired('API_SECRET_KEY'));
define('API_REQUEST_TTL', max(30, (int) env('API_REQUEST_TTL', 300)));
define('API_REQUIRE_HTTPS', (bool) env('API_REQUIRE_HTTPS', IS_PRODUCTION));
define('API_ALLOWED_IPS', trim((string) env('API_ALLOWED_IPS', '')));
define('API_ALLOW_SESSION_FALLBACK', (bool) env('API_ALLOW_SESSION_FALLBACK', !IS_PRODUCTION));
define('API_REQUIRE_NONCE', (bool) env('API_REQUIRE_NONCE', true));
define('API_NONCE_TTL', max(30, (int) env('API_NONCE_TTL', API_REQUEST_TTL)));
define('API_RATE_LIMIT_PER_MIN', max(10, (int) env('API_RATE_LIMIT_PER_MIN', 120)));
define('API_ENFORCE_IP_POLICY', (bool) env('API_ENFORCE_IP_POLICY', false));
// Strict mode must be explicitly enabled in .env to avoid accidental production lockout.
define('API_STRICT_MODE', (bool) env('API_STRICT_MODE', false));
define('API_TRUST_PROXY_HEADERS', (bool) env('API_TRUST_PROXY_HEADERS', false));
define('ADMIN_REQUIRE_HTTPS', (bool) env('ADMIN_REQUIRE_HTTPS', IS_PRODUCTION));
define('ADMIN_ALLOWED_IPS', trim((string) env('ADMIN_ALLOWED_IPS', API_ALLOWED_IPS)));
define('ADMIN_RATE_LIMIT_PER_MIN', max(5, (int) env('ADMIN_RATE_LIMIT_PER_MIN', 60)));
define('ADMIN_LOGIN_RATE_LIMIT_PER_MIN', max(3, (int) env('ADMIN_LOGIN_RATE_LIMIT_PER_MIN', 15)));
define('WEBHOOK_LOG_FILE', dirname(__DIR__) . '/storage/logs/webhook.log');
define('ADMIN_ACCESS_KEY', envRequired('ADMIN_ACCESS_KEY'));
define('ADMIN_DEFAULT_ID', 1);
define('ADMIN_DEFAULT_USERNAME', 'admin');

define('SESSION_NAME', (string) env('SESSION_NAME', 'kaimail_session'));
define('SESSION_LIFETIME', (int) env('SESSION_LIFETIME', 86400));
$sessionCookieSameSite = (string) env('SESSION_COOKIE_SAMESITE', 'Lax');
if (!in_array($sessionCookieSameSite, ['Lax', 'Strict', 'None'], true)) {
    $sessionCookieSameSite = 'Lax';
}
define('SESSION_COOKIE_SECURE', (bool) env('SESSION_COOKIE_SECURE', IS_PRODUCTION));
define('SESSION_COOKIE_HTTP_ONLY', (bool) env('SESSION_COOKIE_HTTP_ONLY', true));
define('SESSION_COOKIE_SAMESITE', $sessionCookieSameSite);
define('SESSION_COOKIE_PATH', (string) env('SESSION_COOKIE_PATH', '/'));
$sessionCookieDomain = trim((string) env('SESSION_COOKIE_DOMAIN', ''));

if ($sessionCookieDomain !== '' && $requestHost !== '') {
    $requestHostWithoutPort = strtolower((string) preg_replace('/:\d+$/', '', $requestHost));
    $normalizedCookieDomain = strtolower(ltrim($sessionCookieDomain, '.'));
    $domainMatches = $requestHostWithoutPort === $normalizedCookieDomain
        || str_ends_with($requestHostWithoutPort, '.' . $normalizedCookieDomain);

    // Fallback to host-only cookie when configured domain doesn't match current host.
    if (!$domainMatches) {
        $sessionCookieDomain = '';
    }
}

define('SESSION_COOKIE_DOMAIN', $sessionCookieDomain);

define('APP_TIMEZONE', (string) env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'));
date_default_timezone_set(APP_TIMEZONE);

define('LONG_POLL_MAX_SECONDS', 25);
define('LONG_POLL_SLEEP_SECONDS', 1);

define('EXPOSE_ERROR_DETAILS', (bool) env('EXPOSE_ERROR_DETAILS', !IS_PRODUCTION));

$displayErrors = env('DISPLAY_ERRORS', ENVIRONMENT === 'development');
ini_set('display_errors', $displayErrors ? '1' : '0');
error_reporting(E_ALL);
