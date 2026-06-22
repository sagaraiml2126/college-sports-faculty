<?php
/**
 * Update the specific roll number for a player on a final team list.
 *
 * POST fields:
 *   entry_id  int (required)
 *   roll_no   string (required)
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit('Method not allowed.');
}

require_login();
csrf_check();

$entry_id = (int)($_POST['entry_id'] ?? 0);
$roll_no  = trim((string)($_POST['roll_no'] ?? ''));

if ($entry_id <= 0 || $roll_no === '') {
    http_response_code(400);
    exit('Invalid entry ID or roll number.');
}

// Verify entry belongs to user's department
[$scope, $p, $t] = scope_sql_department('s');
$entry = db_one(
    "SELECT ft.id FROM final_teams ft
       JOIN students s ON s.id = ft.student_id
      WHERE ft.id = ?
        $scope",
    array_merge([$entry_id], $p), 'i' . $t
);

if (!$entry) {
    http_response_code(403);
    exit('Forbidden.');
}

db_execute(
    "UPDATE final_teams SET roll_no = ? WHERE id = ?",
    [$roll_no, $entry_id],
    'si'
);

// Since this is an inline update, we just return a 200 or a redirect.
// The UI in final_list.php uses a simple form, so we redirect back.
// We need to preserve the list params.
$game  = (string)($_POST['game'] ?? ''); // Ideally passed in hidden fields
$event = (string)($_POST['event'] ?? '');
$ay    = (string)($_POST['ay'] ?? '');

$back_params = http_build_query([
    'game' => $game,
    'event' => $event,
    'ay'   => $ay,
]);
$back_url = 'final_list.php' . ($back_params !== '' ? '?' . $back_params : '');

flash_set('final_saved', 'Roll number updated.', 'success');
redirect($back_url);
