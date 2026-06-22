<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

// Always set Content-Type first so error responses are also valid JSON.
header('Content-Type: application/json; charset=utf-8');

$student_id = (int)($_GET['student_id'] ?? 0);
if ($student_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_id']);
    exit;
}

// Check scope
if (!is_super_admin()) {
    $student = db_one('SELECT department_id FROM students WHERE id = ?', [$student_id], 'i');
    if (!$student || (int)$_SESSION['department_id'] !== (int)$student['department_id']) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden']);
        exit;
    }
}

$docs = db_select('SELECT requirement_id, file_path FROM student_documents WHERE student_id = ?', [$student_id], 'i');
echo json_encode($docs);
?>