<?php
// ============================================================
// process_action.php  —  Centralised POST form handler
//
// All form submissions route here. Each "action" is handled
// in its own clearly delimited block. Every query uses PDO
// prepared statements. CSRF is verified on every request.
// After processing, the script redirects (PRG pattern) to
// prevent duplicate submission on browser refresh.
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php'; // ensure csrf_verify is loaded

session_start_secure();

// ── Only accept POST ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// ── Must be logged in ────────────────────────────────────────
require_login();

// ── CSRF verification (applies to ALL actions) ───────────────
if (!csrf_verify()) {
    http_response_code(403);
    die('Invalid security token. Please go back and try again.');
}

$db     = get_db();
$action = filter_input(INPUT_POST, 'action', FILTER_DEFAULT) ?? '';

// ── Helper: set flash and redirect ──────────────────────────
function redirect_with_flash(string $url, string $type, string $msg): never
{
    $_SESSION[$type === 'success' ? 'flash_success' : 'flash_error'] = $msg;
    header('Location: ' . $url);
    exit;
}

// ════════════════════════════════════════════════════════════
// ACTION: add_vitals  (Doctor only)
// ════════════════════════════════════════════════════════════
if ($action === 'add_vitals') {
    require_role('doctor');

    $patient_id = (int) filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    $redirect   = "patient_view.php?id={$patient_id}";

    if ($patient_id <= 0) {
        redirect_with_flash($redirect, 'error', 'Invalid patient ID.');
    }

    // Verify patient exists
    $chk = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'patient' LIMIT 1");
    $chk->execute([$patient_id]);
    if (!$chk->fetch()) {
        redirect_with_flash($redirect, 'error', 'Patient not found.');
    }

    // Sanitise & validate individual fields (all optional, at least one required)
    $bp   = trim(filter_input(INPUT_POST, 'blood_pressure', FILTER_DEFAULT)  ?? '');
    $hr   = filter_input(INPUT_POST, 'heart_rate',         FILTER_VALIDATE_INT,   ['options' => ['min_range' => 1, 'max_range' => 300]]) ?: null;
    $temp = filter_input(INPUT_POST, 'temperature',        FILTER_VALIDATE_FLOAT) ?: null;
    $wt   = filter_input(INPUT_POST, 'weight_kg',          FILTER_VALIDATE_FLOAT) ?: null;
    $ht   = filter_input(INPUT_POST, 'height_cm',          FILTER_VALIDATE_FLOAT) ?: null;
    $spo2 = filter_input(INPUT_POST, 'oxygen_saturation',  FILTER_VALIDATE_INT,   ['options' => ['min_range' => 1, 'max_range' => 100]]) ?: null;
    $notes = trim(filter_input(INPUT_POST, 'notes', FILTER_DEFAULT) ?? '');

    // Validate blood pressure format if provided
    if ($bp !== '' && !preg_match('/^\d{2,3}\/\d{2,3}$/', $bp)) {
        redirect_with_flash($redirect, 'error', 'Blood pressure must be in format "120/80".');
    }

    // At least one vital must be provided
    if ($bp === '' && $hr === null && $temp === null && $wt === null && $ht === null && $spo2 === null) {
        redirect_with_flash($redirect, 'error', 'Please enter at least one vital measurement.');
    }

    $stmt = $db->prepare(
        "INSERT INTO vitals
            (patient_id, recorded_by, blood_pressure, heart_rate, temperature,
             weight_kg, height_cm, oxygen_saturation, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $patient_id,
        current_user_id(),
        $bp ?: null,
        $hr,
        $temp,
        $wt,
        $ht,
        $spo2,
        $notes ?: null,
    ]);

    redirect_with_flash($redirect, 'success', 'Vitals recorded successfully.');
}

// ════════════════════════════════════════════════════════════
// ACTION: add_allergy  (Doctor only)
// ════════════════════════════════════════════════════════════
if ($action === 'add_allergy') {
    require_role('doctor');

    $patient_id = (int) filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    $redirect   = "patient_view.php?id={$patient_id}";

    $allergen  = trim(filter_input(INPUT_POST, 'allergen',  FILTER_DEFAULT) ?? '');
    $reaction  = trim(filter_input(INPUT_POST, 'reaction',  FILTER_DEFAULT) ?? '');
    $severity  = filter_input(INPUT_POST, 'severity',  FILTER_DEFAULT) ?? 'mild';

    $allowed_severities = ['mild', 'moderate', 'severe'];
    if (!in_array($severity, $allowed_severities, true)) {
        $severity = 'mild';
    }

    if (empty($allergen)) {
        redirect_with_flash($redirect, 'error', 'Allergen name is required.');
    }
    if (strlen($allergen) > 150) {
        redirect_with_flash($redirect, 'error', 'Allergen name is too long (max 150 characters).');
    }

    $stmt = $db->prepare(
        "INSERT INTO allergies (patient_id, allergen, reaction, severity, added_by)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $patient_id,
        $allergen,
        $reaction ?: null,
        $severity,
        current_user_id(),
    ]);

    redirect_with_flash($redirect, 'success', "Allergy '{$allergen}' added successfully.");
}

// ════════════════════════════════════════════════════════════
// ACTION: add_diagnosis  (Doctor only)
// ════════════════════════════════════════════════════════════
if ($action === 'add_diagnosis') {
    require_role('doctor');

    $patient_id = (int) filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    $redirect   = "patient_view.php?id={$patient_id}";

    $title       = trim(filter_input(INPUT_POST, 'title',       FILTER_DEFAULT) ?? '');
    $icd_code    = trim(filter_input(INPUT_POST, 'icd_code',    FILTER_DEFAULT) ?? '');
    $description = trim(filter_input(INPUT_POST, 'description', FILTER_DEFAULT) ?? '');
    $status      = filter_input(INPUT_POST, 'status', FILTER_DEFAULT) ?? 'active';
    $diagnosed_on = filter_input(INPUT_POST, 'diagnosed_on', FILTER_DEFAULT) ?? '';

    $allowed_statuses = ['active', 'resolved', 'chronic'];
    if (!in_array($status, $allowed_statuses, true)) {
        $status = 'active';
    }

    if (empty($title)) {
        redirect_with_flash($redirect, 'error', 'Diagnosis title is required.');
    }
    if (strlen($title) > 200) {
        redirect_with_flash($redirect, 'error', 'Diagnosis title is too long (max 200 characters).');
    }

    // Validate date
    $valid_date = null;
    if ($diagnosed_on !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $diagnosed_on);
        if ($d && $d->format('Y-m-d') === $diagnosed_on) {
            $valid_date = $diagnosed_on;
        }
    }

    // Validate ICD code format loosely (letters+digits, max 10 chars)
    if ($icd_code !== '' && !preg_match('/^[A-Z0-9.\-]{1,10}$/i', $icd_code)) {
        redirect_with_flash($redirect, 'error', 'ICD code appears to be in an invalid format.');
    }

    $stmt = $db->prepare(
        "INSERT INTO diagnoses
            (patient_id, doctor_id, icd_code, title, description, diagnosed_on, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $patient_id,
        current_user_id(),
        $icd_code  ?: null,
        $title,
        $description ?: null,
        $valid_date,
        $status,
    ]);

    redirect_with_flash($redirect, 'success', "Diagnosis '{$title}' added successfully.");
}

// ════════════════════════════════════════════════════════════
// ACTION: save_care_plan  (Doctor only)
// Creates or fully replaces the patient's care plan + tasks.
// ════════════════════════════════════════════════════════════
if ($action === 'save_care_plan') {
    require_role('doctor');

    $patient_id = (int) filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    $redirect   = "patient_view.php?id={$patient_id}";

    // Core plan fields
    $plan_title     = trim(filter_input(INPUT_POST, 'plan_title',      FILTER_DEFAULT) ?? '');
    $goals          = trim(filter_input(INPUT_POST, 'goals',           FILTER_DEFAULT) ?? '');
    $diet_notes     = trim(filter_input(INPUT_POST, 'diet_notes',      FILTER_DEFAULT) ?? '');
    $exercise_notes = trim(filter_input(INPUT_POST, 'exercise_notes',  FILTER_DEFAULT) ?? '');
    $start_date     = filter_input(INPUT_POST, 'start_date',   FILTER_DEFAULT) ?? '';
    $review_date    = filter_input(INPUT_POST, 'review_date',  FILTER_DEFAULT) ?? '';

    if (empty($plan_title)) {
        redirect_with_flash($redirect, 'error', 'Care plan title is required.');
    }

    // Validate dates
    $clean_start  = null;
    $clean_review = null;
    foreach ([[$start_date, &$clean_start], [$review_date, &$clean_review]] as [$raw, &$clean]) {
        if ($raw !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $raw);
            if ($d && $d->format('Y-m-d') === $raw) {
                $clean = $raw;
            }
        }
    }

    // Tasks array
    $raw_tasks = $_POST['tasks'] ?? [];
    if (!is_array($raw_tasks)) {
        $raw_tasks = [];
    }

    // Filter out empty task rows
    $clean_tasks = [];
    $allowed_types = ['medication', 'exercise', 'diet', 'lifestyle', 'other'];
    foreach ($raw_tasks as $i => $t) {
        $desc = trim($t['description'] ?? '');
        if ($desc === '') {
            continue;  // skip blank rows
        }
        $type = in_array($t['type'] ?? '', $allowed_types, true) ? $t['type'] : 'other';
        $clean_tasks[] = [
            'type'            => $type,
            'description'     => substr($desc, 0, 300),
            'medication_name' => substr(trim($t['medication_name'] ?? ''), 0, 150),
            'dosage'          => substr(trim($t['dosage']          ?? ''), 0, 100),
            'frequency'       => substr(trim($t['frequency']       ?? ''), 0, 100),
            'sort_order'      => (int) $i,
        ];
    }

    // Run in a transaction: upsert plan → delete old tasks → insert new tasks
    $db->beginTransaction();
    try {
        // Check if plan exists
        $exists = $db->prepare("SELECT id FROM care_plans WHERE patient_id = ? LIMIT 1");
        $exists->execute([$patient_id]);
        $existing_plan = $exists->fetch();

        if ($existing_plan) {
            // UPDATE
            $upd = $db->prepare(
                "UPDATE care_plans
                 SET doctor_id = ?, title = ?, goals = ?, diet_notes = ?,
                     exercise_notes = ?, start_date = ?, review_date = ?
                 WHERE patient_id = ?"
            );
            $upd->execute([
                current_user_id(),
                $plan_title,
                $goals ?: null,
                $diet_notes ?: null,
                $exercise_notes ?: null,
                $clean_start,
                $clean_review,
                $patient_id,
            ]);
            $plan_id = $existing_plan['id'];

            // Delete all existing tasks so we can re-insert
            $db->prepare("DELETE FROM care_plan_tasks WHERE care_plan_id = ?")->execute([$plan_id]);
        } else {
            // INSERT
            $ins = $db->prepare(
                "INSERT INTO care_plans
                    (patient_id, doctor_id, title, goals, diet_notes, exercise_notes, start_date, review_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $ins->execute([
                $patient_id,
                current_user_id(),
                $plan_title,
                $goals ?: null,
                $diet_notes ?: null,
                $exercise_notes ?: null,
                $clean_start,
                $clean_review,
            ]);
            $plan_id = (int) $db->lastInsertId();
        }

        // Insert tasks
        if (!empty($clean_tasks)) {
            $task_ins = $db->prepare(
                "INSERT INTO care_plan_tasks
                    (care_plan_id, task_type, description, medication_name, dosage, frequency, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            foreach ($clean_tasks as $ct) {
                $task_ins->execute([
                    $plan_id,
                    $ct['type'],
                    $ct['description'],
                    $ct['medication_name'] ?: null,
                    $ct['dosage']          ?: null,
                    $ct['frequency']       ?: null,
                    $ct['sort_order'],
                ]);
            }
        }

        $db->commit();
        $verb = $existing_plan ? 'updated' : 'created';
        redirect_with_flash($redirect, 'success', "Care plan {$verb} successfully with " . count($clean_tasks) . " task(s).");
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('save_care_plan error: ' . $e->getMessage());
        redirect_with_flash($redirect, 'error', 'Failed to save care plan due to a server error. Please try again.');
    }
}

/**
 * ════════════════════════════════════════════════════════════
 * ACTION: update_patient_profile  (Patient only)
 * Updates patient profile fields stored in patient_profiles.
 * ───────────────────────────────────────────────────────────
 * Allowed fields:
 *  - date_of_birth (DATE or empty)
 *  - gender (ENUM values or empty)
 *  - blood_type (ENUM values or empty -> Unknown)
 *  - phone (VARCHAR 30)
 *  - address (TEXT)
 *  - emergency_contact_name (VARCHAR 120)
 *  - emergency_contact_phone (VARCHAR 30)
 * ════════════════════════════════════════════════════════════
 */
if ($action === 'update_patient_profile') {
    require_role('patient');

    $patient_id = current_user_id();
    $redirect   = 'patient_dashboard.php';

    // Validate date (allow empty)
    $dob_raw = trim((string) ($_POST['date_of_birth'] ?? ''));
    $dob = null;
    if ($dob_raw !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $dob_raw);
        if ($d && $d->format('Y-m-d') === $dob_raw) {
            $dob = $dob_raw;
        } else {
            redirect_with_flash($redirect, 'error', 'Invalid date of birth.');
        }
    }

    // Validate gender ENUM (allow empty)
    $gender_raw = trim((string) ($_POST['gender'] ?? ''));
    $allowed_genders = ['male', 'female', 'non-binary', 'prefer_not_to_say', ''];
    if (!in_array($gender_raw, $allowed_genders, true)) {
        $gender_raw = '';
    }
    $gender = $gender_raw !== '' ? $gender_raw : null;

    // Validate blood_type ENUM (allow empty -> Unknown)
    $blood_raw = trim((string) ($_POST['blood_type'] ?? ''));
    $allowed_blood = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown', ''];
    if (!in_array($blood_raw, $allowed_blood, true)) {
        $blood_raw = 'Unknown';
    }
    $blood_type = $blood_raw !== '' ? $blood_raw : 'Unknown';

    // Validate phone fields (VARCHAR 30) — allow empty
    $phone_raw = trim((string) ($_POST['phone'] ?? ''));
    if (mb_strlen($phone_raw) > 30) {
        redirect_with_flash($redirect, 'error', 'Phone is too long (max 30 characters).');
    }
    $phone = $phone_raw !== '' ? $phone_raw : null;

    $ec_name_raw = trim((string) ($_POST['emergency_contact_name'] ?? ''));
    if (mb_strlen($ec_name_raw) > 120) {
        redirect_with_flash($redirect, 'error', 'Emergency contact name is too long (max 120 characters).');
    }
    $ec_name = $ec_name_raw !== '' ? $ec_name_raw : null;

    $ec_phone_raw = trim((string) ($_POST['emergency_contact_phone'] ?? ''));
    if (mb_strlen($ec_phone_raw) > 30) {
        redirect_with_flash($redirect, 'error', 'Emergency contact phone is too long (max 30 characters).');
    }
    $ec_phone = $ec_phone_raw !== '' ? $ec_phone_raw : null;

    // Address (TEXT) — allow empty
    $address_raw = trim((string) ($_POST['address'] ?? ''));
    // no strict max here; TEXT in MySQL is large. Keep it bounded defensively.
    if (mb_strlen($address_raw) > 5000) {
        redirect_with_flash($redirect, 'error', 'Address is too long.');
    }
    $address = $address_raw !== '' ? $address_raw : null;

    // Ensure patient row exists; then update or insert.
    $exists = $db->prepare("SELECT id FROM patient_profiles WHERE user_id = ? LIMIT 1");
    $exists->execute([$patient_id]);
    $row = $exists->fetch();

    if ($row) {
        $upd = $db->prepare(
            "UPDATE patient_profiles
             SET date_of_birth = ?, gender = ?, phone = ?, address = ?, blood_type = ?,
                 emergency_contact_name = ?, emergency_contact_phone = ?
             WHERE user_id = ?"
        );
        $upd->execute([
            $dob,
            $gender,
            $phone,
            $address,
            $blood_type,
            $ec_name,
            $ec_phone,
            $patient_id,
        ]);
    } else {
        $ins = $db->prepare(
            "INSERT INTO patient_profiles
                (user_id, date_of_birth, gender, phone, address, blood_type,
                 emergency_contact_name, emergency_contact_phone)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $ins->execute([
            $patient_id,
            $dob,
            $gender,
            $phone,
            $address,
            $blood_type,
            $ec_name,
            $ec_phone,
        ]);
    }

    redirect_with_flash($redirect, 'success', 'Profile updated successfully.');
}

// ════════════════════════════════════════════════════════════
// ACTION: toggle_task  (Patient only)
// Marks or unmarks a daily care-plan task as complete.
// ════════════════════════════════════════════════════════════
if ($action === 'toggle_task') {
    require_role('patient');

    $patient_id = current_user_id();   // always use session, never trust POST for patient_id
    $task_id    = (int) filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
    $is_done    = filter_input(INPUT_POST, 'is_done', FILTER_VALIDATE_INT);  // '1' = currently done → undo
    $today      = date('Y-m-d');

    $redirect = 'patient_dashboard.php';

    if ($task_id <= 0) {
        redirect_with_flash($redirect, 'error', 'Invalid task.');
    }

    // Verify the task belongs to this patient's care plan (security check)
    $verify = $db->prepare(
        "SELECT cpt.id
         FROM care_plan_tasks cpt
         JOIN care_plans cp ON cp.id = cpt.care_plan_id
         WHERE cpt.id = ? AND cp.patient_id = ?
         LIMIT 1"
    );
    $verify->execute([$task_id, $patient_id]);
    if (!$verify->fetch()) {
        redirect_with_flash($redirect, 'error', 'Task not found or not assigned to you.');
    }

    if ($is_done) {
        // Remove completion (toggle off)
        $del = $db->prepare(
            "DELETE FROM task_completions
             WHERE task_id = ? AND patient_id = ? AND completed_on = ?"
        );
        $del->execute([$task_id, $patient_id, $today]);
    } else {
        // Insert completion (toggle on) — ignore duplicate via UNIQUE key
        $ins = $db->prepare(
            "INSERT IGNORE INTO task_completions (task_id, patient_id, completed_on)
             VALUES (?, ?, ?)"
        );
        $ins->execute([$task_id, $patient_id, $today]);
    }

    redirect_with_flash($redirect, 'success', $is_done ? 'Task unmarked.' : 'Task marked as complete! ✅');
}

/**
 * ── Unknown action fallback ──────────────────────────────────
 * Redirect to a safe place based on role to avoid always bouncing to login.
 */
$role = $_SESSION['user_role'] ?? '';
$dest = ($role === 'doctor') ? 'doctor_dashboard.php' : 'patient_dashboard.php';
redirect_with_flash($dest, 'error', 'Unknown action requested.');
