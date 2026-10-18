<?php
declare(strict_types=1);

namespace KaiMail\Core\Services;

use PDO;
use Throwable;

/**
 * Database Auto-Cleanup Service.
 * 
 * Business Rules:
 * - Retention: Only temporary emails created by 'user' and 'api' within the last 30 days are retained.
 * - Protection: Emails created by 'admin' (created_by = 'admin') are NEVER deleted under any circumstances.
 * - Cascade: Associated messages are fully cleaned up without leaving orphaned records.
 */
final class DatabaseCleanupService
{
    private PDO $db;
    public const DEFAULT_RETENTION_DAYS = 30;
    private const MARKER_FILE = __DIR__ . '/../../../storage/cache/auto_clean_last_run.flag';
    private const THROTTLE_SECONDS = 21600; // 6 hours between opportunistic auto-runs

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Clean expired temporary emails created by 'user' and 'api' older than $retentionDays.
     * Strictly preserves all emails created by 'admin'.
     *
     * @param int $retentionDays Retention window in days (default: 30)
     * @param bool $dryRun If true, only calculate counts without deleting
     * @return array Metrics of the cleanup operation
     */
    public function cleanExpiredEmails(int $retentionDays = self::DEFAULT_RETENTION_DAYS, bool $dryRun = false): array
    {
        $days = max(1, $retentionDays);
        $startTime = microtime(true);

        // 1. Count active protected Admin emails
        $stmtAdmin = $this->db->query("
            SELECT COUNT(*) FROM emails 
            WHERE created_by = 'admin'
        ");
        $retainedAdminEmails = (int) $stmtAdmin->fetchColumn();

        // 2. Count candidate expired emails (user + api + guests older than N days)
        $stmtCount = $this->db->prepare("
            SELECT COUNT(*) FROM emails 
            WHERE (created_by IS NULL OR created_by != 'admin')
              AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmtCount->execute([$days]);
        $expiredCount = (int) $stmtCount->fetchColumn();

        // 3. Count messages associated with candidate expired emails
        $stmtCountMsg = $this->db->prepare("
            SELECT COUNT(*) FROM messages m
            INNER JOIN emails e ON m.email_id = e.id
            WHERE (e.created_by IS NULL OR e.created_by != 'admin')
              AND e.created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmtCountMsg->execute([$days]);
        $expiredMessagesCount = (int) $stmtCountMsg->fetchColumn();

        $deletedEmails = 0;
        $deletedMessages = 0;
        $deletedOrphans = 0;

        if (!$dryRun && $expiredCount > 0) {
            // Delete messages belonging to expired emails first to avoid foreign key locks
            $stmtDelMsg = $this->db->prepare("
                DELETE m FROM messages m
                INNER JOIN emails e ON m.email_id = e.id
                WHERE (e.created_by IS NULL OR e.created_by != 'admin')
                  AND e.created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmtDelMsg->execute([$days]);
            $deletedMessages = $stmtDelMsg->rowCount();

            // Delete the expired emails (Strictly excluding admin emails)
            $stmtDelEmails = $this->db->prepare("
                DELETE FROM emails
                WHERE (created_by IS NULL OR created_by != 'admin')
                  AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmtDelEmails->execute([$days]);
            $deletedEmails = $stmtDelEmails->rowCount();

            // Clean any orphaned messages where email_id no longer exists
            try {
                $stmtDelOrphans = $this->db->query("
                    DELETE m FROM messages m
                    LEFT JOIN emails e ON m.email_id = e.id
                    WHERE e.id IS NULL
                ");
                $deletedOrphans = $stmtDelOrphans ? $stmtDelOrphans->rowCount() : 0;
            } catch (Throwable $e) {
                // Ignore orphan query error if foreign key constraints handle it
            }

            // Update settings table with last cleanup info if settings table exists
            try {
                $now = date('Y-m-d H:i:s');
                $statsJson = json_encode([
                    'last_run' => $now,
                    'deleted_emails' => $deletedEmails,
                    'deleted_messages' => $deletedMessages + $deletedOrphans,
                    'retained_admin_emails' => $retainedAdminEmails,
                    'retention_days' => $days,
                ], JSON_UNESCAPED_UNICODE);

                $stmtSetting = $this->db->prepare("
                    INSERT INTO settings (setting_key, setting_value, updated_at)
                    VALUES ('last_db_cleanup', ?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)
                ");
                $stmtSetting->execute([$statsJson, $now]);
            } catch (Throwable $e) {
                // Ignore settings update failure
            }

            self::touchMarkerFile();
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'success' => true,
            'dry_run' => $dryRun,
            'retention_days' => $days,
            'target_cutoff' => date('Y-m-d H:i:s', strtotime("-{$days} days")),
            'deleted_emails' => $dryRun ? $expiredCount : $deletedEmails,
            'deleted_messages' => $dryRun ? $expiredMessagesCount : ($deletedMessages + $deletedOrphans),
            'retained_admin_emails' => $retainedAdminEmails,
            'duration_ms' => $durationMs,
            'executed_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Check if opportunistic auto-clean is due and run it safely in the background.
     */
    public static function autoCleanOpportunistically(PDO $db, int $retentionDays = self::DEFAULT_RETENTION_DAYS): void
    {
        if (is_file(self::MARKER_FILE)) {
            $lastModified = (int) @filemtime(self::MARKER_FILE);
            if ((time() - $lastModified) < self::THROTTLE_SECONDS) {
                return; // Throttled: was executed recently
            }
        }

        // Touch immediately to prevent concurrent web requests from running simultaneously
        self::touchMarkerFile();

        try {
            $service = new self($db);
            $service->cleanExpiredEmails($retentionDays, false);
        } catch (Throwable $e) {
            error_log('DatabaseCleanupService auto-clean error: ' . $e->getMessage());
        }
    }

    /**
     * Get statistics about current database retention state.
     */
    public function getRetentionStats(int $retentionDays = self::DEFAULT_RETENTION_DAYS): array
    {
        $days = max(1, $retentionDays);

        $stmtAdmin = $this->db->query("SELECT COUNT(*) FROM emails WHERE created_by = 'admin'");
        $adminCount = (int) $stmtAdmin->fetchColumn();

        $stmtUserRecent = $this->db->prepare("
            SELECT COUNT(*) FROM emails 
            WHERE (created_by = 'user' OR created_by IS NULL OR created_by = '')
              AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmtUserRecent->execute([$days]);
        $userRecentCount = (int) $stmtUserRecent->fetchColumn();

        $stmtUserExpired = $this->db->prepare("
            SELECT COUNT(*) FROM emails 
            WHERE (created_by = 'user' OR created_by IS NULL OR created_by = '')
              AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmtUserExpired->execute([$days]);
        $userExpiredCount = (int) $stmtUserExpired->fetchColumn();

        $stmtApiRecent = $this->db->prepare("
            SELECT COUNT(*) FROM emails 
            WHERE created_by = 'api'
              AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmtApiRecent->execute([$days]);
        $apiRecentCount = (int) $stmtApiRecent->fetchColumn();

        $stmtApiExpired = $this->db->prepare("
            SELECT COUNT(*) FROM emails 
            WHERE created_by = 'api'
              AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmtApiExpired->execute([$days]);
        $apiExpiredCount = (int) $stmtApiExpired->fetchColumn();

        $lastCleanup = null;
        try {
            $stmt = $this->db->query("SELECT setting_value FROM settings WHERE setting_key = 'last_db_cleanup' LIMIT 1");
            $val = $stmt->fetchColumn();
            if ($val) {
                $lastCleanup = json_decode((string) $val, true);
            }
        } catch (Throwable $e) {
            // Ignore
        }

        return [
            'retention_days' => $days,
            'admin_emails' => [
                'total' => $adminCount,
                'status' => 'PERMANENTLY_SAVED',
            ],
            'user_emails' => [
                'retained_active' => $userRecentCount,
                'pending_clean' => $userExpiredCount,
            ],
            'api_emails' => [
                'retained_active' => $apiRecentCount,
                'pending_clean' => $apiExpiredCount,
            ],
            'total_pending_clean' => $userExpiredCount + $apiExpiredCount,
            'last_cleanup' => $lastCleanup,
            'server_time' => date('Y-m-d H:i:s'),
        ];
    }

    private static function touchMarkerFile(): void
    {
        $dir = dirname(self::MARKER_FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents(self::MARKER_FILE, (string) time(), LOCK_EX);
    }
}
