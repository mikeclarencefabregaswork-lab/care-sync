<?php
// ============================================================
// includes/header.php  —  Global HTML head + Navigation bar
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
session_start_secure();

if (!isset($page_title)) {
    $page_title = 'EHR System';
}

$is_logged_in  = !empty($_SESSION['user_id']);
$user_role     = $is_logged_in ? current_user_role() : '';
$user_name     = $is_logged_in ? current_user_name() : '';
$current_page  = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> — CareSync EHR</title>

    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50:  '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
                    },
                    fontFamily: {
                        sans: ['"DM Sans"', 'ui-sans-serif', 'system-ui'],
                        mono: ['"DM Mono"', 'ui-monospace'],
                    },
                },
            },
        }
    </script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'DM Sans', ui-sans-serif, system-ui; }

        /* ── Sidebar nav links ── */
        .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 8px;
            font-size: 0.875rem;
            color: #475569;
            text-decoration: none;
            transition: background 0.15s ease, color 0.15s ease;
            font-weight: 500;
        }
        .nav-link:hover {
            background: #f8fafc;
            color: #0f172a;
        }
        .nav-link.active {
            background: #eff6ff;
            color: #2563eb;
            font-weight: 600;
        }
        /* Lock icon size inside nav — prevents SVG blowup */
        .nav-link svg,
        .sidebar-icon {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* ── Alert slide-in ── */
        .alert { animation: slideDown 0.3s ease; }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="h-full antialiased text-slate-700" style="font-family:'DM Sans',ui-sans-serif,system-ui;">

<?php if ($is_logged_in): ?>
<!-- ═══════════════════════════════════════════════════════
     AUTHENTICATED LAYOUT  —  fixed sidebar + scrollable main
═══════════════════════════════════════════════════════════ -->
<div class="min-h-screen flex">

    <!-- ── Sidebar (fixed, 256 px wide) ──────────────────── -->
    <aside style="width:256px;flex-shrink:0;" class="bg-white border-r border-slate-100 flex flex-col shadow-sm fixed inset-y-0 left-0 z-30 overflow-hidden">

        <!-- Logo row -->
        <div style="height:64px;" class="flex items-center px-5 border-b border-slate-100 flex-shrink-0">
            <div class="flex items-center" style="gap:10px;">
                <div style="width:34px;height:34px;background:#2563eb;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 1px 4px rgba(37,99,235,.35);">
                    <!-- Plus / cross medical icon -->
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
                    </svg>
                </div>
                <span style="font-size:1.125rem;font-weight:700;color:#0f172a;letter-spacing:-0.02em;">CareSync</span>
            </div>
        </div>

        <!-- User profile card -->
        <div style="padding:12px 12px 6px;">
            <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0;">
                <!-- Avatar circle -->
                <?php if ($user_role === 'doctor'): ?>
                    <div style="width:32px;height:32px;border-radius:50%;background:#dbeafe;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#2563eb" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div style="min-width:0;flex:1;">
                        <p style="font-size:0.75rem;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($user_name) ?></p>
                        <p style="font-size:0.7rem;color:#2563eb;font-weight:500;margin-top:1px;">Clinician</p>
                    </div>
                <?php else: ?>
                    <div style="width:32px;height:32px;border-radius:50%;background:#d1fae5;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <div style="min-width:0;flex:1;">
                        <p style="font-size:0.75rem;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($user_name) ?></p>
                        <p style="font-size:0.7rem;color:#059669;font-weight:500;margin-top:1px;">Patient</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Navigation section label -->
        <div style="padding:14px 16px 4px;">
            <p style="font-size:0.65rem;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.08em;">Menu</p>
        </div>

        <!-- Nav links -->
        <nav style="flex:1;padding:4px 10px;overflow-y:auto;">

            <?php if ($user_role === 'doctor'): ?>

                <a href="doctor_dashboard.php"
                   class="nav-link <?= $current_page === 'doctor_dashboard.php' ? 'active' : '' ?>">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Dashboard
                </a>

                <!-- Patient directory shortcut (also goes to dashboard) -->
                <a href="doctor_dashboard.php"
                   class="nav-link <?= $current_page === 'patient_view.php' ? 'active' : '' ?>"
                   style="margin-top:2px;">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Patients
                </a>

            <?php else: ?>

                <a href="patient_dashboard.php"
                   class="nav-link <?= $current_page === 'patient_dashboard.php' ? 'active' : '' ?>">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    My Dashboard
                </a>

                <a href="patient_dashboard.php#care-plan"
                   class="nav-link" style="margin-top:2px;">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    Care Plan
                </a>

            <?php endif; ?>
        </nav>

        <!-- Divider + Sign Out pinned at bottom -->
        <div style="padding:10px;border-top:1px solid #f1f5f9;flex-shrink:0;">
            <a href="logout.php"
               style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;
                      font-size:0.875rem;color:#64748b;text-decoration:none;font-weight:500;
                      transition:background 0.15s,color 0.15s;"
               onmouseover="this.style.background='#fff1f2';this.style.color='#dc2626';"
               onmouseout="this.style.background='';this.style.color='#64748b';">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="flex-shrink:0;">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Sign Out
            </a>
        </div>
    </aside>

    <!-- ── Main content wrapper (offset by sidebar width) ─── -->
    <div style="margin-left:256px;flex:1;display:flex;flex-direction:column;min-width:0;">

        <!-- Top bar -->
        <header style="height:64px;background:white;border-bottom:1px solid #f1f5f9;
                        display:flex;align-items:center;justify-content:space-between;
                        padding:0 24px;position:sticky;top:0;z-index:20;
                        box-shadow:0 1px 3px rgba(0,0,0,.04);">
            <h1 style="font-size:0.9375rem;font-weight:600;color:#1e293b;margin:0;">
                <?= e($page_title) ?>
            </h1>
            <div style="display:flex;align-items:center;gap:12px;">
                <span style="font-size:0.7rem;color:#94a3b8;font-family:'DM Mono',monospace;">
                    <?= date('D, d M Y') ?>
                </span>
                <div style="width:1px;height:16px;background:#e2e8f0;"></div>
                <div style="width:34px;height:34px;border-radius:50%;background:#2563eb;
                             display:flex;align-items:center;justify-content:center;
                             color:white;font-size:0.75rem;font-weight:700;">
                    <?= strtoupper(substr($user_name, 0, 1)) ?>
                </div>
            </div>
        </header>

        <!-- Page content injected here -->
        <main style="flex:1;padding:24px;overflow-y:auto;">

<?php else: ?>
<!-- ═══════════════════════════════════════════════════════
     UNAUTHENTICATED LAYOUT — centred card on gradient bg
═══════════════════════════════════════════════════════════ -->
<div class="min-h-screen flex items-center justify-center p-4"
     style="background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 50%,#0f172a 100%);">
<?php endif; ?>
