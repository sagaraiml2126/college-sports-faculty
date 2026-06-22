<?php
/**
 * Delete an entire provisional list (all entries for a game + event + AY).
 *
 * POST only. CSRF protected. Department-scoped via JOIN to students.
 *
 * POST fields:
 *   game   string  (required)
 *   event  string  (required)
 *   ay     string  (optional, empty = NULL/Any)
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

require_login();
csrf_check();

$game  = trim((string)($_POST['game']  ?? ''));
$event = trim((string)($_POST['event'] ?? ''));
$ay    = trim((string)($_POST['ay']    ?? '')) ?: null;

// If game/event are missing, just bounce to the picker page.
if ($game === '' || $event === '') {
    flash_set('prov_error', 'Missing list identifier.', 'error');
    redirect('provisional_list.php');
}

// Scope-check via JOIN to students so we only delete this department's rows.
[$scope, $p, $t] = scope_sql_department('s');
$deleted = db_execute(
    "DELETE pe FROM provisional_entries pe
       JOIN students s ON s.id = pe.student_id
      WHERE pe.game_name = ?
        AND pe.event_label = ?
        AND pe.academic_year <=> ?
        $scope",
    array_merge([$game, $event, $ay], $p),
    'sss' . $t
);

if ($deleted > 0) {
    flash_set('prov_saved', 'Deleted list (' . $deleted . ' player' . ($deleted === 1 ? '' : 's') . ').', 'success');
} else {
    flash_set('prov_error', 'List not found or you do not have permission to delete it.', 'error');
}

// Always send the user back to the picker — the list they deleted (or tried to) is gone.
redirect('provisional_list.php');
