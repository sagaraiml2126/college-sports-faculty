-- =====================================================================
--  College Sports Faculty Portal — v2 Migration
--  Adds: students.sports_history
--
--  Run once on an existing database:
--      mysql -u root csf_portal < sql/migration-v2.sql
--
--  Or in phpMyAdmin: select csf_portal → Import → choose this file → Go.
--
--  This is non-destructive: it only ADDs a column. Existing data is
--  preserved. The new column defaults to NULL.
-- =====================================================================

USE `csf_portal`;

-- 1. Add the sports_history column if it doesn't already exist
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'students'
       AND COLUMN_NAME  = 'sports_history'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE `students` ADD COLUMN `sports_history` TEXT NULL AFTER `achievements`',
    'SELECT "sports_history already exists — skipping" AS info'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
