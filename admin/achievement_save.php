<?php
/**
 * Save a new achievement for a student.
 * POST only. CSRF protected.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit('Method not allowed.');
}

require_login();
csrf_check();

$student_id = (int)($_POST['student_id'] ?? 0);
$title      = trim((string)($_POST['title'] ?? ''));

if ($student_id <= 0 || $title === '') {
    flash_set('student_error', 'Achievement title is required.', 'error');
    redirect('../student-profile.php?id=' . $student_id);
}

// Verify student is in scope
[$scope, $p, $t] = scope_sql_department('s');
$student = db_one("SELECT id FROM students s WHERE s.id = ? $scope",
    array_merge([$student_id], $p), 'i' . $t);
if (!$student) {
    http_response_code(403);
    exit('Forbidden.');
}

// Sanitize optional fields
$level      = in_array($_POST['level'] ?? '', ['College','University','State','National','International'], true)
              ? $_POST['level'] : null;
$position   = trim((string)($_POST['position'] ?? '')) ?: null;
$event_date = ($_POST['event_date'] ?? '') ?: null;
$description= trim((string)($_POST['description'] ?? '')) ?: null;

db_insert(
    'INSERT INTO achievements (student_id, title, event_name, level, position, event_date, description)
     VALUES (?,?,?,?,?,?,?)',
    [$student_id, $title, $title, $level, $position, $event_date, $description],
    'issssss'
);

flash_set('student_saved', 'Achievement added successfully.', 'success');
redirect('../student-profile.php?id=' . $student_id);
