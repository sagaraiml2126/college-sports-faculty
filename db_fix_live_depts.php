<?php
/**
 * ONE-SHOT LIVE FIX — restore faculty_departments on the live DigitalOcean DB.
 *
 * Re-applies the same ownership rules as v14 + v16 (idempotent DELETE+INSERT).
 * Standalone — does NOT use bootstrap.php or auth.php, so it works whether
 * you're logged in or not, and isn't tripped by the seed-check output buffer.
 *
 * Usage:
 *   1. Open https://sportsfacultyyashoda-b38g9.ondigitalocean.app/db_fix_live_depts.php
 *   2. Read the BEFORE / AFTER blocks. It self-deletes on success.
 */

declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

/* ---------------- direct DB connect (no bootstrap) ---------------- */

$isLocal = (getenv('APP_ENV') === 'local') || (getenv('APP_ENV') === false);

$envLookup = function (string $key) {
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
    if (isset($_ENV[$key])    && $_ENV[$key]    !== '') return $_ENV[$key];
    if (strpos($key, 'DB_') === 0) {
        $alt = 'MYSQL' . substr($key, 3);
        $v = getenv($alt);
        if ($v !== false && $v !== '') return $v;
        if (isset($_SERVER[$alt]) && $_SERVER[$alt] !== '') return $_SERVER[$alt];
        if (isset($_ENV[$alt])    && $_ENV[$alt]    !== '') return $_ENV[$alt];
    }
    return false;
};

$host = $envLookup('DB_HOST');
$user = $envLookup('DB_USER');
$pass = $envLookup('DB_PASS');
$name = $envLookup('DB_NAME');
$port = $envLookup('DB_PORT');

if (!$host) {
    $url = $envLookup('DATABASE_URL');
    if ($url && strpos($url, '${') === false && strpos($url, '://') !== false) {
        $parts = parse_url($url);
        if ($parts && isset($parts['host'])) {
            $host = $parts['host'];
            $port = isset($parts['port']) ? (int)$parts['port'] : 3306;
            $user = $parts['user'] ?? $user;
            $pass = $parts['pass'] ?? $pass;
            $name = ltrim($parts['path'] ?? '', '/') ?: $name;
        }
    }
}

echo "db_fix_live_depts: starting\n";
echo "  host=" . ($host ?: '(missing)') . "  port=" . (int)($port ?: 3306) . "  db=" . ($name ?: '(missing)') . "\n";
echo "  app_env=" . ($isLocal ? 'local' : 'production') . "\n\n";

if (!$host || !$user || !$name) {
    http_response_code(500);
    echo "FATAL: missing DB_HOST / DB_USER / DB_NAME env vars.\n";
    echo "Set them in the App Platform dashboard, or fix the DATABASE_URL binding.\n";
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli($host, $user, $pass ?? '', $name, (int)($port ?: 3306));
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo "FATAL: cannot connect to DB: " . $e->getMessage() . "\n";
    exit(1);
}
$conn->set_charset('utf8mb4');
echo "  connected OK\n\n";

/* ---------------- BEFORE ---------------- */

echo "=== BEFORE: faculty_departments ===\n";
$res = $conn->query(
    'SELECT fd.faculty_id, fd.department_id, f.username, d.code, d.is_active
       FROM faculty_departments fd
       JOIN faculty f ON f.id = fd.faculty_id
       JOIN departments d ON d.id = fd.department_id
      ORDER BY f.username, d.code'
);
if (!$res || $res->num_rows === 0) {
    echo "  (no rows)\n";
} else {
    while ($r = $res->fetch_assoc()) {
        printf("  %-15s -> %-14s  active=%d\n",
            $r['username'], $r['code'], $r['is_active']);
    }
}

echo "\n=== Active departments (codes that should appear) ===\n";
$res = $conn->query('SELECT code, name FROM departments WHERE is_active = 1 ORDER BY display_order');
while ($r = $res->fetch_assoc()) {
    printf("  %-14s  %s\n", $r['code'], $r['name']);
}

/* ---------------- FIX ---------------- */

echo "\n=== Applying fix (v14 + v16 ownership rules) ===\n";

try {
    $conn->begin_transaction();

    $conn->query(
        "DELETE fd FROM faculty_departments fd
           JOIN faculty f ON f.id = fd.faculty_id
          WHERE f.username IN ('eng_faculty','poly_faculty','pharm_faculty','dpharm_faculty')"
    );
    $deleted = $conn->affected_rows;

    $conn->query(
        "INSERT INTO faculty_departments (faculty_id, department_id)
         SELECT f.id, d.id
           FROM faculty f
           JOIN departments d
             ON (f.username = 'eng_faculty'   AND d.code IN ('engineering', 'pharmacy', 'ytc_pharmacy'))
             OR (f.username = 'poly_faculty'  AND d.code IN ('polytechnic', 'dpharm'))
             OR (f.username = 'pharm_faculty' AND d.code IN ('management', 'architecture'))"
    );
    $inserted = $conn->affected_rows;

    $conn->commit();
    echo "  deleted $deleted old rows, inserted $inserted new rows\n";
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo "  FAILED (rolled back): " . $e->getMessage() . "\n";
    exit(1);
}

/* ---------------- AFTER ---------------- */

echo "\n=== AFTER: faculty_departments ===\n";
$res = $conn->query(
    'SELECT fd.faculty_id, fd.department_id, f.username, d.code, d.is_active
       FROM faculty_departments fd
       JOIN faculty f ON f.id = fd.faculty_id
       JOIN departments d ON d.id = fd.department_id
      ORDER BY f.username, d.code'
);
if ($res->num_rows === 0) {
    echo "  (no rows — the faculty table may be empty)\n";
} else {
    while ($r = $res->fetch_assoc()) {
        printf("  %-15s -> %-14s  active=%d\n",
            $r['username'], $r['code'], $r['is_active']);
    }
}

$conn->close();

echo "\nDone. faculty-select.php should now show the cards again.\n";
echo "Self-deleting this file in 2s...\n";

sleep(2);
@unlink(__FILE__);
echo "Deleted.\n";