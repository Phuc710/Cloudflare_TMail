<?php
/**
 * KaiMail Database Auto-Clean Tool (CLI & Cron Runner)
 * 
 * Business Policy:
 *   - Retain only temporary emails created by USER and API within the last 30 days.
 *   - Any tempmail created by USER or API older than 30 days is automatically purged along with all messages.
 *   - Emails created by ADMIN (created_by = 'admin') are strictly preserved forever and never deleted.
 * 
 * Usage:
 *   CLI:   php scripts/auto_clean_db.php
 *   Cron:  0 3 * * * php /path/to/scripts/auto_clean_db.php
 *   Flags:
 *          --days=30     Custom retention period in days (default: 30)
 *          --dry-run     Inspect what would be deleted without making changes
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

$baseDir = dirname(__DIR__);
require_once $baseDir . '/includes/Core/App.php';

use KaiMail\Core\App;
use KaiMail\Core\Services\DatabaseCleanupService;

App::boot();

// CLI flag parsing
$options = getopt('', ['days:', 'dry-run']);
$retentionDays = isset($options['days']) ? max(1, (int) $options['days']) : DatabaseCleanupService::DEFAULT_RETENTION_DAYS;
$isDryRun = isset($options['dry-run']);

echo "===================================================================\n";
echo " KaiMail - Auto Clean Database (30-Day User & API Retention)\n";
echo " Executed at: " . date('Y-m-d H:i:s') . "\n";
echo " Mode: " . ($isDryRun ? "DRY-RUN (Simulation only)" : "LIVE PURGE") . "\n";
echo " Retention threshold: " . $retentionDays . " days\n";
echo " Policy: User & API > {$retentionDays} days = REMOVED | Admin = PRESERVED FOREVER\n";
echo "===================================================================\n\n";

try {
    $db = App::getDb();

    /** @var DatabaseCleanupService $cleanupService */
    $cleanupService = App::getService(DatabaseCleanupService::class);
    $result = $cleanupService->cleanExpiredEmails($retentionDays, $isDryRun);

    echo "[INFO] Cutoff timestamp : " . $result['target_cutoff'] . "\n";
    echo "[INFO] Admin emails (Protected) : " . $result['retained_admin_emails'] . " (Never deleted)\n";

    if ($isDryRun) {
        echo "[DRY-RUN] Expired emails that WOULD be deleted : " . $result['deleted_emails'] . "\n";
        echo "[DRY-RUN] Expired messages that WOULD be deleted : " . $result['deleted_messages'] . "\n";
    } else {
        echo "[SUCCESS] Deleted expired emails (User + API)   : " . $result['deleted_emails'] . "\n";
        echo "[SUCCESS] Deleted associated messages           : " . $result['deleted_messages'] . "\n";
    }

    echo "[INFO] Operation completed in " . $result['duration_ms'] . " ms\n";
    echo "\n===================================================================\n";
    echo " Cleanup task completed successfully.\n";
    echo "===================================================================\n";
    exit(0);
} catch (Throwable $e) {
    echo "\n[CRITICAL ERROR] Auto-clean database failed: " . $e->getMessage() . "\n";
    if (defined('IS_PRODUCTION') && !IS_PRODUCTION) {
        echo $e->getTraceAsString() . "\n";
    }
    exit(1);
}
