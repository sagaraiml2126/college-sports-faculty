-- =====================================================================
--  Migration v4 — Add D.Pharm (Diploma in Pharmacy) department + faculty
--
--  Adds a new department (display_order=4), a new FACULTY user
--  (dpharm_faculty), and links the user to that one department.
--  Does NOT modify the existing pharmacy dept, pharm_faculty user, or
--  any student rows.
--
--  Safe to run on existing installs: uses sub-selects by username/code
--  instead of hard-coded ids, so it works regardless of AUTO_INCREMENT
--  state.
-- =====================================================================

USE `csf_portal`;

-- ---------------------------------------------------------------------
-- 1. New department
--    Sits between Pharmacy (3) and MBA (was 4) in the picker.
--    Uses display_order=4; the renumber step below bumps MBA..Architecture
--    to 5..9 so ORDER BY display_order stays tidy.
--    INSERT IGNORE so re-running this migration is safe (uq_dept_code).
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `departments`
    (`code`,    `name`,     `full_name`,           `icon`,        `is_active`, `display_order`)
VALUES
    ('dpharm',  'D.Pharm',  'Diploma in Pharmacy', 'bi-capsule',  1,           4);

-- ---------------------------------------------------------------------
-- 2. Renumber existing departments so display_order is 1..9 with no gap
--    and no collision at position 4.
-- ---------------------------------------------------------------------
UPDATE `departments` SET `display_order` = 5 WHERE `code` = 'mba';
UPDATE `departments` SET `display_order` = 6 WHERE `code` = 'mca';
UPDATE `departments` SET `display_order` = 7 WHERE `code` = 'bba';
UPDATE `departments` SET `display_order` = 8 WHERE `code` = 'bca';
UPDATE `departments` SET `display_order` = 9 WHERE `code` = 'architecture';

-- ---------------------------------------------------------------------
-- 3. New faculty user
--    Uses a real bcrypt hash (not the __FACULTY_HASH__ placeholder from
--    seed.sql) so phpMyAdmin "Import" works in a single step. The hash
--    below verifies against the default password 'Faculty@123'. The
--    user is forced to change it on first login (must_reset_pw=1).
-- ---------------------------------------------------------------------
INSERT INTO `faculty`
    (`username`,       `email`,           `full_name`,              `password_hash`,
     `role`,           `phone`,           `is_active`, `must_reset_pw`)
VALUES
    ('dpharm_faculty', 'dpharm@yspm.org', 'Dr. Pawar (D.Pharm)',
     '$2y$12$u2yoTmeRx/A194Aoei2ELeu9L0mh5bEaOStZ27/bDMtvGrUvxUeEi',
     'FACULTY',        '+91-9000000004',  1,           1);

-- ---------------------------------------------------------------------
-- 4. Link dpharm_faculty -> dpharm department.
--    Sub-select form (idempotent) so it works on any existing DB
--    regardless of id values, and won't fail if re-run.
-- ---------------------------------------------------------------------
INSERT INTO `faculty_departments` (`faculty_id`, `department_id`)
SELECT f.id, d.id
  FROM `faculty` f
  JOIN `departments` d ON d.code = 'dpharm'
 WHERE f.username = 'dpharm_faculty'
   AND NOT EXISTS (
       SELECT 1 FROM `faculty_departments` fd
        WHERE fd.faculty_id    = f.id
          AND fd.department_id = d.id
   );

-- ---------------------------------------------------------------------
-- 5. Link poly_faculty -> dpharm department (requested mapping)
-- ---------------------------------------------------------------------
INSERT INTO `faculty_departments` (`faculty_id`, `department_id`)
SELECT f.id, d.id
  FROM `faculty` f
  JOIN `departments` d ON d.code = 'dpharm'
 WHERE f.username = 'poly_faculty'
   AND NOT EXISTS (
       SELECT 1 FROM `faculty_departments` fd
        WHERE fd.faculty_id    = f.id
          AND fd.department_id = d.id
   );

-- =====================================================================
--  END OF MIGRATION v4
-- =====================================================================
