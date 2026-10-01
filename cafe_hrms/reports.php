<?php
require_once __DIR__ . '/init.php';
require_login();
$user = current_user();

$totals = [
    'employees' => $pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn(),
    'attendance' => $pdo->query('SELECT COUNT(*) FROM attendance')->fetchColumn(),
    'leaves' => $pdo->query('SELECT COUNT(*) FROM leaves')->fetchColumn(),
    'payroll' => $pdo->query('SELECT COUNT(*) FROM payroll')->fetchColumn(),
    'applicants' => $pdo->query('SELECT COUNT(*) FROM applicants')->fetchColumn(),
];
$recentPayroll = $pdo->query('SELECT p.*, CONCAT(e.first_name, " ", e.last_name) AS full_name FROM payroll p JOIN employees e ON e.id = p.employee_id ORDER BY p.created_at DESC LIMIT 6')->fetchAll();
$recentLeaves = $pdo->query('SELECT l.*, CONCAT(e.first_name, " ", e.last_name) AS full_name FROM leaves l JOIN employees e ON e.id = l.employee_id ORDER BY l.created_at DESC LIMIT 6')->fetchAll();
?>
