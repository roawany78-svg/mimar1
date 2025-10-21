<?php
// config.php - NO SECURITY VERSION
declare(strict_types=1);

// Database settings
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'dbo_schema');
define('DB_USER', 'root');
define('DB_PASS', '');

define('GOOGLE_CLIENT_ID', '');
define('BASE_URL', '');

$ALLOWED_ROLES = ['client', 'contractor', 'admin'];

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
    return $pdo;
}

function set_json_header(): void {
    header('Content-Type: application/json; charset=utf-8');
}

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    set_json_header();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}