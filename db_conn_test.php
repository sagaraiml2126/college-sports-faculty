<?php
/**
 * One-time DB connection probe.
 *
 * Attempts a trivial query to see if the SSL connection to the managed
 * database actually works. Outputs the full exception/error if it fails.
 *
 * Delete after debugging: `git rm db_conn_test.php && git push`.
 */

declare(strict_types=1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "db_conn_test: starting\n";
echo "PHP " . PHP_VERSION . " on " . PHP_OS . "\n";

// Bypass bootstrap to avoid session/header side effects.
require_once __DIR__ . '/includes/db.php';

echo "DB_HOST = " . DB_HOST . "\n";
echo "DB_USER = " . DB_USER . "\n";
echo "DB_NAME = " . DB_NAME . "\n";
echo "DB_PORT = " . DB_PORT . "\n";
echo "APP_ENV = " . APP_ENV . "\n";
echo "DB_SSL  = " . var_export(getenv('DB_SSL'), true) . "\n\n";

echo "calling db()...\n";
try {
    $conn = db();
    echo "db() returned: " . get_class($conn) . "\n";
    echo "server info:   " . mysqli_get_server_info($conn) . "\n";
    echo "host info:     " . mysqli_get_host_info($conn) . "\n";
    echo "protocol:      " . mysqli_get_proto_info($conn) . "\n\n";

    echo "running SELECT 1...\n";
    $r = db_one("SELECT 1 AS one");
    echo "result: " . json_encode($r) . "\n\n";

    echo "running SHOW DATABASES...\n";
    $rows = db_select("SHOW DATABASES");
    foreach ($rows as $row) {
        $first = reset($row);
        echo "  - $first\n";
    }
    echo "\ndb_conn_test: ALL OK. DELETE db_conn_test.php from the repo.\n";
} catch (Throwable $e) {
    echo "\n*** EXCEPTION ***\n";
    echo "  class:   " . get_class($e) . "\n";
    echo "  message: " . $e->getMessage() . "\n";
    echo "  file:    " . $e->getFile() . "\n";
    echo "  line:    " . $e->getLine() . "\n";
    echo "  trace:\n";
    foreach ($e->getTrace() as $i => $f) {
        $where = ($f['file'] ?? '?') . ':' . ($f['line'] ?? '?');
        $what  = ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '?');
        echo "    #$i  $what()  at  $where\n";
    }
    echo "\ndb_conn_test: FAILED. Delete this file from the repo when done.\n";
}
