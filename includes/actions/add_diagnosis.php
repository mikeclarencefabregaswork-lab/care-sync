<?php
// ============================================================
// includes/actions/add_diagnosis.php
// Handler for action=add_diagnosis
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

function redirect_with_flash(string $url, string $type, string $msg): never
{
    $_SESSION[$type === 'success' ? 'flash_success' : 'flash_error'] = $msg;
    header('Location: ' . $url);
    exit;
}

function handle_add_diagnosis(PDO $db): never
{
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
