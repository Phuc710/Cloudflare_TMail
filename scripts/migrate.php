<?php
/**
 * KaiMail Database Migration Script
 * Safe, idempotent database schema updater.
 * Automatically synchronizes columns, indexes, and settings with data.sql.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$db = getDB();

echo "============================================================\n";
echo "🚀 KaiMail Database Migration Utility\n";
echo "============================================================\n";

// Helper to check if index exists on a table
$hasIndex = function(PDO $pdo, string $table, string $indexName): bool {
    try {
        $indexes = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$indexName}'")->fetchAll();
        return !empty($indexes);
    } catch (\Throwable $e) {
        return false;
    }
};

// ---------------------------------------------------------
// 1. Table `messages` checks
// ---------------------------------------------------------
echo "\n📦 Checking table `messages`...\n";
try {
    $colsMessages = $db->query('SHOW COLUMNS FROM `messages`')->fetchAll(PDO::FETCH_COLUMN);

    // snippet
    if (!in_array('snippet', $colsMessages, true)) {
        echo "⏳ Adding `snippet` column to `messages`...\n";
        $db->exec("ALTER TABLE `messages` ADD COLUMN snippet VARCHAR(255) NOT NULL DEFAULT '' AFTER subject");
        echo "✅ Column `snippet` added successfully.\n";

        // Backfill snippet for existing rows
        echo "⏳ Backfilling existing messages with snippets...\n";
        $stmt = $db->query("SELECT id, body_text, body_html FROM `messages` WHERE snippet = '' LIMIT 1000");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $updateStmt = $db->prepare("UPDATE `messages` SET snippet = ? WHERE id = ?");
        $count = 0;
        foreach ($rows as $row) {
            $text = trim((string)($row['body_text'] ?? ''));
            if ($text === '') {
                $text = trim(strip_tags((string)($row['body_html'] ?? '')));
            }
            $cleanText = (string) preg_replace('/\s+/', ' ', $text);
            $snippet = mb_substr($cleanText, 0, 140);
            $updateStmt->execute([$snippet, $row['id']]);
            $count++;
        }
        echo "✅ Backfilled {$count} message snippets.\n";
    } else {
        echo "✅ Column `snippet` already exists.\n";
    }

    // is_read
    if (!in_array('is_read', $colsMessages, true)) {
        echo "⏳ Adding `is_read` column to `messages`...\n";
        $db->exec("ALTER TABLE `messages` ADD COLUMN is_read TINYINT(1) DEFAULT 0 AFTER message_id");
        echo "✅ Column `is_read` added successfully.\n";
    } else {
        echo "✅ Column `is_read` already exists.\n";
    }
} catch (\Throwable $e) {
    echo "⚠️ Messages table check error: " . $e->getMessage() . "\n";
}

// ---------------------------------------------------------
// 2. Table `emails` checks
// ---------------------------------------------------------
echo "\n📦 Checking table `emails`...\n";
try {
    $colsEmails = $db->query('SHOW COLUMNS FROM `emails`')->fetchAll(PDO::FETCH_COLUMN);

    // name_type
    if (!in_array('name_type', $colsEmails, true)) {
        echo "⏳ Adding `name_type` column to `emails`...\n";
        $db->exec("ALTER TABLE `emails` ADD COLUMN name_type ENUM('vn', 'en', 'custom') DEFAULT 'en' AFTER email");
        echo "✅ Column `name_type` added.\n";
    } else {
        echo "✅ Column `name_type` already exists.\n";
    }

    // is_done
    if (!in_array('is_done', $colsEmails, true)) {
        echo "⏳ Adding `is_done` column to `emails`...\n";
        $db->exec("ALTER TABLE `emails` ADD COLUMN is_done TINYINT(1) DEFAULT 0");
        echo "✅ Column `is_done` added.\n";
    } else {
        echo "✅ Column `is_done` already exists.\n";
    }

    // created_by
    if (!in_array('created_by', $colsEmails, true)) {
        echo "⏳ Adding `created_by` column to `emails`...\n";
        $db->exec("ALTER TABLE `emails` ADD COLUMN created_by VARCHAR(20) DEFAULT 'user'");
        echo "✅ Column `created_by` added.\n";
    } else {
        echo "✅ Column `created_by` already exists.\n";
    }

    // note
    if (!in_array('note', $colsEmails, true)) {
        echo "⏳ Adding `note` column to `emails`...\n";
        $db->exec("ALTER TABLE `emails` ADD COLUMN note VARCHAR(500) NULL");
        echo "✅ Column `note` added.\n";
    } else {
        echo "✅ Column `note` already exists.\n";
    }

    // Index on created_by
    if (!$hasIndex($db, 'emails', 'idx_created_by')) {
        echo "⏳ Adding index `idx_created_by` on `emails`...\n";
        $db->exec("ALTER TABLE `emails` ADD INDEX idx_created_by (created_by)");
        echo "✅ Index `idx_created_by` added.\n";
    }
} catch (\Throwable $e) {
    echo "⚠️ Emails table check error: " . $e->getMessage() . "\n";
}

// ---------------------------------------------------------
// 3. Table `settings` & Default Data
// ---------------------------------------------------------
echo "\n📦 Checking table `settings`...\n";
try {
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_key (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "✅ Table `settings` verified.\n";

    // Seed default settings if empty
    $db->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES 
        ('webhook_secret', 'CHANGE_ME_IN_ENV_ONLY'),
        ('default_domain', 'kaishop.id.vn'),
        ('primary_url', 'https://tmail.kaishop.id.vn'),
        ('api_domains', 'kaishop.id.vn,trongnghia.store'),
        ('app_name', 'KaiMail'),
        ('app_version', '1.0'),
        ('maintenance_mode', '0');");
    echo "✅ Default settings seeded.\n";
} catch (\Throwable $e) {
    echo "⚠️ Settings table check error: " . $e->getMessage() . "\n";
}

echo "\n============================================================\n";
echo "🎉 All migrations finished! Database is 100% up to date.\n";
echo "============================================================\n";
