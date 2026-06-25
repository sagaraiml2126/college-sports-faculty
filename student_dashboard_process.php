<?php
/**
 * Student wizard save endpoint.
 *
 * Three modes on POST, all guarded by CSRF + require_student():
 *   ?step=N       (N=1,2,3,4) — save the fields for that wizard step,
 *                                bump students.form_step to N+1, redirect
 *                                to the next step with a green flash.
 *   ?step=5       — handle a single document upload or delete (called via
 *                    fetch() from the documents UI). Returns JSON.
 *   ?finalize=1   — student hit Submit on the preview. Set
 *                    form_submitted_at = NOW(), form_step = 6, redirect
 *                    back to step 6 with a success flash.
 *
 * If a DOB is changed on step 1, the password_hash is re-derived from the
 * new DOB (DDMMYYYY), same as the legacy single-page flow.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_student();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('student-dashboard.php');
}

csrf_check();

$meId = (int)current_student()['id'];

/* -----------------------------------------------------------------
 * Helpers (local — keep this file self-contained)
 * ----------------------------------------------------------------- */

$stepParam = $_GET['step']    ?? null;
$finalize  = !empty($_GET['finalize']);
$deleteId  = (int)($_GET['delete'] ?? 0);

if ($finalize) {
    handle_finalize($meId);
    return;
}

if ($deleteId > 0) {
    handle_doc_delete($meId, $deleteId);
    return;
}

$step = (int)$stepParam;
if ($step < 1 || $step > 5) {
    redirect('student-dashboard.php');
}

if ($step === 5) {
    handle_doc_upload($meId);
    return;
}

/* Steps 1-4: field save */
handle_step_save($meId, $step);

/* unreachable */
redirect('student-dashboard.php?step=' . $step);

/* =================================================================
 * Mode 1 — Step field save (1..4)
 * ================================================================= */
function handle_step_save(int $meId, int $step): void
{
    /* ---- load current row (dept + DOB for password-reset detection) ---- */
    $current = db_one(
        'SELECT s.*, d.code AS department_code
           FROM students s
           JOIN departments d ON d.id = s.department_id
          WHERE s.id = ?',
        [$meId], 'i'
    );
    if (!$current) {
        http_response_code(404);
        exit('Student not found.');
    }

    $dept_code              = (string)($current['department_code'] ?? '');
    $uses_father_first_name = in_array($dept_code, ['engineering', 'pharmacy'], true);
    $stores_roll_no         = in_array($dept_code, ['polytechnic', 'dpharm', 'pharmacy', 'ytc_pharmacy', 'management', 'architecture'], true);

    /* Game-picker detection — same lookup as student-dashboard.php. */
    $catalog_rows = db_select(
        'SELECT game_code, max_picks FROM dept_game_catalog
          WHERE department_id = ? AND is_active = 1',
        [(int)$current['department_id']], 'i'
    );
    $uses_game_picker = !empty($catalog_rows);
    $game_max_picks   = $uses_game_picker ? (int)$catalog_rows[0]['max_picks'] : 0;
    $catalog_codes    = array_column($catalog_rows, 'game_code');
    $catalog_codes_set = array_flip(array_map('strval', $catalog_codes));

    /* ---- pick the field set this step owns ---- */
    $errors = [];

    if ($step === 1) {
        /* Personal */
        $surname     = trim((string)($_POST['surname']     ?? ''));
        $first_name  = trim((string)($_POST['first_name']  ?? ''));
        $middle_name = trim((string)($_POST['middle_name'] ?? ''));
        $posted_full = trim((string)($_POST['full_name']   ?? ''));
        $full_name   = trim(implode(' ', array_filter([$surname, $first_name, $middle_name])));
        if ($full_name === '') $full_name = $posted_full;

        $mother_name  = trim((string)($_POST['mother_name']  ?? ''));
        $dob          = trim((string)($_POST['dob']          ?? ''));
        $gender       = trim((string)($_POST['gender']       ?? ''));
        $blood_group  = trim((string)($_POST['blood_group']  ?? ''));
        $mobile       = trim((string)($_POST['mobile']       ?? ''));
        $address      = trim((string)($_POST['address']      ?? ''));

        if (strlen($full_name) < 2 || strlen($full_name) > 160) {
            $errors[] = 'Full name must be 2-160 characters.';
        }
        $dob_ts = strtotime($dob);
        if (!$dob_ts || $dob_ts > time() || $dob_ts < strtotime('1990-01-01')) {
            $errors[] = 'Please enter a valid date of birth.';
        }
        if (!preg_match('/^[0-9]{10}$/', $mobile)) {
            $errors[] = 'Mobile number must be exactly 10 digits.';
        }
        if ($gender === '' || !in_array($gender, gender_options(), true)) {
            $errors[] = 'Please select your gender.';
        }
        if ($blood_group !== '' && !in_array($blood_group, blood_options(), true)) {
            $errors[] = 'Invalid blood group.';
        }
        if ($address === '' || strlen($address) > 500) {
            $errors[] = 'Address is required (max 500 characters).';
        }
        /* parent-name semantics:
           For engineering/pharmacy, the father's first name IS the middle name.
           Copy middle_name into mother_name (the column we use to store the
           parent name). Middle name is required for every department. */
        if ($middle_name === '') {
            $errors[] = 'Middle name is required.';
        } elseif ($uses_father_first_name) {
            $mother_name = $middle_name;
        }

        if ($errors) {
            flash_set('student_dashboard_err', implode(' ', $errors), 'error');
            redirect('student-dashboard.php?step=1');
        }

        /* detect DOB change → reset password */
        $dob_changed = (date('Y-m-d', $dob_ts) !== (string)$current['dob']);

        $sql = 'UPDATE students SET
                    full_name = ?, mother_name = ?, dob = ?, gender = ?, blood_group = ?,
                    mobile = ?, address = ?';
        $params = [
            $full_name,
            $mother_name !== '' ? $mother_name : null,
            date('Y-m-d', $dob_ts),
            $gender !== '' ? $gender : null,
            $blood_group !== '' ? $blood_group : null,
            $mobile,
            $address !== '' ? $address : null,
        ];
        $types = 'sssssss';

        if ($dob_changed) {
            $new_pw_plain = dob_to_password(date('Y-m-d', $dob_ts));
            $new_pw_hash  = password_hash($new_pw_plain, PASSWORD_BCRYPT);
            $sql .= ', password_hash = ?';
            $params[] = $new_pw_hash;
            $types   .= 's';
        }

        $sql .= ', form_step = GREATEST(COALESCE(form_step, 0), 2) WHERE id = ?';
        $params[] = $meId;
        $types   .= 'i';

        db_execute($sql, $params, $types);

        flash_set('student_dashboard_ok',
            $dob_changed
                ? 'Personal info saved. Your password has been reset to your new DOB (DDMMYYYY).'
                : 'Personal info saved.',
            'success');
        redirect('student-dashboard.php?step=2');
    }

    if ($step === 2) {
        /* Academic */
        $enrollment_no  = trim((string)($_POST['enrollment_no']  ?? ''));
        $roll_no        = trim((string)($_POST['roll_no']        ?? ''));
        $program        = trim((string)($_POST['program']        ?? ''));
        $academic_year  = trim((string)($_POST['academic_year']  ?? ''));
        $study_year     = trim((string)($_POST['study_year']     ?? ''));
        $department_id  = (int)($_POST['department_id'] ?? 0);

        if ($department_id <= 0 || $department_id !== (int)$current['department_id']) {
            $errors[] = 'Invalid department.';
        }
        if ($enrollment_no === '' || strlen($enrollment_no) > 40) {
            $errors[] = 'Enrollment number is required (max 40 characters).';
        } else {
            /* UNIQUE check — same as admin/student_save.php:93-99 */
            $dup = db_one(
                'SELECT id FROM students WHERE enrollment_no = ? AND id <> ? LIMIT 1',
                [$enrollment_no, $meId], 'si'
            );
            if ($dup) {
                $errors[] = "Enrollment number '$enrollment_no' is already in use.";
            }
        }
        if ($study_year === '' || !in_array($study_year, year_options(), true)) {
            $errors[] = 'Year of Study is required.';
        }
        if ($program === '' || strlen($program) > 120) {
            $errors[] = 'Program / Branch is required (max 120 characters).';
        }
        if ($academic_year === '' || !in_array($academic_year, academic_year_options(), true)) {
            $errors[] = 'Academic Year is required.';
        }

        if ($errors) {
            flash_set('student_dashboard_err', implode(' ', $errors), 'error');
            redirect('student-dashboard.php?step=2');
        }

        $sql = 'UPDATE students SET
                    enrollment_no = ?, roll_no = ?, program = ?, academic_year = ?, study_year = ?,
                    form_step = GREATEST(COALESCE(form_step, 0), 3)
                WHERE id = ?';
        $params = [
            $enrollment_no,
            ($stores_roll_no && $roll_no !== '') ? $roll_no : null,
            $program      !== '' ? $program      : null,
            $academic_year!== '' ? $academic_year: null,
            $study_year   !== '' ? $study_year   : null,
            $meId,
        ];
        $types = 'sssssi';

        db_execute($sql, $params, $types);

        flash_set('student_dashboard_ok', 'Academic details saved.', 'success');
        redirect('student-dashboard.php?step=3');
    }

    if ($step === 3) {
        /* Sports */
        $achievements = trim((string)($_POST['achievements'] ?? ''));

        if ($uses_game_picker) {
            /* Picker mode (polytechnic, dpharm, etc.) — validate games[] */
            $posted_games = $_POST['games'] ?? null;
            if (!is_array($posted_games)) {
                $errors[] = 'Please select your games.';
            } else {
                $posted_games = array_values(array_filter(
                    array_map(function ($v) { return trim((string)$v); }, $posted_games),
                    'strlen'
                ));
                if (count($posted_games) !== $game_max_picks) {
                    $errors[] = "Please select exactly {$game_max_picks} games.";
                }
                if (count($posted_games) !== count(array_unique($posted_games))) {
                    $errors[] = 'Duplicate game selections are not allowed.';
                }
                foreach ($posted_games as $gc) {
                    if (!isset($catalog_codes_set[$gc])) {
                        $errors[] = "Invalid game selection: {$gc}.";
                        break;
                    }
                }
            }

            if ($errors) {
                flash_set('student_dashboard_err', implode(' ', $errors), 'error');
                redirect('student-dashboard.php?step=3');
            }

            /* Replace-write to student_selected_games + update achievements.
               sport_1/sport_2 columns are left alone for picker depts (legacy data is preserved). */
            db_execute(
                'DELETE FROM student_selected_games WHERE student_id = ?',
                [$meId], 'i'
            );
            $ins_sql = 'INSERT INTO student_selected_games (student_id, game_code) VALUES (?, ?)';
            $ins_type = 'is';
            foreach ($posted_games as $gc) {
                db_execute($ins_sql, [$meId, $gc], $ins_type);
            }

            db_execute(
                'UPDATE students SET
                    achievements = ?,
                    form_step = GREATEST(COALESCE(form_step, 0), 4)
                 WHERE id = ?',
                [
                    $achievements !== '' ? $achievements : null,
                    $meId,
                ],
                'si'
            );

            flash_set('student_dashboard_ok', 'Sports info saved.', 'success');
            redirect('student-dashboard.php?step=4');
        }

        /* Legacy mode — free-text sport_1/sport_2/achievements. */
        $sport_1      = trim((string)($_POST['sport_1']      ?? ''));
        $sport_2      = trim((string)($_POST['sport_2']      ?? ''));

        if ($sport_1 === '') $errors[] = 'Please enter your primary sport.';

        if ($errors) {
            flash_set('student_dashboard_err', implode(' ', $errors), 'error');
            redirect('student-dashboard.php?step=3');
        }

        $sql = 'UPDATE students SET
                    sport_1 = ?, sport_2 = ?, achievements = ?,
                    form_step = GREATEST(COALESCE(form_step, 0), 4)
                WHERE id = ?';
        $params = [
            $sport_1,
            $sport_2      !== '' ? $sport_2      : null,
            $achievements !== '' ? $achievements : null,
            $meId,
        ];
        $types = 'sssi';

        db_execute($sql, $params, $types);

        flash_set('student_dashboard_ok', 'Sports info saved.', 'success');
        redirect('student-dashboard.php?step=4');
    }

    if ($step === 4) {
        /* Played history — gated by has_played_in_college (Yes/No). */
        $raw = $_POST['has_played_in_college'] ?? null;
        if ($raw === null || $raw === '' || !in_array((string)$raw, ['0', '1'], true)) {
            flash_set('student_dashboard_err', 'Please choose whether you have played any sports representing this college.', 'error');
            redirect('student-dashboard.php?step=4');
        }
        $has_played = (int)$raw;
        $sports_history = trim((string)($_POST['sports_history'] ?? ''));

        // If the student says "No", always wipe any previous history.
        // If "Yes", store the typed text (or NULL if they left it blank).
        $history_to_store = $has_played === 1
            ? ($sports_history !== '' ? $sports_history : null)
            : null;

        $sql = 'UPDATE students SET
                    has_played_in_college = ?,
                    sports_history = ?,
                    form_step = GREATEST(COALESCE(form_step, 0), 5)
                WHERE id = ?';
        $params = [
            $has_played,
            $history_to_store,
            $meId,
        ];
        $types = 'isi';

        db_execute($sql, $params, $types);

        $msg = $has_played === 1
            ? 'Played history saved.'
            : 'No prior college-level sports recorded. You can update this later.';
        flash_set('student_dashboard_ok', $msg, 'success');
        redirect('student-dashboard.php?step=5');
    }
}

/* =================================================================
 * Mode 2 — Document upload (step=5)
 * Returns JSON; called via fetch().
 * ================================================================= */
function handle_doc_upload(int $meId): void
{
    header('Content-Type: application/json; charset=utf-8');

    /* Allow either doc_<id> upload or plain "doc" without an id (rejected). */
    $req_id = 0;
    foreach ($_FILES as $key => $file_data) {
        if (str_starts_with($key, 'doc_')) {
            $req_id = (int)substr($key, 4);
            break;
        }
    }
    if ($req_id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'no_doc_field']);
        return;
    }

    /* Verify the requirement belongs to this student's dept */
    $req = db_one(
        'SELECT dr.id, dr.document_name, dr.allowed_mime_types, d.id AS dept_id
           FROM dept_document_requirements dr
           JOIN students s ON s.department_id = dr.department_id
           JOIN departments d ON d.id = dr.department_id
          WHERE dr.id = ? AND s.id = ?
          LIMIT 1',
        [$req_id, $meId], 'ii'
    );
    if (!$req) {
        echo json_encode(['ok' => false, 'error' => 'invalid_requirement']);
        return;
    }

    $allowed = array_values(array_filter(array_map('trim', explode(',', (string)($req['allowed_mime_types'] ?? 'application/pdf,image/jpeg,image/png')))));
    if (!$allowed) $allowed = ['application/pdf', 'image/jpeg', 'image/png'];

    $up = handle_generic_document_upload('documents', $_FILES[key($_FILES)] ?? null, $allowed, 1024);
    if (!$up['ok']) {
        $err = $up['error'] ?? 'upload_failed';
        $isPhoto = in_array('image/jpeg', $allowed, true) && !in_array('application/pdf', $allowed, true);
        if ($err === 'too_large') {
            echo json_encode(['ok' => false, 'error' => 'too_large',
                'message' => 'File is too large. Maximum allowed size is 1 MB. Please compress and try again.']);
            return;
        }
        if ($err === 'bad_mime' || $err === 'bad_extension') {
            $msg = $isPhoto
                ? 'Only JPEG/JPG files are accepted for the photo. Please upload a JPEG.'
                : 'Only PDF files are accepted for this document. Please upload a PDF.';
            echo json_encode(['ok' => false, 'error' => $err, 'message' => $msg]);
            return;
        }
        echo json_encode(['ok' => false, 'error' => $err]);
        return;
    }

    /* Remove existing record + file for this req (replace semantics) */
    $old_docs = db_select(
        'SELECT id, file_path FROM student_documents WHERE student_id = ? AND requirement_id = ?',
        [$meId, $req_id], 'ii'
    );
    if ($old_docs) {
        foreach ($old_docs as $old) {
            @unlink(__DIR__ . '/' . $old['file_path']);
        }
        db_execute('DELETE FROM student_documents WHERE student_id = ? AND requirement_id = ?',
            [$meId, $req_id], 'ii');
    }

    db_insert(
        'INSERT INTO student_documents (student_id, requirement_id, file_path) VALUES (?,?,?)',
        [$meId, $req_id, $up['path']], 'iis'
    );

    /* If this requirement is the student's Passport-size Photo, also mirror
       the same path into students.photo_path so faculty avatar views (which
       read students.photo_path) keep working. The student's Step 1 no longer
       carries a photo upload — the document requirement is the single source. */
    if ((string)($req['document_name'] ?? '') === 'Passport-size Photo') {
        db_execute(
            'UPDATE students SET photo_path = ? WHERE id = ?',
            [$up['path'], $meId], 'si'
        );
    }

    echo json_encode(['ok' => true, 'path' => $up['path']]);
}

/* =================================================================
 * Mode 3 — Document delete (step=5&delete=<doc_id>)
 * Returns JSON; called via fetch().
 * ================================================================= */
function handle_doc_delete(int $meId, int $docId): void
{
    header('Content-Type: application/json; charset=utf-8');

    $doc = db_one(
        'SELECT sd.id, sd.file_path, dr.document_name
           FROM student_documents sd
           JOIN dept_document_requirements dr ON dr.id = sd.requirement_id
          WHERE sd.id = ? AND sd.student_id = ?',
        [$docId, $meId], 'ii'
    );
    if (!$doc) {
        echo json_encode(['ok' => false, 'error' => 'not_found']);
        return;
    }
    @unlink(__DIR__ . '/' . $doc['file_path']);
    db_execute('DELETE FROM student_documents WHERE id = ?', [$docId], 'i');

    /* If the deleted doc was the student's Passport-size Photo, also clear
       students.photo_path so the avatar doesn't keep showing a missing file. */
    if ((string)($doc['document_name'] ?? '') === 'Passport-size Photo') {
        db_execute(
            'UPDATE students SET photo_path = NULL WHERE id = ? AND photo_path = ?',
            [$meId, $doc['file_path']], 'is'
        );
    }

    echo json_encode(['ok' => true]);
}

/* =================================================================
 * Mode 4 — Final submit (finalize=1)
 * Marks form_submitted_at = NOW() and form_step = 6.
 * ================================================================= */
function handle_finalize(int $meId): void
{
    db_execute(
        'UPDATE students
            SET form_submitted_at = NOW(),
                form_step         = 6
          WHERE id = ?',
        [$meId], 'i'
    );
    flash_set('student_dashboard_ok',
        'Profile submitted to your faculty. You can still edit any section and re-submit.',
        'success');
    redirect('student-dashboard.php?step=6');
}
