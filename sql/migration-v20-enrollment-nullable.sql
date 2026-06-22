-- =====================================================================
--  Migration v20 — Make enrollment_no nullable so self-registered
--  students can leave it empty until they fill it in on Step 2 of the
--  wizard.
--
--  The SELF-YYYYMMDD-<random> placeholder was confusing students.
--  With this change, register_process.php inserts NULL and the student
--  is required to enter a real enrollment number on Step 2 of the
--  wizard. The UNIQUE constraint still allows multiple NULLs in
--  MySQL/InnoDB, so the constraint stays intact.
--
--  Idempotent: re-running on a DB that already has the column nullable
--  emits a 'skipping' info row.
-- =====================================================================

USE `csf_portal`;

SET @c := (
    SELECT IS_NULLABLE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'students'
       AND COLUMN_NAME  = 'enrollment_no'
);
SET @d := IF(@c = 'NO',
    'ALTER TABLE `students` MODIFY COLUMN `enrollment_no` VARCHAR(40) NULL',
    'SELECT ''enrollment_no already nullable — skipping'' AS info'
);
PREPARE stmt FROM @d;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================================
--  END OF MIGRATION v20
-- =====================================================================