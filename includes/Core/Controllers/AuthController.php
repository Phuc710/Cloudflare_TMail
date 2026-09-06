<?php
declare(strict_types=1);

namespace KaiMail\Core\Controllers;

use KaiMail\Core\Auth\AuthContext;
use KaiMail\Core\Auth\Gate;
use KaiMail\Core\Http\ApiException;
use KaiMail\Core\Http\Request;
use KaiMail\Core\Http\Response;
use KaiMail\Core\Security\RateLimiter;

/**
 * Admin Authentication Controller.
 */
final class AuthController
{
    public function handle(Request $request, AuthContext $context): Response
    {
        return match ($request->getMethod()) {
            'POST' => $this->handleLogin($request),
            'GET' => $this->handleStatus($context),
            'DELETE' => $this->handleLogout(),
            default => throw ApiException::methodNotAllowed(),
        };
    }

    private function handleLogin(Request $request): Response
    {
        // Rate limit login attempts per IP
        $limit = defined('ADMIN_LOGIN_RATE_LIMIT_PER_MIN') ? (int) ADMIN_LOGIN_RATE_LIMIT_PER_MIN : 15;
        $ip = $request->getClientIp() ?: 'unknown';
        $res = RateLimiter::enforce('admin_login', $limit, 60, $ip);

        if (!$res['allowed']) {
            throw ApiException::tooManyRequests('Quá nhiều lần thử đăng nhập, vui lòng đợi', $res['retry_after']);
        }

        $password = trim($request->string('password'));
        if (str_starts_with($password, 'ADMIN_ACCESS_KEY=')) {
            $password = substr($password, strlen('ADMIN_ACCESS_KEY='));
        }

        if ($password === '') {
            throw ApiException::badRequest('Vui lòng nhập khóa truy cập');
        }

        require_once dirname(__DIR__, 2) . '/Auth.php';
        if (!\Auth::login($password)) {
            usleep(250000); // 250ms backoff
            throw ApiException::unauthorized('Khóa truy cập không chính xác');
        }

        return Response::json([
            'success' => true,
            'message' => 'Authenticated',
            'auth_type' => 'admin_access_key',
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function handleStatus(AuthContext $context): Response
    {
        if (!$context->isAdmin()) {
            throw ApiException::unauthorized('Chưa xác thực quyền quản trị');
        }

        return Response::json([
            'authenticated' => true,
            'auth_type' => $context->getAuthType(),
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function handleLogout(): Response
    {
        require_once dirname(__DIR__, 2) . '/Auth.php';
        \Auth::logout();

        return Response::json([
            'success' => true,
            'message' => 'Đã đăng xuất thành công',
        ]);
    }
}
