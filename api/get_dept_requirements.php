<?php
/**
 * Returns document requirements for a given department.
 * Returns JSON array of requirements.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$dept_id = (int)($_GET['dept_id'] ?? 0);
if ($dept_id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid department ID']);
    exit;
}

// Ensure the user has access to this department
if (!is_super_admin()) {
    $faculty_id = (int)($_SESSION['faculty_id'] ?? 0);
    $allowed = db_one(
        'SELECT 1 FROM faculty_departments WHERE faculty_id = ? AND department_id = ?',
        [$faculty_id, $dept_id], 'ii'
    );
    if (!$allowed) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}

$reqs = db_select(
    'SELECT id, document_name, is_required, allowed_mime_types FROM dept_document_requirements WHERE department_id = ? ORDER BY (document_name = "Passport-size Photo") DESC, id',
    [$dept_id], 'i'
);

header('Content-Type: application/json');
echo json_encode($reqs);
