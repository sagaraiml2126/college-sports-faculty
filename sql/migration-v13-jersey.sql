-- =====================================================================
--  Migration v13: Jersey Kit Management
--  Adds jersey_forms (per-team collection window) and
--  jersey_requests (individual student submissions).
-- =====================================================================

CREATE TABLE IF NOT EXISTS `jersey_forms` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_name`       VARCHAR(80)  NOT NULL,
    `event_label`     VARCHAR(120) NOT NULL,
    `academic_year`   VARCHAR(10)  NULL,
    `department_id`   TINYINT UNSIGNED NOT NULL,
    `is_open`         TINYINT(1)   NOT NULL DEFAULT 0,
    `access_token`    VARCHAR(64)  NOT NULL,
    `created_by`      INT UNSIGNED NULL,
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_jersey_form_team` (`department_id`, `game_name`, `event_label`, `academic_year`),
    UNIQUE KEY `uq_jersey_token` (`access_token`),
    KEY `idx_jersey_form_dept` (`department_id`),
    CONSTRAINT `fk_jersey_form_department` FOREIGN KEY (`department_id`)
        REFERENCES `departments`(`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_jersey_form_faculty` FOREIGN KEY (`created_by`)
        REFERENCES `faculty`(`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jersey_requests` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `jersey_form_id`   INT UNSIGNED NOT NULL,
    `student_id`       INT UNSIGNED NOT NULL,
    `enrollment_no`    VARCHAR(40)  NOT NULL,
    `mobile`           VARCHAR(20)  NOT NULL,
    `tshirt_size`      ENUM('XS','S','M','L','XL','XXL','3XL') NOT NULL,
    `jersey_name`      VARCHAR(30)  NOT NULL,
    `preferred_number` SMALLINT UNSIGNED NOT NULL,
    `final_number`     SMALLINT UNSIGNED NULL,
    `status`           ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_jersey_student` (`jersey_form_id`, `student_id`),
    KEY `idx_jersey_form` (`jersey_form_id`),
    KEY `idx_jersey_student` (`student_id`),
    KEY `idx_jersey_number` (`jersey_form_id`, `preferred_number`),
    CONSTRAINT `fk_jersey_form` FOREIGN KEY (`jersey_form_id`)
        REFERENCES `jersey_forms`(`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_jersey_student` FOREIGN KEY (`student_id`)
        REFERENCES `students`(`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  END OF MIGRATION v13
-- =====================================================================
