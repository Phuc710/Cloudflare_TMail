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
use KaiMail\Core\Services\EmailDecoder;

/**
 * Cloudflare Email Routing Webhook Controller.
 */
final class WebhookController
{
    private EmailService $emailService;
    private MessageService $messageService;

    public function __construct(EmailService $emailService, MessageService $messageService)
    {
        $this->emailService = $emailService;
        $this->messageService = $messageService;
    }

    public function handle(Request $request, AuthContext $context): Response
    {
        Gate::authorize($context, Permission::WEBHOOK_INGEST);

        if (!$request->isMethod('POST')) {
            throw ApiException::methodNotAllowed();
        }

        $data = $request->body();
        if (empty($data)) {
            throw ApiException::badRequest('Invalid or empty JSON body');
        }

        $to = (string) ($data['to'] ?? '');
        $from = (string) ($data['from'] ?? '');
        $fromName = (string) ($data['from_name'] ?? '');
        $subject = (string) ($data['subject'] ?? '(No subject)');
        $textBody = (string) ($data['text'] ?? '');
        $htmlBody = (string) ($data['html'] ?? '');
        $messageId = (string) ($data['message_id'] ?? uniqid('msg_', true));

        if ($to === '' || $from === '') {
            throw ApiException::badRequest('Missing required fields: to, from');
        }

        // MIME & Quoted-Printable decoding
        $emailPayload = [
            'subject' => $subject,
            'from_name' => $fromName,
            'text' => $textBody,
            'html' => $htmlBody,
        ];
        $decoded = EmailDecoder::processEmail($emailPayload);

        $subject = $decoded['subject'];
        $fromName = $decoded['from_name'];
        $textBody = $decoded['text'];
        $htmlBody = $decoded['html'];

        // Parse from_name and from_email
        if (empty($fromName) && !empty($from)) {
            if (preg_match('/^(.+?)\s*<(.+)>$/', $from, $matches)) {
                $fromName = trim($matches[1], '" ');
                $fromEmail = $matches[2];
            } else {
                $fromEmail = $from;
                $fromName = '';
            }
        } else {
            $fromEmail = $from;
            if (preg_match('/<(.+)>/', $fromEmail, $matches)) {
                $fromEmail = $matches[1];
            }
        }

        // Clean recipient email address
        $cleanTo = $to;
        if (preg_match('/<(.+)>/', $to, $matches)) {
            $cleanTo = $matches[1];
        }
        $cleanTo = strtolower(trim($cleanTo));

        $emailAccount = $this->emailService->findEmail($cleanTo);
        if (!$emailAccount) {
            self::log("Email not registered in DB: {$cleanTo}");
            return Response::json([
                'status' => 'success',
                'message' => 'Email address not registered',
            ]);
        }

        $savedId = $this->messageService->saveIncomingMessage([
            'email_id' => (int) $emailAccount['id'],
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'subject' => $subject,
            'text' => $textBody,
            'html' => $htmlBody,
            'message_id' => $messageId,
            'received_at' => $data['received_at'] ?? date('Y-m-d H:i:s'),
        ]);

        if ($savedId === null) {
            self::log("WARN: Duplicate message skipped: {$messageId}");
            return Response::json([
                'status' => 'ignored',
                'message' => 'Message already exists',
            ]);
        }

        self::log("Message saved ID {$savedId} for {$cleanTo} ({$messageId})");

        return Response::json([
            'success' => true,
            'id' => $savedId,
            'message' => 'Message saved successfully',
        ], 201);
    }

    private static function log(string $msg): void
    {
        $logFile = defined('WEBHOOK_LOG_FILE') ? (string) WEBHOOK_LOG_FILE : dirname(__DIR__, 3) . '/storage/logs/webhook.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $ts = date('Y-m-d H:i:s');
        @file_put_contents($logFile, "[{$ts}] {$msg}\n", FILE_APPEND);
    }
}
