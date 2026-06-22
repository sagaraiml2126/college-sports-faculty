-- =====================================================================
--  v16: Add YTC(pharmacy) faculty card linked to eng_faculty
--
--  Adds a new department row 'ytc_pharmacy' and grants eng_faculty
--  access to it. Existing YCP(pharmacy) card is preserved.
--
--  Idempotent: safe to run more than once. INSERT IGNORE on
--  faculty_departments handles the case where the link already exists.
-- =====================================================================

USE `csf_portal`;

-- New department row. code is the stable UNIQUE identifier.
INSERT INTO `departments`
    (`code`, `name`, `full_name`, `icon`, `is_active`, `display_order`)
VALUES
    ('ytc_pharmacy', 'YTC(pharmacy)', 'YTC(pharmacy) - Pharmacy Programs', 'bi-capsule', 1, 4);

-- Bump subsequent rows so display_order stays sequential
-- (ORDER BY falls back to `name` for ties, but cleaner numbers = cleaner UI).
UPDATE `departments` SET `display_order` = 5  WHERE `code` = 'dpharm'      AND `display_order` < 5;
UPDATE `departments` SET `display_order` = 6  WHERE `code` = 'mba'         AND `display_order` < 6;
UPDATE `departments` SET `display_order` = 7  WHERE `code` = 'mca'         AND `display_order` < 7;
UPDATE `departments` SET `display_order` = 8  WHERE `code` = 'bba'         AND `display_order` < 8;
UPDATE `departments` SET `display_order` = 9  WHERE `code` = 'bca'         AND `display_order` < 9;
UPDATE `departments` SET `display_order` = 10 WHERE `code` = 'architecture' AND `display_order` < 10;

-- Grant eng_faculty access to the new department.
INSERT IGNORE INTO `faculty_departments` (`faculty_id`, `department_id`)
SELECT f.id, d.id
  FROM `faculty` f
  JOIN `departments` d ON d.code = 'ytc_pharmacy'
 WHERE f.username = 'eng_faculty';
