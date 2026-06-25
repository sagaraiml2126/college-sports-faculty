<?php
/**
 * Editable Word export for supported final-team formats.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
require_department();

$game  = trim((string)($_GET['game'] ?? ''));
$event = trim((string)($_GET['event'] ?? ''));
$ay    = trim((string)($_GET['ay'] ?? ''));

if ($game === '' || $event === '') {
    http_response_code(400);
    exit('Game and event label are required.');
}

$me = current_faculty();
$departmentCode = (string)($me['department_code'] ?? '');
$shivajiDepartments = ['management', 'architecture'];
if (!in_array($departmentCode, ['polytechnic', 'engineering', 'pharmacy', 'dpharm', ...$shivajiDepartments], true)) {
    http_response_code(403);
    exit('Word export is not available for this faculty.');
}

[$scope, $params, $types] = scope_sql_department('s');
// Hide wizard drafts (students who registered but haven't hit Final Submit).
$visible = faculty_visible_student_filter('s');
$scope  .= $visible[0];
$params  = array_merge($params, $visible[1]);
$types  .= $visible[2];
$rows = db_select(
    "SELECT ft.roll_no,
            s.full_name, s.mother_name, s.enrollment_no, s.dob,
            s.study_year, s.program, s.mobile, s.photo_path
       FROM final_teams ft
       JOIN students s ON s.id = ft.student_id
      WHERE ft.game_name = ?
        AND ft.event_label = ?
        AND ft.academic_year <=> ?
        $scope
      ORDER BY ft.created_at ASC, s.enrollment_no ASC",
    array_merge([$game, $event, $ay === '' ? null : $ay], $params),
    'sss' . $types
);

if (in_array($departmentCode, ['engineering', 'pharmacy'], true)) {
    require_once __DIR__ . '/../includes/engineering_identity_card_docx.php';
    $docx = build_engineering_identity_card_docx($game, $ay, $rows, dirname(__DIR__), $event);
} elseif (in_array($departmentCode, $shivajiDepartments, true)) {
    require_once __DIR__ . '/../includes/shivaji_eligibility_proforma_docx.php';
    $docx = build_shivaji_eligibility_proforma_docx(
        $game,
        $event,
        $ay,
        $departmentCode,
        (string)($me['department_name'] ?? strtoupper($departmentCode)),
        $rows
    );
} else {
    require_once __DIR__ . '/../includes/polytechnic_eligibility_docx.php';
    $docx = build_polytechnic_eligibility_docx(
        $game,
        $event,
        $ay,
        $rows,
        __DIR__ . '/../images/ytc-logo.png'
    );
}

$safeGame = trim((string)preg_replace('/[^A-Za-z0-9_-]+/', '_', $game), '_');
$prefix = in_array($departmentCode, ['engineering', 'pharmacy'], true)
    ? 'Eligibility_Proforma_'
    : (in_array($departmentCode, $shivajiDepartments, true)
        ? 'Shivaji_Eligibility_Proforma_'
        : 'Eligibility_Form_');
$filename = $prefix . ($safeGame !== '' ? $safeGame : 'Team') . '.docx';

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($docx));
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: public');
echo $docx;
