<?php
/**
 * Editable Shivaji University eligibility proforma for pharm_faculty departments.
 */

declare(strict_types=1);

require_once __DIR__ . '/polytechnic_eligibility_docx.php';

function shivaji_docx_dob(?string $dob): string
{
    if ($dob === null || $dob === '' || $dob === '0000-00-00') {
        return '';
    }

    $timestamp = strtotime($dob);
    return $timestamp === false ? '' : date('d/m/Y', $timestamp);
}

function shivaji_docx_class(?string $studyYear): string
{
    return [
        'first' => '1st Year',
        'second' => '2nd Year',
        'third' => '3rd Year',
        'final' => 'Final Year',
    ][strtolower(trim((string)$studyYear))] ?? trim((string)$studyYear);
}

function shivaji_participating_college(string $departmentCode, string $departmentName): string
{
    return match ($departmentCode) {
        'architecture' => 'Yashoda College of Architecture, Wadhe, Satara',
        'management'   => 'Yashoda College of Management, Satara',
        default => 'Yashoda Technical Campus, ' . $departmentName . ', Satara',
    };
}

function shivaji_docx_text(
    string $text,
    int $size = 14,
    bool $bold = false,
    string $align = 'center'
): string {
    return poly_docx_paragraph(
        poly_docx_run($text, ['bold' => $bold, 'size' => $size, 'font' => 'Times New Roman']),
        ['align' => $align, 'line' => 175]
    );
}

/**
 * @param array{span?:int,vmerge?:string,padding?:int} $options
 */
function shivaji_docx_cell(string $content, int $width, array $options = []): string
{
    $span = max(1, (int)($options['span'] ?? 1));
    $padding = max(0, (int)($options['padding'] ?? 35));
    $vmerge = '';
    if (isset($options['vmerge'])) {
        $vmerge = $options['vmerge'] === 'restart'
            ? '<w:vMerge w:val="restart"/>'
            : '<w:vMerge/>';
    }

    return '<w:tc><w:tcPr><w:tcW w:w="' . $width . '" w:type="dxa"/>'
        . ($span > 1 ? '<w:gridSpan w:val="' . $span . '"/>' : '')
        . $vmerge
        . '<w:vAlign w:val="center"/>'
        . '<w:tcMar><w:top w:w="' . $padding . '" w:type="dxa"/>'
        . '<w:left w:w="' . $padding . '" w:type="dxa"/>'
        . '<w:bottom w:w="' . $padding . '" w:type="dxa"/>'
        . '<w:right w:w="' . $padding . '" w:type="dxa"/></w:tcMar>'
        . poly_docx_borders(true)
        . '</w:tcPr>' . $content . '</w:tc>';
}

/**
 * @param array<int,array<int,array{content:string,width:int,span?:int,vmerge?:string,padding?:int}>> $rows
 * @param array<int,int> $gridWidths
 * @param array<int,int> $rowHeights
 */
function shivaji_docx_table(array $rows, array $gridWidths, array $rowHeights): string
{
    $total = array_sum($gridWidths);
    $grid = '';
    foreach ($gridWidths as $width) {
        $grid .= '<w:gridCol w:w="' . $width . '"/>';
    }

    $xml = '<w:tbl><w:tblPr><w:tblW w:w="' . $total . '" w:type="dxa"/>'
        . '<w:jc w:val="center"/><w:tblLayout w:type="fixed"/>'
        . '<w:tblCellMar><w:top w:w="0" w:type="dxa"/><w:left w:w="0" w:type="dxa"/>'
        . '<w:bottom w:w="0" w:type="dxa"/><w:right w:w="0" w:type="dxa"/></w:tblCellMar>'
        . '</w:tblPr><w:tblGrid>' . $grid . '</w:tblGrid>';

    foreach ($rows as $rowIndex => $cells) {
        $height = (int)($rowHeights[$rowIndex] ?? 0);
        $xml .= '<w:tr><w:trPr>'
            . ($height > 0 ? '<w:trHeight w:val="' . $height . '" w:hRule="atLeast"/>' : '')
            . '<w:cantSplit/></w:trPr>';
        foreach ($cells as $cell) {
            $options = $cell;
            unset($options['content'], $options['width']);
            $xml .= shivaji_docx_cell($cell['content'], $cell['width'], $options);
        }
        $xml .= '</w:tr>';
    }

    return $xml . '</w:tbl>';
}

function shivaji_docx_seal(): string
{
    return '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="20" w:after="0"/></w:pPr>'
        . '<w:r><w:pict><v:oval style="width:54pt;height:54pt" fillcolor="white" strokecolor="black">'
        . '<v:textbox inset="0,0,0,0"><w:txbxContent><w:p><w:pPr>'
        . '<w:jc w:val="center"/><w:spacing w:before="260" w:after="0" w:line="180" w:lineRule="auto"/>'
        . '</w:pPr>' . poly_docx_run("Seal of the\nCollege", ['size' => 14])
        . '</w:p></w:txbxContent></v:textbox></v:oval></w:pict></w:r></w:p>';
}

/**
 * @param array<int,array<string,mixed>> $participants
 */
function shivaji_docx_page(
    string $game,
    string $event,
    string $academicYear,
    string $departmentCode,
    string $departmentName,
    array $participants,
    int $startingNumber
): string {
    $content = poly_docx_paragraph(
        poly_docx_run('Shivaji University, Kolhapur.', ['bold' => true, 'size' => 28]),
        ['align' => 'center', 'after' => 70, 'keep' => true]
    );
    $content .= poly_docx_paragraph(
        poly_docx_run('Eligibility Proforma for Zonal/Inter-Zonal Tournaments', ['bold' => true, 'size' => 21]),
        ['align' => 'center', 'after' => 220, 'keep' => true]
    );

    $content .= poly_docx_table([[
        poly_docx_paragraph(
            poly_docx_run('Name of the Tournament: ', ['bold' => true, 'size' => 17])
            . poly_docx_run($game, ['size' => 17])
            . poly_docx_run('  Section : ', ['bold' => true, 'size' => 17])
            . poly_docx_run($event, ['size' => 17]),
            ['line' => 210]
        ),
        poly_docx_paragraph(
            poly_docx_run('Name of the team manager & Mob No : ', ['bold' => true, 'size' => 17])
            . poly_docx_run('____________________________', ['size' => 17]),
            ['align' => 'center', 'line' => 210]
        ),
        poly_docx_paragraph(
            poly_docx_run('His/her Status : ', ['bold' => true, 'size' => 17])
            . poly_docx_run('________________', ['size' => 17]),
            ['align' => 'right', 'line' => 210]
        ),
    ]], [4900, 6500, 4200], ['row_heights' => [0 => 420]]);

    $content .= poly_docx_table([[
        poly_docx_paragraph(
            poly_docx_run('Name of the Organizing College: ', ['bold' => true, 'size' => 17])
            . poly_docx_run('________________________________________', ['size' => 17]),
            ['line' => 210]
        ),
        poly_docx_paragraph(
            poly_docx_run('Name of the Participating College: ', ['bold' => true, 'size' => 17])
            . poly_docx_run(shivaji_participating_college($departmentCode, $departmentName), ['size' => 17]),
            ['align' => 'right', 'line' => 210]
        ),
    ]], [7600, 8000], ['row_heights' => [0 => 420]]);

    $content .= poly_docx_paragraph(
        poly_docx_run(
            'Year A.Y. ' . ($academicYear !== '' ? $academicYear : '________'),
            ['bold' => true, 'size' => 20]
        ),
        ['align' => 'center', 'before' => 100, 'after' => 180]
    );

    $widths = [450, 1900, 1050, 1300, 600, 1100, 650, 750, 950, 1100, 800, 1100, 1150, 1000, 900, 850];
    $header = static fn(string $text): string => shivaji_docx_text($text, 12, true);
    $subheader = static fn(string $text): string => shivaji_docx_text($text, 11);
    $continue = static fn(int $width): array => [
        'content' => poly_docx_paragraph(''),
        'width' => $width,
        'vmerge' => 'continue',
    ];

    $rows = [
        [
            ['content' => $header("Sr.\nNo."), 'width' => $widths[0], 'vmerge' => 'restart'],
            ['content' => $header("Name of the\nPlayer\n(Beginning\nSurname)"), 'width' => $widths[1], 'vmerge' => 'restart'],
            ['content' => $header("Mother's\nName"), 'width' => $widths[2], 'vmerge' => 'restart'],
            ['content' => $header("University\nP.R.N. no."), 'width' => $widths[3], 'vmerge' => 'restart'],
            ['content' => $header("Roll\nNo."), 'width' => $widths[4], 'vmerge' => 'restart'],
            ['content' => $header("Date of\nBirth"), 'width' => $widths[5], 'vmerge' => 'restart'],
            [
                'content' => $header("Date & Year\nof Passing\nH.S.C.\nExamination"),
                'width' => $widths[6] + $widths[7],
                'span' => 2,
            ],
            ['content' => $header("Present\nClass"), 'width' => $widths[8], 'vmerge' => 'restart'],
            ['content' => $header("Name of\nthe\nPresent\nCourse"), 'width' => $widths[9], 'vmerge' => 'restart'],
            ['content' => $header("Duration of\nCourse"), 'width' => $widths[10], 'vmerge' => 'restart'],
            [
                'content' => $header("Date & Year of First\nAdmission to"),
                'width' => $widths[11] + $widths[12],
                'span' => 2,
            ],
            [
                'content' => $header("Number of Year\nof Previous\nParticipation\nwhile pursuing"),
                'width' => $widths[13] + $widths[14],
                'span' => 2,
            ],
            ['content' => $header('Remarks'), 'width' => $widths[15], 'vmerge' => 'restart'],
        ],
        [
            $continue($widths[0]),
            $continue($widths[1]),
            $continue($widths[2]),
            $continue($widths[3]),
            $continue($widths[4]),
            $continue($widths[5]),
            ['content' => $subheader("Name of\nExam"), 'width' => $widths[6]],
            ['content' => $subheader("Date &\nYear"), 'width' => $widths[7]],
            $continue($widths[8]),
            $continue($widths[9]),
            $continue($widths[10]),
            ['content' => $subheader("University\n/College"), 'width' => $widths[11]],
            ['content' => $subheader("Present\nCourse"), 'width' => $widths[12]],
            ['content' => $subheader("Graduate\nCourse"), 'width' => $widths[13]],
            ['content' => $subheader("P.G.\nCourse"), 'width' => $widths[14]],
            $continue($widths[15]),
        ],
    ];

    for ($slot = 0; $slot < 7; $slot++) {
        $participant = $participants[$slot] ?? [];
        $values = $participant === [] ? array_fill(0, 16, '') : [
            (string)($startingNumber + $slot) . '.',
            trim((string)($participant['full_name'] ?? '')),
            trim((string)($participant['mother_name'] ?? '')),
            trim((string)($participant['enrollment_no'] ?? '')),
            trim((string)($participant['roll_no'] ?? '')),
            shivaji_docx_dob($participant['dob'] ?? null),
            '',
            '',
            shivaji_docx_class($participant['study_year'] ?? null),
            trim((string)($participant['program'] ?? '')),
            '',
            '',
            '',
            '',
            '',
            '',
        ];

        $row = [];
        foreach ($values as $index => $value) {
            $row[] = [
                'content' => shivaji_docx_text($value, 12),
                'width' => $widths[$index],
                'padding' => 35,
            ];
        }
        $rows[] = $row;
    }

    $content .= shivaji_docx_table(
        $rows,
        $widths,
        [0 => 950, 1 => 540, 2 => 560, 3 => 560, 4 => 560, 5 => 560, 6 => 560, 7 => 560, 8 => 560]
    );

    $content .= poly_docx_paragraph(
        poly_docx_run('Certified that the above particulars are true as per records of the College', ['size' => 16]),
        ['before' => 150, 'after' => 30]
    );
    $content .= poly_docx_paragraph(
        poly_docx_run('Certified the above players are not employed on full time basis.', ['size' => 16]),
        ['after' => 120]
    );

    $content .= poly_docx_table([[
        poly_docx_paragraph(
            poly_docx_run('Date: ______________', ['bold' => true, 'size' => 16]),
            ['align' => 'left']
        ) . shivaji_docx_seal(),
        poly_docx_paragraph(
            poly_docx_run('Director of Physical Education', ['bold' => true, 'size' => 18])
            . poly_docx_run("\n\nSignature of the Director of Physical Education", ['size' => 15]),
            ['align' => 'center']
        ),
        poly_docx_paragraph(
            poly_docx_run('Principal', ['bold' => true, 'size' => 18])
            . poly_docx_run("\n\nSignature of the Principal", ['size' => 15]),
            ['align' => 'center']
        ),
    ]], [4400, 6500, 4700], ['row_heights' => [0 => 900]]);

    return $content;
}

/**
 * @param array<int,array<string,mixed>> $participants
 */
function build_shivaji_eligibility_proforma_docx(
    string $game,
    string $event,
    string $academicYear,
    string $departmentCode,
    string $departmentName,
    array $participants
): string {
    $pages = array_chunk($participants, 7);
    if ($pages === []) {
        $pages = [[]];
    }

    $body = '';
    foreach ($pages as $pageIndex => $pageRows) {
        if ($pageIndex > 0) {
            $body .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
        }
        $body .= shivaji_docx_page(
            $game,
            $event,
            $academicYear,
            $departmentCode,
            $departmentName,
            $pageRows,
            ($pageIndex * 7) + 1
        );
    }

    $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
        . ' xmlns:v="urn:schemas-microsoft-com:vml"><w:body>' . $body
        . '<w:sectPr><w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/>'
        . '<w:pgMar w:top="300" w:right="420" w:bottom="300" w:left="420" w:header="0" w:footer="0" w:gutter="0"/>'
        . '<w:cols w:space="720"/><w:docGrid w:linePitch="360"/></w:sectPr>'
        . '</w:body></w:document>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
        . '<Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
        . '</Types>';

    $zip = new SimpleDocxZip();
    $zip->add('[Content_Types].xml', $contentTypes);
    $zip->add('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
        . '</Relationships>');
    $zip->add('word/document.xml', $document);
    $zip->add('word/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman"/>'
        . '<w:sz w:val="16"/><w:szCs w:val="16"/></w:rPr></w:rPrDefault>'
        . '<w:pPrDefault><w:pPr><w:spacing w:after="0"/></w:pPr></w:pPrDefault></w:docDefaults>'
        . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/>'
        . '<w:qFormat/><w:pPr><w:spacing w:after="0"/></w:pPr></w:style></w:styles>');
    $zip->add('word/settings.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:zoom w:percent="70"/><w:doNotTrackMoves/><w:doNotTrackFormatting/>'
        . '<w:compat><w:compatSetting w:name="compatibilityMode"'
        . ' w:uri="http://schemas.microsoft.com/office/word" w:val="15"/></w:compat></w:settings>');
    $zip->add('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>');
    $now = gmdate('Y-m-d\TH:i:s\Z');
    $zip->add('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/"'
        . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:title>Shivaji University Eligibility Proforma - ' . poly_docx_xml($game) . '</dc:title>'
        . '<dc:creator>Sports Portal</dc:creator>'
        . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
        . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
        . '</cp:coreProperties>');
    $zip->add('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">'
        . '<Application>Sports Portal</Application><AppVersion>1.0</AppVersion></Properties>');

    return $zip->finish();
}
