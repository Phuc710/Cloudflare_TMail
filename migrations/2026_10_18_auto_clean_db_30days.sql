-- ===================================================================
-- Migration: Auto Clean DB - 30-Day User & API Retention Policy
-- Target: kaishopi_kaimail
-- Policy: Retain temporary emails created by User and API for 30 days.
--         Admin emails (created_by = 'admin') are strictly preserved forever.
-- ===================================================================

-- 1. Create optimized composite index for rapid retention filtering
SET @exist_idx := (
    SELECT COUNT(*) 
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
      AND table_name = 'emails' 
      AND index_name = 'idx_emails_createdby_createdat'
);
SET @sql_idx := IF(
    @exist_idx = 0, 
    'CREATE INDEX idx_emails_createdby_createdat ON emails (created_by, created_at)', 
    'SELECT 1'
);
PREPARE stmt_idx FROM @sql_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

-- 2. Register MySQL Event for native background execution
-- Runs every 24 hours to automatically purge User & API emails older than 30 days
DROP EVENT IF EXISTS evt_auto_clean_tempmail_30days;
CREATE EVENT evt_auto_clean_tempmail_30days
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
COMMENT 'Purge User and API tempmails older than 30 days while preserving Admin emails'
DO
  DELETE FROM emails 
  WHERE (created_by IS NULL OR created_by != 'admin') 
    AND created_at < NOW() - INTERVAL 30 DAY;
