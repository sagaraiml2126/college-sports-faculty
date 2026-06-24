<?php
/**
 * One-time environment diagnostic.
 *
 * Echoes the values of every DB_* and APP_ENV env var so we can see what
 * DigitalOcean App Platform actually passes to the PHP container. Lets us
 * debug "Connection refused" / 500 errors without guesswork.
 *
 * Safety:
 *  - Reads no secrets back (passwords are masked to first 3 chars + length).
 *  - Does NOT touch the database. If the App is up at all, this script runs.
 *
 * Usage:
 *   https://sportsfacultyyashoda-b38g9.ondigitalocean.app/db_env.php
 *
 * Delete after debugging: `git rm db_env.php && git push`.
 */

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "db_env: PHP " . PHP_VERSION . " on " . PHP_OS . "\n";
echo "db_env: APP_ENV = " . var_export(getenv('APP_ENV'), true) . "\n";
echo "db_env: DB_SSL  = " . var_export(getenv('DB_SSL'), true) . "\n\n";

$keys = ['DB_HOST', 'DB_USER', 'DB_NAME', 'DB_PORT', 'DB_PASS',
         'MYSQLHOST', 'MYSQLUSER', 'MYSQLDATABASE', 'MYSQLPORT', 'MYSQLPASSWORD'];

foreach ($keys as $k) {
    $v = getenv($k);
    if ($v === false || $v === '') {
        echo str_pad($k, 18) . " = (not set)\n";
    } elseif (stripos($k, 'PASS') !== false || stripos($k, 'PASSWORD') !== false) {
        // Mask secret values: keep first 3 chars + length, no more.
        $mask = strlen($v) <= 3 ? str_repeat('*', strlen($v))
                                 : substr($v, 0, 3) . str_repeat('*', max(0, strlen($v) - 3))
                                 . " (len=" . strlen($v) . ")";
        echo str_pad($k, 18) . " = $mask\n";
    } else {
        echo str_pad($k, 18) . " = $v\n";
    }
}

echo "\n--- mysqli capability check ---\n";
echo "function_exists('mysqli_init'):         " . var_export(function_exists('mysqli_init'), true) . "\n";
echo "function_exists('mysqli_real_connect'): " . var_export(function_exists('mysqli_real_connect'), true) . "\n";
echo "function_exists('mysqli_ssl_set'):      " . var_export(function_exists('mysqli_ssl_set'), true) . "\n";
echo "defined('MYSQLI_CLIENT_SSL'):           " . var_export(defined('MYSQLI_CLIENT_SSL'), true) . "\n";
if (defined('MYSQLI_CLIENT_SSL')) {
    echo "MYSQLI_CLIENT_SSL value:               " . MYSQLI_CLIENT_SSL . "\n";
}
echo "extension_loaded('openssl'):            " . var_export(extension_loaded('openssl'), true) . "\n";
echo "extension_loaded('mysqli'):             " . var_export(extension_loaded('mysqli'), true) . "\n";
echo "\ndb_env: done. DELETE db_env.php from the repo when done debugging.\n";
