<?php
// ============================================================
// config/db.php  —  PDO Database Connection
// ============================================================
// Returns a singleton PDO instance. Throws a PDOException
// (caught below) on connection failure rather than exposing
// credentials in raw error output.
// ============================================================

declare(strict_types=1);

function get_db(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    // ── Connection parameters ────────────────────────────────
    // In production, load these from environment variables or
    // a .env file outside the web root, never hard-coded.
    $host    = getenv('DB_HOST')    ?: 'localhost';
    $dbname  = getenv('DB_NAME')    ?: 'ehr_db';
    $user    = getenv('DB_USER')    ?: 'root';
    $pass    = getenv('DB_PASS')    ?: '';
    $charset = 'utf8mb4';

    $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // throw on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // arrays by default
        PDO::ATTR_EMULATE_PREPARES   => false,                    // native prepared stmts
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        // Log the real error server-side; show a safe message to the browser
        error_log('DB connection error: ' . $e->getMessage());
        http_response_code(503);
        die('<h1>Service Unavailable</h1><p>Database connection failed. Please try again later.</p>');
    }

    return $pdo;
}
