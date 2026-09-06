<?php
declare(strict_types=1);

namespace KaiMail\Core\Services;

use PDO;

/**
 * System and Dashboard Statistics service.
 */
final class StatsService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getDashboardStats(): array
    {
        $totalEmails = (int) $this->db->query("SELECT COUNT(*) FROM emails")->fetchColumn();
        $adminEmails = (int) $this->db->query("SELECT COUNT(*) FROM emails WHERE created_by = 'admin'")->fetchColumn();
        $apiEmails = (int) $this->db->query("SELECT COUNT(*) FROM emails WHERE created_by = 'api'")->fetchColumn();
        $userEmails = (int) $this->db->query("SELECT COUNT(*) FROM emails WHERE (created_by = 'user' OR created_by IS NULL OR created_by = '')")->fetchColumn();
        $activeEmails = (int) $this->db->query("SELECT COUNT(*) FROM emails WHERE is_done = 0")->fetchColumn();
        $doneEmails = (int) $this->db->query("SELECT COUNT(*) FROM emails WHERE is_done = 1")->fetchColumn();

        $totalMessages = (int) $this->db->query("SELECT COUNT(*) FROM messages")->fetchColumn();
        $unreadMessages = (int) $this->db->query("SELECT COUNT(*) FROM messages WHERE is_read = 0")->fetchColumn();

        $sevenDaysAgo = date('Y-m-d H:i:s', strtotime('-7 days'));

        $stmtEmails = $this->db->prepare("SELECT COUNT(*) FROM emails WHERE created_at >= ?");
        $stmtEmails->execute([$sevenDaysAgo]);
        $recentEmails = (int) $stmtEmails->fetchColumn();

        $stmtMessages = $this->db->prepare("SELECT COUNT(*) FROM messages WHERE received_at >= ?");
        $stmtMessages->execute([$sevenDaysAgo]);
        $recentMessages = (int) $stmtMessages->fetchColumn();

        return [
            'total_emails' => $totalEmails,
            'admin_emails' => $adminEmails,
            'api_emails' => $apiEmails,
            'user_emails' => $userEmails,
            'active_emails' => $activeEmails,
            'done_emails' => $doneEmails,
            'expired_emails' => 0,
            'total_messages' => $totalMessages,
            'unread_messages' => $unreadMessages,
            'recent_emails' => $recentEmails,
            'recent_messages' => $recentMessages,
            'server_time' => date('Y-m-d H:i:s'),
        ];
    }
}
