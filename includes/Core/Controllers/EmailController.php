<?php
declare(strict_types=1);

namespace KaiMail\Core\Controllers;

use KaiMail\Core\Auth\AuthContext;
use KaiMail\Core\Auth\Gate;
use KaiMail\Core\Auth\Permission;
use KaiMail\Core\Http\ApiException;
use KaiMail\Core\Http\Request;
use KaiMail\Core\Http\Response;
use KaiMail\Core\Security\RateLimiter;
use KaiMail\Core\Services\EmailService;

/**
 * Unified Email Controller for both Admin Dashboard and External API clients.
 * Behavior is cleanly driven by RBAC permissions.
 */
final class EmailController
{
    private EmailService $service;

    public function __construct(EmailService $service)
    {
        $this->service = $service;
    }

    public function handle(Request $request, AuthContext $context): Response
    {
        return match ($request->getMethod()) {
            'GET' => $this->handleGet($request, $context),
            'POST' => $this->handlePost($request, $context),
            'DELETE' => $this->handleDelete($request, $context),
            default => throw ApiException::methodNotAllowed(),
        };
    }

    private function handleGet(Request $request, AuthContext $context): Response
    {
        $emailQuery = $request->string('email');

        // Check single email query: /api/emails.php?email=...
        if ($emailQuery !== '' && !$request->hasHeader('x-admin-access-key') && !$context->isAdmin()) {
            Gate::authorize($context, Permission::EMAIL_CHECK_EXISTS);

            if (!filter_var($emailQuery, FILTER_VALIDATE_EMAIL)) {
                throw ApiException::badRequest('Định dạng email không hợp lệ');
            }

            $emailData = $this->service->findEmail($emailQuery);
            if (!$emailData) {
                return Response::json([
                    'exists' => false,
                    'error' => 'Email not found',
                ], 404);
            }

            return Response::json([
                'exists' => true,
                'id' => (int) $emailData['id'],
                'email' => (string) $emailData['email'],
                'created_at' => (string) $emailData['created_at'],
            ]);
        }

        // Admin email list query (paginated with filters & statistics)
        Gate::authorize($context, Permission::EMAIL_LIST_ALL);

        $page = $request->int('page', 1);
        $limit = $request->int('limit', 13);
        $search = $request->string('search') ?: null;
        $domain = $request->string('domain') ?: null;
        $noMessage = $request->string('no_message') === '1';
        $createdBy = $request->string('created_by') ?: null;
        $status = $request->string('status') ?: null;

        $data = $this->service->listEmails($page, $limit, $search, $domain, $noMessage, $createdBy, $status);
        return Response::json($data);
    }

    private function handlePost(Request $request, AuthContext $context): Response
    {
        // 1. Toggle done status (single or batch)
        if ($request->string('action') === 'toggle_done') {
            Gate::authorize($context, Permission::EMAIL_TOGGLE_DONE);
            $ids = $request->array('ids');
            $id = $request->int('id');
            $isDone = $request->int('is_done', 0);

            if (!empty($ids)) {
                $count = 0;
                foreach ($ids as $singleId) {
                    $cleanId = (int) $singleId;
                    if ($cleanId > 0) {
                        $this->service->toggleDone($cleanId, $isDone);
                        $count++;
                    }
                }
                return Response::success(['updated' => $count]);
            }

            if ($id <= 0) {
                throw ApiException::badRequest('Thiếu ID email');
            }

            $this->service->toggleDone($id, $isDone);
            return Response::success();
        }

        // 2. Update note for email
        if ($request->string('action') === 'update_note') {
            Gate::authorize($context, Permission::EMAIL_UPDATE_NOTE);
            $id = $request->int('id');
            $note = $request->string('note');

            if ($id <= 0) {
                throw ApiException::badRequest('Thiếu ID email');
            }

            $this->service->updateNote($id, $note);
            return Response::success([
                'id' => $id,
                'note' => trim($note),
            ]);
        }

        // 3. Create email(s)
        $maxAllowed = 10;
        if (Gate::allows($context, Permission::EMAIL_CREATE_BATCH)) {
            $maxAllowed = 50;
        } elseif (Gate::allows($context, Permission::EMAIL_CREATE_SINGLE)) {
            $maxAllowed = $context->isPublicUser() ? 1 : 10;
            if ($context->isPublicUser()) {
                $clientIp = $request->getClientIp();
                $rate = RateLimiter::enforce('public_email_create', 15, 60, $clientIp);
                if (!$rate['allowed']) {
                    throw ApiException::tooManyRequests('Bạn đang tạo email quá nhanh. Vui lòng chờ ' . $rate['retry_after'] . ' giây.');
                }
            }
        } else {
            throw ApiException::forbidden('Không có quyền tạo email');
        }

        $source = $context->isAdmin() ? 'admin' : ($context->isApiUser() ? 'api' : 'user');
        $body = $request->body();
        if (!isset($body['created_by']) || !$context->isAdmin()) {
            $body['created_by'] = $source;
        }

        $result = $this->service->createEmails($body, $maxAllowed, $source, $request->string('note') ?: null);

        if ($result['count'] === 0) {
            return Response::json([
                'success' => false,
                'created' => 0,
                'errors' => $result['errors'],
            ], 400);
        }

        return Response::json([
            'success' => true,
            'created' => $result['count'],
            'emails' => $result['created'],
            'errors' => $result['errors'],
        ], 201);
    }

    private function handleDelete(Request $request, AuthContext $context): Response
    {
        Gate::authorize($context, Permission::EMAIL_DELETE);

        // If public web user, strictly allow deleting single email and rate limit
        if ($context->isPublicUser()) {
            $clientIp = $request->getClientIp();
            $rate = RateLimiter::enforce('public_email_delete', 30, 60, $clientIp);
            if (!$rate['allowed']) {
                throw ApiException::tooManyRequests('Thao tác quá nhanh. Vui lòng chờ ' . $rate['retry_after'] . ' giây.');
            }

            $email = $request->string('email');
            if ($email === '') {
                throw ApiException::badRequest('Vui lòng cung cấp địa chỉ email cần xóa');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw ApiException::badRequest('Định dạng email không hợp lệ');
            }

            $emailData = $this->service->findEmail($email);
            if (!$emailData) {
                return Response::json([
                    'success' => false,
                    'error' => 'Email không tồn tại trong hệ thống',
                    'deleted' => 0,
                ], 404);
            }

            $deleted = $this->service->deleteByAddress($email);
            return Response::success([
                'deleted' => $deleted,
                'email' => $email,
            ]);
        }

        // Delete by IDs (bulk) for Admin/API
        $ids = $request->array('ids');
        if (!empty($ids)) {
            $deleted = $this->service->deleteEmails($ids);
            return Response::success(['deleted' => $deleted]);
        }

        // Delete by email address
        $email = $request->string('email');
        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw ApiException::badRequest('Định dạng email không hợp lệ');
            }

            $emailData = $this->service->findEmail($email);
            if (!$emailData) {
                return Response::json(['error' => 'Email not found'], 404);
            }

            $deleted = $this->service->deleteByAddress($email);
            return Response::success([
                'deleted' => $deleted,
                'email' => $email,
            ]);
        }

        throw ApiException::badRequest('Vui lòng cung cấp danh sách ids hoặc địa chỉ email cần xóa');
    }
}
