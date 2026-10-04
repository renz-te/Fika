<?php

// 1. Core setup
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// 2. Load Helpers & Classes
require_once APP_ROOT . '/app/helpers.php';
require_once APP_ROOT . '/app/Database.php';
require_once APP_ROOT . '/app/Auth.php';
require_once APP_ROOT . '/app/AuditLog.php';

// 3. Timezone
date_default_timezone_set(config('app.timezone', 'Asia/Manila'));

// 4. Error Reporting (Safe defaults)
// "No display_errors outside local dev." 
// We assume this is checked via environment, but we'll disable it for safety.
ini_set('display_errors', '0');
error_reporting(E_ALL);

// 5. Session Initialization
if (session_status() === PHP_SESSION_NONE) {
    // Basic secure session cookie params (HTTPS only if possible in prod)
    session_set_cookie_params([
        'lifetime' => config('app.session_timeout', 3600),
        'path' => '/',
        'domain' => '',
        'secure' => false, // Set to true in prod with HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// 6. Session Timeout Enforcement
$timeout = config('app.session_timeout', 3600);
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

// 7. Ensure CSRF is generated for the session
Auth::generateCsrfToken();
