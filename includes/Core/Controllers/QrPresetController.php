<?php
declare(strict_types=1);

namespace KaiMail\Core\Controllers;

use KaiMail\Core\Auth\AuthContext;
use KaiMail\Core\Auth\Gate;
use KaiMail\Core\Auth\Permission;
use KaiMail\Core\Http\ApiException;
use KaiMail\Core\Http\Request;
use KaiMail\Core\Http\Response;
use KaiMail\Core\Services\QrPresetService;

/**
 * Controller for QR Code presets (Admin CRUD & Public Listing).
 */
final class QrPresetController
{
    private QrPresetService $service;

    public function __construct(QrPresetService $service)
    {
        $this->service = $service;
    }

    /**
     * Entry handler for route dispatch.
     */
    public function handle(Request $request, AuthContext $context): Response
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $isAdmin = str_contains($uri, '/api/admin/');

        if ($isAdmin) {
            Gate::authorize($context, Permission::QR_PRESET_MANAGE);
            return match ($request->getMethod()) {
                'GET' => $this->handleAdminGet(),
                'POST' => $this->handleAdminPost($request),
                'PUT' => $this->handleAdminPut($request),
                'DELETE' => $this->handleAdminDelete($request),
                default => throw ApiException::methodNotAllowed(),
            };
        }

        // Public route: GET /api/qr-presets
        if ($request->isMethod('GET')) {
            return $this->handlePublicGet();
        }

        throw ApiException::methodNotAllowed();
    }

    /**
     * Public GET: List active QR presets and current QR preset text.
     */
    private function handlePublicGet(): Response
    {
        $presets = $this->service->listPublicActive();
        $qrText = $this->service->getQrText();
        return Response::json([
            'success' => true,
            'qr_text' => $qrText,
            'count' => count($presets),
            'presets' => $presets,
        ]);
    }

    /**
     * Admin GET: List all QR presets (including inactive ones) and current QR preset text.
     */
    private function handleAdminGet(): Response
    {
        $presets = $this->service->listAll();
        $qrText = $this->service->getQrText();
        return Response::json([
            'success' => true,
            'qr_text' => $qrText,
            'count' => count($presets),
            'presets' => $presets,
        ]);
    }

    /**
     * Admin POST: Create a new QR preset or save primary QR text.
     */
    private function handleAdminPost(Request $request): Response
    {
        $body = $request->getJson();
        $action = trim((string) ($body['action'] ?? $request->string('action', '')));

        if ($action === 'save_qr_text' || isset($body['qr_text'])) {
            $text = (string) ($body['qr_text'] ?? $body['text'] ?? '');
            $saved = $this->service->saveQrText($text);
            return Response::json([
                'success' => true,
                'message' => 'Lưu văn bản mẫu QR thành công',
                'qr_text' => $saved,
            ]);
        }

        $title = trim((string) ($body['title'] ?? $request->string('title')));
        $content = trim((string) ($body['content'] ?? $request->string('content')));
        $category = trim((string) ($body['category'] ?? $request->string('category', 'general')));
        $sortOrder = (int) ($body['sort_order'] ?? $request->int('sort_order', 0));
        $isActive = (int) ($body['is_active'] ?? $request->int('is_active', 1));

        $preset = $this->service->create($title, $content, $category, $sortOrder, $isActive);

        return Response::json([
            'success' => true,
            'message' => 'Tạo mẫu QR thành công',
            'preset' => $preset,
        ], 201);
    }

    /**
     * Admin PUT: Update an existing QR preset or toggle status or save QR text.
     */
    private function handleAdminPut(Request $request): Response
    {
        $body = $request->getJson();
        $id = (int) ($body['id'] ?? $request->int('id', 0));
        $action = trim((string) ($body['action'] ?? $request->string('action', '')));

        if ($action === 'save_qr_text' || isset($body['qr_text'])) {
            $text = (string) ($body['qr_text'] ?? $body['text'] ?? '');
            $saved = $this->service->saveQrText($text);
            return Response::json([
                'success' => true,
                'message' => 'Lưu văn bản mẫu QR thành công',
                'qr_text' => $saved,
            ]);
        }

        if ($id <= 0) {
            throw ApiException::badRequest('Thiếu ID mẫu QR cần cập nhật');
        }

        if ($action === 'toggle_status') {
            $status = (int) ($body['is_active'] ?? $request->int('is_active', 1));
            $this->service->updateStatus($id, $status);
            return Response::json([
                'success' => true,
                'message' => 'Cập nhật trạng thái thành công',
                'id' => $id,
                'is_active' => $status,
            ]);
        }

        $preset = $this->service->update($id, $body);

        return Response::json([
            'success' => true,
            'message' => 'Cập nhật mẫu QR thành công',
            'preset' => $preset,
        ]);
    }

    /**
     * Admin DELETE: Delete a QR preset.
     */
    private function handleAdminDelete(Request $request): Response
    {
        $body = $request->getJson();
        $id = (int) ($body['id'] ?? $request->int('id', 0));

        if ($id <= 0) {
            throw ApiException::badRequest('Thiếu ID mẫu QR cần xóa');
        }

        $this->service->delete($id);

        return Response::json([
            'success' => true,
            'message' => 'Xóa mẫu QR thành công',
            'id' => $id,
        ]);
    }
}
