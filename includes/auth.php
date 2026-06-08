<?php
// ============================================================
// includes/auth.php  —  Session, CSRF & Role Helpers
// ============================================================

declare(strict_types=1);

// ── Start session securely (call once per request) ──────────
function session_start_secure(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),  // HTTPS-only in prod
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

// ── CSRF token generation & validation ──────────────────────
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verify(): bool
{
    $submitted = $_POST['csrf_token'] ?? '';
    return hash_equals(csrf_token(), $submitted);
}

// ── Authentication guards ────────────────────────────────────
function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: /index.php?error=Please+log+in+to+continue');
        exit;
    }
}

function require_role(string $role): void
{
    require_login();
    if (($_SESSION['user_role'] ?? '') !== $role) {
        http_response_code(403);
        die('<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>');
    }
}

// ── Current user helpers ─────────────────────────────────────
function current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function current_user_role(): string
{
    return (string) ($_SESSION['user_role'] ?? '');
}

function current_user_name(): string
{
    return (string) ($_SESSION['user_name'] ?? '');
}

// ── Safe output escaping ─────────────────────────────────────
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
