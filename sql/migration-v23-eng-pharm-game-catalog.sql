-- =====================================================================
--  Migration v23 — Extend game-checkbox picker to engineering, pharmacy,
--                  and ytc_pharmacy.
--
--  Background: v22 introduced dept_game_catalog + student_selected_games
--  and seeded 12 games for polytechnic and dpharm. This migration adds
--  the 9-game list (per client spec) for the engineering-family
--  departments:
--    1. Table Tennis, 2. Basketball, 3. Volleyball, 4. Kho Kho,
--    5. Kabaddi, 6. Cricket, 7. Badminton, 8. Football, 9. Athletics
--
--  Picker-dept detection is automatic: any dept that has rows in
--  dept_game_catalog is treated as a picker dept by the wizard, the
--  faculty profile, and the search. No PHP changes are needed.
--
--  Idempotent: re-running is safe. UNIQUE(department_id, game_code)
--  prevents duplicate rows. Each per-game INSERT below only fires
--  for a dept that doesn't already have that game_code.
-- =====================================================================

USE `csf_portal`;

-- ---------------------------------------------------------------------
-- 1. The 9 games, applied to each of: engineering, pharmacy, ytc_pharmacy.
--    display_order matches the client spec (1..9).
-- ---------------------------------------------------------------------

-- (1) Table Tennis
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'table_tennis', 'Table Tennis', 4, 1
  FROM `departments` d
 WHERE d.code IN ('engineering', 'pharmacy', 'ytc_pharmacy');

-- (2) Basketball
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'basketball',   'Basketball',   4, 2
  FROM `departments` d
 WHERE d.code IN ('engineering', 'pharmacy', 'ytc_pharmacy');

-- (3) Volleyball
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'volleyball',   'Volleyball',   4, 3
  FROM `departments` d
 WHERE d.code IN ('engineering', 'pharmacy', 'ytc_pharmacy');

-- (4) Kho Kho
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'kho_kho',      'Kho Kho',      4, 4
  FROM `departments` d
 WHERE d.code IN ('engineering', 'pharmacy', 'ytc_pharmacy');

-- (5) Kabaddi
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'kabaddi',      'Kabaddi',      4, 5
  FROM `departments` d
 WHERE d.code IN ('engineering', 'pharmacy', 'ytc_pharmacy');

-- (6) Cricket
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'cricket',      'Cricket',      4, 6
  FROM `departments` d
 WHERE d.code IN ('engineering', 'pharmacy', 'ytc_pharmacy');

-- (7) Badminton
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'badminton',    'Badminton',    4, 7
  FROM `departments` d
 WHERE d.code IN ('engineering', 'pharmacy', 'ytc_pharmacy');

-- (8) Football
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'football',     'Football',     4, 8
  FROM `departments` d
 WHERE d.code IN ('engineering', 'pharmacy', 'ytc_pharmacy');

-- (9) Athletics
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'athletics',    'Athletics',    4, 9
  FROM `departments` d
 WHERE d.code IN ('engineering', 'pharmacy', 'ytc_pharmacy');

-- =====================================================================
--  Verification queries (run by hand, uncommented if you want):
--    SELECT d.code, COUNT(c.id) AS catalog_rows
--      FROM departments d
--      LEFT JOIN dept_game_catalog c
--        ON c.department_id = d.id AND c.is_active = 1
--     GROUP BY d.id, d.code
--     ORDER BY d.id;
--
--  Expected: engineering=9, pharmacy=9, ytc_pharmacy=9,
--            polytechnic=12, dpharm=12, architecture=0, management=0.
-- =====================================================================

-- =====================================================================
--  END OF MIGRATION v23
-- =====================================================================
