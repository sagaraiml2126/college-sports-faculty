<?php
/**
 * ONE-SHOT LIVE FIX — restore faculty_departments on the live DigitalOcean DB.
 *
 * Re-applies the same ownership rules as v14 + v16 (idempotent DELETE+INSERT),
 * so a faculty account whose department cards are missing should see them again.
 *
 * Usage:
 *   1. Upload this file to the live site root.
 *   2. Open  https://sportsfacultyyashoda-b38g9.ondigitalocean.app/db_fix_live_depts.php
 *      while logged in as a SUPER_ADMIN (so the page is allowed).
 *   3. Read the output. The script deletes itself on success.
 *
 * Safety:
 *   - Reads the current state first (so you see the "before").
 *   - Wraps the whole fix in a transaction; rolls back on any error.
 *   - Idempotent: re-running it leaves the same end state.
 */

declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/includes/bootstrap.php';

// Gate: only SUPER_ADMIN (or no login at all, if the live site is misconfigured).
$f = current_faculty();
if ($f && $f['role'] !== 'SUPER_ADMIN') {
    http_response_code(403);
    echo "Forbidden: must be SUPER_ADMIN to run this script.\n";
    exit;
}

echo "db_fix_live_depts: live DB = " . h(db_host_info()) . "\n\n";

// ---------- BEFORE ----------
echo "=== BEFORE: faculty_departments ===\n";
$before = db_select(
    'SELECT fd.faculty_id, fd.department_id, f.username, d.code, d.is_active
       FROM faculty_departments fd
       JOIN faculty f ON f.id = fd.faculty_id
       JOIN departments d ON d.id = fd.department_id
      ORDER BY f.username, d.code'
);
if (!$before) {
    echo "  (no rows)\n";
} else {
    foreach ($before as $r) {
        printf("  %-15s -> %-14s  active=%d\n",
            $r['username'], $r['code'], $r['is_active']);
    }
}

echo "\n=== Active departments (codes that should appear) ===\n";
$active = db_select('SELECT code, name FROM departments WHERE is_active = 1 ORDER BY display_order');
foreach ($active as $d) {
    printf("  %-14s  %s\n", $d['code'], $d['name']);
}

// ---------- FIX ----------
echo "\n=== Applying fix (v14 + v16 ownership rules) ===\n";

try {
    db()->begin_transaction();

    // v14 + v16: clear existing mappings for the canonical 4 seed users.
    db()->exec(
        "DELETE fd FROM faculty_departments fd
           JOIN faculty f ON f.id = fd.faculty_id
          WHERE f.username IN ('eng_faculty','poly_faculty','pharm_faculty','dpharm_faculty')"
    );

    // Re-insert the intended ownership.
    $rows = db()->exec(
        "INSERT INTO faculty_departments (faculty_id, department_id)
         SELECT f.id, d.id
           FROM faculty f
           JOIN departments d
             ON (f.username = 'eng_faculty'   AND d.code IN ('engineering', 'pharmacy', 'ytc_pharmacy'))
             OR (f.username = 'poly_faculty'  AND d.code IN ('polytechnic', 'dpharm'))
             OR (f.username = 'pharm_faculty' AND d.code IN ('management', 'architecture'))"
    );

    db()->commit();
    echo "  inserted rows: $rows\n";
} catch (Throwable $e) {
    db()->rollBack();
    echo "  FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// ---------- AFTER ----------
echo "\n=== AFTER: faculty_departments ===\n";
$after = db_select(
    'SELECT fd.faculty_id, fd.department_id, f.username, d.code, d.is_active
       FROM faculty_departments fd
       JOIN faculty f ON f.id = fd.faculty_id
       JOIN departments d ON d.id = fd.department_id
      ORDER BY f.username, d.code'
);
if (!$after) {
    echo "  (no rows — something is wrong with the faculty table)\n";
} else {
    foreach ($after as $r) {
        printf("  %-15s -> %-14s  active=%d\n",
            $r['username'], $r['code'], $r['is_active']);
    }
}

echo "\nDone. Open faculty-select.php and the cards should be back.\n";
echo "Self-deleting this file in 2s...\n";

// Self-delete so the URL can't be re-abused.
sleep(2);
@unlink(__FILE__);
echo "Deleted.\n";
