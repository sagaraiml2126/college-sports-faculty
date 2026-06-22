<?php
/**
 * CSRF token: per-session, generated lazily, rotated on login.
 * All state-changing endpoints call csrf_check().
 */

declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function csrf_meta(): string
{
    return '<meta name="csrf-token" content="' . h(csrf_token()) . '">';
}

/**
 * Call at the top of every POST handler. 403 on mismatch.
 * Returns true on success.
 */
function csrf_check(): bool
{
    $sent = $_POST['_csrf']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? '';
    $have = $_SESSION['csrf_token'] ?? '';
    if (!$sent || !$have || !hash_equals($have, $sent)) {
        http_response_code(403);
        if (APP_ENV === 'local') {
            exit('Invalid CSRF token.');
        }
        exit('Forbidden.');
    }
    return true;
}

/**
 * Call after login to force a fresh token.
 */
function csrf_rotate(): void
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
