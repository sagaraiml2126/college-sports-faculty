<?php
/**
 * Delete an achievement. POST only. CSRF protected.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit('Method not allowed.');
}

require_login();
csrf_check();

$ach_id     = (int)($_POST['ach_id'] ?? 0);
$student_id = (int)($_POST['student_id'] ?? 0);

if ($ach_id <= 0 || $student_id <= 0) {
    http_response_code(400);
    exit('Bad request.');
}

// Verify the achievement belongs to a student in scope
[$scope, $p, $t] = scope_sql_department('s');
$valid = db_one(
    "SELECT a.id FROM achievements a
       JOIN students s ON s.id = a.student_id
      WHERE a.id = ? AND a.student_id = ? $scope",
    array_merge([$ach_id, $student_id], $p), 'ii' . $t
);
if (!$valid) {
    http_response_code(403);
    exit('Forbidden.');
}

db_execute('DELETE FROM achievements WHERE id = ?', [$ach_id], 'i');

flash_set('student_saved', 'Achievement deleted.', 'success');
redirect('../student-profile.php?id=' . $student_id);
