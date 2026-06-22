<?php
/**
 * Student search: filter the students table and present results.
 * Admin-internal page, mirrors student-search.html.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_department();

$me = current_faculty();
if ($me === null) {
    $prefix = is_file('faculty-login.php') ? '' : '../';
    redirect($prefix . 'faculty-login.php');
    exit;
}

// Whitelist filter values.
$q_raw        = trim((string)($_GET['q'] ?? ''));
$department   = (int)($_GET['department'] ?? 0);
$sport_filter = (string)($_GET['sport'] ?? '');
$year_filter  = (string)($_GET['year'] ?? '');
$view         = (string)($_GET['view'] ?? 'table');
if (!in_array($view, ['table', 'card'], true)) $view = 'table';

[$scope, $p, $t] = scope_sql_department('s');
$where = ['1=1']; $params = []; $types = '';
if ($q_raw !== '') {
    $where[] = '(s.full_name LIKE ? OR s.enrollment_no LIKE ? OR s.mobile LIKE ?)';
    $like = '%' . $q_raw . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sss';
}
if ($department > 0) {
    $where[] = 's.department_id = ?';
    $params[] = $department; $types .= 'i';
}
if ($sport_filter !== '') {
    $where[] = '(s.sport_1 = ? OR s.sport_2 = ?)';
    $params[] = $sport_filter; $params[] = $sport_filter; $types .= 'ss';
}
if ($year_filter !== '') {
    $where[] = 's.study_year = ?';
    $params[] = $year_filter; $types .= 's';
}
$params  = array_merge($params, $p);
$types  .= $t;

$sql = "SELECT s.*, d.name AS dept_name, d.code AS department_code
          FROM students s JOIN departments d ON d.id = s.department_id
         WHERE " . implode(' AND ', $where) . " $scope
         ORDER BY s.full_name
         LIMIT 200";
$rows = db_select($sql, $params, $types !== '' ? $types : null);

/* Bulk-load selected games for any student whose dept is a picker dept.
   Non-picker depts stay on sport_1/sport_2 chip rendering. */
$picker_dept_ids = load_picker_dept_ids();
$games_by_student = [];
if (!empty($rows)) {
    $games_by_student = load_games_by_student(
        array_map(static fn($r) => (int)$r['id'], $rows)
    );
}

function is_selected($value, $current): string {
    return (string)$value === (string)$current ? 'selected' : '';
}

/* render_sports_cell() is now in includes/student_rendering.php and is
   auto-loaded by bootstrap.php — used by both this page and the faculty
   dashboard's "Players by Game" card. */

$departments = db_select('SELECT id, name FROM departments WHERE is_active = 1 ORDER BY display_order, name');
$total       = count($rows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Students | Sports Portal</title>
    <?= csrf_meta() ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= h(url('css/public.css')) ?>">
    <link rel="stylesheet" href="<?= h(url('css/admin.css')) ?>">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="app-wrapper">

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <img src="<?= h(url('images/ytc-logo.png')) ?>" alt="YTC Logo">
                <div class="sidebar-brand-text">
                    <h2>Sports Database</h2>
                    <span>Yashoda Technical Campus</span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <div class="sidebar-nav-label">Main</div>
                <a href="admin/dashboard.php"><i class="bi bi-speedometer2"></i> <span>Dashboard</span></a>
                <?php if (has_multiple_departments()): ?>
                    <a href="faculty-select.php?change=1"><i class="bi bi-building"></i> <span>Select Faculty</span></a>
                <?php endif; ?>
                <a href="student-search.php" class="active"><i class="bi bi-search"></i> <span>Search Students</span></a>
                <a href="student-profile.php?new=1"><i class="bi bi-person-plus"></i> <span>Add Student</span></a>
                <a href="admin/provisional_list.php"><i class="bi bi-clipboard-check"></i> <span>Provisional Players</span></a>
                <?php if ($me['role'] === 'SUPER_ADMIN'): ?>
                    <div class="sidebar-nav-label">Admin</div>
                    <a href="admin/faculty_manage.php"><i class="bi bi-people-fill"></i> <span>Faculty Management</span></a>
                <?php endif; ?>
                <div class="sidebar-nav-label">Site</div>
                <a href="index.php"><i class="bi bi-globe"></i> <span>View Website</span></a>
            </nav>
            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <div class="sidebar-user-avatar"><?= h(initials($me['full_name'])) ?></div>
                    <div class="sidebar-user-info">
                        <h4><?= h($me['full_name']) ?></h4>
                        <span><?= h($me['department_name'] ?? $me['role']) ?></span>
                    </div>
                    <a href="admin/logout.php" class="btn-logout" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
                </div>
            </div>
        </aside>

        <div class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
                    <nav class="breadcrumb-nav" aria-label="Breadcrumb">
                        <a href="admin/dashboard.php"><i class="bi bi-house-fill"></i> Dashboard</a><span class="sep">/</span>
                        <span class="current">Search Students</span>
                    </nav>
                </div>
                <div class="top-bar-right">
                    <a href="student-profile.php?new=1" class="btn-act edit" style="text-decoration:none"><i class="bi bi-person-plus"></i> Add Student</a>
                </div>
            </header>

            <div class="content-body">
                <!-- Search Header -->
                <div class="search-header">
                    <div class="search-header-content">
                        <h1><i class="bi bi-search"></i> Search Students</h1>
                        <p>Find any student in the sports department database</p>
                    </div>
                    <div class="search-header-stats">
                        <div class="search-stat">
                            <h3><?= number_format($total) ?></h3>
                            <span>Results</span>
                        </div>
                    </div>
                </div>

                <!-- Search Panel -->
                <div class="search-panel">
                    <div class="search-modes">
                        <button type="button" class="search-mode-btn active" data-mode="general">
                            <i class="bi bi-search"></i> General
                        </button>
                        <button type="button" class="search-mode-btn" data-mode="advanced">
                            <i class="bi bi-funnel"></i> Advanced
                        </button>
                    </div>

                    <form id="searchForm" method="get" action="student-search.php">
                        <!-- General mode -->
                        <div class="search-input-row" data-mode="general">
                            <div class="search-input-wrapper">
                                <i class="bi bi-search"></i>
                                <input type="text" name="q" id="searchInput" placeholder="Search by name, enrollment number, or phone…" value="<?= h($q_raw) ?>" autocomplete="off">
                            </div>
                            <button type="submit" class="btn-search">
                                <i class="bi bi-search"></i> Search
                            </button>
                        </div>

                        <!-- Advanced mode -->
                        <div class="search-input-row" data-mode="advanced" style="display:none">
                            <div class="search-input-wrapper">
                                <i class="bi bi-building"></i>
                                <select name="department" id="filterDepartment">
                                    <option value="0">All Faculties</option>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?= (int)$d['id'] ?>" <?= $department === (int)$d['id'] ? 'selected' : '' ?>><?= h($d['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="search-input-wrapper">
                                <i class="bi bi-trophy"></i>
                                <input type="text" name="sport" list="sportList" placeholder="Filter by sport" value="<?= h($sport_filter) ?>">
                                <datalist id="sportList">
                                    <?php foreach (sport_options() as $sp): ?>
                                        <option value="<?= h($sp) ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div class="search-input-wrapper">
                                <i class="bi bi-mortarboard"></i>
                                <select name="year">
                                    <option value="">Any Year</option>
                                    <?php foreach (year_options() as $y): ?>
                                        <option value="<?= h($y) ?>" <?= $year_filter === (string)$y ? 'selected' : '' ?>><?= h($y) ?> Year</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn-search">
                                <i class="bi bi-funnel"></i> Apply Filters
                            </button>
                        </div>

                        <?php if ($view !== 'table'): ?>
                            <input type="hidden" name="view" value="<?= h($view) ?>">
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Results Panel -->
                <div class="results-panel">
                    <div class="results-header">
                        <h2>
                            <i class="bi bi-list-ul"></i>
                            <?= $total > 0 ? $total . ' student' . ($total !== 1 ? 's' : '') . ' found' : 'No matches' ?>
                        </h2>
                        <div class="view-toggles">
                            <button class="view-toggle<?= $view === 'table' ? ' active' : '' ?>" data-view="table" title="Table view">
                                <i class="bi bi-list"></i>
                            </button>
                            <button class="view-toggle<?= $view === 'card' ? ' active' : '' ?>" data-view="card" title="Card view">
                                <i class="bi bi-grid-3x3-gap"></i>
                            </button>
                        </div>
                    </div>

                    <?php if ($total === 0): ?>
                        <div class="empty-state">
                            <i class="bi bi-search"></i>
                            <h3>No students found</h3>
                            <p>Try adjusting your search terms or filters.</p>
                            <a href="student-search.php" class="btn-search" style="display:inline-flex;width:auto;padding:.6rem 1.25rem;text-decoration:none">
                                <i class="bi bi-arrow-counterclockwise"></i> Reset Search
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="<?= $view === 'table' ? 'data-table-wrap' : 'card-grid' ?>" id="resultsContainer">
                            <?php if ($view === 'table'): ?>
                            <table class="data-table" id="studentsTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Student</th>
                                        <th>Enrollment</th>
                                        <th>Department</th>
                                        <th>Sport(s)</th>
                                        <th>Year</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $i => $r): ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td>
                                                <div class="student-cell">
                                                    <?php if (!empty($r['photo_path']) && is_file(__DIR__ . '/' . $r['photo_path'])): ?>
                                                        <img src="<?= h(url($r['photo_path'])) ?>" alt="" class="student-avatar">
                                                    <?php else: ?>
                                                        <div class="student-avatar-placeholder"><?= h(initials($r['full_name'])) ?></div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div style="font-weight:600"><?= h($r['full_name']) ?></div>
                                                        <div style="font-size:.78rem;color:var(--medium-gray)"><?= h($r['mobile']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= h($r['enrollment_no']) ?></td>
                                            <td><?= h($r['dept_name']) ?></td>
                                            <td><?= render_sports_cell($r, $picker_dept_ids, $games_by_student) ?></td>
                                            <td><?= h($r['study_year'] ?: '—') ?></td>
                                            <td><span class="status-dot active"></span> Active</td>
                                            <td>
                                                <?php if (!empty($r['form_submitted_at'])): ?>
                                                    <span style="display:inline-flex;align-items:center;gap:.3rem;padding:.15rem .55rem;border-radius:50px;font-size:.7rem;font-weight:600;background:rgba(25,135,84,.12);color:#0a3622">
                                                        <i class="bi bi-check-circle-fill"></i> <?= h(date('d M Y', strtotime((string)$r['form_submitted_at']))) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="display:inline-flex;align-items:center;gap:.3rem;padding:.15rem .55rem;border-radius:50px;font-size:.7rem;font-weight:600;background:rgba(255,193,7,.12);color:#664d03">
                                                        <i class="bi bi-pencil"></i> Draft
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="row-action-btns">
                                                    <a class="row-action-btn" title="View profile" href="student-profile.php?id=<?= (int)$r['id'] ?>"><i class="bi bi-eye"></i></a>
                                                    <a class="row-action-btn edit" title="Edit" href="student-profile.php?id=<?= (int)$r['id'] ?>#formMode"><i class="bi bi-pencil"></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                                <div class="student-cards">
                                <?php foreach ($rows as $r): ?>
                                    <a class="student-card" href="student-profile.php?id=<?= (int)$r['id'] ?>">
                                        <div class="student-card-photo">
                                            <?php if (!empty($r['photo_path']) && is_file(__DIR__ . '/' . $r['photo_path'])): ?>
                                                <img src="<?= h(url($r['photo_path'])) ?>" alt="">
                                            <?php else: ?>
                                                <div class="student-avatar-placeholder"><?= h(initials($r['full_name'])) ?></div>
                                            <?php endif; ?>
                                            <span class="status-dot active"></span>
                                        </div>
                                        <div class="student-card-body">
                                            <h3><?= h($r['full_name']) ?></h3>
                                            <div class="enroll"><?= h($r['enrollment_no']) ?></div>
                                            <div class="meta"><?= h($r['dept_name']) ?> · <?= h($r['study_year'] ?: '—') ?> Year</div>
                                            <div class="sports"><?= render_sports_cell($r, $picker_dept_ids, $games_by_student) ?></div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="results-footer">
                            <div class="results-info">
                                Showing <strong><?= $total ?></strong> result<?= $total !== 1 ? 's' : '' ?>
                            </div>
                            <div class="pagination-btns">
                                <button class="page-btn" disabled><i class="bi bi-chevron-left"></i></button>
                                <button class="page-btn active">1</button>
                                <button class="page-btn" disabled><i class="bi bi-chevron-right"></i></button>
                            </div>

                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var sidebar = document.getElementById('sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            var toggle  = document.getElementById('sidebarToggle');
            if (toggle) {
                toggle.addEventListener('click', function () {
                    sidebar.classList.toggle('open');
                    overlay.classList.toggle('show');
                });
                overlay.addEventListener('click', function () {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('show');
                });
            }

            // Search mode switching
            var modeBtns = document.querySelectorAll('.search-mode-btn');
            var rows     = document.querySelectorAll('.search-input-row');
            modeBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var mode = this.dataset.mode;
                    modeBtns.forEach(function (b) { b.classList.toggle('active', b === btn); });
                    rows.forEach(function (r) { r.style.display = r.dataset.mode === mode ? 'flex' : 'none'; });
                });
            });
            // Initial mode based on which filter is set
            var showAdvanced = <?= ($department > 0 || $sport_filter !== '' || $year_filter !== '') ? 'true' : 'false' ?>;
            if (showAdvanced && modeBtns[1]) {
                modeBtns[1].click();
            }

            // View toggles
            var viewBtns = document.querySelectorAll('.view-toggle');
            viewBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var v = this.dataset.view;
                    var url = new URL(window.location.href);
                    url.searchParams.set('view', v);
                    window.location.href = url.toString();
                });
            });
        });
    </script>
</body>
</html>
