<?php
// ============================================================
// includes/actions/add_vitals.php
// Handler for action=add_vitals
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

// Match redirect helper behavior from process_action.php (PRG + flash)
function redirect_with_flash(string $url, string $type, string $msg): never
{
    $_SESSION[$type === 'success' ? 'flash_success' : 'flash_error'] = $msg;
    header('Location: ' . $url);
    exit;
}

function handle_add_vitals(PDO $db): never
{
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

    $bp   = trim(filter_input(INPUT_POST, 'blood_pressure', FILTER_DEFAULT)  ?? '');
    $hr   = filter_input(INPUT_POST, 'heart_rate',         FILTER_VALIDATE_INT,   ['options' => ['min_range' => 1, 'max_range' => 300]]) ?: null;
    $temp = filter_input(INPUT_POST, 'temperature',        FILTER_VALIDATE_FLOAT) ?: null;
    $wt   = filter_input(INPUT_POST, 'weight_kg',          FILTER_VALIDATE_FLOAT) ?: null;
    $ht   = filter_input(INPUT_POST, 'height_cm',          FILTER_VALIDATE_FLOAT) ?: null;
    $spo2 = filter_input(INPUT_POST, 'oxygen_saturation',  FILTER_VALIDATE_INT,   ['options' => ['min_range' => 1, 'max_range' => 100]]) ?: null;
    $notes = trim(filter_input(INPUT_POST, 'notes', FILTER_DEFAULT) ?? '');

    if ($bp !== '' && !preg_match('/^\d{2,3}\/\d{2,3}$/', $bp)) {
        redirect_with_flash($redirect, 'error', 'Blood pressure must be in format "120/80".');
    }

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
?>
