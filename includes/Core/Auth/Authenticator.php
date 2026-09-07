<?php
declare(strict_types=1);

namespace KaiMail\Core\Auth;

use KaiMail\Core\App;
use KaiMail\Core\Http\ApiException;
use KaiMail\Core\Http\Request;
use KaiMail\Core\Security\RateLimiter;
use KaiMail\Core\Security\ReplayGuard;
use KaiMail\Core\Services\TokenService;

/**
 * Unified multi-strategy authentication pipeline with rate-limiting & network checks.
 */
final class Authenticator
{
    /**
     * Resolve the AuthContext for the incoming request without throwing.
     */
    public static function resolve(Request $request): AuthContext
    {
        // 1. Check Admin Access Key Header
        $adminHeaderKey = $request->getHeader('X-ADMIN-ACCESS-KEY');
        if ($adminHeaderKey !== '') {
            if (hash_equals((string) ADMIN_ACCESS_KEY, $adminHeaderKey)) {
                return new AuthContext(
                    Role::ADMIN,
                    'admin_key',
                    'admin_access_key',
                    Permission::forRole(Role::ADMIN),
                    ['ip' => $request->getClientIp()]
                );
            }
        }

        // 2. Check Admin Session / Persistent Cookie
        if (self::checkAdminSession()) {
            return new AuthContext(
                Role::ADMIN,
                'admin_session',
                'admin_cookie',
                Permission::forRole(Role::ADMIN),
                ['ip' => $request->getClientIp()]
            );
        }

        // 3. Check Webhook Secret Header
        $webhookSecret = $request->getHeader('X-Webhook-Secret');
        if ($webhookSecret !== '') {
            $configuredWebhook = defined('WEBHOOK_SECRET') ? (string) WEBHOOK_SECRET : (string) getenv('WEBHOOK_SECRET');
            if ($configuredWebhook !== '' && hash_equals($configuredWebhook, $webhookSecret)) {
                return new AuthContext(
                    Role::WEBHOOK,
                    'cloudflare_worker',
                    'webhook_secret',
                    Permission::forRole(Role::WEBHOOK),
                    ['ip' => $request->getClientIp()]
                );
            }
        }

        // 4. Check External API HMAC Headers
        $apiKey = $request->getHeader('X-API-KEY');
        $apiTimestamp = $request->getHeader('X-API-TIMESTAMP');
        $apiNonce = $request->getHeader('X-API-NONCE');
        $apiSignature = $request->getHeader('X-API-SIGNATURE');

        if ($apiKey !== '' && $apiTimestamp !== '' && $apiSignature !== '') {
            $hmacResult = self::verifyHmac($request, $apiKey, $apiTimestamp, $apiNonce, $apiSignature);
            if ($hmacResult !== false) {
                $metadata = [
                    'key_prefix' => substr($apiKey, 0, 8),
                    'ip' => $request->getClientIp(),
                ];
                if (is_array($hmacResult)) {
                    $metadata['rate_limit'] = (int) ($hmacResult['rate_limit_per_min'] ?? 120);
                    $metadata['token_id'] = (int) $hmacResult['id'];
                    $metadata['token_name'] = (string) ($hmacResult['name'] ?? '');
                }

                return new AuthContext(
                    Role::API_USER,
                    substr($apiKey, 0, 12),
                    'api_hmac',
                    Permission::forRole(Role::API_USER),
                    $metadata
                );
            }
        }

        // 5. Check Public Web UI Session Token
        $webToken = $request->getHeader('X-WEB-UI-TOKEN');
        if ($webToken !== '') {
            if (self::verifyWebUiToken($request, $webToken)) {
                return new AuthContext(
                    Role::PUBLIC_USER,
                    'web_user',
                    'web_ui_token',
                    Permission::forRole(Role::PUBLIC_USER),
                    ['ip' => $request->getClientIp()]
                );
            }
        }

        return AuthContext::anonymous();
    }

    /**
     * Enforce security policies and authentication requirements.
     */
    public static function authenticate(Request $request, ?string $requiredPermission = null): AuthContext
    {
        // Enforce HTTPS in production for protected operations
        if (defined('API_REQUIRE_HTTPS') && API_REQUIRE_HTTPS && !$request->isHttps() && !$request->isLocal()) {
            throw ApiException::forbidden('Bắt buộc sử dụng kết nối HTTPS bảo mật');
        }

        $context = self::resolve($request);

        // Enforce rate limiting based on role
        self::enforceRateLimit($request, $context);

        if ($requiredPermission !== null) {
            Gate::authorize($context, $requiredPermission);
        }

        return $context;
    }

    /**
     * Enforce rate limit per caller role.
     */
    private static function enforceRateLimit(Request $request, AuthContext $context): void
    {
        $ip = $request->getClientIp() ?: 'unknown';
        $role = $context->getRole();

        $customLimit = $context->getMetadata('rate_limit');
        $limit = ($customLimit !== null && (int) $customLimit > 0)
            ? (int) $customLimit
            : match ($role) {
                Role::ADMIN => defined('ADMIN_RATE_LIMIT_PER_MIN') ? (int) ADMIN_RATE_LIMIT_PER_MIN : 60,
                Role::WEBHOOK => 600, // High throughput for inbound emails
                default => defined('API_RATE_LIMIT_PER_MIN') ? (int) API_RATE_LIMIT_PER_MIN : 120,
            };

        $scope = 'rate_' . $role->value;
        $identifier = $ip . '|' . $context->getIdentifier();
        $res = RateLimiter::enforce($scope, $limit, 60, $identifier);

        if (!headers_sent()) {
            header('X-RateLimit-Limit: ' . $res['limit']);
            header('X-RateLimit-Remaining: ' . $res['remaining']);
            header('X-RateLimit-Reset: ' . $res['reset_at']);
        }

        if (!$res['allowed']) {
            throw ApiException::tooManyRequests('Bạn đã gửi quá nhiều yêu cầu, vui lòng đợi một lát', $res['retry_after']);
        }
    }

    private static function checkAdminSession(): bool
    {
        if (PHP_SAPI === 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        require_once __DIR__ . '/../../Auth.php';
        return \Auth::isLoggedIn();
    }

    /**
     * @return array<string, mixed>|bool Returns token array if dynamic token, true if static .env key.
     */
    private static function verifyHmac(
        Request $request,
        string $apiKey,
        string $timestampHeader,
        string $nonce,
        string $signature
    ): array|bool {
        $customToken = null;
        $secretKey = '';

        try {
            $tokenService = App::getService(TokenService::class);
            $token = $tokenService->findByKeyId($apiKey);
            if ($token !== null) {
                if ((int) $token['status'] !== 1) {
                    throw ApiException::unauthorized('API Token đã bị vô hiệu hóa');
                }
                if (!empty($token['expires_at']) && strtotime((string) $token['expires_at']) < time()) {
                    throw ApiException::unauthorized('API Token đã hết hạn sử dụng');
                }
                $secretKey = (string) $token['secret_key'];
                $customToken = $token;
            }
        } catch (ApiException $ae) {
            throw $ae;
        } catch (\Throwable $e) {
            // DB fallback or table error
        }

        if ($customToken === null) {
            $expectedKey = defined('API_ACCESS_KEY') ? (string) API_ACCESS_KEY : (string) getenv('API_ACCESS_KEY');
            $secretKey = defined('API_SECRET_KEY') ? (string) API_SECRET_KEY : (string) getenv('API_SECRET_KEY');

            if ($expectedKey === '' || !hash_equals($expectedKey, $apiKey)) {
                throw ApiException::unauthorized('API key không hợp lệ');
            }
        }

        $timestamp = (int) $timestampHeader;
        if ($timestamp <= 0) {
            throw ApiException::unauthorized('Timestamp không hợp lệ');
        }

        $ttl = defined('API_REQUEST_TTL') ? (int) API_REQUEST_TTL : 300;
        if (abs(time() - $timestamp) > $ttl) {
            throw ApiException::unauthorized('Yêu cầu đã hết thời gian hiệu lực (Timestamp quá lệch)');
        }

        $method = $request->getMethod();
        $path = $request->getPath();
        $payloadHash = hash('sha256', $request->getRawBody());

        // Payload = METHOD + \n + PATH + \n + TIMESTAMP + \n + NONCE + \n + BODY_HASH
        $payload = $method . "\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . $payloadHash;
        $expectedSignature = hash_hmac('sha256', $payload, $secretKey);

        if (!hash_equals($expectedSignature, $signature)) {
            throw ApiException::unauthorized('Chữ ký API không hợp lệ');
        }

        $requireNonce = defined('API_REQUIRE_NONCE') ? (bool) API_REQUIRE_NONCE : true;
        if ($requireNonce) {
            if ($nonce === '') {
                throw ApiException::unauthorized('Thiếu X-API-NONCE');
            }
            $nonceTtl = defined('API_NONCE_TTL') ? (int) API_NONCE_TTL : 300;
            if (!ReplayGuard::verifyNonce('api', $nonce, $nonceTtl)) {
                throw ApiException::unauthorized('Nonce đã được sử dụng hoặc không hợp lệ');
            }
        }

        if ($customToken !== null) {
            try {
                $tokenService = App::getService(TokenService::class);
                $tokenService->recordUsage((int) $customToken['id']);
            } catch (\Throwable) {
                // Ignore stats increment error
            }
            return $customToken;
        }

        return true;
    }

    private static function verifyWebUiToken(Request $request, string $token): bool
    {
        $allowFallback = defined('API_ALLOW_SESSION_FALLBACK') ? (bool) API_ALLOW_SESSION_FALLBACK : true;
        if (!$allowFallback) {
            return false;
        }

        if (!$request->isSameOrigin(BASE_URL)) {
            return false;
        }

        self::startSessionForFallback($request);

        $sessionToken = trim((string) ($_SESSION['kaimail_web_ui_token'] ?? ''));
        if ($sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    private static function startSessionForFallback(Request $request): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (defined('SESSION_NAME') && SESSION_NAME !== '') {
            session_name((string) SESSION_NAME);
        }

        $secureCookie = (defined('SESSION_COOKIE_SECURE') ? (bool) SESSION_COOKIE_SECURE : false) && $request->isHttps();
        session_set_cookie_params([
            'lifetime' => defined('SESSION_LIFETIME') ? (int) SESSION_LIFETIME : 86400,
            'path' => defined('SESSION_COOKIE_PATH') ? (string) SESSION_COOKIE_PATH : '/',
            'domain' => defined('SESSION_COOKIE_DOMAIN') ? (string) SESSION_COOKIE_DOMAIN : '',
            'secure' => $secureCookie,
            'httponly' => defined('SESSION_COOKIE_HTTP_ONLY') ? (bool) SESSION_COOKIE_HTTP_ONLY : true,
            'samesite' => defined('SESSION_COOKIE_SAMESITE') ? (string) SESSION_COOKIE_SAMESITE : 'Lax',
        ]);

        session_start(['read_and_close' => true]);
    }
}
