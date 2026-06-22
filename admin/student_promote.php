<?php
/**
 * Promote student to the next year of study.
 *
 * POST + CSRF + scope.
 *   study_year: First -> Second -> Third -> Final (Engineering and other
 *   four-year programs). Polytechnic stops after Third.
 *   academic_year: advanced to the next academic year (e.g. 2024-25 -> 2025-26)
 *
 * Faculty can only promote students in their own department.
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

$row = db_one(
    "SELECT s.id, s.full_name, s.enrollment_no, s.study_year, s.academic_year,
            d.code AS department_code
       FROM students s
       JOIN departments d ON d.id = s.department_id
      WHERE s.id = ? $scope",
    array_merge([$id], $p), 'i' . $t
);

if (!$row) {
    http_response_code(404);
    exit('Student not found.');
}

/* year progression */
$progression = [
    'First'  => 'Second',
    'Second' => 'Third',
    'Third'  => ($row['department_code'] ?? '') === 'polytechnic' ? null : 'Final',
    'Final'  => null,
];

$current = $row['study_year'] ?? '';
$next    = $progression[$current] ?? null;

if ($next === null) {
    $lastYear = ($row['department_code'] ?? '') === 'polytechnic' ? 'Third Year' : 'Final Year';
    flash_set('student_error',
        $row['full_name'] . ' is already at ' . $lastYear . ' and cannot be promoted further.',
        'error');
    redirect('../student-profile.php?id=' . $id);
}

/* academic year: 2024-25 -> 2025-26 */
$ay = $row['academic_year'] ?? '';
$new_ay = null;
if (preg_match('/^(\d{4})\s*-\s*(\d{2})$/', $ay, $m)) {
    $start = (int)$m[1] + 1;
    $end   = substr((string)($start + 1), -2);
    $new_ay = $start . '-' . $end;
} else {
    $new_ay = $ay; // leave as-is if format unknown
}

db_execute(
    'UPDATE students SET study_year = ?, academic_year = ? WHERE id = ?',
    [$next, $new_ay, $id],
    'ssi'
);

flash_set('student_saved',
    $row['full_name'] . ' promoted to ' . $next . ' (AY ' . $new_ay . ').',
    'success');
redirect('../student-profile.php?id=' . $id);
