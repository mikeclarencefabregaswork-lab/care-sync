<?php
// ============================================================
// register.php  —  New user registration
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

session_start_secure();

// Redirect if already logged in
if (!empty($_SESSION['user_id'])) {
    $dest = ($_SESSION['user_role'] === 'doctor') ? 'doctor_dashboard.php' : 'patient_dashboard.php';
    header("Location: {$dest}");
    exit;
}

$error   = '';
$success = '';
$form    = ['full_name' => '', 'email' => '', 'role' => 'patient'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_verify()) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        // Collect & sanitise inputs
        $form['full_name'] = trim(filter_input(INPUT_POST, 'full_name', FILTER_DEFAULT) ?? '');
        $form['email']     = trim(filter_input(INPUT_POST, 'email',    FILTER_SANITIZE_EMAIL) ?? '');
        $form['role']      = filter_input(INPUT_POST, 'role', FILTER_DEFAULT) ?? 'patient';
        $password          = filter_input(INPUT_POST, 'password',         FILTER_DEFAULT) ?? '';
        $password_confirm  = filter_input(INPUT_POST, 'password_confirm', FILTER_DEFAULT) ?? '';

        // Validate role to allowed set
        $allowed_roles = ['doctor', 'patient'];
        if (!in_array($form['role'], $allowed_roles, true)) {
            $form['role'] = 'patient';
        }

        // Validation
        if (empty($form['full_name']) || strlen($form['full_name']) < 2) {
            $error = 'Please enter your full name (at least 2 characters).';
        } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($password !== $password_confirm) {
            $error = 'Passwords do not match.';
        } else {
            $db = get_db();

            // Check email uniqueness
            $chk = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $chk->execute([$form['email']]);
            if ($chk->fetch()) {
                $error = 'An account with that email already exists. Please log in.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                $db->beginTransaction();
                try {
                    $ins = $db->prepare(
                        'INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)'
                    );
                    $ins->execute([$form['full_name'], $form['email'], $hash, $form['role']]);
                    $new_id = (int) $db->lastInsertId();

                    // Auto-create patient profile row
                    if ($form['role'] === 'patient') {
                        $pp = $db->prepare('INSERT INTO patient_profiles (user_id) VALUES (?)');
                        $pp->execute([$new_id]);
                    }

                    $db->commit();
                    $success = 'Account created successfully! You can now sign in.';
                    $form    = ['full_name' => '', 'email' => '', 'role' => 'patient']; // reset form
                } catch (PDOException $e) {
                    $db->rollBack();
                    error_log('Registration error: ' . $e->getMessage());
                    $error = 'Registration failed due to a server error. Please try again.';
                }
            }
        }
    }
}

$page_title = 'Create Account';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Registration card ─────────────────────────────────── -->
<div class="w-full max-w-md">

    <!-- Brand header -->
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-14 h-14 bg-brand-600 rounded-2xl shadow-lg mb-4">
            <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M4.5 12h15m-7.5-7.5v15M9 4.5h6a7.5 7.5 0 010 15H9a7.5 7.5 0 010-15z"/>
            </svg>
        </div>
        <h1 class="text-white text-3xl font-700 tracking-tight">CareSync</h1>
        <p class="text-slate-400 text-sm mt-1">Create your account</p>
    </div>

    <!-- Card -->
    <div class="bg-white rounded-2xl shadow-2xl p-8">
        <h2 class="text-slate-800 text-xl font-600 mb-1">Get started</h2>
        <p class="text-slate-400 text-sm mb-6">Fill in the details below to register</p>

        <?php if ($error): ?>
            <div class="alert mb-5 flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert mb-5 flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-xl px-4 py-3">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                <?= e($success) ?>
                <a href="index.php" class="ml-auto font-600 underline">Sign in →</a>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" novalidate>
            <?= csrf_field() ?>

            <!-- Full name -->
            <div class="mb-4">
                <label for="full_name" class="block text-sm font-500 text-slate-600 mb-1.5">Full name</label>
                <input type="text" id="full_name" name="full_name"
                       value="<?= e($form['full_name']) ?>"
                       placeholder="Dr. Jane Smith"
                       required
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-800 text-sm
                              placeholder:text-slate-300 bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition">
            </div>

            <!-- Email -->
            <div class="mb-4">
                <label for="email" class="block text-sm font-500 text-slate-600 mb-1.5">Email address</label>
                <input type="email" id="email" name="email"
                       value="<?= e($form['email']) ?>"
                       placeholder="you@example.com"
                       autocomplete="username"
                       required
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-800 text-sm
                              placeholder:text-slate-300 bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition">
            </div>

            <!-- Role -->
            <div class="mb-4">
                <label class="block text-sm font-500 text-slate-600 mb-1.5">Account type</label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl border cursor-pointer transition
                                  <?= ($form['role'] === 'patient') ? 'border-brand-400 bg-brand-50 text-brand-700' : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-brand-300' ?>">
                        <input type="radio" name="role" value="patient"
                               <?= ($form['role'] === 'patient') ? 'checked' : '' ?>
                               class="text-brand-600 focus:ring-brand-500">
                        <span class="text-sm font-500">Patient</span>
                    </label>
                    <label class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl border cursor-pointer transition
                                  <?= ($form['role'] === 'doctor') ? 'border-brand-400 bg-brand-50 text-brand-700' : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-brand-300' ?>">
                        <input type="radio" name="role" value="doctor"
                               <?= ($form['role'] === 'doctor') ? 'checked' : '' ?>
                               class="text-brand-600 focus:ring-brand-500">
                        <span class="text-sm font-500">Doctor / Clinician</span>
                    </label>
                </div>
            </div>

            <!-- Password -->
            <div class="mb-4">
                <label for="password" class="block text-sm font-500 text-slate-600 mb-1.5">Password</label>
                <input type="password" id="password" name="password"
                       placeholder="Minimum 8 characters"
                       autocomplete="new-password"
                       required
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-800 text-sm
                              placeholder:text-slate-300 bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition">
            </div>

            <!-- Confirm Password -->
            <div class="mb-6">
                <label for="password_confirm" class="block text-sm font-500 text-slate-600 mb-1.5">Confirm password</label>
                <input type="password" id="password_confirm" name="password_confirm"
                       placeholder="Re-enter password"
                       autocomplete="new-password"
                       required
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-800 text-sm
                              placeholder:text-slate-300 bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition">
            </div>

            <button type="submit"
                    class="w-full py-2.5 bg-brand-600 hover:bg-brand-700 text-white font-600 text-sm
                           rounded-xl shadow-sm hover:shadow-md transition-all duration-200 active:scale-[0.98]">
                Create Account
            </button>
        </form>

        <p class="text-center text-sm text-slate-400 mt-5">
            Already have an account?
            <a href="index.php" class="text-brand-600 hover:text-brand-700 font-500 hover:underline">Sign in</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
