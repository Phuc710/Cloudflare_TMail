<?php
declare(strict_types=1);

namespace KaiMail\Core\Controllers;

use KaiMail\Core\Auth\AuthContext;
use KaiMail\Core\Auth\Gate;
use KaiMail\Core\Auth\Permission;
use KaiMail\Core\Http\ApiException;
use KaiMail\Core\Http\Request;
use KaiMail\Core\Http\Response;
use KaiMail\Core\Services\EmailService;
use KaiMail\Core\Services\MessageService;

/**
 * Unified Message Controller for Public UI, External API, and Admin Dashboard.
 */
final class MessageController
{
    private MessageService $messageService;
    private EmailService $emailService;

    public function __construct(MessageService $messageService, EmailService $emailService)
    {
        $this->messageService = $messageService;
        $this->emailService = $emailService;
    }

    public function handle(Request $request, AuthContext $context): Response
    {
        return match ($request->getMethod()) {
            'GET' => $this->handleGet($request, $context),
            'DELETE' => $this->handleDelete($request, $context),
            default => throw ApiException::methodNotAllowed(),
        };
    }

    private function handleGet(Request $request, AuthContext $context): Response
    {
        // 1. Single message detail retrieval
        $id = $request->int('id');
        if ($id > 0) {
            Gate::authorize($context, Permission::MESSAGE_VIEW_DETAIL);

            $message = $this->messageService->getMessage($id);
            if (!$message) {
                return Response::json(['error' => 'Không tìm thấy tin nhắn'], 404);
            }

            // Non-admin callers must specify and prove email ownership
            if (!$context->isAdmin()) {
                $requestedEmail = strtolower(trim($request->string('email')));
                if ($requestedEmail === '') {
                    throw ApiException::badRequest('Vui lòng cung cấp email để xem chi tiết tin nhắn');
                }

                $recipient = strtolower(trim((string) ($message['recipient'] ?? '')));
                if ($recipient !== $requestedEmail) {
                    throw ApiException::forbidden('Không có quyền xem tin nhắn này');
                }
            }

            return Response::json($message);
        }

        // 2. Message list by email_id (Admin UI flow)
        $emailId = $request->int('email_id');
        if ($emailId > 0 && ($context->isAdmin() || $request->hasHeader('x-admin-access-key'))) {
            Gate::authorize($context, Permission::MESSAGE_LIST_BY_EMAIL);

            $messages = $this->messageService->getMessagesByEmailId($emailId, 100);
            return Response::json([
                'total' => count($messages),
                'messages' => $messages,
            ]);
        }

        // 3. Message list by email address (Public UI & External Bot flow)
        Gate::authorize($context, Permission::MESSAGE_LIST_BY_EMAIL);

        $email = strtolower(trim($request->string('email')));
        if ($email === '') {
            throw ApiException::badRequest('Vui lòng nhập địa chỉ email');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ApiException::badRequest('Định dạng email không hợp lệ');
        }

        $emailData = $this->emailService->findEmail($email);
        if (!$emailData) {
            return Response::json(['error' => 'Email not found'], 404);
        }

        $resolvedEmailId = (int) $emailData['id'];
        $limit = max(1, min($request->int('limit', 30), 100));
        $messages = $this->messageService->getMessagesByEmailId($resolvedEmailId, $limit);
        $unreadCount = $this->messageService->countUnread($resolvedEmailId);

        return Response::json([
            'email' => $email,
            'email_id' => $resolvedEmailId,
            'total' => count($messages),
            'unread' => $unreadCount,
            'messages' => $messages,
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function handleDelete(Request $request, AuthContext $context): Response
    {
        Gate::authorize($context, Permission::MESSAGE_DELETE);

        $singleId = $request->int('id');
        $ids = $request->array('ids');
        if ($singleId > 0) {
            $ids[] = $singleId;
        }
        $deleteAll = $request->bool('delete_all', false);

        // Admin deletion by email_id
        $emailId = $request->int('email_id');
        if ($context->isAdmin() && $emailId > 0) {
            $deleted = $this->messageService->deleteMessages($emailId, $ids, $deleteAll);
            return Response::success(['deleted' => $deleted]);
        }

        // External API / User deletion requires email address for ownership
        $email = strtolower(trim($request->string('email')));
        if ($email === '') {
            throw ApiException::badRequest('Email là bắt buộc để thực hiện xóa tin');
        }

        $emailData = $this->emailService->findEmail($email);
        if (!$emailData) {
            return Response::json(['error' => 'Email not found'], 404);
        }

        $resolvedEmailId = (int) $emailData['id'];
        if (!$deleteAll && empty($ids)) {
            throw ApiException::badRequest('Vui lòng cung cấp id/ids hoặc delete_all=true');
        }

        $deleted = $this->messageService->deleteMessages($resolvedEmailId, $ids, $deleteAll);
        return Response::success([
            'deleted' => $deleted,
            'email' => $email,
        ]);
    }
}
