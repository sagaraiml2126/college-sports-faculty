<?php
/**
 * Student login handler. Validates email + DOB-password, sets the
 * student session, and redirects to student-dashboard.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (current_student()) {
    redirect('student-dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('student-login.php');
}

csrf_check();

if (is_locked_out()) {
    flash_set('student_login_error',
        'Too many failed attempts. Please wait a few minutes and try again.', 'error');
    redirect('student-login.php');
}

$email    = strtolower(trim((string)($_POST['email'] ?? '')));
$password = trim((string)($_POST['password'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    record_login_attempt($email, false);
    flash_set('student_login_error', 'Please enter a valid email and password.', 'error');
    redirect('student-login.php');
}

// Password must be 8 digits (DDMMYYYY)
if (!preg_match('/^[0-9]{8}$/', $password)) {
    record_login_attempt($email, false);
    flash_set('student_login_error',
        'Password must be 8 digits in DDMMYYYY format. (Your date of birth, e.g. 15082004).', 'error');
    redirect('student-login.php');
}

$student = db_one(
    'SELECT id, email, full_name, department_id, password_hash, is_active
       FROM students
      WHERE email = ?',
    [$email], 's'
);

if (!$student || empty($student['password_hash']) || !$student['is_active']) {
    record_login_attempt($email, false);
    flash_set('student_login_error',
        'No active account found with these credentials. Please register first.', 'error');
    redirect('student-login.php');
}

if (!password_verify($password, $student['password_hash'])) {
    record_login_attempt($email, false);
    flash_set('student_login_error', 'Incorrect password. Hint: it is your date of birth (DDMMYYYY).', 'error');
    redirect('student-login.php');
}

// Success — clear any reset banner, log in, update last_login_at
record_login_attempt($email, true);
db_execute('UPDATE students SET last_login_at = NOW() WHERE id = ?', [(int)$student['id']], 'i');

student_login($student);
redirect('student-dashboard.php');
