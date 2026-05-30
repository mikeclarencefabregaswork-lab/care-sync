<?php
// ============================================================
// index.php  —  Login page (landing)
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

session_start_secure();

// Redirect already-logged-in users
if (!empty($_SESSION['user_id'])) {
    $dest = ($_SESSION['user_role'] === 'doctor') ? 'doctor_dashboard.php' : 'patient_dashboard.php';
    header("Location: {$dest}");
    exit;
}

$error   = '';
$success = '';

// ── Handle demo-hint query string ───────────────────────────
if (!empty($_GET['error'])) {
    $error = htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8');
}

// ── Process login form ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!csrf_verify()) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $email    = trim(filter_input(INPUT_POST, 'email',    FILTER_SANITIZE_EMAIL)    ?? '');
        $password = trim(filter_input(INPUT_POST, 'password', FILTER_DEFAULT)           ?? '');

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $db   = get_db();
            $stmt = $db->prepare('SELECT id, full_name, password, role FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Regenerate session ID to prevent fixation
                session_regenerate_id(true);

                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];

                $dest = ($user['role'] === 'doctor') ? 'doctor_dashboard.php' : 'patient_dashboard.php';
                header("Location: {$dest}");
                exit;
            } else {
                // Deliberately vague to avoid user enumeration
                $error = 'Invalid email or password. Please try again.';
            }
        }
    }
}

$page_title = 'Sign In';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Login card ─────────────────────────────────────────── -->
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
        <p class="text-slate-400 text-sm mt-1">Electronic Health Records &amp; Care Planning</p>
    </div>

    <!-- Card -->
    <div class="bg-white rounded-2xl shadow-2xl p-8">
        <h2 class="text-slate-800 text-xl font-600 mb-1">Welcome back</h2>
        <p class="text-slate-400 text-sm mb-6">Sign in to your account to continue</p>

        <?php if ($error): ?>
            <div class="alert mb-5 flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="index.php" novalidate>
            <?= csrf_field() ?>

            <!-- Email -->
            <div class="mb-4">
                <label for="email" class="block text-sm font-500 text-slate-600 mb-1.5">Email address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    autocomplete="username"
                    placeholder="you@example.com"
                    value="<?= isset($_POST['email']) ? e($_POST['email']) : '' ?>"
                    required
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-800 text-sm
                           placeholder:text-slate-300 bg-slate-50
                           focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                           transition">
            </div>

            <!-- Password -->
            <div class="mb-6">
                <label for="password" class="block text-sm font-500 text-slate-600 mb-1.5">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    placeholder="••••••••"
                    required
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-800 text-sm
                           placeholder:text-slate-300 bg-slate-50
                           focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                           transition">
            </div>

            <button type="submit"
                    class="w-full py-2.5 bg-brand-600 hover:bg-brand-700 text-white font-600 text-sm
                           rounded-xl shadow-sm hover:shadow-md transition-all duration-200 active:scale-[0.98]">
                Sign In
            </button>
        </form>

        <p class="text-center text-sm text-slate-400 mt-5">
            Don't have an account?
            <a href="register.php" class="text-brand-600 hover:text-brand-700 font-500 hover:underline">Register here</a>
        </p>
    </div>

    <!-- Demo credentials hint -->
    <div class="mt-6 bg-white/10 border border-white/20 rounded-xl p-4 backdrop-blur-sm">
        <p class="text-white/80 text-xs font-600 uppercase tracking-widest mb-2">Demo Accounts</p>
        <div class="space-y-1.5">
            <div class="flex items-center justify-between text-xs">
                <span class="text-slate-300">🩺 Doctor</span>
                <span class="font-mono text-slate-200">doctor@ehr.dev / password123</span>
            </div>
            <div class="flex items-center justify-between text-xs">
                <span class="text-slate-300">👤 Patient</span>
                <span class="font-mono text-slate-200">patient@ehr.dev / password123</span>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
