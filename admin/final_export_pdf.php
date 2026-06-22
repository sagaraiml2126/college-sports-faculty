<?php
/**
 * Export the final-team eligibility form in the college's physical format.
 *
 * Final-roster students are printed into the physical form. Unused participant
 * rows remain blank so more names can still be entered by hand.
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

$tcpdf_path = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
if (!file_exists($tcpdf_path)) {
    http_response_code(500);
    exit('TCPDF library not found.');
}

require_once $tcpdf_path;
require_once __DIR__ . '/../includes/eligibility_form_pdf.php';
require_once __DIR__ . '/../includes/engineering_eligibility_proforma_pdf.php';

$me = current_faculty();
[$scope, $params, $types] = scope_sql_department('s');

$rows = db_select(
    "SELECT ft.roll_no,
            s.full_name, s.mother_name, s.enrollment_no, s.dob, s.study_year, s.program,
            s.mobile, s.photo_path,
            d.code AS dept_code, d.name AS dept_name
       FROM final_teams ft
       JOIN students s    ON s.id = ft.student_id
       JOIN departments d ON d.id = s.department_id
      WHERE ft.game_name = ?
        AND ft.event_label = ?
        AND ft.academic_year <=> ?
        $scope
      ORDER BY ft.created_at ASC, s.enrollment_no ASC",
    array_merge([$game, $event, $ay === '' ? null : $ay], $params),
    'sss' . $types
);

$isEngineering = (($me['department_code'] ?? '') === 'engineering')
    || (($rows[0]['dept_code'] ?? '') === 'engineering');
$playersPerPage = $isEngineering ? 3 : 16;
$pages = array_chunk($rows, $playersPerPage);
if ($pages === []) {
    $pages = [[]];
}

$pdf = new TCPDF($isEngineering ? 'L' : 'P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Sports Portal');
$pdf->SetAuthor('Yashoda Technical Campus');
$pdf->SetTitle('Eligibility Form - ' . $game);
$pdf->SetMargins(0, 0, 0);
$pdf->SetHeaderMargin(0);
$pdf->SetFooterMargin(0);
$pdf->SetAutoPageBreak(false);
$pdf->SetPrintHeader(false);
$pdf->SetPrintFooter(false);

foreach ($pages as $pageIndex => $pageRows) {
    $pdf->AddPage();
    if ($isEngineering) {
        draw_engineering_eligibility_proforma($pdf, [
            'game'          => $game,
            'event'         => $event,
            'academic_year' => $ay,
            'participants'  => $pageRows,
            'starting_number' => ($pageIndex * $playersPerPage) + 1,
        ]);
    } else {
        draw_eligibility_form($pdf, [
            'game'          => $game,
            'event'         => $event,
            'academic_year' => $ay,
            'logo_path'     => __DIR__ . '/../images/ytc-logo.png',
            'participants'  => $pageRows,
            'row_count'     => count($pageRows),
        ]);
    }
}

$safe_game = preg_replace('/[^A-Za-z0-9_-]+/', '_', $game);
$prefix = $isEngineering ? 'Eligibility_Proforma_' : 'Eligibility_Form_';
$pdf->Output($prefix . trim((string)$safe_game, '_') . '.pdf', 'D');
