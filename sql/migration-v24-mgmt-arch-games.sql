-- =====================================================================
--  Migration v24 — Extend game-checkbox picker to management + architecture.
--
--  Background: v22 introduced dept_game_catalog + student_selected_games
--  and seeded 12 games for polytechnic and dpharm. v23 added 9 games for
--  engineering, pharmacy, ytc_pharmacy. This v24 migration adds the
--  16-game list (per client spec) for the remaining two depts:
--    management, architecture.
--
--  The 16 games (display_order 1..16):
--    1. Kabaddi        9. Athletics
--    2. Football      10. Softball
--    3. Table Tennis  11. Netball
--    4. Kho Kho       12. Boxing
--    5. Cricket       13. Taekwondo
--    6. Handball      14. Baseball
--    7. Swimming      15. Shooting Ball
--    8. Wrestling (FS & GR)  -- Freestyle + Greco-Roman
--    (16) Badminton   -- note: appended to keep display_order aligned with spec
--
--  Picker-dept detection is automatic: any dept that has rows in
--  dept_game_catalog is treated as a picker dept by the wizard, the
--  faculty profile, and the search. No PHP changes are needed.
--
--  Idempotent: re-running is safe. UNIQUE(department_id, game_code)
--  prevents duplicate rows.
-- =====================================================================

USE `csf_portal`;

-- ---------------------------------------------------------------------
-- 16 games, applied to each of: management, architecture.
--    display_order matches the client spec (1..16).
-- ---------------------------------------------------------------------

-- (1) Kabaddi
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'kabaddi',       'Kabaddi',              4, 1
  FROM `departments` d
 WHERE d.code IN ('management', 'architecture');

-- (2) Football
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'football',      'Football',             4, 2
  FROM `departments` d
 WHERE d.code IN ('management', 'architecture');

-- (3) Table Tennis
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'table_tennis',  'Table Tennis',         4, 3
  FROM `departments` d
 WHERE d.code IN ('management', 'architecture');

-- (4) Kho Kho
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'kho_kho',       'Kho Kho',              4, 4
  FROM `departments` d
 WHERE d.code IN ('management', 'architecture');

-- (5) Cricket
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'cricket',       'Cricket',              4, 5
  FROM `departments` d
 WHERE d.code IN ('management', 'architecture');

-- (6) Handball
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'handball',      'Handball',             4, 6
  FROM `departments` d
 WHERE d.code IN ('management', 'architecture');

-- (7) Swimming
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'swimming',      'Swimming',             4, 7
  FROM `departments` d
 WHERE d.code IN ('management', 'architecture');

-- (8) Wrestling (FS & GR)
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,        `max_picks`, `display_order`)
SELECT d.id, 'wrestling',     'Wrestling (FS & GR)',   4, 8
  FROM `departments` d
 WHERE d.code IN ('management', 'architecture');

-- (9) Athletics
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'athletics',     'Athletics',            4, 9
  FROM `departments` d
 WHERE d.code IN ('management', 'architecture');

-- (10) Softball
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'softball',      'Softball',             4, 10
  FROM `departments` d
 WHERE d.code IN ('management', 'architecture');

-- (11) Netball
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'netball',       'Netball',              4, 11
  FROM `departments` d
 WHERE d.code IN ('management', 'architecture');

-- (12) Boxing
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'boxing',        'Boxing',               4, 12
  FROM `departments` d
 WHERE d.code IN ('management', 'architecture');

-- (13) Taekwondo
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'taekwondo',     'Taekwondo',            4, 13
  FROM `departments` d
 WHERE d.code IN ('management', 'architecture');

-- (14) Baseball
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'baseball',      'Baseball',             4, 14
  FROM `departments` d
 WHERE d.code IN ('management', 'architecture');

-- (15) Shooting Ball
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'shooting_ball', 'Shooting Ball',        4, 15
  FROM `departments` d
 WHERE d.code IN ('management', 'architecture');

-- (16) Badminton
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,      `display_name`,   `max_picks`, `display_order`)
SELECT d.id, 'badminton',     'Badminton',            4, 16
  FROM `departments` d
 WHERE d.code IN ('management', 'architecture');

-- =====================================================================
--  Verification queries (run by hand, uncommented if you want):
--    SELECT d.code, COUNT(c.id) AS catalog_rows
--      FROM departments d
--      LEFT JOIN dept_game_catalog c
--        ON c.department_id = d.id AND c.is_active = 1
--     GROUP BY d.id, d.code
--     ORDER BY d.id;
--
--  Expected after v22+v23+v24:
--    engineering=9, pharmacy=9, ytc_pharmacy=9, management=16,
--    architecture=16, polytechnic=12, dpharm=12.
-- =====================================================================

-- =====================================================================
--  END OF MIGRATION v24
-- =====================================================================