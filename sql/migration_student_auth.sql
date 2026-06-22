-- =====================================================================
--  Migration: student self-registration & login
--  Adds auth columns to the existing `students` table.
--  Safe to re-run: uses IF NOT EXISTS where possible.
-- =====================================================================

USE `csf_portal`;

-- 1. New columns on students -----------------------------------------------
-- (enrollment_no is intentionally left NULL for self-registered students;
--  it can be back-filled later by faculty.)

ALTER TABLE `students`
    ADD COLUMN IF NOT EXISTS `password_hash`  VARCHAR(255) NULL          AFTER `photo_path`,
    ADD COLUMN IF NOT EXISTS `is_active`      TINYINT(1)   NOT NULL DEFAULT 1 AFTER `password_hash`,
    ADD COLUMN IF NOT EXISTS `registered_at`  TIMESTAMP    NULL          AFTER `is_active`,
    ADD COLUMN IF NOT EXISTS `last_login_at`  TIMESTAMP    NULL          AFTER `registered_at`;

-- 2. Email must be unique for self-registration -----------------------------
-- Existing rows may have NULL or duplicate emails; only the index will
-- reject future duplicates.  MySQL allows multiple NULLs in a UNIQUE index.

ALTER TABLE `students`
    ADD UNIQUE KEY IF NOT EXISTS `uq_student_email` (`email`);

-- 3. Optional: a flag distinguishing self-registered from faculty-added -----
-- Useful for analytics/audits. Defaults to 0 (faculty-added).

ALTER TABLE `students`
    ADD COLUMN IF NOT EXISTS `is_self_registered` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`;
