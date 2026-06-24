<?php
/**
 * Permanent maintenance helper: re-sync the canonical seed-user password
 * hashes to match the published passwords in the project documentation.
 *
 * Run from project root on the affected server:
 *   php sql/fix-seed-hashes.php
 *
 * Use cases:
 *   - After accidentally re-importing sql/seed.ready.sql onto a live DB
 *     (which overwrites any rotated passwords with the stale seed values).
 *   - As a self-test: after the fix, eng_faculty / poly_faculty /
 *     pharm_faculty will once again log in with "Faculty@123" and the
 *     admin account with "Admin@123".
 *
 * Note: this only updates the *canonical* seed users. If you have rotated
 * a seed account's password on purpose, this script will overwrite your
 * change — that's intentional, the seed user is meant to be predictable.
 * For non-seed users, use the admin "change password" UI.
 */

if (PHP_SAPI !== 'cli') { die("Run from CLI: php sql/fix-seed-hashes.php\n"); }

// Use the same env-var lookup as the App, so this script works on both
// local XAMPP and production managed MySQL (DO, AWS RDS, etc.).
require_once __DIR__ . '/../includes/db.php';

$conn = db();

$users = [
    ['username' => 'admin',         'password' => 'Admin@123'],
    ['username' => 'eng_faculty',   'password' => 'Faculty@123'],
    ['username' => 'poly_faculty',  'password' => 'Faculty@123'],
    ['username' => 'pharm_faculty', 'password' => 'Faculty@123'],
];

$conn->query("UPDATE faculty SET updated_at = NOW() WHERE 0"); // warm up

$stmt = $conn->prepare('UPDATE faculty SET password_hash = ?, updated_at = created_at WHERE username = ?');
foreach ($users as $u) {
    $hash = password_hash($u['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt->bind_param('ss', $hash, $u['username']);
    $stmt->execute();
    printf("%-15s updated, affected=%d\n", $u['username'], $stmt->affected_rows);
}
$stmt->close();

echo "\nVerify after fix:\n";
$res = $conn->query("SELECT username FROM faculty ORDER BY id");
while ($row = $res->fetch_assoc()) {
    $username = $conn->real_escape_string($row['username']);
    $r2 = $conn->query("SELECT password_hash FROM faculty WHERE username='$username'");
    $h = $r2->fetch_row()[0];
    $ok = password_verify('Faculty@123', $h);
    printf("  %-15s Faculty@123 verify = %s\n", $row['username'], $ok ? 'YES' : 'NO');
}
echo "\nDone. If 'admin' shows verify=NO above, that is expected — 'admin' uses 'Admin@123'.\n";
