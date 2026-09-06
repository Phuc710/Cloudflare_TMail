<?php
declare(strict_types=1);

namespace KaiMail\Core\Services;

use PDO;

/**
 * Clean, senior-grade Message management service.
 * Centralizes retrieval, Quoted-Printable decoding, sender heuristics, and persistence.
 */
final class MessageService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get a single message by ID, automatically decode content and mark as read.
     */
    public function getMessage(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT m.*, UNIX_TIMESTAMP(m.received_at) as ts, e.email 
            FROM messages m 
            JOIN emails e ON m.email_id = e.id 
            WHERE m.id = ?
        ");
        $stmt->execute([$id]);
        $message = $stmt->fetch();

        if (!is_array($message)) {
            return null;
        }

        // Mark as read
        $this->db->prepare("UPDATE messages SET is_read = 1 WHERE id = ?")->execute([$id]);

        return self::processMessage($message);
    }

    /**
     * Get messages list for an email ID.
     * Uses indexed snippet column to avoid reading off-page LONGTEXT LOB chunks.
     */
    public function getMessagesByEmailId(int $emailId, int $limit = 30): array
    {
        $safeLimit = max(1, min($limit, 100));

        $stmt = $this->db->prepare("
            SELECT id, from_email, from_name, subject, is_read, received_at,
                   UNIX_TIMESTAMP(received_at) as ts,
                   COALESCE(NULLIF(snippet, ''), SUBSTR(body_text, 1, 100)) as preview
            FROM messages 
            WHERE email_id = ?
            ORDER BY received_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $emailId, PDO::PARAM_INT);
        $stmt->bindValue(2, $safeLimit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Count unread messages for an email ID.
     */
    public function countUnread(int $emailId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM messages WHERE email_id = ? AND is_read = 0");
        $stmt->execute([$emailId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Delete messages.
     */
    public function deleteMessages(int $emailId, array $ids = [], bool $deleteAll = false): int
    {
        if ($deleteAll && $emailId > 0) {
            $stmt = $this->db->prepare("DELETE FROM messages WHERE email_id = ?");
            $stmt->execute([$emailId]);
            return $stmt->rowCount();
        }

        $cleanIds = array_values(array_filter(array_map('intval', $ids), fn(int $id) => $id > 0));
        if (!empty($cleanIds)) {
            $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
            $sql = "DELETE FROM messages WHERE id IN ({$placeholders})";
            $params = $cleanIds;

            if ($emailId > 0) {
                $sql .= " AND email_id = ?";
                $params[] = $emailId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        }

        return 0;
    }

    /**
     * Ingest and save an incoming email from webhook.
     */
    public function saveIncomingMessage(array $data): ?int
    {
        $messageId = (string) ($data['message_id'] ?? uniqid('msg_', true));

        // Deduplication check
        $stmt = $this->db->prepare("SELECT id FROM messages WHERE message_id = ? LIMIT 1");
        $stmt->execute([$messageId]);
        if ($stmt->fetch()) {
            return null; // Already exists
        }

        $textBody = (string) ($data['text'] ?? '');
        $htmlBody = (string) ($data['html'] ?? '');

        // Decode Quoted-Printable if detected
        if (str_contains($textBody, '=20') || str_contains($textBody, '=3D')) {
            $textBody = quoted_printable_decode($textBody);
        }
        if (str_contains($htmlBody, '=20') || str_contains($htmlBody, '=3D')) {
            $htmlBody = quoted_printable_decode($htmlBody);
        }

        // Generate fast, clean snippet without touching LOB on future list queries
        $rawSnippet = !empty($textBody) ? $textBody : strip_tags($htmlBody);
        $cleanSnippet = preg_replace('/\s+/', ' ', trim($rawSnippet));
        $snippet = mb_substr((string) $cleanSnippet, 0, 140);

        $stmt = $this->db->prepare("
            INSERT INTO messages (
                email_id, from_email, from_name, subject, snippet, body_text, body_html, message_id, is_read, received_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
        ");

        $stmt->execute([
            (int) $data['email_id'],
            (string) ($data['from_email'] ?? ''),
            (string) ($data['from_name'] ?? ''),
            (string) ($data['subject'] ?? '(No subject)'),
            $snippet,
            $textBody,
            $htmlBody,
            $messageId,
            $data['received_at'] ?? date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Process message content: Quoted-Printable decode & smart sender name heuristic.
     */
    public static function processMessage(array $message): array
    {
        $bodyText = (string) ($message['body_text'] ?? '');
        $bodyHtml = (string) ($message['body_html'] ?? '');

        if (str_contains($bodyText, '=20') || str_contains($bodyText, '=3D')) {
            $bodyText = quoted_printable_decode($bodyText);
        }
        if (str_contains($htmlHtml = $bodyHtml, '=20') || str_contains($bodyHtml, '=3D')) {
            $bodyHtml = quoted_printable_decode($bodyHtml);
        }

        $fromName = (string) ($message['from_name'] ?? '');
        $fromEmail = (string) ($message['from_email'] ?? '');

        $isSuspicious = empty($fromName) ||
            preg_match('/^(Em|Ma|No|Auto)\d+$/i', $fromName) ||
            str_contains(strtolower($fromName), 'bounce') ||
            str_contains(strtolower($fromName), 'no-reply') ||
            $fromName === $fromEmail;

        if ($isSuspicious || str_contains($fromEmail, '.openai.com')) {
            $domain = substr((string) strrchr($fromEmail, "@"), 1);
            $parts = explode('.', $domain);
            $count = count($parts);

            $knownBrands = [
                'openai' => 'OpenAI',
                'github' => 'GitHub',
                'gitlab' => 'GitLab',
                'facebook' => 'Facebook',
                'youtube' => 'YouTube',
                'linkedin' => 'LinkedIn',
                'wordpress' => 'WordPress',
                'paypal' => 'PayPal',
                'microsoft' => 'Microsoft',
                'apple' => 'Apple',
                'google' => 'Google',
                'twitter' => 'Twitter',
                'amazon' => 'Amazon',
                'vercel' => 'Vercel',
                'netflix' => 'Netflix',
                'spotify' => 'Spotify',
            ];

            if ($count >= 2) {
                $main = $parts[$count - 2];
                if (strlen($main) <= 3 && $count >= 3 && in_array(strtolower($main), ['co', 'com', 'net', 'org', 'gov', 'edu'], true)) {
                    $main = $parts[$count - 3];
                }

                $companyKey = strtolower($main);
                $fromName = $knownBrands[$companyKey] ?? ucfirst($main);
            } else {
                $parts = explode('@', $fromEmail);
                $fromName = ucfirst($parts[0]);
            }
        }

        return [
            'id' => (int) $message['id'],
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'subject' => (string) ($message['subject'] ?? '(No subject)'),
            'body_text' => $bodyText,
            'body_html' => $bodyHtml,
            'is_read' => true,
            'received_at' => (string) ($message['received_at'] ?? ''),
            'created_ts' => isset($message['ts']) ? (int) $message['ts'] : null,
            'recipient' => (string) ($message['email'] ?? ''),
        ];
    }
}
