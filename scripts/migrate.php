<?php
declare(strict_types=1);

/**
 * KaiMail Database Migration Tool (CLI & Browser)
 * Run from CLI: php scripts/migrate.php
 */

require_once __DIR__ . '/../config/database.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><title>KaiMail DB Migration</title>';
    echo '<style>body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#f8fafc;padding:30px;line-height:1.6}';
    echo '.card{max-width:700px;margin:0 auto;background:#1e293b;border-radius:12px;padding:24px;border:1px solid #334155}';
    echo '.success{color:#10b981;font-weight:bold} .error{color:#ef4444;font-weight:bold} .info{color:#38bdf8}';
    echo 'pre{background:#0b0f19;padding:12px;border-radius:8px;overflow-x:auto}</style></head><body><div class="card">';
    echo '<h2>🚀 KaiMail Database Migration</h2>';
}

function logMsg(string $msg, string $type = 'info'): void {
    global $isCli;
    if ($isCli) {
        $prefix = match ($type) {
            'success' => "\033[32m[SUCCESS]\033[0m ",
            'error'   => "\033[31m[ERROR]\033[0m ",
            'warn'    => "\033[33m[WARN]\033[0m ",
            default   => "\033[36m[INFO]\033[0m ",
        };
        echo $prefix . $msg . PHP_EOL;
    } else {
        $class = match ($type) {
            'success' => 'success',
            'error'   => 'error',
            default   => 'info',
        };
        echo "<p class=\"{$class}\">{$msg}</p>";
    }
}

try {
    $db = getDB();
    logMsg("Connected to database: " . DB_NAME, 'info');

    $tables = [
        'domains' => "CREATE TABLE IF NOT EXISTS `domains` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `domain` VARCHAR(255) NOT NULL UNIQUE,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_domain` (`domain`),
            INDEX `idx_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        'emails' => "CREATE TABLE IF NOT EXISTS `emails` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `domain_id` INT NOT NULL,
            `email` VARCHAR(255) NOT NULL UNIQUE,
            `name_type` ENUM('vn', 'en', 'custom') DEFAULT 'en',
            `is_done` TINYINT(1) DEFAULT 0,
            `created_by` VARCHAR(20) DEFAULT 'user',
            `note` VARCHAR(500) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`domain_id`) REFERENCES `domains`(`id`) ON DELETE CASCADE,
            INDEX `idx_email` (`email`),
            INDEX `idx_domain_id` (`domain_id`),
            INDEX `idx_created_by` (`created_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        'messages' => "CREATE TABLE IF NOT EXISTS `messages` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `email_id` INT NOT NULL,
            `from_email` VARCHAR(255) NOT NULL,
            `from_name` VARCHAR(255) DEFAULT '',
            `subject` VARCHAR(500) DEFAULT '(No subject)',
            `snippet` VARCHAR(255) DEFAULT '',
            `body_text` LONGTEXT,
            `body_html` LONGTEXT,
            `message_id` VARCHAR(255),
            `is_read` TINYINT(1) DEFAULT 0,
            `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`email_id`) REFERENCES `emails`(`id`) ON DELETE CASCADE,
            INDEX `idx_email_id` (`email_id`),
            INDEX `idx_received` (`received_at`),
            INDEX `idx_messages_email_received` (`email_id`, `received_at`),
            INDEX `idx_messages_email_read` (`email_id`, `is_read`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        'api_tokens' => "CREATE TABLE IF NOT EXISTS `api_tokens` (
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
            INDEX `idx_key_id` (`key_id`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        'settings' => "CREATE TABLE IF NOT EXISTS `settings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `setting_key` VARCHAR(100) NOT NULL UNIQUE,
            `setting_value` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_key` (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    ];

    foreach ($tables as $name => $query) {
        $db->exec($query);
        logMsg("✔ Table `{$name}` checked/created successfully.", 'success');
    }

    // Default Domain & Settings
    $db->exec("INSERT IGNORE INTO `domains` (`domain`, `is_active`) VALUES ('kaishop.id.vn', 1);");
    logMsg("✔ Default domain checked.", 'success');

    $defaultSettings = [
        'webhook_secret' => 'CHANGE_ME_IN_ENV_ONLY',
        'default_domain' => 'kaishop.id.vn',
        'primary_url' => 'https://tmail.kaishop.id.vn',
        'api_domains' => 'kaishop.id.vn,trongnghia.store',
        'app_name' => 'KaiMail',
        'app_version' => '1.1',
        'maintenance_mode' => '0',
    ];

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?)");
    foreach ($defaultSettings as $k => $v) {
        $stmt->execute([$k, $v]);
    }
    logMsg("✔ Default settings seeded.", 'success');

    logMsg("🎉 Migration completed without errors!", 'success');

} catch (\Throwable $e) {
    logMsg("Migration failed: " . $e->getMessage(), 'error');
}

if (!$isCli) {
    echo '<hr style="border-color:#334155;margin:20px 0;"><a href="/adminkaishop/tokens" style="display:inline-block;padding:10px 18px;background:#10b981;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;">👉 Trở lại Quản Lý API Token</a>';
    echo '</div></body></html>';
}
