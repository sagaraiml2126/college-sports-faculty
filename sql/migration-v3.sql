-- =====================================================================
--  Migration v3 — Faculty ↔ Department access control (many-to-many)
--
--  Adds a junction table so each FACULTY user only sees their
--  assigned departments on the selection page.
--  SUPER_ADMIN users have NO rows here → they see all departments.
-- =====================================================================

USE `csf_portal`;

-- ---------------------------------------------------------------------
-- 1. Junction table
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `faculty_departments` (
    `faculty_id`    INT UNSIGNED     NOT NULL,
    `department_id` TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (`faculty_id`, `department_id`),
    CONSTRAINT `fk_fd_faculty`
        FOREIGN KEY (`faculty_id`)    REFERENCES `faculty`(`id`)     ON DELETE CASCADE,
    CONSTRAINT `fk_fd_department`
        FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. Seed assignments
--    eng_faculty   (id=2) → Engineering        (id=1)
--    poly_faculty  (id=3) → Polytechnic        (id=2)
--    pharm_faculty (id=4) → Pharmacy(3), MBA(4), MCA(5), BBA(6), BCA(7), Architecture(8)
-- ---------------------------------------------------------------------
INSERT INTO `faculty_departments` (`faculty_id`, `department_id`) VALUES
    (2, 1),
    (3, 2),
    (4, 3),
    (4, 4),
    (4, 5),
    (4, 6),
    (4, 7),
    (4, 8);

-- =====================================================================
--  END OF MIGRATION v3
-- =====================================================================
