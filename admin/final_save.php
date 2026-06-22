<?php
/**
 * Add a student to a final team.
 *
 * POST fields:
 *   student_id    int (required)
 *   game_name     string (required)
 *   event_label   string (required)
 *   academic_year string (optional)
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit('Method not allowed.');
}

require_login();
csrf_check();

$me            = current_faculty();
$student_id    = (int)($_POST['student_id'] ?? 0);
$game_name     = trim((string)($_POST['game_name'] ?? ''));
$event_label   = trim((string)($_POST['event_label'] ?? ''));
$academic_year = trim((string)($_POST['academic_year'] ?? '')) ?: null;

$back_params = http_build_query([
    'game' => $game_name,
    'event' => $event_label,
    'ay'   => $academic_year ?? '',
]);
$back_url = 'final_list.php' . ($back_params !== '' ? '?' . $back_params : '');

if ($student_id <= 0 || $game_name === '' || $event_label === '') {
    flash_set('final_error', 'Game, event label, and student are required.', 'error');
    redirect($back_url);
}

// Verify student is in scope
[$scope, $p, $t] = scope_sql_department('s');
$student = db_one(
    "SELECT enrollment_no, roll_no FROM students s WHERE s.id = ? $scope",
    array_merge([$student_id], $p), 'i' . $t
);

if (!$student) {
    http_response_code(403);
    exit('Forbidden.');
}

// Prevent duplicates
$dup = db_one(
    "SELECT id FROM final_teams
      WHERE game_name = ?
        AND event_label = ?
        AND academic_year <=> ?
        AND student_id = ?",
    [$game_name, $event_label, $academic_year, $student_id], 'sssi'
);

if ($dup) {
    flash_set('final_error', 'This student is already on the final team.', 'info');
    redirect($back_url);
}

db_insert(
    "INSERT INTO final_teams
        (game_name, event_label, academic_year, student_id, roll_no, added_by)
     VALUES (?, ?, ?, ?, ?, ?)",
    [
        $game_name,
        $event_label,
        $academic_year,
        $student_id,
        trim((string)($student['roll_no'] ?? '')),
        (int)$me['id'],
    ],
    'sssisi'
);

flash_set('final_saved', 'Student added to the final team.', 'success');
redirect($back_url);
