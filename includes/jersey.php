<?php
/**
 * Shared helpers for jersey form department scoping.
 */

declare(strict_types=1);

function jersey_forms_has_department_id(): bool
{
    static $has = null;
    if ($has !== null) {
        return $has;
    }

    $rows = db_select("SHOW COLUMNS FROM jersey_forms LIKE 'department_id'");
    $has = !empty($rows);
    return $has;
}

function jersey_request_department_id(): ?int
{
    $f = current_faculty();
    if (!$f) {
        return null;
    }

    if ($f['role'] === 'FACULTY') {
        return $f['department_id'] ? (int)$f['department_id'] : null;
    }

    $raw = $_REQUEST['dept'] ?? null;
    if ($raw !== null && ctype_digit((string)$raw)) {
        $dept_id = (int)$raw;
        $exists = db_one(
            'SELECT id FROM departments WHERE id = ? AND is_active = 1',
            [$dept_id],
            'i'
        );
        if ($exists) {
            return $dept_id;
        }
    }

    return null;
}

function jersey_department_for_team(string $game, string $event, ?string $academic_year): ?int
{
    $dept_id = jersey_request_department_id();
    if ($dept_id !== null) {
        return $dept_id;
    }

    $rows = db_select(
        "SELECT DISTINCT s.department_id
           FROM final_teams ft
           JOIN students s ON s.id = ft.student_id
          WHERE ft.game_name = ? AND ft.event_label = ? AND ft.academic_year <=> ?",
        [$game, $event, $academic_year],
        'sss'
    );

    return count($rows) === 1 ? (int)$rows[0]['department_id'] : null;
}

function jersey_form_department_filter(?int $department_id, string $alias = ''): array
{
    if (!jersey_forms_has_department_id() || $department_id === null) {
        return ['', [], ''];
    }

    $prefix = $alias !== '' ? $alias . '.' : '';
    return [" AND {$prefix}department_id = ? ", [$department_id], 'i'];
}
