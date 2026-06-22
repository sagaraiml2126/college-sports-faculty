-- =====================================================================
--  Migration v19 — Student wizard state columns
--
--  Adds two additive columns to `students` so the new 6-step profile
--  wizard (Personal → Academic → Sports → Played History → Documents →
--  Preview) can persist its position across page loads:
--
--    form_step          TINYINT UNSIGNED NULL — NULL = not started,
--                         1..6 = current step the student is on
--    form_submitted_at  TIMESTAMP NULL — NULL = draft (still being
--                         edited by the student), set to NOW() the
--                         first time the student hits Submit on the
--                         final preview. Faculty can still edit.
--
--  Both columns are nullable and default to NULL. Idempotent:
--  re-running the migration on a database that already has these
--  columns emits a `skipping` info row and exits cleanly.
-- =====================================================================

USE `csf_portal`;

-- 1. form_submitted_at
SET @c1 := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'students'
       AND COLUMN_NAME  = 'form_submitted_at'
);
SET @d1 := IF(@c1 = 0,
    'ALTER TABLE `students` ADD COLUMN `form_submitted_at` TIMESTAMP NULL AFTER `is_self_registered`',
    'SELECT ''form_submitted_at already exists — skipping'' AS info'
);
PREPARE stmt FROM @d1;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. form_step
SET @c2 := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'students'
       AND COLUMN_NAME  = 'form_step'
);
SET @d2 := IF(@c2 = 0,
    'ALTER TABLE `students` ADD COLUMN `form_step` TINYINT UNSIGNED NULL DEFAULT NULL AFTER `form_submitted_at`',
    'SELECT ''form_step already exists — skipping'' AS info'
);
PREPARE stmt FROM @d2;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================================
--  END OF MIGRATION v19
-- =====================================================================
