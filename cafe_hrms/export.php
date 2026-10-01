<?php
require_once __DIR__ . '/init.php';
require_login();
$type = $_GET['type'] ?? 'employee';
$fileName = 'export_' . $type . '_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $fileName);
$output = fopen('php://output', 'w');
if ($type === 'employee') {
    fputcsv($output, ['Employee ID', 'Full Name', 'Email', 'Department', 'Position', 'Status', 'Date Hired']);
    $rows = $pdo->query('SELECT employee_id, CONCAT(first_name, " ", last_name) AS full_name, email, department, position, status, date_hired FROM employees ORDER BY first_name')->fetchAll();
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
} elseif ($type === 'payroll') {
    fputcsv($output, ['Employee', 'Gross Pay', 'Deductions', 'Net Pay', 'Date']);
    $rows = $pdo->query('SELECT CONCAT(e.first_name, " ",   e.last_name) AS full_name, p.gross_pay, p.deductions, p.net_pay, p.created_at FROM payroll p JOIN employees e ON e.id = p.employee_id ORDER BY p.created_at DESC')->fetchAll();
    foreach ($rows as $row) {
        fputcsv($output, [$row['full_name'], $row['gross_pay'], $row['deductions'], $row['net_pay'], $row['created_at']]);
    }
} elseif ($type === 'attendance') {
    fputcsv($output, ['Employee', 'Time In', 'Time Out', 'OT Hours', 'Undertime Hours', 'Status']);
    $rows = $pdo->query('SELECT CONCAT(e.first_name, " ", e.last_name) AS full_name, a.time_in, a.time_out, a.overtime_hours, a.undertime_hours, a.status FROM attendance a JOIN employees e ON e.id = a.employee_id ORDER BY a.time_in DESC')->fetchAll();
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
} else {
    fputcsv($output, ['Message']);
    fputcsv($output, ['Invalid export type.']);
}
fclose($output);
