<?php
/**
 * Seed-user self-check.
 *
 * On any request to an admin/faculty area, verifies that the canonical seed
 * user accounts still accept their published passwords. The whole point of
 * "seed users" is that they have known credentials for first-time setup
 * and recovery; if the hashes drift (someone re-imported a stale
 * seed.ready.sql after a password change, or a build copy overwrote the
 * live DB), this check will surface the problem on the next page load.
 *
 * Behaviour:
 *   - The check is CACHED in $_SESSION['seed_check'] for 6 hours so it
 *     does not hit the DB on every request.
 *   - The check is SKIPPED for a user whose `updated_at` is newer than
 *     the published seed `created_at` value — this means an admin who
 *     intentionally rotated a password will not see a false alarm.
 *     (We rely on the faculty table having a `created_at` column with
 *     a constant seed value; if it doesn't, we bail with a no-op.)
 *   - When the check fails, a flash-style banner is exposed via
 *     seed_check_warning() for the admin layout to render.
 *
 * Only loads on admin/faculty areas (caller checks the request path).
 */

declare(strict_types=1);

const SEED_CHECK_USERS = [
    ['username' => 'admin',         'password' => 'Admin@123'],
    ['username' => 'eng_faculty',   'password' => 'Faculty@123'],
    ['username' => 'poly_faculty',  'password' => 'Faculty@123'],
    ['username' => 'pharm_faculty', 'password' => 'Faculty@123'],
];
const SEED_CHECK_TTL = 21600; // 6 hours

/**
 * Run the seed check, cache the result, and return true if every seed
 * user's current hash accepts its published password.
 */
function seed_check_run(): bool {
    // Static cache within the request.
    static $cached = null;
    if ($cached !== null) return $cached;

    // Session cache to avoid hitting the DB on every request.
    if (isset($_SESSION['seed_check']) && is_array($_SESSION['seed_check'])
        && isset($_SESSION['seed_check']['at'], $_SESSION['seed_check']['ok'])
        && (time() - (int)$_SESSION['seed_check']['at']) < SEED_CHECK_TTL) {
        $cached = (bool)$_SESSION['seed_check']['ok'];
        return $cached;
    }

    $ok = true;
    $details = [];
    foreach (SEED_CHECK_USERS as $u) {
        $row = db_one(
            'SELECT password_hash, created_at, updated_at
               FROM faculty WHERE username = ? LIMIT 1',
            [$u['username']], 's'
        );
        if (!$row) { continue; } // user removed — not our problem.
        // If the row was touched after the seed import, assume the admin
        // knows the password and skip. The check is for the
        // "stale-seed-overwrote-live-DB" failure mode only.
        $created = isset($row['created_at']) ? strtotime((string)$row['created_at']) : 0;
        $updated = isset($row['updated_at']) ? strtotime((string)$row['updated_at']) : 0;
        if ($updated > $created + 60) { continue; } // 60s grace for import-time variance.
        $verifies = password_verify($u['password'], (string)$row['password_hash']);
        if (!$verifies) {
            $ok = false;
            $details[] = $u['username'];
        }
    }

    $_SESSION['seed_check'] = [
        'at'      => time(),
        'ok'      => $ok,
        'details' => $details,
    ];
    $cached = $ok;
    return $ok;
}

/**
 * Returns a dismissable warning string if the seed check has flagged an
 * issue, or an empty string if everything is fine. Layouts render this
 * as a yellow admin banner.
 */
function seed_check_warning(): string {
    if (empty($_SESSION['seed_check']) || !is_array($_SESSION['seed_check'])) return '';
    if (!empty($_SESSION['seed_check']['ok'])) return '';
    $details = $_SESSION['seed_check']['details'] ?? [];
    $list = $details ? implode(', ', $details) : 'one or more seed users';
    return '<div class="seed-check-warn" role="alert">'
        . '<strong>Seed-user password drift detected.</strong> '
        . 'The following account(s) no longer accept their published passwords: '
        . '<code>' . htmlspecialchars($list, ENT_QUOTES) . '</code>. '
        . 'This usually means <code>seed.ready.sql</code> was re-imported on a server '
        . 'with existing data. Run <code>php sql/fix-seed-hashes.php</code> to repair. '
        . '<a href="?dismiss_seed_check=1">Dismiss</a>'
        . '</div>';
}

/**
 * Side-effect helper: run the check and, if it fails, mark the session
 * so the bootstrap output buffer can inject a visible warning into the
 * rendered HTML. Idempotent: only runs once per request.
 */
function seed_check_flash(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    seed_check_run();
}
