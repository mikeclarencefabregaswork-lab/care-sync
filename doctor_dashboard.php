<?php
// ============================================================
// doctor_dashboard.php  —  Clinician main portal
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

session_start_secure();
require_role('doctor');

$db        = get_db();
$doctor_id = current_user_id();

// ── Search / filter patients ─────────────────────────────────
$search = trim(filter_input(INPUT_GET, 'q', FILTER_DEFAULT) ?? '');
$search_param = '%' . $search . '%';

$patients_stmt = $db->prepare(
    "SELECT
         u.id,
         u.full_name,
         u.email,
         u.created_at,
         pp.date_of_birth,
         pp.gender,
         pp.blood_type,
         pp.phone,
         -- latest vitals recorded date
         (SELECT MAX(v.recorded_at) FROM vitals v WHERE v.patient_id = u.id) AS last_vitals_date,
         -- active diagnosis count
         (SELECT COUNT(*) FROM diagnoses d WHERE d.patient_id = u.id AND d.status != 'resolved') AS active_conditions,
         -- has care plan
         (SELECT COUNT(*) FROM care_plans cp WHERE cp.patient_id = u.id) AS has_care_plan
     FROM users u
     LEFT JOIN patient_profiles pp ON pp.user_id = u.id
     WHERE u.role = 'patient'
       AND (u.full_name LIKE ? OR u.email LIKE ?)
     ORDER BY u.full_name ASC"
);
$patients_stmt->execute([$search_param, $search_param]);
$patients = $patients_stmt->fetchAll();

// ── Stats for top cards ──────────────────────────────────────
$total_patients = $db->query("SELECT COUNT(*) FROM users WHERE role='patient'")->fetchColumn();
$plans_count    = $db->query("SELECT COUNT(*) FROM care_plans")->fetchColumn();
$recent_vitals  = $db->query(
    "SELECT COUNT(*) FROM vitals WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
)->fetchColumn();

$page_title = 'Clinician Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Stats row ─────────────────────────────────────────────── -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">

    <!-- Total patients -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
        <div class="w-11 h-11 rounded-xl bg-brand-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-2xl font-700 text-slate-800"><?= (int)$total_patients ?></p>
            <p class="text-xs text-slate-400 font-500 mt-0.5">Total Patients</p>
        </div>
    </div>

    <!-- Active care plans -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
        <div class="w-11 h-11 rounded-xl bg-emerald-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
            </svg>
        </div>
        <div>
            <p class="text-2xl font-700 text-slate-800"><?= (int)$plans_count ?></p>
            <p class="text-xs text-slate-400 font-500 mt-0.5">Active Care Plans</p>
        </div>
    </div>

    <!-- Recent vitals -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
        <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-2xl font-700 text-slate-800"><?= (int)$recent_vitals ?></p>
            <p class="text-xs text-slate-400 font-500 mt-0.5">Vitals Recorded (7 days)</p>
        </div>
    </div>
</div>

<!-- ── Patient directory ────────────────────────────────────── -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">

    <!-- Table header / search bar -->
    <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center gap-3">
        <div>
            <h2 class="text-base font-600 text-slate-800">Patient Directory</h2>
            <p class="text-xs text-slate-400 mt-0.5"><?= count($patients) ?> result<?= count($patients) !== 1 ? 's' : '' ?></p>
        </div>
        <form method="GET" action="doctor_dashboard.php" class="sm:ml-auto flex gap-2">
            <div class="relative">
                <span class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </span>
                <input type="text" name="q" value="<?= e($search) ?>"
                       placeholder="Search patients…"
                       class="pl-9 pr-4 py-2 text-sm rounded-xl border border-slate-200 bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                              w-56 placeholder:text-slate-300">
            </div>
            <button type="submit"
                    class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-500 rounded-xl transition">
                Search
            </button>
            <?php if ($search): ?>
                <a href="doctor_dashboard.php"
                   class="px-3 py-2 border border-slate-200 text-slate-500 text-sm rounded-xl hover:bg-slate-50 transition">
                    Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($patients)): ?>
        <div class="py-16 text-center">
            <svg class="w-10 h-10 text-slate-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
            </svg>
            <p class="text-slate-400 text-sm">No patients found<?= $search ? ' matching "' . e($search) . '"' : '' ?>.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="text-left px-5 py-3 text-xs font-600 text-slate-500 uppercase tracking-wider">Patient</th>
                        <th class="text-left px-4 py-3 text-xs font-600 text-slate-500 uppercase tracking-wider hidden md:table-cell">Blood Type</th>
                        <th class="text-left px-4 py-3 text-xs font-600 text-slate-500 uppercase tracking-wider hidden lg:table-cell">Conditions</th>
                        <th class="text-left px-4 py-3 text-xs font-600 text-slate-500 uppercase tracking-wider hidden lg:table-cell">Care Plan</th>
                        <th class="text-left px-4 py-3 text-xs font-600 text-slate-500 uppercase tracking-wider hidden xl:table-cell">Last Vitals</th>
                        <th class="text-right px-5 py-3 text-xs font-600 text-slate-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($patients as $patient): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <!-- Name / email -->
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-brand-100 flex items-center justify-center text-brand-600 text-xs font-700 flex-shrink-0">
                                        <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <p class="font-500 text-slate-800"><?= e($patient['full_name']) ?></p>
                                        <p class="text-xs text-slate-400"><?= e($patient['email']) ?></p>
                                    </div>
                                </div>
                            </td>

                            <!-- Blood type -->
                            <td class="px-4 py-4 hidden md:table-cell">
                                <?php $bt = $patient['blood_type'] ?? 'Unknown'; ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-600
                                             <?= $bt !== 'Unknown' ? 'bg-red-50 text-red-600' : 'bg-slate-100 text-slate-400' ?>">
                                    <?= e($bt) ?>
                                </span>
                            </td>

                            <!-- Active conditions -->
                            <td class="px-4 py-4 hidden lg:table-cell">
                                <?php $cond = (int)$patient['active_conditions']; ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-600
                                             <?= $cond > 0 ? 'bg-amber-50 text-amber-600' : 'bg-slate-100 text-slate-400' ?>">
                                    <?= $cond ?> active
                                </span>
                            </td>

                            <!-- Care plan status -->
                            <td class="px-4 py-4 hidden lg:table-cell">
                                <?php if ($patient['has_care_plan']): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-600 bg-emerald-50 text-emerald-600">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-600 bg-slate-100 text-slate-400">
                                        None
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Last vitals -->
                            <td class="px-4 py-4 hidden xl:table-cell">
                                <span class="text-xs text-slate-400">
                                    <?= $patient['last_vitals_date']
                                        ? date('d M Y', strtotime($patient['last_vitals_date']))
                                        : '—' ?>
                                </span>
                            </td>

                            <!-- View button -->
                            <td class="px-5 py-4 text-right">
                                <a href="patient_view.php?id=<?= (int)$patient['id'] ?>"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-brand-600 hover:bg-brand-700
                                          text-white text-xs font-500 rounded-lg transition">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
