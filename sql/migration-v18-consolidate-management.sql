-- =====================================================================
--  v18: Consolidate 4 mgmt departments (mba, mca, bba, bca) into ONE
--       'management' department, owned by pharm_faculty.
--
--  Context:
--    The 4 management programs (MBA / MCA / BBA / BCA) were each their
--    own department card on the faculty-select page. Per UX request,
--    they should now appear as a single 'Management' card beside
--    Architecture — both owned by pharm_faculty.
--
--    pharm_faculty's role is unchanged (no separate mgmt_faculty user);
--    it's still the single faculty handling pharmacy-adjacent programs.
--
--  Steps:
--    1. Insert the new 'management' department row (idempotent).
--    2. Move students from the 4 old departments into 'management'.
--    3. Update pharm_faculty links: drop the 4 old depts, add 'management'.
--    4. Delete the 4 old department rows.
--    5. Bump architecture's display_order to 7 so the picker stays tidy.
--    6. Verification SELECT.
--
--  Idempotent: every step uses IGNORE / conditional UPDATE so re-runs
--  are safe.
-- =====================================================================

USE `csf_portal`;

-- ---------------------------------------------------------------------
-- 1. Insert the consolidated 'management' department row.
--    INSERT IGNORE so re-runs of v18 don't fail with duplicate key.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `departments`
    (`code`,     `name`,        `full_name`,                  `icon`,          `is_active`, `display_order`)
VALUES
    ('management','Management',  'MBA / MCA / BBA / BCA Programs','bi-briefcase',  1,           6);

-- ---------------------------------------------------------------------
-- 2. Move students from the 4 old departments into 'management'.
--    Idempotent: re-runs are a no-op because students with department_id
--    = management.id (the new dept) won't match the WHERE clause.
-- ---------------------------------------------------------------------
UPDATE `students` s
  JOIN `departments` old_d ON old_d.id = s.department_id
  JOIN `departments` new_d ON new_d.code = 'management'
   SET s.department_id = new_d.id
 WHERE old_d.code IN ('mba', 'mca', 'bba', 'bca');

-- ---------------------------------------------------------------------
-- 3. Update pharm_faculty's links.
--    3a. Drop the 4 old-dept links (if any survived v14 or were re-added
--        by the user manually).
--    3b. Add the new 'management' link (idempotent via INSERT IGNORE).
-- ---------------------------------------------------------------------
DELETE fd
  FROM `faculty_departments` fd
  JOIN `faculty`     f ON f.id = fd.faculty_id
  JOIN `departments` d ON d.id = fd.department_id
 WHERE f.username = 'pharm_faculty'
   AND d.code IN ('mba', 'mca', 'bba', 'bca');

INSERT IGNORE INTO `faculty_departments` (`faculty_id`, `department_id`)
SELECT f.id, d.id
  FROM `faculty`     f
  JOIN `departments` d
    ON d.code = 'management'
 WHERE f.username = 'pharm_faculty';

-- ---------------------------------------------------------------------
-- 4. Delete the 4 old department rows.
--    Safe because step 2 already moved any students off them, and step 3
--    already removed any faculty_departments links pointing to them.
--    We use a transactional order to be extra-safe in case of partial
--    failures (MyISAM doesn't support transactions, but InnoDB does —
--    and faculty_departments / students / departments are all InnoDB).
-- ---------------------------------------------------------------------
START TRANSACTION;
DELETE FROM `departments` WHERE `code` IN ('mba', 'mca', 'bba', 'bca');
COMMIT;

-- ---------------------------------------------------------------------
-- 5. Bump architecture's display_order to 7 (after the new 'management'
--    at 6) so the picker card order stays sequential and tidy.
-- ---------------------------------------------------------------------
UPDATE `departments` SET `display_order` = 7 WHERE `code` = 'architecture';

-- ---------------------------------------------------------------------
-- 6. Verification — fails loud if the result is wrong.
--    Expected after a clean apply:
--      7 active departments (engineering, polytechnic, pharmacy,
--      ytc_pharmacy, dpharm, management, architecture)
--      pharm_faculty → 2 links: management, architecture
--      eng_faculty   → 3 links: engineering, pharmacy, ytc_pharmacy
--      poly_faculty  → 2 links: polytechnic, dpharm
--      0 students in the 4 old dept codes
-- ---------------------------------------------------------------------
SELECT 'DEPARTMENTS' AS section, code, name, display_order
  FROM `departments`
 WHERE is_active = 1
 ORDER BY display_order, code;

SELECT 'FACULTY_LINKS' AS section, f.username, d.code
  FROM `faculty`            f
  JOIN `faculty_departments` fd ON fd.faculty_id    = f.id
  JOIN `departments`         d  ON d.id            = fd.department_id
 WHERE f.username IN ('pharm_faculty', 'eng_faculty', 'poly_faculty', 'dpharm_faculty')
 ORDER BY f.username, d.code;

SELECT 'STUDENTS_IN_OLD_DEPTS' AS section, d.code, COUNT(*) AS cnt
  FROM `students` s
  JOIN `departments` d ON d.id = s.department_id
 WHERE d.code IN ('mba', 'mca', 'bba', 'bca', 'management')
 GROUP BY d.code
 ORDER BY d.code;

-- =====================================================================
--  END OF MIGRATION v18
-- =====================================================================