<?php
// ============================================================
// patient_view.php  —  Doctor's detailed view of a patient
//                      EHR management + Care Plan builder
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

require_role('doctor');

$db        = get_db();
$doctor_id = current_user_id();

// ── Validate patient ID ──────────────────────────────────────
$patient_id = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($patient_id <= 0) {
    header('Location: doctor_dashboard.php');
    exit;
}

// ── Fetch patient (must be role=patient) ────────────────────
$stmt = $db->prepare(
    "SELECT u.id, u.full_name, u.email, u.created_at,
            pp.date_of_birth, pp.gender, pp.phone, pp.address,
            pp.blood_type, pp.emergency_contact_name, pp.emergency_contact_phone
     FROM users u
     LEFT JOIN patient_profiles pp ON pp.user_id = u.id
     WHERE u.id = ? AND u.role = 'patient'
     LIMIT 1"
);
$stmt->execute([$patient_id]);
$patient = $stmt->fetch();

if (!$patient) {
    header('Location: doctor_dashboard.php?error=Patient+not+found');
    exit;
}

// ── Fetch vitals (latest first) ──────────────────────────────
$vitals_stmt = $db->prepare(
    "SELECT v.*, u.full_name AS recorded_by_name
     FROM vitals v
     JOIN users u ON u.id = v.recorded_by
     WHERE v.patient_id = ?
     ORDER BY v.recorded_at DESC
     LIMIT 10"
);
$vitals_stmt->execute([$patient_id]);
$vitals = $vitals_stmt->fetchAll();

// ── Fetch allergies ──────────────────────────────────────────
$allergy_stmt = $db->prepare(
    "SELECT * FROM allergies WHERE patient_id = ? ORDER BY severity DESC, allergen ASC"
);
$allergy_stmt->execute([$patient_id]);
$allergies = $allergy_stmt->fetchAll();

// ── Fetch diagnoses ──────────────────────────────────────────
$diag_stmt = $db->prepare(
    "SELECT d.*, u.full_name AS doctor_name
     FROM diagnoses d
     JOIN users u ON u.id = d.doctor_id
     WHERE d.patient_id = ?
     ORDER BY d.diagnosed_on DESC"
);
$diag_stmt->execute([$patient_id]);
$diagnoses = $diag_stmt->fetchAll();

// ── Fetch existing care plan + tasks ────────────────────────
$cp_stmt = $db->prepare(
    "SELECT cp.*, u.full_name AS doctor_name
     FROM care_plans cp
     JOIN users u ON u.id = cp.doctor_id
     WHERE cp.patient_id = ?
     LIMIT 1"
);
$cp_stmt->execute([$patient_id]);
$care_plan = $cp_stmt->fetch();

$tasks = [];
if ($care_plan) {
    $t_stmt = $db->prepare(
        "SELECT * FROM care_plan_tasks WHERE care_plan_id = ? ORDER BY sort_order ASC, id ASC"
    );
    $t_stmt->execute([$care_plan['id']]);
    $tasks = $t_stmt->fetchAll();
}

// ── Flash messages ───────────────────────────────────────────
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Helpers ──────────────────────────────────────────────────
$severity_classes = [
    'mild'     => 'bg-yellow-50 text-yellow-700 border-yellow-200',
    'moderate' => 'bg-orange-50 text-orange-700 border-orange-200',
    'severe'   => 'bg-red-50   text-red-700   border-red-200',
];
$status_classes = [
    'active'   => 'bg-amber-50  text-amber-700',
    'chronic'  => 'bg-red-50    text-red-700',
    'resolved' => 'bg-emerald-50 text-emerald-700',
];
$task_type_icons = [
    'medication' => '💊',
    'exercise'   => '🏃',
    'diet'       => '🥗',
    'lifestyle'  => '🌿',
    'other'      => '📋',
];

$age = $patient['date_of_birth']
    ? (new DateTime($patient['date_of_birth']))->diff(new DateTime())->y . ' yrs'
    : '—';

$page_title = 'Patient: ' . $patient['full_name'];
require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Breadcrumb ───────────────────────────────────────────── -->
<nav class="flex items-center gap-2 text-sm text-slate-400 mb-5">
    <a href="doctor_dashboard.php" class="hover:text-brand-600 transition">Dashboard</a>
    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-slate-600 font-500"><?= e($patient['full_name']) ?></span>
</nav>

<?php require_once __DIR__ . '/includes/flash.php'; ?>

<!-- ── Patient hero card ─────────────────────────────────────── -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 mb-6">
    <div class="flex flex-col md:flex-row md:items-center gap-5">
        <!-- Avatar -->
        <div class="w-16 h-16 rounded-2xl bg-brand-600 flex items-center justify-center text-white text-2xl font-700 flex-shrink-0 shadow">
            <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
        </div>

        <!-- Core info -->
        <div class="flex-1">
            <h2 class="text-xl font-700 text-slate-800"><?= e($patient['full_name']) ?></h2>
            <p class="text-sm text-slate-400 mt-0.5"><?= e($patient['email']) ?></p>
            <div class="flex flex-wrap gap-3 mt-3">
                <span class="inline-flex items-center gap-1.5 text-xs text-slate-500 bg-slate-50 border border-slate-100 rounded-lg px-3 py-1.5">
                    🎂 Age: <strong><?= e($age) ?></strong>
                </span>
                <span class="inline-flex items-center gap-1.5 text-xs text-slate-500 bg-slate-50 border border-slate-100 rounded-lg px-3 py-1.5">
                    🩸 Blood Type: <strong><?= e($patient['blood_type'] ?? 'Unknown') ?></strong>
                </span>
                <span class="inline-flex items-center gap-1.5 text-xs text-slate-500 bg-slate-50 border border-slate-100 rounded-lg px-3 py-1.5">
                    ⚧ Gender: <strong><?= e(ucwords(str_replace('_', ' ', $patient['gender'] ?? 'Not set'))) ?></strong>
                </span>
                <?php if ($patient['phone']): ?>
                <span class="inline-flex items-center gap-1.5 text-xs text-slate-500 bg-slate-50 border border-slate-100 rounded-lg px-3 py-1.5">
                    📞 <?= e($patient['phone']) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Emergency contact -->
        <?php if ($patient['emergency_contact_name']): ?>
        <div class="bg-red-50 border border-red-100 rounded-xl p-3 text-sm md:text-right flex-shrink-0">
            <p class="text-xs text-red-400 font-600 uppercase tracking-wider mb-1">Emergency Contact</p>
            <p class="font-600 text-slate-700"><?= e($patient['emergency_contact_name']) ?></p>
            <p class="text-slate-500 text-xs"><?= e($patient['emergency_contact_phone'] ?? '') ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     TWO-COLUMN LAYOUT: EHR sections left, forms right
══════════════════════════════════════════════════════════ -->
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

<!-- ─────────────────────── LEFT COLUMN ──────────────────── -->
<div class="space-y-6">

    <!-- ── Latest Vitals ─────────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-600 text-slate-800 flex items-center gap-2">
                <span class="w-6 h-6 bg-rose-100 rounded-md flex items-center justify-center text-xs">❤️</span>
                Vitals History
            </h3>
            <span class="text-xs text-slate-400"><?= count($vitals) ?> record<?= count($vitals) !== 1 ? 's' : '' ?></span>
        </div>

        <?php if (empty($vitals)): ?>
            <p class="text-center text-slate-400 text-sm py-8">No vitals recorded yet.</p>
        <?php else: ?>
            <?php $v = $vitals[0]; // highlight most recent ?>
            <!-- Latest vitals grid -->
            <div class="p-5 grid grid-cols-3 gap-3 border-b border-slate-50">
                <?php
                $vital_items = [
                    ['label' => 'Blood Pressure', 'value' => $v['blood_pressure'] ?? '—', 'unit' => 'mmHg', 'icon' => '🫀'],
                    ['label' => 'Heart Rate',     'value' => $v['heart_rate']    ?? '—', 'unit' => 'bpm',  'icon' => '💓'],
                    ['label' => 'Temperature',    'value' => $v['temperature']   ?? '—', 'unit' => '°C',   'icon' => '🌡️'],
                    ['label' => 'Weight',         'value' => $v['weight_kg']     ?? '—', 'unit' => 'kg',   'icon' => '⚖️'],
                    ['label' => 'Height',         'value' => $v['height_cm']     ?? '—', 'unit' => 'cm',   'icon' => '📏'],
                    ['label' => 'SpO₂',          'value' => $v['oxygen_saturation'] ?? '—', 'unit' => '%', 'icon' => '🫁'],
                ];
                foreach ($vital_items as $item): ?>
                    <div class="bg-slate-50 rounded-xl p-3 text-center">
                        <p class="text-base mb-1"><?= $item['icon'] ?></p>
                        <p class="text-sm font-700 text-slate-800"><?= e((string)$item['value']) ?> <span class="text-xs font-400 text-slate-400"><?= $item['unit'] ?></span></p>
                        <p class="text-xs text-slate-400 mt-0.5"><?= $item['label'] ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($vitals) > 1): ?>
            <div class="px-5 pb-4">
                <p class="text-xs text-slate-400 font-500 uppercase tracking-wider mt-4 mb-2">Previous Records</p>
                <div class="space-y-2">
                    <?php foreach (array_slice($vitals, 1) as $pv): ?>
                        <div class="flex items-center justify-between text-xs text-slate-500 bg-slate-50 rounded-lg px-3 py-2">
                            <span><?= date('d M Y', strtotime($pv['recorded_at'])) ?></span>
                            <span>BP: <?= e($pv['blood_pressure'] ?? '—') ?> &nbsp;|&nbsp; HR: <?= e((string)($pv['heart_rate'] ?? '—')) ?> bpm</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- ── Allergies ─────────────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-600 text-slate-800 flex items-center gap-2">
                <span class="w-6 h-6 bg-orange-100 rounded-md flex items-center justify-center text-xs">⚠️</span>
                Allergies
            </h3>
        </div>
        <?php if (empty($allergies)): ?>
            <p class="text-center text-slate-400 text-sm py-8">No allergies on record.</p>
        <?php else: ?>
            <div class="p-4 space-y-2">
                <?php foreach ($allergies as $allergy): ?>
                    <div class="flex items-start justify-between p-3 rounded-xl border <?= $severity_classes[$allergy['severity']] ?? 'bg-slate-50 border-slate-200 text-slate-600' ?>">
                        <div>
                            <p class="font-600 text-sm"><?= e($allergy['allergen']) ?></p>
                            <?php if ($allergy['reaction']): ?>
                                <p class="text-xs mt-0.5 opacity-80"><?= e($allergy['reaction']) ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="text-xs font-600 uppercase ml-3 flex-shrink-0"><?= e($allergy['severity']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── Diagnoses / Medical History ───────────────────── -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="font-600 text-slate-800 flex items-center gap-2">
                <span class="w-6 h-6 bg-violet-100 rounded-md flex items-center justify-center text-xs">🩺</span>
                Medical History & Diagnoses
            </h3>
        </div>
        <?php if (empty($diagnoses)): ?>
            <p class="text-center text-slate-400 text-sm py-8">No diagnoses recorded yet.</p>
        <?php else: ?>
            <div class="divide-y divide-slate-50">
                <?php foreach ($diagnoses as $diag): ?>
                    <div class="px-5 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="font-600 text-slate-800 text-sm"><?= e($diag['title']) ?></p>
                                    <?php if ($diag['icd_code']): ?>
                                        <span class="font-mono text-xs bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded"><?= e($diag['icd_code']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($diag['description']): ?>
                                    <p class="text-xs text-slate-400 mt-1 leading-relaxed"><?= e($diag['description']) ?></p>
                                <?php endif; ?>
                                <p class="text-xs text-slate-400 mt-1.5">
                                    Diagnosed <?= $diag['diagnosed_on'] ? date('d M Y', strtotime($diag['diagnosed_on'])) : '—' ?>
                                    &nbsp;·&nbsp; By <?= e($diag['doctor_name']) ?>
                                </p>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-600 flex-shrink-0 <?= $status_classes[$diag['status']] ?? 'bg-slate-100 text-slate-500' ?>">
                                <?= ucfirst(e($diag['status'])) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── Care Plan Summary (read-only view) ────────────── -->
    <?php if ($care_plan): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-600 text-slate-800 flex items-center gap-2">
                <span class="w-6 h-6 bg-emerald-100 rounded-md flex items-center justify-center text-xs">📋</span>
                Current Care Plan
            </h3>
            <span class="text-xs text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-lg font-600">Active</span>
        </div>
        <div class="p-5 space-y-4">
            <div>
                <p class="text-xs font-600 text-slate-400 uppercase tracking-wider mb-1">Plan Title</p>
                <p class="font-600 text-slate-800"><?= e($care_plan['title']) ?></p>
            </div>
            <?php if ($care_plan['goals']): ?>
            <div>
                <p class="text-xs font-600 text-slate-400 uppercase tracking-wider mb-1">Goals</p>
                <p class="text-sm text-slate-600 leading-relaxed"><?= e($care_plan['goals']) ?></p>
            </div>
            <?php endif; ?>
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-slate-50 rounded-xl p-3">
                    <p class="text-xs font-600 text-slate-400 mb-1">🥗 Diet Notes</p>
                    <p class="text-xs text-slate-600 leading-relaxed"><?= e($care_plan['diet_notes'] ?: 'Not specified') ?></p>
                </div>
                <div class="bg-slate-50 rounded-xl p-3">
                    <p class="text-xs font-600 text-slate-400 mb-1">🏃 Exercise Notes</p>
                    <p class="text-xs text-slate-600 leading-relaxed"><?= e($care_plan['exercise_notes'] ?: 'Not specified') ?></p>
                </div>
            </div>

            <!-- Tasks list -->
            <?php if (!empty($tasks)): ?>
            <div>
                <p class="text-xs font-600 text-slate-400 uppercase tracking-wider mb-2">Daily Tasks (<?= count($tasks) ?>)</p>
                <div class="space-y-2">
                    <?php foreach ($tasks as $task): ?>
                        <div class="flex items-start gap-2.5 p-3 bg-slate-50 rounded-xl text-sm">
                            <span class="text-base flex-shrink-0"><?= $task_type_icons[$task['task_type']] ?? '📋' ?></span>
                            <div class="flex-1 min-w-0">
                                <p class="font-500 text-slate-700 text-xs"><?= e($task['description']) ?></p>
                                <?php if ($task['medication_name']): ?>
                                    <p class="text-xs text-slate-400 mt-0.5"><?= e($task['medication_name']) ?> &mdash; <?= e($task['dosage'] ?? '') ?></p>
                                <?php endif; ?>
                                <?php if ($task['frequency']): ?>
                                    <p class="text-xs text-brand-500 mt-0.5"><?= e($task['frequency']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /LEFT COLUMN -->

<!-- ─────────────────────── RIGHT COLUMN ─────────────────── -->
<div class="space-y-6">

    <!-- ── Add Vitals Form ───────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="font-600 text-slate-800 flex items-center gap-2">
                <span class="w-6 h-6 bg-rose-100 rounded-md flex items-center justify-center text-xs">➕</span>
                Record New Vitals
            </h3>
        </div>
        <form method="POST" action="process_action.php" class="p-5 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action"     value="add_vitals">
            <input type="hidden" name="patient_id" value="<?= $patient_id ?>">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-500 text-slate-500 mb-1">Blood Pressure</label>
                    <input type="text" name="blood_pressure" placeholder="120/80"
                           pattern="^\d{2,3}\/\d{2,3}$"
                           inputmode="numeric"
                           class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-xs font-500 text-slate-500 mb-1">Heart Rate (bpm)</label>
                    <input type="number" name="heart_rate" placeholder="72" min="20" max="250"
                           class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-xs font-500 text-slate-500 mb-1">Temperature (°C)</label>
                    <input type="number" name="temperature" placeholder="36.8" step="0.1" min="30" max="45"
                           class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-xs font-500 text-slate-500 mb-1">Weight (kg)</label>
                    <input type="number" name="weight_kg" placeholder="70.5" step="0.1"
                           class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-xs font-500 text-slate-500 mb-1">Height (cm)</label>
                    <input type="number" name="height_cm" placeholder="175" step="0.1"
                           class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-xs font-500 text-slate-500 mb-1">SpO₂ (%)</label>
                    <input type="number" name="oxygen_saturation" placeholder="98" min="50" max="100"
                           class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                </div>
            </div>
            <div>
                <label class="block text-xs font-500 text-slate-500 mb-1">Clinical Notes</label>
                <textarea name="notes" rows="2" placeholder="Observations, notes…"
                          class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-slate-50
                                 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent resize-none"></textarea>
            </div>
            <button type="submit"
                    class="w-full py-2.5 bg-rose-500 hover:bg-rose-600 text-white text-sm font-600 rounded-xl transition">
                Save Vitals
            </button>
        </form>
    </div>

    <!-- ── Add Allergy Form ──────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="font-600 text-slate-800 flex items-center gap-2">
                <span class="w-6 h-6 bg-orange-100 rounded-md flex items-center justify-center text-xs">➕</span>
                Add Allergy
            </h3>
        </div>
        <form method="POST" action="process_action.php" class="p-5 space-y-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action"     value="add_allergy">
            <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-500 text-slate-500 mb-1">Allergen *</label>
                    <input type="text" name="allergen" required placeholder="e.g. Penicillin"
                           class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-xs font-500 text-slate-500 mb-1">Severity</label>
                    <select name="severity"
                            class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-slate-50
                                   focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                        <option value="mild">Mild</option>
                        <option value="moderate">Moderate</option>
                        <option value="severe">Severe</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-500 text-slate-500 mb-1">Reaction Description</label>
                <input type="text" name="reaction" placeholder="e.g. Hives, difficulty breathing"
                       class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
            </div>
            <button type="submit"
                    class="w-full py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-600 rounded-xl transition">
                Add Allergy
            </button>
        </form>
    </div>

    <!-- ── Add Diagnosis Form ────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="font-600 text-slate-800 flex items-center gap-2">
                <span class="w-6 h-6 bg-violet-100 rounded-md flex items-center justify-center text-xs">➕</span>
                Add Diagnosis
            </h3>
        </div>
        <form method="POST" action="process_action.php" class="p-5 space-y-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action"     value="add_diagnosis">
            <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2">
                    <label class="block text-xs font-500 text-slate-500 mb-1">Diagnosis Title *</label>
                    <input type="text" name="title" required placeholder="e.g. Type 2 Diabetes Mellitus"
                           class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-xs font-500 text-slate-500 mb-1">ICD-10 Code</label>
                    <input type="text" name="icd_code" placeholder="e.g. E11"
                           class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-xs font-500 text-slate-500 mb-1">Status</label>
                    <select name="status"
                            class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-slate-50
                                   focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                        <option value="active">Active</option>
                        <option value="chronic">Chronic</option>
                        <option value="resolved">Resolved</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-500 text-slate-500 mb-1">Date Diagnosed</label>
                    <input type="date" name="diagnosed_on" value="<?= date('Y-m-d') ?>"
                           class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                </div>
            </div>
            <div>
                <label class="block text-xs font-500 text-slate-500 mb-1">Description / Notes</label>
                <textarea name="description" rows="2" placeholder="Clinical details…"
                          class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-slate-50
                                 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent resize-none"></textarea>
            </div>
            <button type="submit"
                    class="w-full py-2.5 bg-violet-600 hover:bg-violet-700 text-white text-sm font-600 rounded-xl transition">
                Save Diagnosis
            </button>
        </form>
    </div>

    <!-- ── Care Plan Builder ─────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="font-600 text-slate-800 flex items-center gap-2">
                <span class="w-6 h-6 bg-emerald-100 rounded-md flex items-center justify-center text-xs">📋</span>
                <?= $care_plan ? 'Update' : 'Create' ?> Care Plan
            </h3>
            <?php if ($care_plan): ?>
                <p class="text-xs text-slate-400 mt-0.5">Updating will replace the existing plan and all its tasks.</p>
            <?php endif; ?>
        </div>

        <form method="POST" action="process_action.php" id="care-plan-form" class="p-5 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action"     value="save_care_plan">
            <input type="hidden" name="patient_id" value="<?= $patient_id ?>">

            <!-- Plan meta -->
            <div>
                <label class="block text-xs font-500 text-slate-500 mb-1">Plan Title *</label>
                <input type="text" name="plan_title" required
                       value="<?= e($care_plan['title'] ?? '') ?>"
                       placeholder="e.g. Diabetes Management Plan"
                       class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-xs font-500 text-slate-500 mb-1">Overall Goals</label>
                <textarea name="goals" rows="2"
                          placeholder="Describe measurable health goals…"
                          class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-slate-50
                                 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent resize-none"><?= e($care_plan['goals'] ?? '') ?></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-500 text-slate-500 mb-1">🥗 Diet Notes</label>
                    <textarea name="diet_notes" rows="2"
                              placeholder="Dietary recommendations…"
                              class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-slate-50
                                     focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent resize-none"><?= e($care_plan['diet_notes'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-xs font-500 text-slate-500 mb-1">🏃 Exercise Notes</label>
                    <textarea name="exercise_notes" rows="2"
                              placeholder="Exercise guidance…"
                              class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-slate-50
                                     focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent resize-none"><?= e($care_plan['exercise_notes'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-xs font-500 text-slate-500 mb-1">Start Date</label>
                    <input type="date" name="start_date"
                           value="<?= e($care_plan['start_date'] ?? date('Y-m-d')) ?>"
                           class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-xs font-500 text-slate-500 mb-1">Review Date</label>
                    <input type="date" name="review_date"
                           value="<?= e($care_plan['review_date'] ?? '') ?>"
                           class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                </div>
            </div>

            <!-- ── Dynamic Task Builder ─────────────────── -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-600 text-slate-500 uppercase tracking-wider">Daily Tasks / Checklist Items</label>
                    <button type="button" id="add-task-btn"
                            aria-label="Add a new daily task"
                            title="Add a new daily task"
                            class="inline-flex items-center gap-1 text-xs text-brand-600 hover:text-brand-700 font-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Task
                    </button>
                </div>

                <div id="tasks-container" class="space-y-3">
                    <?php
                    // Pre-populate existing tasks if editing
                    $prefill_tasks = !empty($tasks) ? $tasks : [[]];
                    foreach ($prefill_tasks as $i => $task):
                    ?>
                    <div class="task-row bg-slate-50 border border-slate-200 rounded-xl p-3 space-y-2" data-index="<?= $i ?>">
                        <div class="flex items-center gap-2">
                            <select name="tasks[<?= $i ?>][type]"
                                    class="flex-shrink-0 px-2 py-1.5 text-xs rounded-lg border border-slate-200 bg-white
                                           focus:outline-none focus:ring-2 focus:ring-brand-500">
                                <?php foreach (['medication','exercise','diet','lifestyle','other'] as $t): ?>
                                    <option value="<?= $t ?>" <?= (($task['task_type'] ?? '') === $t) ? 'selected' : '' ?>>
                                        <?= $task_type_icons[$t] ?> <?= ucfirst($t) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="tasks[<?= $i ?>][description]" required maxlength="300"
                                   value="<?= e($task['description'] ?? '') ?>"
                                   placeholder="Task description *"
                                   class="flex-1 px-2 py-1.5 text-xs rounded-lg border border-slate-200 bg-white
                                          focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <button type="button"
                                    class="remove-task-btn text-slate-300 hover:text-red-400 transition flex-shrink-0"
                                    aria-label="Remove this task"
                                    title="Remove this task">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <div class="grid grid-cols-3 gap-2">
                            <input type="text" name="tasks[<?= $i ?>][medication_name]" maxlength="150"
                                   value="<?= e($task['medication_name'] ?? '') ?>"
                                   placeholder="Drug name"
                                   class="px-2 py-1.5 text-xs rounded-lg border border-slate-200 bg-white
                                          focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <input type="text" name="tasks[<?= $i ?>][dosage]" maxlength="100"
                                   value="<?= e($task['dosage'] ?? '') ?>"
                                   placeholder="Dosage"
                                   class="px-2 py-1.5 text-xs rounded-lg border border-slate-200 bg-white
                                          focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <input type="text" name="tasks[<?= $i ?>][frequency]" maxlength="100"
                                   value="<?= e($task['frequency'] ?? '') ?>"
                                   placeholder="Frequency"
                                   class="px-2 py-1.5 text-xs rounded-lg border border-slate-200 bg-white
                                          focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit"
                    class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-600 rounded-xl transition">
                <?= $care_plan ? '💾 Update Care Plan' : '✅ Create Care Plan' ?>
            </button>
        </form>
    </div>

</div><!-- /RIGHT COLUMN -->
</div><!-- /grid -->

<!-- ── Dynamic task JS ────────────────────────────────────── -->
<script>
(function () {
    let taskIndex = <?= count($prefill_tasks) ?>;
    const container = document.getElementById('tasks-container');
    const typeIcons = {
        medication: '💊', exercise: '🏃', diet: '🥗', lifestyle: '🌿', other: '📋'
    };

    function buildTaskRow(index) {
        const options = ['medication','exercise','diet','lifestyle','other'].map(t =>
            `<option value="${t}">${typeIcons[t]} ${t.charAt(0).toUpperCase()+t.slice(1)}</option>`
        ).join('');

        return `
        <div class="task-row bg-slate-50 border border-slate-200 rounded-xl p-3 space-y-2" data-index="${index}">
            <div class="flex items-center gap-2">
                <select name="tasks[${index}][type]"
                        class="flex-shrink-0 px-2 py-1.5 text-xs rounded-lg border border-slate-200 bg-white
                               focus:outline-none focus:ring-2 focus:ring-brand-500">
                    ${options}
                </select>
                <input type="text" name="tasks[${index}][description]" required maxlength="300"
                       placeholder="Task description *"
                       class="flex-1 px-2 py-1.5 text-xs rounded-lg border border-slate-200 bg-white
                              focus:outline-none focus:ring-2 focus:ring-brand-500">
                <button type="button"
                        class="remove-task-btn text-slate-300 hover:text-red-400 transition flex-shrink-0"
                        aria-label="Remove this task"
                        title="Remove this task">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="grid grid-cols-3 gap-2">
                <input type="text" name="tasks[${index}][medication_name]" maxlength="150" placeholder="Drug name"
                       class="px-2 py-1.5 text-xs rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                <input type="text" name="tasks[${index}][dosage]" maxlength="100" placeholder="Dosage"
                       class="px-2 py-1.5 text-xs rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                <input type="text" name="tasks[${index}][frequency]" maxlength="100" placeholder="Frequency"
                       class="px-2 py-1.5 text-xs rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
        </div>`;
    }

    document.getElementById('add-task-btn').addEventListener('click', function () {
        container.insertAdjacentHTML('beforeend', buildTaskRow(taskIndex++));
    });

    container.addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-task-btn');
        if (!btn) return;
        const rows = container.querySelectorAll('.task-row');
        if (rows.length > 1) {
            btn.closest('.task-row').remove();
        }
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
