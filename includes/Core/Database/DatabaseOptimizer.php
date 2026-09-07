<?php
declare(strict_types=1);

namespace KaiMail\Core\Database;

use PDO;
use Throwable;

/**
 * Runtime DB performance guard.
 * Ensures critical indexes for inbox/read OTP flow exist.
 */
final class DatabaseOptimizer
{
    private static bool $ensured = false;
    private const MARKER_FILE = __DIR__ . '/../../../storage/cache/db_indexes_ready.flag';

    public static function ensureCoreIndexes(PDO $db): void
    {
        if (self::$ensured) {
            return;
        }

        self::$ensured = true;

        try {
            self::ensureApiTokensTable($db);
            self::ensureDomainColumns($db);
        } catch (Throwable $e) {
            error_log('DatabaseOptimizer initialization error: ' . $e->getMessage());
        }

        if (is_file(self::MARKER_FILE)) {
            return;
        }

        try {
            self::ensureIndex($db, 'messages', 'idx_messages_email_received', '(email_id, received_at)');
            self::ensureIndex($db, 'messages', 'idx_messages_email_read', '(email_id, is_read)');
            self::writeMarker();
        } catch (Throwable $e) {
            error_log('DatabaseOptimizer error: ' . $e->getMessage());
        }
    }

    public static function ensureDomainColumns(PDO $db): void
    {
        try {
            $columns = [];
            $stmt = $db->query("SHOW COLUMNS FROM `domains`");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = strtolower((string) ($row['Field'] ?? ''));
            }

            if (!in_array('webhook_secret', $columns, true)) {
                $db->exec("ALTER TABLE `domains` ADD COLUMN `webhook_secret` VARCHAR(64) NULL AFTER `is_active`");
            }
            if (!in_array('verify_token', $columns, true)) {
                $db->exec("ALTER TABLE `domains` ADD COLUMN `verify_token` VARCHAR(64) NULL AFTER `webhook_secret`");
            }
            if (!in_array('type', $columns, true)) {
                $db->exec("ALTER TABLE `domains` ADD COLUMN `type` ENUM('system', 'custom') DEFAULT 'system' AFTER `verify_token`");
            }
        } catch (Throwable $e) {
            error_log('ensureDomainColumns error: ' . $e->getMessage());
        }
    }

    public static function ensureApiTokensTable(PDO $db): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `api_tokens` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `key_id` VARCHAR(48) UNIQUE NOT NULL,
            `secret_key` VARCHAR(64) NOT NULL,
            `rate_limit_per_min` INT DEFAULT 120,
            `total_requests` BIGINT DEFAULT 0,
            `last_used_at` DATETIME NULL,
            `expires_at` DATETIME NULL,
            `status` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_key_id (`key_id`),
            INDEX idx_status (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $db->exec($sql);
    }

    private static function ensureIndex(PDO $db, string $table, string $indexName, string $columnsSql): void
    {
        if (self::indexExists($db, $table, $indexName)) {
            return;
        }

        $sql = sprintf(
            'CREATE INDEX %s ON %s %s',
            self::quoteIdentifier($indexName),
            self::quoteIdentifier($table),
            $columnsSql
        );
        $db->exec($sql);
    }

    private static function indexExists(PDO $db, string $table, string $indexName): bool
    {
        $sql = '
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND index_name = ?
            LIMIT 1
        ';
        $stmt = $db->prepare($sql);
        $stmt->execute([$table, $indexName]);
        return (bool) $stmt->fetchColumn();
    }

    private static function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private static function writeMarker(): void
    {
        $dir = dirname(self::MARKER_FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents(self::MARKER_FILE, (string) time(), LOCK_EX);
    }
}
