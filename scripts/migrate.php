<?php
/**
 * KaiMail Database Migration Runner
 * Usage:
 *   CLI: php scripts/migrate.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "==============================================\n";
echo " KaiMail - Database Migration Runner\n";
echo "==============================================\n\n";

$baseDir = dirname(__DIR__);
$configFile = $baseDir . '/config/database.php';

if (!file_exists($configFile)) {
    echo "[ERROR] File config/database.php not found!\n";
    exit(1);
}

require_once $configFile;

try {
    $db = getDB();
    echo "[INFO] Connected to database: " . (defined('DB_NAME') ? DB_NAME : 'unknown') . "\n";
} catch (Throwable $e) {
    echo "[ERROR] Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

$migrationsDir = $baseDir . '/migrations';
if (!is_dir($migrationsDir)) {
    mkdir($migrationsDir, 0755, true);
}

$sqlFiles = glob($migrationsDir . '/*.sql');
sort($sqlFiles);

if (empty($sqlFiles)) {
    echo "[WARN] No SQL migration files found in migrations/ directory.\n";
} else {
    echo "[INFO] Found " . count($sqlFiles) . " migration file(s).\n\n";

    foreach ($sqlFiles as $file) {
        $filename = basename($file);
        echo ">>> Running: {$filename} ... ";

        $sqlContent = file_get_contents($file);
        if ($sqlContent === false || trim($sqlContent) === '') {
            echo "[SKIPPED - EMPTY]\n";
            continue;
        }

        try {
            $db->exec($sqlContent);
            echo "[SUCCESS]\n";
        } catch (PDOException $e) {
            echo "[FAILED]\n";
            echo "    Error: " . $e->getMessage() . "\n";
        }
    }
}

// Ensure Domain Columns & Integrity
echo "\n[INFO] Checking 'domains' table schema integrity...\n";
try {
    $stmt = $db->query("SHOW COLUMNS FROM `domains`");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    $neededColumns = [
        'webhook_secret' => "ALTER TABLE `domains` ADD COLUMN `webhook_secret` VARCHAR(64) NULL AFTER `is_active`",
        'verify_token'   => "ALTER TABLE `domains` ADD COLUMN `verify_token` VARCHAR(64) NULL AFTER `webhook_secret`",
        'type'           => "ALTER TABLE `domains` ADD COLUMN `type` ENUM('system', 'custom') DEFAULT 'system' AFTER `verify_token`"
    ];

    foreach ($neededColumns as $col => $alterSql) {
        if (!in_array($col, $columns, true)) {
            echo "  + Adding missing column `{$col}`... ";
            $db->exec($alterSql);
            echo "[ADDED]\n";
        } else {
            echo "  ✓ Column `{$col}` exists.\n";
        }
    }

    $db->exec("UPDATE `domains` SET `type` = 'system' WHERE `type` IS NULL OR `type` = ''");
    echo "  ✓ Default domain types verified.\n";

} catch (Throwable $e) {
    echo "[ERROR] Schema check failed: " . $e->getMessage() . "\n";
}

echo "\n==============================================\n";
echo " Migration finished successfully!\n";
echo "==============================================\n";
