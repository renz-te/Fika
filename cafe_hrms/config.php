<?php
// Database and application configuration
$is_local = in_array($_SERVER['HTTP_HOST'] ?? 'localhost', ['localhost', '127.0.0.1']);

$db_host = $is_local ? 'localhost' : 'sql312.infinityfree.com';
$db_user = $is_local ? 'root' : 'if0_43057509';
$db_pass = $is_local ? '' : 'SN202459193';
$db_name = $is_local ? 'hrms' : 'if0_43057509_fika';

return [
    'db' => [
        'host' => $db_host,
        'name' => $db_name,
        'user' => $db_user,
        'pass' => $db_pass,
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'Café HRMS',
        'url' => '',
        'theme' => 'light',
        'session_timeout' => 1800, // 30 minutes
    ],
];
