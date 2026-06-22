<?php
/**
 * Achievement create/update endpoint for the admin CMS. POST only.
 * CSRF + login protected. Handles image upload via handle_image_upload().
 * On replace, the old image is removed from disk (best-effort).
 *
 * Named _admin to coexist with the existing per-student achievement_save.php
 * used by student-profile.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

require_role('SUPER_ADMIN');
csrf_check();

$id          = (int)($_POST['id'] ?? 0);
$title       = trim((string)($_POST['title'] ?? ''));
$student_id  = (int)($_POST['student_id'] ?? 0);
$event_name  = trim((string)($_POST['event_name'] ?? ''));
$level_raw   = trim((string)($_POST['level'] ?? ''));
$position    = trim((string)($_POST['position'] ?? ''));
$event_date  = trim((string)($_POST['event_date'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$is_published = isset($_POST['is_published']) ? 1 : 0;

$back = 'achievement_edit.php?' . ($id > 0 ? 'id=' . $id : 'new=1');

if ($title === '') {
    flash_set('ach_error', 'Title is required.', 'error');
    redirect($back);
}

$allowed_levels = ['College','University','State','National','International'];
$level = in_array($level_raw, $allowed_levels, true) ? $level_raw : null;

// Normalize event_date to Y-m-d
if ($event_date !== '') {
    $ts = strtotime($event_date);
    $event_date = $ts ? date('Y-m-d', $ts) : null;
} else {
    $event_date = null;
}

$student_id = $student_id > 0 ? $student_id : null;

/* ---------------- Optional image upload ---------------- */

$new_image_path = null; // null = "don't touch the column"
$old_image_path = null;

if ($id > 0) {
    $existing = db_one('SELECT image_path FROM achievements WHERE id = ?', [$id], 'i');
    $old_image_path = $existing['image_path'] ?? null;
}

if (!empty($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $up = handle_image_upload('achievements', $_FILES['image'], 5000);
    if (!$up['ok']) {
        $reason = $up['error'] === 'too_large'      ? 'Image is larger than 5 MB.'
                : ($up['error'] === 'upload_error_' . UPLOAD_ERR_INI_SIZE
                    ? 'The server rejected the image because it exceeds the upload limit.'
                : ($up['error'] === 'bad_extension' ? 'Only JPG, PNG, or WebP images are allowed.'
                : ($up['error'] === 'not_an_image'   ? 'File is not a valid image.'
                : ('Upload failed (' . h($up['error']) . ').'))));
        flash_set('ach_error', $reason, 'error');
        redirect($back);
    }
    $new_image_path = $up['path']; // 'uploads/achievements/xxxxxxxx.jpg'
}

/* ---------------- INSERT vs UPDATE ---------------- */

if ($id > 0) {
    if ($new_image_path !== null) {
        db_execute(
            'UPDATE achievements SET student_id=?, title=?, description=?, event_name=?, level=?, position=?, event_date=?, image_path=?, is_published=? WHERE id=?',
            [$student_id, $title, $description ?: null, $event_name ?: $title, $level, $position ?: null, $event_date, $new_image_path, $is_published, $id],
            'isssssssii'
        );
        // Remove the old image now that the new one is safely in the DB
        if ($old_image_path && $old_image_path !== $new_image_path) {
            $abs = __DIR__ . '/../' . ltrim($old_image_path, '/');
            if (is_file($abs)) @unlink($abs);
        }
    } else {
        db_execute(
            'UPDATE achievements SET student_id=?, title=?, description=?, event_name=?, level=?, position=?, event_date=?, is_published=? WHERE id=?',
            [$student_id, $title, $description ?: null, $event_name ?: $title, $level, $position ?: null, $event_date, $is_published, $id],
            'issssssii'
        );
    }
    flash_set('ach_saved', 'Achievement updated.', 'success');
} else {
    db_insert(
        'INSERT INTO achievements (student_id, title, description, event_name, level, position, event_date, image_path, is_published)
         VALUES (?,?,?,?,?,?,?,?,?)',
        [$student_id, $title, $description ?: null, $event_name ?: $title, $level, $position ?: null, $event_date, $new_image_path, $is_published],
        'isssssssi'
    );
    flash_set('ach_saved', 'Achievement created.', 'success');
}

redirect('achievements_list.php');
