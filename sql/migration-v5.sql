-- =====================================================================
--  Migration v5 — Provisional Player Lists
--
--  Adds the `provisional_entries` table that backs the Provisional
--  Players feature in /admin/provisional_list.php. Faculty can build
--  a shortlist of students for a specific game (Cricket, Kho-Kho, ...)
--  and event (e.g. "Zonal 2025-26"). One row per (game, event, ay,
--  student). Lists persist forever; nothing in the schema or code
--  auto-deletes entries.
--
--  Faculty-only; department isolation is enforced in PHP via
--  scope_sql_department() — there is intentionally no department_id
--  column on this table, every read/write joins through students
--  (same pattern as achievements).
--
--  Safe to re-run: CREATE TABLE IF NOT EXISTS.
-- =====================================================================

USE `csf_portal`;

-- ---------------------------------------------------------------------
-- 1. provisional_entries
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `provisional_entries` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_name`       VARCHAR(80)  NOT NULL,                -- e.g. "Cricket", "Kho-Kho"
    `event_label`     VARCHAR(120) NOT NULL DEFAULT '',    -- e.g. "Zonal 2025-26"
    `event_date`      DATE         NULL,                    -- optional upcoming event date
    `academic_year`   VARCHAR(10)  NULL,                    -- matches students.academic_year
    `student_id`      INT UNSIGNED NOT NULL,
    `notes`           VARCHAR(500) NULL,                    -- e.g. "wicketkeeper" (free-text)
    `is_provisional`  TINYINT(1)   NOT NULL DEFAULT 1,      -- reserved for future "final team" flow
    `added_by`        INT UNSIGNED NOT NULL,                -- faculty who added the entry
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- Hot path: "show me this list" — a (game, event, ay) triple
    KEY `idx_prov_game_event` (`game_name`, `event_label`, `academic_year`),
    -- Reverse lookup: "what lists is this student on?"
    KEY `idx_prov_student`    (`student_id`),
    -- Audit
    KEY `idx_prov_added_by`   (`added_by`),
    KEY `idx_prov_created`    (`created_at`),

    CONSTRAINT `fk_prov_student`
        FOREIGN KEY (`student_id`) REFERENCES `students`(`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_prov_added_by`
        FOREIGN KEY (`added_by`) REFERENCES `faculty`(`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  END OF MIGRATION v5
-- =====================================================================
