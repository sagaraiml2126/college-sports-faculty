<?php
/**
 * Student logout. Destroys only the student session (faculty session
 * stays alive, in case an admin is testing both sides).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

student_logout();
flash_set('student_login_info', 'You have been signed out.', 'info');
redirect('student-login.php');
