<?php
/**
 * One-time token check.
 *
 * Tells the user whether DB_SETUP_TOKEN is set, without leaking the value.
 * Hit this BEFORE running db_setup.php to make sure the token gate will pass.
 *
 * Delete after debugging: `git rm db_token_check.php && git push`.
 */

declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

$tok = getenv('DB_SETUP_TOKEN');
if (!$tok) {
    http_response_code(503);
    echo "db_token_check: DB_SETUP_TOKEN env var is NOT set on the App.\n";
    echo "Go to DO App Platform -> your App -> Settings -> Environment Variables.\n";
    echo "Add: DB_SETUP_TOKEN = setmeup2026   (or anything you want)\n";
    echo "Redeploy, then hit /db_setup.php?t=setmeup2026\n";
    exit;
}

echo "db_token_check: DB_SETUP_TOKEN is set (len=" . strlen($tok) . ")\n";
echo "You can now run /db_setup.php?t=<the value you set>\n";
echo "DELETE db_token_check.php from the repo when done.\n";
