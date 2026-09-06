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
