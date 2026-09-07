<?php
declare(strict_types=1);

namespace KaiMail\Core\Controllers;

use KaiMail\Core\Auth\AuthContext;
use KaiMail\Core\Auth\Gate;
use KaiMail\Core\Auth\Permission;
use KaiMail\Core\Http\ApiException;
use KaiMail\Core\Http\Request;
use KaiMail\Core\Http\Response;
use KaiMail\Core\Services\TokenService;

/**
 * Token Management Controller for Admin.
 */
final class TokenController
{
    private TokenService $service;

    public function __construct(TokenService $service)
    {
        $this->service = $service;
    }

    public function handle(Request $request, AuthContext $context): Response
    {
        Gate::authorize($context, Permission::TOKEN_MANAGE);

        return match ($request->getMethod()) {
            'GET' => $this->handleGet(),
            'POST' => $this->handlePost($request),
            'PUT' => $this->handlePut($request),
            'DELETE' => $this->handleDelete($request),
            default => throw ApiException::methodNotAllowed(),
        };
    }

    private function handleGet(): Response
    {
        $tokens = $this->service->listAll();
        return Response::json([
            'success' => true,
            'count' => count($tokens),
            'tokens' => $tokens,
        ]);
    }

    private function handlePost(Request $request): Response
    {
        $body = $request->getJson();
        $name = trim((string) ($body['name'] ?? $request->string('name')));
        $rateLimit = (int) ($body['rate_limit_per_min'] ?? $request->int('rate_limit_per_min', 120));

        if ($name === '') {
            throw ApiException::badRequest('Tên định danh Token không được để trống');
        }

        $expiresAt = null;
        $expiresDays = (int) ($body['expires_days'] ?? $request->int('expires_days', 0));
        if ($expiresDays > 0) {
            $expiresAt = date('Y-m-d H:i:s', time() + ($expiresDays * 86400));
        } elseif (!empty($body['expires_at'])) {
            $expiresAt = trim((string) $body['expires_at']);
        } elseif ($request->string('expires_at') !== '') {
            $expiresAt = $request->string('expires_at');
        }

        $token = $this->service->create($name, $rateLimit, $expiresAt);

        return Response::json([
            'success' => true,
            'message' => 'Tạo API Token thành công',
            'token' => $token,
        ], 201);
    }

    private function handlePut(Request $request): Response
    {
        $body = $request->getJson();
        $id = (int) ($body['id'] ?? $request->int('id', 0));
        $status = (int) ($body['status'] ?? $request->int('status', 0));

        if ($id <= 0) {
            throw ApiException::badRequest('Thiếu ID token cần cập nhật');
        }

        $this->service->updateStatus($id, $status);

        return Response::json([
            'success' => true,
            'message' => 'Cập nhật trạng thái token thành công',
            'id' => $id,
            'status' => $status,
        ]);
    }

    private function handleDelete(Request $request): Response
    {
        $body = $request->getJson();
        $id = (int) ($body['id'] ?? $request->int('id', 0));

        if ($id <= 0) {
            throw ApiException::badRequest('Thiếu ID token cần xóa');
        }

        $this->service->delete($id);

        return Response::json([
            'success' => true,
            'message' => 'Đã thu hồi và xóa API Token thành công',
            'id' => $id,
        ]);
    }
}
