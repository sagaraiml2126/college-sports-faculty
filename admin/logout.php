<?php
/**
 * Logout endpoint. Clears session and redirects to login.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

logout_user();
session_start(); // start a fresh session for the flash
flash_set('login_error', 'You have been signed out.', 'info');
session_write_close();
header('Location: ' . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/../faculty-login.php');
exit;
