<?php
// ============================================================
// logout.php  —  Destroy session and redirect to login
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

session_start_secure();

// Unset all session variables
$_SESSION = [];

// Destroy the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy the session
session_destroy();

header('Location: index.php?success=You+have+been+signed+out+successfully');
exit;
