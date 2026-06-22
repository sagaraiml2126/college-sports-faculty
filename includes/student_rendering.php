<?php
/**
 * Shared student-rendering helpers used by the faculty dashboard
 * (admin/dashboard.php) and the student search (student-search.php).
 *
 * The dashboard's "Players by Game" card and the search results table
 * both need the same per-student game chip rendering logic — extracting
 * it here keeps them in lockstep.
 *
 * Functions:
 *   - load_picker_dept_ids()              : array<int>
 *   - load_games_by_student(array $ids)   : array<int, array<string>>
 *   - render_sports_cell(...)             : string
 */

declare(strict_types=1);

/**
 * Return the department IDs that have at least one active row in
 * `dept_game_catalog` — i.e. the "picker" depts. Used everywhere we
 * need to branch picker-dept vs legacy-dept rendering.
 *
 * @return array<int> department_id values (as ints)
 */
function load_picker_dept_ids(): array {
    $rows = db_select(
        'SELECT DISTINCT department_id FROM dept_game_catalog WHERE is_active = 1'
    );
    return array_map(static fn($r) => (int)$r['department_id'], $rows);
}

/**
 * For a set of student IDs, return the games they have picked in
 * `student_selected_games`. Format: [student_id => [game_code, ...]]
 * sorted alphabetically by game_code (deterministic order for chips).
 *
 * Empty input returns an empty array.
 *
 * @param  array<int> $student_ids
 * @return array<int, array<string>>
 */
function load_games_by_student(array $student_ids): array {
    $out = [];
    $ids = array_values(array_unique(array_filter(
        array_map('intval', $student_ids),
        static fn($v) => $v > 0
    )));
    if (empty($ids)) return $out;

    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $typ = str_repeat('i', count($ids));
    $rows = db_select(
        "SELECT student_id, game_code FROM student_selected_games
          WHERE student_id IN ($ph)
          ORDER BY student_id, game_code",
        $ids,
        $typ
    );
    foreach ($rows as $r) {
        $out[(int)$r['student_id']][] = (string)$r['game_code'];
    }
    return $out;
}

/**
 * Render the "Sports" cell for one student row.
 *
 * - Picker-dept students get up to 4 green game chips (one per game they
 *   picked in the wizard).
 * - Legacy (non-picker) dept students get sport_1 + sport_2 chips.
 * - No data → italic em-dash.
 *
 * $row must include at least: id, department_id, sport_1, sport_2.
 *
 * @param array{id:int,department_id:int,sport_1:?string,sport_2:?string,...} $row
 * @param array<int> $picker_dept_ids    from load_picker_dept_ids()
 * @param array<int, array<string>> $games_by_student    from load_games_by_student()
 */
function render_sports_cell(array $row, array $picker_dept_ids, array $games_by_student): string {
    $is_picker = in_array((int)$row['department_id'], $picker_dept_ids, true);
    if ($is_picker) {
        $games = $games_by_student[(int)$row['id']] ?? [];
        if (empty($games)) {
            return '<em style="color:var(--medium-gray);font-style:italic">— no games —</em>';
        }
        $html = '';
        foreach ($games as $g) {
            $html .= '<span class="sport-tag" style="background:rgba(5,150,105,.1);color:#0a3622;border-color:rgba(5,150,105,.25)">'
                   . h(ucwords(str_replace('_', ' ', $g)))
                   . '</span> ';
        }
        return rtrim($html);
    }
    $html = '';
    if (!empty($row['sport_1'])) $html .= '<span class="sport-tag">' . h($row['sport_1']) . '</span> ';
    if (!empty($row['sport_2'])) $html .= '<span class="sport-tag">' . h($row['sport_2']) . '</span> ';
    return $html !== '' ? rtrim($html) : '<em style="color:var(--medium-gray);font-style:italic">—</em>';
}
