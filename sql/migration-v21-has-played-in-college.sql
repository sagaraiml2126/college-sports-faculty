-- =====================================================================
--  Migration v21 — Add has_played_in_college to gate Step 4 (Played
--  History) textarea.
--
--  The student answers a Yes/No question "Have you played any sports
--  representing this college?". If No, the textarea is hidden and
--  sports_history stays NULL. If Yes, the textarea is shown and the
--  student lists the events / tournaments / selections.
--
--  Idempotent: re-running on a DB that already has the column emits a
--  'skipping' info row.
-- =====================================================================

USE `csf_portal`;

SET @c := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'students'
       AND COLUMN_NAME  = 'has_played_in_college'
);
SET @d := IF(@c = 0,
    'ALTER TABLE `students` ADD COLUMN `has_played_in_college` TINYINT(1) NULL DEFAULT NULL AFTER `sports_history`',
    'SELECT ''has_played_in_college already exists — skipping'' AS info'
);
PREPARE stmt FROM @d;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill: any student who already has a non-empty sports_history
-- is presumed to have played. Students with NULL/empty keep NULL
-- (the dashboard will default the radio to No when the column is NULL).
SET @c2 := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'students'
       AND COLUMN_NAME  = 'has_played_in_college'
);
SET @d2 := IF(@c2 = 1,
    'UPDATE `students` SET `has_played_in_college` = 1 WHERE `has_played_in_college` IS NULL AND `sports_history` IS NOT NULL AND `sports_history` <> ''''',
    'SELECT ''has_played_in_college not present — skipping backfill'' AS info'
);
PREPARE stmt2 FROM @d2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- =====================================================================
--  END OF MIGRATION v21
-- =====================================================================
