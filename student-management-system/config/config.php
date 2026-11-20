<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Auspify Student Management System - Configuration
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'httponly' => true,
        'secure'   => $secure,
        'samesite' => 'Lax',
    ]);

    session_start();
}

date_default_timezone_set('Asia/Karachi');

/*
|--------------------------------------------------------------------------
| Application URL
|--------------------------------------------------------------------------
| Local project folder:
| C:\xampp\htdocs\student-management-system
*/

define(
    'BASE_URL',
    rtrim(
        getenv('BASE_URL') ?: '/student-management-system',
        '/'
    )
);

/*
|--------------------------------------------------------------------------
| Database Configuration
|--------------------------------------------------------------------------
*/

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3307));
define('DB_NAME', getenv('DB_NAME') ?: 'auspify_sms');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST
        . ';port=' . DB_PORT
        . ';dbname=' . DB_NAME
        . ';charset=utf8mb4';

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function h($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
*/

function csrf(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function check_csrf(): void
{
    $token = (string)(
        $_POST['_csrf']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? ''
    );

    if (!hash_equals(csrf(), $token)) {
        http_response_code(419);
        exit('Session token expired. Refresh the page and try again.');
    }
}

/*
|--------------------------------------------------------------------------
| Admin Authentication
|--------------------------------------------------------------------------
*/

function logged(): bool
{
    return !empty($_SESSION['admin_id']);
}

function auth(): void
{
    if (!logged()) {
        header('Location: ' . url('login.php'));
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['_flash'] = [
        'message' => $message,
        'type'    => $type,
    ];
}

function popflash(): ?array
{
    $message = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);

    return $message;
}