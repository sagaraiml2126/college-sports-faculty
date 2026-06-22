-- =====================================================================
--  Migration v22 — Add game-checkbox picker for sports enrollment
--
--  Two new tables:
--    dept_game_catalog     : which games each department offers, and
--                            how many games a student must pick (default 4)
--    student_selected_games: the per-student picks (4 rows per fully-
--                            enrolled student). Cascade-deletes with the
--                            student row.
--
--  Scope of THIS migration: seed 12 games for `polytechnic` and
--  12 games for `dpharm`. Other departments (engineering, pharmacy,
--  ytc_pharmacy, management, architecture) keep their existing
--  sport_1 / sport_2 / achievements free-text flow for now.
--
--  Idempotent: re-running is safe — uses information_schema gates
--  to skip tables that already exist, and INSERT IGNORE for seeds
--  (UNIQUE on department_id, game_code prevents duplicates).
-- =====================================================================

USE `csf_portal`;

-- ---------------------------------------------------------------------
-- 1. dept_game_catalog
-- ---------------------------------------------------------------------
SET @t := (
    SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'dept_game_catalog'
);
SET @sql := IF(@t = 0,
    'CREATE TABLE `dept_game_catalog` (
        `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `department_id`  TINYINT UNSIGNED NOT NULL,
        `game_code`      VARCHAR(40)  NOT NULL,
        `display_name`   VARCHAR(80)  NOT NULL,
        `max_picks`      TINYINT UNSIGNED NOT NULL DEFAULT 4,
        `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
        `display_order`  INT UNSIGNED NOT NULL DEFAULT 0,
        `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_dept_game` (`department_id`, `game_code`),
        KEY `idx_dept_active` (`department_id`, `is_active`, `display_order`),
        CONSTRAINT `fk_dept_game_dept` FOREIGN KEY (`department_id`)
            REFERENCES `departments`(`id`) ON UPDATE CASCADE ON DELETE CASCADE
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT ''dept_game_catalog already exists — skipping'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 2. student_selected_games
-- ---------------------------------------------------------------------
SET @t := (
    SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'student_selected_games'
);
SET @sql := IF(@t = 0,
    'CREATE TABLE `student_selected_games` (
        `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `student_id` INT UNSIGNED NOT NULL,
        `game_code`  VARCHAR(40)  NOT NULL,
        `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_student_game` (`student_id`, `game_code`),
        KEY `idx_ssg_game` (`game_code`),
        CONSTRAINT `fk_ssg_student` FOREIGN KEY (`student_id`)
            REFERENCES `students`(`id`) ON UPDATE CASCADE ON DELETE CASCADE
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT ''student_selected_games already exists — skipping'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 3. Seed: 12 games for `polytechnic` and 12 for `dpharm`.
--    INSERT IGNORE is safe on re-run thanks to UNIQUE(department_id, game_code).
-- ---------------------------------------------------------------------
-- The 12-game list (per client spec), ordered to match the spec:
--   Kabaddi, Kho Kho, Volleyball, Basketball, Hockey, Football,
--   Badminton, Table Tennis, Cricket, Carrom, 4x100m Relay, Chess

INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,    `max_picks`, `display_order`)
SELECT d.id, 'kabaddi',         'Kabaddi',         4, 1
  FROM `departments` d WHERE d.code = 'polytechnic';

INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,    `max_picks`, `display_order`)
SELECT d.id, 'kho_kho',         'Kho Kho',         4, 2
  FROM `departments` d WHERE d.code = 'polytechnic';

INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,    `max_picks`, `display_order`)
SELECT d.id, 'volleyball',      'Volleyball',      4, 3
  FROM `departments` d WHERE d.code = 'polytechnic';

INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,    `max_picks`, `display_order`)
SELECT d.id, 'basketball',      'Basketball',      4, 4
  FROM `departments` d WHERE d.code = 'polytechnic';

INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,    `max_picks`, `display_order`)
SELECT d.id, 'hockey',          'Hockey',          4, 5
  FROM `departments` d WHERE d.code = 'polytechnic';

INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,    `max_picks`, `display_order`)
SELECT d.id, 'football',        'Football',        4, 6
  FROM `departments` d WHERE d.code = 'polytechnic';

INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,    `max_picks`, `display_order`)
SELECT d.id, 'badminton',       'Badminton',       4, 7
  FROM `departments` d WHERE d.code = 'polytechnic';

INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,    `max_picks`, `display_order`)
SELECT d.id, 'table_tennis',    'Table Tennis',    4, 8
  FROM `departments` d WHERE d.code = 'polytechnic';

INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,    `max_picks`, `display_order`)
SELECT d.id, 'cricket',         'Cricket',         4, 9
  FROM `departments` d WHERE d.code = 'polytechnic';

INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,    `max_picks`, `display_order`)
SELECT d.id, 'carrom',          'Carrom',          4, 10
  FROM `departments` d WHERE d.code = 'polytechnic';

INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,         `max_picks`, `display_order`)
SELECT d.id, 'relay_4x100m',    '4 x 100 m Relay',  4, 11
  FROM `departments` d WHERE d.code = 'polytechnic';

INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,    `max_picks`, `display_order`)
SELECT d.id, 'chess',           'Chess',           4, 12
  FROM `departments` d WHERE d.code = 'polytechnic';

-- Same 12 games for dpharm (display_order 1..12 reused)
INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,    `max_picks`, `display_order`)
SELECT d.id, 'kabaddi',         'Kabaddi',         4, 1
  FROM `departments` d WHERE d.code = 'dpharm';

INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,    `max_picks`, `display_order`)
SELECT d.id, 'kho_kho',         'Kho Kho',         4, 2
  FROM `departments` d WHERE d.code = 'dpharm';

INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,    `max_picks`, `display_order`)
SELECT d.id, 'volleyball',      'Volleyball',      4, 3
  FROM `departments` d WHERE d.code = 'dpharm';

INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,    `max_picks`, `display_order`)
SELECT d.id, 'basketball',      'Basketball',      4, 4
  FROM `departments` d WHERE d.code = 'dpharm';

INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,    `max_picks`, `display_order`)
SELECT d.id, 'hockey',          'Hockey',          4, 5
  FROM `departments` d WHERE d.code = 'dpharm';

INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,    `max_picks`, `display_order`)
SELECT d.id, 'football',        'Football',        4, 6
  FROM `departments` d WHERE d.code = 'dpharm';

INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,    `max_picks`, `display_order`)
SELECT d.id, 'badminton',       'Badminton',       4, 7
  FROM `departments` d WHERE d.code = 'dpharm';

INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,    `max_picks`, `display_order`)
SELECT d.id, 'table_tennis',    'Table Tennis',    4, 8
  FROM `departments` d WHERE d.code = 'dpharm';

INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,    `max_picks`, `display_order`)
SELECT d.id, 'cricket',         'Cricket',         4, 9
  FROM `departments` d WHERE d.code = 'dpharm';

INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,    `max_picks`, `display_order`)
SELECT d.id, 'carrom',          'Carrom',          4, 10
  FROM `departments` d WHERE d.code = 'dpharm';

INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,         `max_picks`, `display_order`)
SELECT d.id, 'relay_4x100m',    '4 x 100 m Relay',  4, 11
  FROM `departments` d WHERE d.code = 'dpharm';

INSERT IGNORE INTO `dept_game_catalog`
    (`department_id`, `game_code`,         `display_name`,    `max_picks`, `display_order`)
SELECT d.id, 'chess',           'Chess',           4, 12
  FROM `departments` d WHERE d.code = 'dpharm';

-- =====================================================================
--  END OF MIGRATION v22
-- =====================================================================
