<?php
$config = require __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 1. Authentication Check
$current_page = basename($_SERVER['PHP_SELF']);
$public_pages = ['login.php', 'forgot_password.php', 'reset_password.php', 'verify.php'];

if (!isset($_SESSION['user']) && !in_array($current_page, $public_pages)) {
    header('Location: login');
    exit();
}

// 2. Authorization Check for Admin Modules
$admin_pages = ['finance_budgeting.php', 'finance_reports.php', 'system_accounts.php', 'settings.php'];
if (in_array($current_page, $admin_pages) && isset($_SESSION['user'])) {
    $role = $_SESSION['user']['role'] ?? '';
    // If not in a higher management role, deny access
    if (!in_array($role, ['System Admin', 'Super Admin', 'Central HR', 'Executives', 'Admin'])) {
        header("Location: dashboard.php?error=unauthorized");
        exit();
    }
}
date_default_timezone_set('Asia/Manila');
if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

if (!defined('APP_KEY')) {
    define('APP_KEY', 'xK9pL2vM8qJ5wH4tN7eR3yA6cZ1bF0dG');
}

try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'"
    ]);
} catch (PDOException $ex) {
    die('Database connection failed: ' . htmlspecialchars($ex->getMessage()));
}

require_once __DIR__ . '/functions.php';

// Session timeout enforcement
if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $config['app']['session_timeout']) {
    session_unset();
    session_destroy();
}
$_SESSION['last_activity'] = time();

// Strict Routing for Employee Portal
if (isset($_SESSION['user']) && in_array($_SESSION['user']['role'], ['Employee', 'Barista', 'Head Barista'])) {
    $currentScript = basename($_SERVER['SCRIPT_NAME']);
    $allowedEssPages = ['ess.php', 'shift_board.php', 'rate_staff.php', 'performance.php', 'performance_form.php', 'settings.php', 'read_notif.php', 'logout.php', 'login.php'];
    if (!in_array($currentScript, $allowedEssPages)) {
        header('Location: ess');
        exit;
    }
}
