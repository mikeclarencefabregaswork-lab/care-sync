<?php
// ============================================================
// includes/actions/save_care_plan.php
// Handler for action=save_care_plan
// Creates or fully replaces the patient's care plan + tasks
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

function redirect_with_flash(string $url, string $type, string $msg): never
{
    $_SESSION[$type === 'success' ? 'flash_success' : 'flash_error'] = $msg;
    header('Location: ' . $url);
    exit;
}

function handle_save_care_plan(PDO $db): never
{
    require_role('doctor');

    $patient_id = (int) filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    $redirect   = "patient_view.php?id={$patient_id}";

    // Core plan fields
    $plan_title     = trim(filter_input(INPUT_POST, 'plan_title',      FILTER_DEFAULT) ?? '');
    $goals          = trim(filter_input(INPUT_POST, 'goals',           FILTER_DEFAULT) ?? '');
    $diet_notes     = trim(filter_input(INPUT_POST, 'diet_notes',      FILTER_DEFAULT) ?? '');
    $exercise_notes = trim(filter_input(INPUT_POST, 'exercise_notes',  FILTER_DEFAULT) ?? '');
    $start_date     = filter_input(INPUT_POST, 'start_date',        FILTER_DEFAULT) ?? '';
    $review_date    = filter_input(INPUT_POST, 'review_date',       FILTER_DEFAULT) ?? '';

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
            continue;
        }

        $type = in_array($t['type'] ?? '', $allowed_types, true) ? ($t['type'] ?? '') : 'other';

        $clean_tasks[] = [
            'type'            => $type,
            'description'     => substr($desc, 0, 300),
            'medication_name' => substr(trim($t['medication_name'] ?? ''), 0, 150),
            'dosage'          => substr(trim($t['dosage'] ?? ''), 0, 100),
            'frequency'       => substr(trim($t['frequency'] ?? ''), 0, 100),
            'sort_order'      => (int) $i,
        ];
    }

    // Run in a transaction: upsert plan → delete old tasks → insert new tasks
    $db->beginTransaction();
    try {
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

            $plan_id = (int) $existing_plan['id'];

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
                    $ct['dosage'] ?: null,
                    $ct['frequency'] ?: null,
                    $ct['sort_order'],
                ]);
            }
        }

        $db->commit();

        $verb = $existing_plan ? 'updated' : 'created';
        redirect_with_flash(
            $redirect,
            'success',
            "Care plan {$verb} successfully with " . count($clean_tasks) . " task(s)."
        );
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('save_care_plan error: ' . $e->getMessage());
        redirect_with_flash($redirect, 'error', 'Failed to save care plan due to a server error. Please try again.');
    }
}
?>
