-- =======================================================
-- KaiMail Database Migration Script
-- Version: 1.1 (Multi-tenant API Token & System Settings)
-- =======================================================

-- 1. BẢNG QUẢN LÝ TÊN MIỀN
CREATE TABLE IF NOT EXISTS `domains` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `domain` VARCHAR(255) NOT NULL UNIQUE,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_domain` (`domain`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. BẢNG EMAIL TẠM THỜI
CREATE TABLE IF NOT EXISTS `emails` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. BẢNG TIN NHẮN (MESSAGES)
CREATE TABLE IF NOT EXISTS `messages` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. BẢNG API TOKENS (ĐA NGƯỜI DÙNG / MULTI-TENANT)
CREATE TABLE IF NOT EXISTS `api_tokens` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. BẢNG SETTINGS (CẤU HÌNH HỆ THỐNG TOÀN CỤC)
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. DỮ LIỆU MẪU KHỞI TẠO (INSERT IGNORE)
INSERT IGNORE INTO `domains` (`domain`, `is_active`) VALUES 
('kaishop.id.vn', 1);

INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES 
('webhook_secret', 'CHANGE_ME_IN_ENV_ONLY'),
('default_domain', 'kaishop.id.vn'),
('primary_url', 'https://tmail.kaishop.id.vn'),
('api_domains', 'kaishop.id.vn,trongnghia.store'),
('app_name', 'KaiMail'),
('app_version', '1.1'),
('maintenance_mode', '0');
