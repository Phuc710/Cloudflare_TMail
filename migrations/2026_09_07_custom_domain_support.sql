-- ==========================================================
-- KaiMail Migration: 2026_09_07_custom_domain_support.sql
-- Description: Thêm hỗ trợ Custom Domain (webhook_secret, verify_token, type)
-- Target Database: MySQL 5.7+ / MySQL 8.0+ / MariaDB 10.3+
-- ==========================================================

-- 1. Thêm các cột mới vào bảng `domains` (Tương thích mọi phiên bản MySQL / MariaDB)
SET @dbname = DATABASE();
SET @tablename = "domains";

-- Thêm cột webhook_secret nếu chưa có
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = "webhook_secret"
  ) > 0,
  "SELECT 1",
  "ALTER TABLE `domains` ADD COLUMN `webhook_secret` VARCHAR(64) NULL AFTER `is_active`;"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Thêm cột verify_token nếu chưa có
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = "verify_token"
  ) > 0,
  "SELECT 1",
  "ALTER TABLE `domains` ADD COLUMN `verify_token` VARCHAR(64) NULL AFTER `webhook_secret`;"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Thêm cột type nếu chưa có
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = "type"
  ) > 0,
  "SELECT 1",
  "ALTER TABLE `domains` ADD COLUMN `type` ENUM('system', 'custom') DEFAULT 'system' AFTER `verify_token`;"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Đảm bảo toàn bộ domain hiện tại được đánh dấu là 'system'
UPDATE `domains` SET `type` = 'system' WHERE `type` IS NULL OR `type` = '';
