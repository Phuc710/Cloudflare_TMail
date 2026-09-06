<?php
/**
 * KaiMail Database Migration Script
 * Safe, idempotent database schema updater.
 * Can be run repeatedly without breaking data.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$db = getDB();

echo "============================================================\n";
echo "🚀 KaiMail Database Migration Utility\n";
echo "============================================================\n";

// 1. Check & Add `snippet` column to `messages`
try {
    $cols = $db->query('SHOW COLUMNS FROM messages')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('snippet', $cols, true)) {
        echo "⏳ Adding `snippet` column to `messages` table...\n";
        $db->exec("ALTER TABLE messages ADD COLUMN snippet VARCHAR(255) NOT NULL DEFAULT '' AFTER subject");
        echo "✅ Column `snippet` added successfully.\n";

        // Backfill snippet for existing rows
        echo "⏳ Backfilling existing messages with snippets...\n";
        $stmt = $db->query("SELECT id, body_text, body_html FROM messages WHERE snippet = '' LIMIT 500");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $updateStmt = $db->prepare("UPDATE messages SET snippet = ? WHERE id = ?");
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
        echo "✅ Column `snippet` on `messages` table already exists.\n";
    }
} catch (\Throwable $e) {
    echo "⚠️ Messages table check warning: " . $e->getMessage() . "\n";
}

// 2. Check & Add `note` column to `emails` table
try {
    $colsEmails = $db->query('SHOW COLUMNS FROM emails')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('note', $colsEmails, true)) {
        echo "⏳ Adding `note` column to `emails` table...\n";
        $db->exec("ALTER TABLE emails ADD COLUMN note VARCHAR(500) NULL AFTER created_by");
        echo "✅ Column `note` added successfully.\n";
    } else {
        echo "✅ Column `note` on `emails` table already exists.\n";
    }
} catch (\Throwable $e) {
    echo "⚠️ Emails table check warning: " . $e->getMessage() . "\n";
}

echo "============================================================\n";
echo "🎉 Migration completed! Database schema is up to date.\n";
echo "============================================================\n";
