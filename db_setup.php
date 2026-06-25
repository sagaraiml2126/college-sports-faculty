<?php
/**
 * ONE-TIME database setup script.
 *
 * Loads sql/schema.sql, sql/seed.sql, sql/seed.ready.sql against the
 * configured DB. The script is intended to be hit ONCE after first
 * deployment of the App to a fresh managed database, then DELETED
 * from the repo. It is gated by a token (DB_SETUP_TOKEN env var) so
 * that an accidental public hit cannot wipe a live database.
 *
 * Safety:
 *  - If the `students` table already exists, this script refuses to
 *    run (it would DROP and re-create everything). The user must
 *    DELETE the file once the setup is verified.
 *  - The token check runs BEFORE any DB query, so a wrong token is
 *    a no-op.
 *  - Errors are echoed with file:line precision so you can debug.
 *
 * Usage (after deploying):
 *   https://your-app.ondigitalocean.app/db_setup.php?t=YOUR_TOKEN
 *
 * Cleanup:
 *   After verifying SHOW TABLES is populated, delete this file from
 *   the repo and re-deploy (or `git rm db_setup.php`).
 */

declare(strict_types=1);

// Force display of all errors during setup, regardless of APP_ENV.
// The whole point of this one-time script is to surface the cause.
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/bootstrap.php';

/* ---------------- token gate ---------------- */

$expected = getenv('DB_SETUP_TOKEN');
if (!$expected) {
    // No token set at all -> refuse. Force the operator to set one.
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "db_setup: DB_SETUP_TOKEN env var is not set on the App.\n";
    echo "Set it under Settings -> Environment Variables, redeploy, then retry.\n";
    exit;
}
$given = (string)($_GET['t'] ?? '');
if (!hash_equals($expected, $given)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "db_setup: forbidden (wrong or missing token).\n";
    exit;
}

/* ---------------- "already initialized" guard ---------------- */

header('Content-Type: text/plain; charset=utf-8');

// DigitalOcean managed MySQL clusters ship with only `defaultdb`. We need
// to connect to it first, create csf_portal, then reconnect.
$dbName = DB_NAME;

// One-off connection to `defaultdb` (always present on DO managed MySQL)
// so we can check for + create csf_portal without going through the
// db() singleton (which calls exit() on connection failure).
echo "==> connecting to defaultdb (server-level) ...\n";
$tmp = mysqli_init();
if ($tmp === false) {
    echo "FAILED to init mysqli\n"; exit(1);
}
$use_ssl = (APP_ENV !== 'local') || getenv('DB_SSL') === 'true';
if ($use_ssl) {
    @mysqli_ssl_set($tmp, null, null, null, null, null);
}
$sslFlag = defined('MYSQLI_CLIENT_SSL') ? MYSQLI_CLIENT_SSL : 0;
$ok = mysqli_real_connect(
    $tmp, DB_HOST, DB_USER, DB_PASS, 'defaultdb', DB_PORT, null, $sslFlag
);
if (!$ok) {
    echo "FAILED to connect to defaultdb: " . mysqli_connect_error() . "\n";
    exit(1);
}
echo "    OK\n";

// Does csf_portal exist?
$exists = mysqli_query($tmp, "SHOW DATABASES LIKE '$dbName'");
$row = $exists ? mysqli_fetch_row($exists) : null;
if ($exists instanceof mysqli_result) mysqli_free_result($exists);
if (!$row) {
    echo "==> csf_portal does not exist. Creating it ...\n";
    $createSql = "CREATE DATABASE `$dbName` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if (!mysqli_query($tmp, $createSql)) {
        echo "FAILED to create $dbName: " . mysqli_error($tmp) . "\n";
        exit(1);
    }
    echo "    OK — created $dbName\n";
} else {
    echo "==> csf_portal already exists.\n";
}
mysqli_close($tmp);

// Now the regular db() connection to csf_portal will succeed.
$conn = db();
echo "==> connected to " . DB_NAME . "\n";

// SHOW TABLES does not accept LIMIT — use db_select and inspect first row.
// (db_one() auto-appends LIMIT 1, which fails on SHOW statements.)
$check = db_select("SHOW TABLES LIKE 'students'");
if (!empty($check)) {
    echo "db_setup: students table already exists. Refusing to re-run.\n";
    echo "If you want a fresh start, DROP DATABASE first (manually) and re-run.\n";
    echo "Otherwise, delete db_setup.php from the repo and redeploy.\n";
    exit;
}

/* ---------------- run the three SQL files ---------------- */

$sqlDir = __DIR__ . '/sql';
$files  = [
    // Base schema + seed data
    // NOTE: seed.sql and seed.ready.sql are duplicate content — pick one.
    // seed.ready.sql is the canonical production seed; seed.sql is the older
    // demo seed. Running both causes duplicate-key errors on uq_dept_code.
    'schema.sql',
    'seed.ready.sql',
    // All migrations in version order
    'migration-v2.sql',
    'migration-v3.sql',
    'migration-v4.sql',
    'migration-v5.sql',
    'migration-v6-docs.sql',
    'migration-v7-dpharm-docs.sql',
    'migration-v8-fix-document-names.sql',
    'migration-v9-final-teams.sql',
    'migration-v10-student-mother-name.sql',
    'migration-v11-student-roll-no.sql',
    'migration-v12-transfer-pharmacy.sql',
    'migration-v13-jersey.sql',
    'migration-v14-fix-faculty-departments.sql',
    'migration-v15-rename-pharmacy.sql',
    'migration-v16-add-ytc-pharmacy.sql',
    'migration-v18-consolidate-management.sql',
    'migration-v19-form-submitted-at.sql',
    'migration-v20-enrollment-nullable.sql',
    'migration-v21-has-played-in-college.sql',
    'migration-v22-game-catalog.sql',
    'migration-v23-eng-pharm-game-catalog.sql',
    'migration-v24-mgmt-arch-games.sql',
    'migration-v26-pdf-only-docs.sql',
    'migration-v27-passport-photo.sql',
    'migration_student_auth.sql',
];

$conn = db(); // fresh handle, charset already set in db()

foreach ($files as $name) {
    $path = $sqlDir . '/' . $name;
    if (!is_file($path)) {
        echo "db_setup: missing file $path\n";
        exit(1);
    }
    $sql = file_get_contents($path);
    if ($sql === false) {
        echo "db_setup: failed to read $path\n";
        exit(1);
    }

    echo "==> $name\n";

    // mysqli_multi_query is the only way to run multiple statements from
    // a single string in PHP. We iterate result sets to drain them all.
    if (!mysqli_multi_query($conn, $sql)) {
        echo "    FAILED: " . mysqli_error($conn) . "\n";
        exit(1);
    }
    do {
        $res = mysqli_store_result($conn);
        if ($res instanceof mysqli_result) {
            // Some statements (CREATE, INSERT) return no result set, but
            // SHOW TABLES returns one. Drain it.
            mysqli_free_result($res);
        }
    } while (mysqli_next_result($conn));

    if (mysqli_errno($conn) !== 0) {
        echo "    FAILED mid-stream: " . mysqli_error($conn) . "\n";
        exit(1);
    }
    echo "    OK\n";
}

/* ---------------- post-run verification ---------------- */

echo "\n--- verification ---\n";

$tables = db_select("SHOW TABLES");
echo "Tables in csf_portal:\n";
foreach ($tables as $row) {
    // SHOW TABLES returns a single column whose key is the literal table name.
    $first = reset($row);
    echo "  - $first\n";
}

$counts = db_select(
    "SELECT
        (SELECT COUNT(*) FROM departments)        AS dept_count,
        (SELECT COUNT(*) FROM dept_game_catalog)  AS game_count,
        (SELECT COUNT(*) FROM faculty)            AS faculty_count,
        (SELECT COUNT(*) FROM dept_document_requirements) AS doc_req_count"
);
if ($counts) {
    $c = $counts[0];
    echo "\nRow counts:\n";
    echo "  departments:              $c[dept_count]\n";
    echo "  dept_game_catalog:        $c[game_count]\n";
    echo "  faculty:                  $c[faculty_count]\n";
    echo "  dept_document_requirements: $c[doc_req_count]\n";
}

echo "\ndb_setup: done. DELETE db_setup.php from the repo now.\n";