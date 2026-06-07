<?php
// ============================================================
// patient_dashboard.php  —  Patient portal main page
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

session_start_secure();
require_role('patient');

$db         = get_db();
$patient_id = current_user_id();
$today      = date('Y-m-d');

// ── Flash messages ───────────────────────────────────────────
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Patient profile ──────────────────────────────────────────
$profile_stmt = $db->prepare(
    "SELECT u.full_name, u.email, u.created_at,
            pp.date_of_birth, pp.gender, pp.phone, pp.blood_type,
            pp.emergency_contact_name, pp.emergency_contact_phone
     FROM users u
     LEFT JOIN patient_profiles pp ON pp.user_id = u.id
     WHERE u.id = ? LIMIT 1"
);
$profile_stmt->execute([$patient_id]);
$profile = $profile_stmt->fetch();

// ── Latest vitals ────────────────────────────────────────────
$vitals_stmt = $db->prepare(
    "SELECT * FROM vitals WHERE patient_id = ? ORDER BY recorded_at DESC LIMIT 1"
);
$vitals_stmt->execute([$patient_id]);
$latest_vitals = $vitals_stmt->fetch();

// ── Allergies ────────────────────────────────────────────────
$allergy_stmt = $db->prepare(
    "SELECT * FROM allergies WHERE patient_id = ? ORDER BY severity DESC"
);
$allergy_stmt->execute([$patient_id]);
$allergies = $allergy_stmt->fetchAll();

// ── Active diagnoses ─────────────────────────────────────────
$diag_stmt = $db->prepare(
    "SELECT * FROM diagnoses WHERE patient_id = ? ORDER BY status ASC, diagnosed_on DESC"
);
$diag_stmt->execute([$patient_id]);
$diagnoses = $diag_stmt->fetchAll();

// ── Care plan + tasks ────────────────────────────────────────
$cp_stmt = $db->prepare(
    "SELECT cp.*, u.full_name AS doctor_name
     FROM care_plans cp
     JOIN users u ON u.id = cp.doctor_id
     WHERE cp.patient_id = ? LIMIT 1"
);
$cp_stmt->execute([$patient_id]);
$care_plan = $cp_stmt->fetch();

$tasks        = [];
$completions  = [];   // task_id => true if completed today

if ($care_plan) {
    $t_stmt = $db->prepare(
        "SELECT * FROM care_plan_tasks WHERE care_plan_id = ? ORDER BY sort_order ASC, id ASC"
    );
    $t_stmt->execute([$care_plan['id']]);
    $tasks = $t_stmt->fetchAll();

    // Fetch today's completions for this patient
    if (!empty($tasks)) {
        $task_ids      = array_column($tasks, 'id');
        $placeholders  = implode(',', array_fill(0, count($task_ids), '?'));
        $comp_stmt     = $db->prepare(
            "SELECT task_id FROM task_completions
             WHERE patient_id = ? AND completed_on = ? AND task_id IN ({$placeholders})"
        );
        $comp_stmt->execute(array_merge([$patient_id, $today], $task_ids));
        foreach ($comp_stmt->fetchAll() as $row) {
            $completions[$row['task_id']] = true;
        }
    }
}

// ── Helpers ──────────────────────────────────────────────────
$severity_badge = [
    'mild'     => 'bg-yellow-100 text-yellow-700',
    'moderate' => 'bg-orange-100 text-orange-700',
    'severe'   => 'bg-red-100   text-red-700',
];
$status_badge = [
    'active'   => 'bg-amber-100  text-amber-700',
    'chronic'  => 'bg-red-100    text-red-700',
    'resolved' => 'bg-emerald-100 text-emerald-700',
];
$task_type_icons = [
    'medication' => '💊', 'exercise' => '🏃', 'diet' => '🥗',
    'lifestyle'  => '🌿', 'other'    => '📋',
];

$completed_count = count($completions);
$total_tasks     = count($tasks);
$progress_pct    = $total_tasks > 0 ? round(($completed_count / $total_tasks) * 100) : 0;

$age = ($profile['date_of_birth'] ?? null)
    ? (new DateTime($profile['date_of_birth']))->diff(new DateTime())->y
    : null;

$page_title = 'My Health Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Flash alerts ─────────────────────────────────────────── -->
<?php if ($flash_success): ?>
    <div class="alert mb-5 flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-xl px-4 py-3">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
        </svg>
        <?= e($flash_success) ?>
    </div>
<?php endif; ?>
<?php if ($flash_error): ?>
    <div class="alert mb-5 flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
        <?= e($flash_error) ?>
    </div>
<?php endif; ?>

<!-- ── Welcome banner ───────────────────────────────────────── -->
<div class="bg-gradient-to-r from-brand-600 to-brand-700 rounded-2xl p-6 mb-6 text-white relative overflow-hidden">
    <!-- decorative circles -->
    <div class="absolute -right-8 -top-8 w-32 h-32 bg-white/10 rounded-full"></div>
    <div class="absolute -right-4 top-8 w-16 h-16 bg-white/10 rounded-full"></div>
    <div class="relative">
        <p class="text-brand-200 text-xs font-500 uppercase tracking-widest mb-1">Good <?= date('G') < 12 ? 'Morning' : (date('G') < 17 ? 'Afternoon' : 'Evening') ?></p>
        <h2 class="text-2xl font-700 mb-1"><?= e($profile['full_name'] ?? 'Patient') ?> 👋</h2>
        <p class="text-brand-100 text-sm">Today is <?= date('l, F j, Y') ?></p>

        <?php if ($care_plan && $total_tasks > 0): ?>
        <div class="mt-4">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs text-brand-200 font-500">Today's Progress</span>
                <span class="text-xs font-700"><?= $completed_count ?>/<?= $total_tasks ?> tasks</span>
            </div>
            <div class="w-full bg-white/20 rounded-full h-2">
                <div class="bg-white rounded-full h-2 transition-all duration-700"
                     style="width: <?= $progress_pct ?>%"></div>
            </div>
            <p class="text-xs text-brand-100 mt-1">
                <?= $progress_pct ?>% complete
                <?= $progress_pct === 100 ? '🎉 Outstanding!' : '' ?>
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     THREE-COLUMN STATS ROW
══════════════════════════════════════════════════════════ -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">

    <!-- Blood type -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 text-center">
        <p class="text-2xl font-800 text-red-500"><?= e($profile['blood_type'] ?? '—') ?></p>
        <p class="text-xs text-slate-400 mt-0.5 font-500">Blood Type</p>
    </div>

    <!-- Latest BP -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 text-center">
        <p class="text-xl font-700 text-slate-800"><?= e($latest_vitals['blood_pressure'] ?? '—') ?></p>
        <p class="text-xs text-slate-400 mt-0.5 font-500">Blood Pressure</p>
    </div>

    <!-- Conditions -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 text-center">
        <?php $active_cond = count(array_filter($diagnoses, fn($d) => $d['status'] !== 'resolved')); ?>
        <p class="text-2xl font-700 text-amber-500"><?= $active_cond ?></p>
        <p class="text-xs text-slate-400 mt-0.5 font-500">Active Conditions</p>
    </div>

    <!-- Allergies -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 text-center">
        <p class="text-2xl font-700 text-orange-500"><?= count($allergies) ?></p>
        <p class="text-xs text-slate-400 mt-0.5 font-500">Known Allergies</p>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MAIN GRID
══════════════════════════════════════════════════════════ -->
<div class="grid grid-cols-1 xl:grid-cols-5 gap-6">

<!-- ── Left: EHR summary (2/5) ──────────────────────────── -->
<div class="xl:col-span-2 space-y-6">

    <!-- Latest Vitals -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="font-600 text-slate-800 flex items-center gap-2">
                <span class="text-base">❤️</span> Latest Vitals
            </h3>
            <?php if ($latest_vitals): ?>
                <p class="text-xs text-slate-400 mt-0.5">
                    Recorded <?= date('d M Y', strtotime($latest_vitals['recorded_at'])) ?>
                </p>
            <?php endif; ?>
        </div>

        <?php if (!$latest_vitals): ?>
            <div class="py-10 text-center">
                <p class="text-3xl mb-2">🩺</p>
                <p class="text-sm text-slate-400">No vitals recorded yet.</p>
                <p class="text-xs text-slate-300 mt-1">Your doctor will add these at your next visit.</p>
            </div>
        <?php else: ?>
            <div class="p-4 grid grid-cols-2 gap-2.5">
                <?php
                $vital_display = [
                    ['icon' => '🫀', 'label' => 'Blood Pressure', 'value' => $latest_vitals['blood_pressure'],     'unit' => 'mmHg'],
                    ['icon' => '💓', 'label' => 'Heart Rate',     'value' => $latest_vitals['heart_rate'],          'unit' => 'bpm'],
                    ['icon' => '🌡️', 'label' => 'Temperature',    'value' => $latest_vitals['temperature'],         'unit' => '°C'],
                    ['icon' => '⚖️', 'label' => 'Weight',         'value' => $latest_vitals['weight_kg'],           'unit' => 'kg'],
                    ['icon' => '📏', 'label' => 'Height',         'value' => $latest_vitals['height_cm'],           'unit' => 'cm'],
                    ['icon' => '🫁', 'label' => 'SpO₂',          'value' => $latest_vitals['oxygen_saturation'],   'unit' => '%'],
                ];
                foreach ($vital_display as $vd): ?>
                    <div class="bg-slate-50 rounded-xl p-3 flex items-center gap-2.5">
                        <span class="text-xl flex-shrink-0"><?= $vd['icon'] ?></span>
                        <div class="min-w-0">
                            <p class="text-xs text-slate-400 truncate"><?= $vd['label'] ?></p>
                            <p class="font-700 text-slate-800 text-sm">
                                <?= $vd['value'] !== null ? e((string)$vd['value']) : '—' ?>
                                <span class="text-xs font-400 text-slate-400"><?= $vd['unit'] ?></span>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($latest_vitals['notes']): ?>
                <div class="px-5 pb-4">
                    <p class="text-xs text-slate-400 font-500 uppercase tracking-wider mb-1">Doctor's Note</p>
                    <p class="text-xs text-slate-600 italic leading-relaxed bg-slate-50 rounded-xl px-3 py-2">
                        "<?= e($latest_vitals['notes']) ?>"
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Allergies -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="font-600 text-slate-800 flex items-center gap-2">
                <span class="text-base">⚠️</span> My Allergies
            </h3>
        </div>
        <?php if (empty($allergies)): ?>
            <div class="py-8 text-center">
                <p class="text-sm text-slate-400">No known allergies on record.</p>
            </div>
        <?php else: ?>
            <div class="p-4 space-y-2">
                <?php foreach ($allergies as $al): ?>
                    <div class="flex items-center justify-between p-3 rounded-xl <?= $severity_badge[$al['severity']] ?? 'bg-slate-100 text-slate-600' ?>">
                        <div>
                            <p class="font-600 text-sm"><?= e($al['allergen']) ?></p>
                            <?php if ($al['reaction']): ?>
                                <p class="text-xs opacity-80 mt-0.5"><?= e($al['reaction']) ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="text-xs font-700 uppercase flex-shrink-0 ml-2"><?= e($al['severity']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Medical History -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="font-600 text-slate-800 flex items-center gap-2">
                <span class="text-base">🩺</span> Medical History
            </h3>
        </div>
        <?php if (empty($diagnoses)): ?>
            <div class="py-8 text-center">
                <p class="text-sm text-slate-400">No diagnoses recorded.</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-50">
                <?php foreach ($diagnoses as $diag): ?>
                    <div class="px-5 py-3.5 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-600 text-slate-800"><?= e($diag['title']) ?></p>
                            <?php if ($diag['icd_code']): ?>
                                <span class="font-mono text-xs text-slate-400"><?= e($diag['icd_code']) ?></span>
                            <?php endif; ?>
                            <?php if ($diag['diagnosed_on']): ?>
                                <p class="text-xs text-slate-400 mt-0.5">Since <?= date('M Y', strtotime($diag['diagnosed_on'])) ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="text-xs font-600 px-2 py-0.5 rounded-lg flex-shrink-0 <?= $status_badge[$diag['status']] ?? 'bg-slate-100 text-slate-500' ?>">
                            <?= ucfirst(e($diag['status'])) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div><!-- /left col -->

<!-- ── Right: Care Plan (3/5) ──────────────────────────────── -->
<div class="xl:col-span-3">
    <?php if (!$care_plan): ?>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-10 text-center">
            <div class="text-4xl mb-3">📋</div>
            <h3 class="font-600 text-slate-700 mb-1">No Care Plan Yet</h3>
            <p class="text-sm text-slate-400 max-w-xs mx-auto">
                Your doctor hasn't created a personalised care plan for you yet.
                It will appear here once assigned.
            </p>
        </div>
    <?php else: ?>
        <!-- Care plan header -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-4">
            <div class="bg-gradient-to-r from-emerald-500 to-teal-500 px-6 py-5 text-white">
                <p class="text-xs font-600 uppercase tracking-widest text-emerald-100 mb-1">Personalised Care Plan</p>
                <h3 class="text-xl font-700"><?= e($care_plan['title']) ?></h3>
                <p class="text-emerald-100 text-xs mt-1">
                    Assigned by <?= e($care_plan['doctor_name']) ?>
                    &nbsp;·&nbsp;
                    Review: <?= $care_plan['review_date'] ? date('d M Y', strtotime($care_plan['review_date'])) : 'Not set' ?>
                </p>
            </div>

            <?php if ($care_plan['goals']): ?>
            <div class="px-6 py-4 border-b border-slate-100">
                <p class="text-xs font-600 text-slate-400 uppercase tracking-wider mb-2">Goals</p>
                <p class="text-sm text-slate-600 leading-relaxed"><?= e($care_plan['goals']) ?></p>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-2 divide-x divide-slate-100">
                <div class="px-5 py-4">
                    <p class="text-xs font-600 text-slate-400 uppercase tracking-wider mb-2">🥗 Diet</p>
                    <p class="text-xs text-slate-600 leading-relaxed"><?= e($care_plan['diet_notes'] ?: 'Not specified') ?></p>
                </div>
                <div class="px-5 py-4">
                    <p class="text-xs font-600 text-slate-400 uppercase tracking-wider mb-2">🏃 Exercise</p>
                    <p class="text-xs text-slate-600 leading-relaxed"><?= e($care_plan['exercise_notes'] ?: 'Not specified') ?></p>
                </div>
            </div>
        </div>

        <!-- Daily task checklist -->
        <?php if (!empty($tasks)): ?>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h4 class="font-600 text-slate-800 flex items-center gap-2">
                    <span class="text-base">✅</span>
                    Today's Checklist
                    <span class="text-xs text-slate-400 font-400"><?= date('d M Y') ?></span>
                </h4>
                <div class="flex items-center gap-2">
                    <div class="w-24 bg-slate-100 rounded-full h-1.5">
                        <div class="bg-emerald-500 rounded-full h-1.5 transition-all"
                             style="width: <?= $progress_pct ?>%"></div>
                    </div>
                    <span class="text-xs text-slate-500 font-600"><?= $progress_pct ?>%</span>
                </div>
            </div>

            <div class="p-4 space-y-2.5" id="checklist">
                <?php foreach ($tasks as $task):
                    $is_done = isset($completions[$task['id']]);
                    $type_color = [
                        'medication' => 'border-rose-200 bg-rose-50',
                        'exercise'   => 'border-blue-200 bg-blue-50',
                        'diet'       => 'border-green-200 bg-green-50',
                        'lifestyle'  => 'border-teal-200 bg-teal-50',
                        'other'      => 'border-slate-200 bg-slate-50',
                    ][$task['task_type']] ?? 'border-slate-200 bg-slate-50';
                ?>
                <div class="task-item flex items-start gap-3 p-3.5 rounded-xl border <?= $type_color ?> <?= $is_done ? 'opacity-60' : '' ?>"
                     data-task-id="<?= (int)$task['id'] ?>">

                    <!-- Checkbox form -->
                    <form method="POST" action="process_action.php" class="flex-shrink-0 mt-0.5">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action"     value="toggle_task">
                        <input type="hidden" name="task_id"    value="<?= (int)$task['id'] ?>">
                        <input type="hidden" name="is_done"    value="<?= $is_done ? '1' : '0' ?>">
                        <button type="submit"
                                class="w-6 h-6 rounded-full border-2 flex items-center justify-center transition-all
                                       <?= $is_done
                                           ? 'bg-emerald-500 border-emerald-500 text-white'
                                           : 'border-slate-300 hover:border-emerald-400 bg-white' ?>">
                            <?php if ($is_done): ?>
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            <?php endif; ?>
                        </button>
                    </form>

                    <!-- Task details -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-base"><?= $task_type_icons[$task['task_type']] ?? '📋' ?></span>
                            <p class="text-sm font-600 text-slate-800 <?= $is_done ? 'line-through text-slate-400' : '' ?>">
                                <?= e($task['description']) ?>
                            </p>
                        </div>
                        <?php if ($task['medication_name']): ?>
                            <p class="text-xs text-slate-500 mt-1">
                                <strong><?= e($task['medication_name']) ?></strong>
                                <?= $task['dosage'] ? '— ' . e($task['dosage']) : '' ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($task['frequency']): ?>
                            <p class="text-xs text-brand-500 mt-0.5 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <?= e($task['frequency']) ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Done badge -->
                    <?php if ($is_done): ?>
                        <span class="flex-shrink-0 text-xs text-emerald-600 font-600 bg-emerald-100 rounded-lg px-2 py-0.5">Done</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($progress_pct === 100): ?>
                <div class="mx-4 mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-center">
                    <p class="text-emerald-700 font-700 text-sm">🎉 All tasks completed for today!</p>
                    <p class="text-xs text-emerald-500 mt-1">Excellent work staying on track with your care plan.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div><!-- /right col -->

</div><!-- /main grid -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
