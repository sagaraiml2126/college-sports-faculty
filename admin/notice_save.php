<?php
/**
 * Notice create/update endpoint. POST only. CSRF protected.
 * Handles PDF attachment upload via handle_pdf_upload('notices', …).
 * On replace, the old PDF is removed from disk (best-effort).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

require_role('SUPER_ADMIN');
csrf_check();

$id           = (int)($_POST['id'] ?? 0);
$title        = trim((string)($_POST['title'] ?? ''));
$category     = trim((string)($_POST['category'] ?? ''));
$summary      = trim((string)($_POST['summary'] ?? ''));
$body         = trim((string)($_POST['body'] ?? ''));
$notice_date  = trim((string)($_POST['notice_date'] ?? ''));
$is_published = isset($_POST['is_published']) ? 1 : 0;

$back = 'notice_edit.php?' . ($id > 0 ? 'id=' . $id : 'new=1');

if ($title === '') {
    flash_set('notice_error', 'Title is required.', 'error');
    redirect($back);
}
if ($notice_date === '') {
    flash_set('notice_error', 'Notice date is required.', 'error');
    redirect($back);
}
// Normalize date to Y-m-d
$ts = strtotime($notice_date);
if (!$ts) {
    flash_set('notice_error', 'Invalid notice date.', 'error');
    redirect($back);
}
$notice_date = date('Y-m-d', $ts);

/* ---------------- Optional PDF upload ---------------- */

$attachment_filename = null; // null = "don't touch the column"
$old_attachment = null;

if ($id > 0) {
    $existing = db_one('SELECT attachment FROM notices WHERE id = ?', [$id], 'i');
    $old_attachment = $existing['attachment'] ?? null;
}

if (!empty($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $up = handle_pdf_upload('notices', $_FILES['attachment'], 5000);
    if (!$up['ok']) {
        $reason = $up['error'] === 'too_large'      ? 'PDF is larger than 5 MB.'
                : ($up['error'] === 'bad_extension' ? 'Only PDF files are allowed.'
                : ($up['error'] === 'bad_mime'      ? 'File is not a valid PDF.'
                : ('Upload failed (' . h($up['error']) . ').')));
        flash_set('notice_error', $reason, 'error');
        redirect($back);
    }
    $attachment_filename = $up['filename'];
}

/* ---------------- INSERT vs UPDATE ---------------- */

if ($id > 0) {
    // Update
    if ($attachment_filename !== null) {
        db_execute(
            'UPDATE notices SET title=?, category=?, summary=?, body=?, notice_date=?, is_published=?, attachment=? WHERE id=?',
            [$title, $category ?: null, $summary ?: null, $body ?: null, $notice_date, $is_published, $attachment_filename, $id],
            'sssssssi'
        );
        // Remove the old PDF now that the new one is safely in the DB
        if ($old_attachment && $old_attachment !== $attachment_filename) {
            $abs = __DIR__ . '/../uploads/notices/' . basename($old_attachment);
            if (is_file($abs)) @unlink($abs);
        }
    } else {
        db_execute(
            'UPDATE notices SET title=?, category=?, summary=?, body=?, notice_date=?, is_published=? WHERE id=?',
            [$title, $category ?: null, $summary ?: null, $body ?: null, $notice_date, $is_published, $id],
            'ssssssi'
        );
    }
    flash_set('notice_saved', 'Notice updated.', 'success');
} else {
    // Insert
    $me = current_faculty();
    db_insert(
        'INSERT INTO notices (title, category, summary, body, notice_date, is_published, attachment, posted_by)
         VALUES (?,?,?,?,?,?,?,?)',
        [$title, $category ?: null, $summary ?: null, $body ?: null, $notice_date, $is_published, $attachment_filename, $me['id']],
        'sssssssi'
    );
    flash_set('notice_saved', 'Notice created.', 'success');
}

redirect('notices_list.php');
