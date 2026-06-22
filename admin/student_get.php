<?php
/**
 * JSON endpoint: returns one student, department-scoped.
 * GET: ?id=123
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();
require_department();

header('Content-Type: application/json; charset=utf-8');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_id']);
    exit;
}

[$scope, $p, $t] = scope_sql_department('s');
$row = db_one(
    "SELECT s.*, d.name AS dept_name FROM students s
       JOIN departments d ON d.id = s.department_id
      WHERE s.id = ? $scope",
    array_merge([$id], $p), 'i' . $t
);

if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'not_found']);
    exit;
}

echo json_encode(['ok' => true, 'data' => $row], JSON_UNESCAPED_UNICODE);
