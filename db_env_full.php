<?php
/**
 * One-time full env dump.
 *
 * Lists every environment variable visible to PHP. Lets us see if the
 * DigitalOcean App Platform is exposing DB_HOST etc. as expected.
 *
 * Delete after debugging: `git rm db_env_full.php && git push`.
 */

declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "db_env_full: PHP " . PHP_VERSION . " on " . PHP_OS . "\n";
echo "db_env_full: SAPI = " . PHP_SAPI . "\n";
echo "db_env_full: cwd  = " . getcwd() . "\n";
echo "db_env_full: count($_ENV) = " . count($_ENV) . "\n";
echo "db_env_full: count($_SERVER) = " . count($_SERVER) . "\n\n";

// 1. Raw getenv() of every DB_/MYSQL_/APP_/SITE_ var
$interesting = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME', 'DB_PORT', 'DB_SSL',
                'APP_ENV', 'SITE_URL', 'DB_SETUP_TOKEN',
                'MYSQLHOST', 'MYSQLUSER', 'MYSQLPASSWORD', 'MYSQLDATABASE', 'MYSQLPORT',
                'RAILWAY_PUBLIC_DOMAIN', 'PORT', 'HOME'];
echo "--- getenv() targeted ---\n";
foreach ($interesting as $k) {
    $v = getenv($k);
    $shown = ($v === false || $v === '') ? '(not set)' : $v;
    if (stripos($k, 'PASS') !== false && $v !== false && $v !== '') {
        $shown = substr($v, 0, 3) . '*** (len=' . strlen($v) . ')';
    }
    echo str_pad($k, 24) . " = $shown\n";
}

echo "\n--- getenv() DB-prefixed (full list) ---\n";
$db_keys = [];
foreach (range(0, 50) as $i) {
    $k = "DB_{$i}_HOST";
    if (getenv($k) !== false) $db_keys[] = $k;
}
// Most PHP getenv() impls don't enumerate; show all DB-prefixed via $_SERVER too
foreach ($_SERVER as $k => $v) {
    if (stripos($k, 'DB_') === 0 || stripos($k, 'MYSQL') === 0
        || stripos($k, 'APP_') === 0 || stripos($k, 'DATABASE') !== false) {
        $shown = stripos($k, 'PASS') !== false && is_string($v) && $v !== ''
                 ? substr($v, 0, 3) . '***'
                 : (is_string($v) ? $v : json_encode($v));
        echo str_pad($k, 32) . " = $shown\n";
    }
}

echo "\n--- getenv() APP_/SITE_ (full list) ---\n";
foreach ($_ENV as $k => $v) {
    if (stripos($k, 'DB_') === 0 || stripos($k, 'MYSQL') === 0
        || stripos($k, 'APP_') === 0 || stripos($k, 'SITE') === 0
        || stripos($k, 'RAILWAY') === 0) {
        $shown = stripos($k, 'PASS') !== false && is_string($v) && $v !== ''
                 ? substr($v, 0, 3) . '***'
                 : (is_string($v) ? $v : json_encode($v));
        echo str_pad($k, 32) . " = $shown\n";
    }
}

echo "\ndb_env_full: done. DELETE db_env_full.php from the repo when done debugging.\n";
