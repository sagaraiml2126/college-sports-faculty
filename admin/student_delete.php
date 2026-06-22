<?php
/**
 * Student delete. POST + CSRF + scope.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit('Method not allowed.');
}

require_login();
csrf_check();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    flash_set('student_error', 'Invalid id.', 'error');
    redirect('../admin/student_list.php');
}

[$scope, $p, $t] = scope_sql_department('s');

$row = db_one("SELECT id, photo_path FROM students s WHERE s.id = ? $scope",
    array_merge([$id], $p), 'i' . $t);

if (!$row) {
    flash_set('student_error', 'Student not found.', 'error');
    redirect('../admin/student_list.php');
}

db_execute('DELETE FROM students WHERE id = ?', [$id], 'i');

// Best-effort photo cleanup
if (!empty($row['photo_path'])) {
    @unlink(__DIR__ . '/../' . $row['photo_path']);
}

flash_set('student_saved', 'Student deleted.', 'success');
redirect('../admin/student_list.php');
