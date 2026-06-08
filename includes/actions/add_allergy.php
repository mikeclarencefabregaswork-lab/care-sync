<?php
// ============================================================
// includes/actions/add_allergy.php
// Handler for action=add_allergy
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

function redirect_with_flash(string $url, string $type, string $msg): never
{
    $_SESSION[$type === 'success' ? 'flash_success' : 'flash_error'] = $msg;
    header('Location: ' . $url);
    exit;
}

function handle_add_allergy(PDO $db): never
{
    require_role('doctor');

    $patient_id = (int) filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    $redirect   = "patient_view.php?id={$patient_id}";

    $allergen  = trim(filter_input(INPUT_POST, 'allergen', FILTER_DEFAULT) ?? '');
    $reaction  = trim(filter_input(INPUT_POST, 'reaction', FILTER_DEFAULT) ?? '');
    $severity  = filter_input(INPUT_POST, 'severity', FILTER_DEFAULT) ?? 'mild';

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
?>
