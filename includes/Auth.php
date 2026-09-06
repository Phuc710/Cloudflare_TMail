<?php
declare(strict_types=1);

/**
 * Authentication Helper
 * KaiMail - Temp Mail System  
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

class Auth
{
    private const COOKIE_NAME = 'kaimail_admin_token';
    private const COOKIE_LIFETIME = 30 * 86400; // 30 days

    /**
     * Check if request is HTTPS
     */
    private static function isHttps(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off') {
            return true;
        }
        return ((string) ($_SERVER['SERVER_PORT'] ?? '')) === '443';
    }

    /**
     * Start session with secure parameters
     */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            $secure = SESSION_COOKIE_SECURE && self::isHttps();

            ini_set('session.use_strict_mode', '1');
            ini_set('session.cookie_httponly', SESSION_COOKIE_HTTP_ONLY ? '1' : '0');
            ini_set('session.cookie_secure', $secure ? '1' : '0');

            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path' => SESSION_COOKIE_PATH,
                'domain' => SESSION_COOKIE_DOMAIN,
                'secure' => $secure,
                'httponly' => SESSION_COOKIE_HTTP_ONLY,
                'samesite' => SESSION_COOKIE_SAMESITE,
            ]);

            session_name(SESSION_NAME);
            session_start();
        }
    }

    /**
     * Verify credentials and log in the admin
     */
    public static function login(string $password): bool
    {
        if (hash_equals((string) ADMIN_ACCESS_KEY, $password)) {
            self::startSession();
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = 'admin';

            // Generate cryptographically secure token derived from app secrets
            $token = self::generateToken();
            $secure = SESSION_COOKIE_SECURE && self::isHttps();
            
            $cookieParams = [
                'expires' => time() + self::COOKIE_LIFETIME,
                'path' => SESSION_COOKIE_PATH,
                'domain' => SESSION_COOKIE_DOMAIN,
                'secure' => $secure,
                'httponly' => true, // HttpOnly prevents JavaScript from reading the cookie
                'samesite' => SESSION_COOKIE_SAMESITE,
            ];

            setcookie(self::COOKIE_NAME, $token, $cookieParams);
            
            session_write_close();
            return true;
        }

        return false;
    }

    /**
     * Check if admin is logged in (via active session or valid persistent cookie)
     */
    public static function isLoggedIn(): bool
    {
        self::startSession();
        
        // 1. Check Session
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            session_write_close();
            return true;
        }

        // 2. Check Persistent Cookie
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            $expectedToken = self::generateToken();
            if (hash_equals($expectedToken, $_COOKIE[self::COOKIE_NAME])) {
                // Restore session
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = 'admin';
                session_write_close();
                return true;
            }
        }

        session_write_close();
        return false;
    }

    /**
     * Log out admin by destroying the session and clearing the persistent cookie
     */
    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];

        // Clear session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'],
            ]);
        }

        $secure = SESSION_COOKIE_SECURE && self::isHttps();

        // Clear persistent remember cookie
        setcookie(self::COOKIE_NAME, '', [
            'expires' => time() - 42000,
            'path' => SESSION_COOKIE_PATH,
            'domain' => SESSION_COOKIE_DOMAIN,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => SESSION_COOKIE_SAMESITE,
        ]);

        session_destroy();
    }

    /**
     * Require admin authentication or redirect/error
     */
    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            if (isAjax()) {
                jsonResponse(['error' => 'Unauthorized'], 401);
            } else {
                header('Location: ' . BASE_URL . '/adminkaishop/login');
                exit;
            }
        }
    }

    /**
     * Generate secure token derived from ADMIN_ACCESS_KEY and API_SECRET_KEY
     */
    private static function generateToken(): string
    {
        return hash_hmac('sha256', 'admin_session', (string) ADMIN_ACCESS_KEY);
    }
}
