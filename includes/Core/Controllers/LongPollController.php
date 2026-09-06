<?php
declare(strict_types=1);

namespace KaiMail\Core\Controllers;

use PDO;
use KaiMail\Core\Auth\AuthContext;
use KaiMail\Core\Auth\Gate;
use KaiMail\Core\Auth\Permission;
use KaiMail\Core\Http\ApiException;
use KaiMail\Core\Http\Request;
use KaiMail\Core\Http\Response;

/**
 * Unified Long Polling Controller for single Mailbox (Public/Bot) and System-wide (Admin).
 */
final class LongPollController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function handle(Request $request, AuthContext $context): Response
    {
        if (!$request->isMethod('GET')) {
            throw ApiException::methodNotAllowed();
        }

        $emailId = $request->int('email_id');

        if ($emailId > 0) {
            return $this->handleMailboxPoll($request, $context, $emailId);
        }

        return $this->handleSystemPoll($request, $context);
    }

    private function handleMailboxPoll(Request $request, AuthContext $context, int $emailId): Response
    {
        Gate::authorize($context, Permission::LONG_POLL_MAILBOX);

        $email = strtolower(trim($request->string('email')));
        if ($email === '') {
            throw ApiException::badRequest('Vui lòng nhập địa chỉ email');
        }

        // Verify account ownership
        $stmtCheck = $this->db->prepare("SELECT id FROM emails WHERE id = ? AND email = ? LIMIT 1");
        $stmtCheck->execute([$emailId, $email]);
        if (!$stmtCheck->fetch()) {
            throw ApiException::forbidden('Email ID và địa chỉ email không khớp');
        }

        $lastCheck = $request->string('last_check') ?: date('Y-m-d H:i:s');
        $maxSeconds = defined('LONG_POLL_MAX_SECONDS') ? (int) LONG_POLL_MAX_SECONDS : 25;
        $sleepSeconds = defined('LONG_POLL_SLEEP_SECONDS') ? (int) LONG_POLL_SLEEP_SECONDS : 1;
        $startTime = time();

        $stmt = $this->db->prepare("
            SELECT id, from_email, from_name, subject, is_read, received_at,
                   COALESCE(NULLIF(snippet, ''), SUBSTR(body_text, 1, 100)) as preview
            FROM messages
            WHERE email_id = ? AND received_at > ?
            ORDER BY received_at DESC
            LIMIT 20
        ");

        while (time() - $startTime < $maxSeconds) {
            $stmt->closeCursor();
            $stmt->execute([$emailId, $lastCheck]);
            $messages = $stmt->fetchAll();
            $count = is_array($messages) ? count($messages) : 0;

            if ($count > 0) {
                return Response::json([
                    'has_new' => true,
                    'count' => $count,
                    'messages' => $messages,
                    'last_check' => date('Y-m-d H:i:s'),
                ]);
            }

            sleep($sleepSeconds);
        }

        return Response::json([
            'has_new' => false,
            'count' => 0,
            'last_check' => date('Y-m-d H:i:s'),
        ]);
    }

    private function handleSystemPoll(Request $request, AuthContext $context): Response
    {
        Gate::authorize($context, Permission::LONG_POLL_SYSTEM);

        $lastCheck = $request->string('last_check') ?: date('Y-m-d H:i:s');
        $maxSeconds = defined('LONG_POLL_MAX_SECONDS') ? (int) LONG_POLL_MAX_SECONDS : 25;
        $sleepSeconds = defined('LONG_POLL_SLEEP_SECONDS') ? (int) LONG_POLL_SLEEP_SECONDS : 1;
        $startTime = time();

        $stmtMsg = $this->db->prepare("SELECT 1 FROM messages WHERE received_at > ? LIMIT 1");
        $stmtEmail = $this->db->prepare("SELECT 1 FROM emails WHERE created_at > ? LIMIT 1");

        while (time() - $startTime < $maxSeconds) {
            $stmtMsg->execute([$lastCheck]);
            $hasNewMsg = (bool) $stmtMsg->fetchColumn();

            $stmtEmail->execute([$lastCheck]);
            $hasNewEmail = (bool) $stmtEmail->fetchColumn();

            if ($hasNewMsg || $hasNewEmail) {
                return Response::json([
                    'has_updates' => true,
                    'has_new' => true,
                    'new_messages' => $hasNewMsg,
                    'new_emails' => $hasNewEmail,
                    'last_check' => date('Y-m-d H:i:s'),
                ]);
            }

            sleep($sleepSeconds);
        }

        return Response::json([
            'has_updates' => false,
            'last_check' => date('Y-m-d H:i:s'),
        ]);
    }
}
