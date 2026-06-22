-- =====================================================================
--  Migration v9: Final Team Selections
-- =====================================================================

CREATE TABLE IF NOT EXISTS `final_teams` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_name`     VARCHAR(80)  NOT NULL,
    `event_label`   VARCHAR(120) NOT NULL,
    `academic_year` VARCHAR(10)  NULL,
    `student_id`    INT UNSIGNED NOT NULL,
    `roll_no`       VARCHAR(40)  NOT NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `added_by`      INT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    KEY `idx_final_game_event` (`game_name`, `event_label`, `academic_year`),
    KEY `idx_final_student` (`student_id`),
    CONSTRAINT `fk_final_student` FOREIGN KEY (`student_id`)
        REFERENCES `students`(`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_final_faculty` FOREIGN KEY (`added_by`)
        REFERENCES `faculty`(`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Unique constraint to prevent duplicate students on the same final list
CREATE UNIQUE INDEX `uq_final_student_list` ON `final_teams` (`game_name`, `event_label`, `academic_year`, `student_id`);
