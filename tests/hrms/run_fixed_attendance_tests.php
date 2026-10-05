<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/attendance_summary.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.");
}

echo "Running HRMS Fixed Attendance Tests...\n\n";
$failed = 0;

function assertTest($name, $condition, $failMsg) {
    global $failed;
    if ($condition) {
        echo "[PASS] {$name}\n";
    } else {
        echo "[FAIL] {$name}: {$failMsg}\n";
        $failed++;
    }
}

global $pdo;

// Prepare data
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM attendance_logs WHERE employee_id = 7777;");
$pdo->exec("DELETE FROM leave_requests WHERE employee_id = 7777;");
$pdo->exec("DELETE FROM holidays WHERE date LIKE '2026-11-%';");
$pdo->exec("DELETE FROM employees WHERE id = 7777;");
$pdo->exec("DELETE FROM leave_types WHERE code IN ('VAC');");

$pdo->exec("INSERT INTO employees (id, employee_code, first_name, last_name, email, branch_id, status, attendance_mode) VALUES (7777, 'EMP_FIXED', 'F', 'F', 'f@f', 1, 'ACTIVE', 'FIXED');");
$pdo->exec("INSERT INTO leave_types (code, name, is_paid, annual_days, min_service_months) VALUES ('VAC', 'Vacation', 1, 5, 12);");

// Fixture: Nov 2026 (Nov 1 to Nov 15 cutoff)
// 1. Nov 2 (Mon) - Paid Leave (1 day)
$pdo->exec("INSERT INTO leave_requests (employee_id, type, date_from, date_to, days, reason, status) VALUES (7777, 'VAC', '2026-11-02', '2026-11-02', 1, 'Vacation', 'APPROVED');");
// 2. Nov 3 (Tue) - Explicit Absence (Logged by HR)
$pdo->exec("INSERT INTO attendance_logs (employee_id, branch_id, clock_in, clock_out, work_date, status) VALUES (7777, 1, '2026-11-03 00:00:00', '2026-11-03 00:00:00', '2026-11-03', 'ABSENT');");

// Let's run summary
$summary = attendance_summary(7777, '2026-11-01', '2026-11-15');

/*
Expected for Nov 1 to 15 (Nov 1 is Sun. Nov 15 is Sun. Total 10 Business Days: 2,3,4,5,6, 9,10,11,12,13):
- absences: 1 (explicitly logged)
- paid_leave_days: 1
- days_worked: 10 - (1 absence + 1 paid leave) = 8
*/

assertTest("Summary has exactly the expected keys", array_keys($summary) === ['days_worked', 'late_minutes', 'ot_minutes', 'night_diff_minutes', 'holiday_days', 'paid_leave_days', 'unpaid_leave_days', 'absences'], "Keys mismatch");

assertTest("Days worked is 8", (float)$summary['days_worked'] === 8.0, "Got {$summary['days_worked']}");
assertTest("Paid leave days is 1", (float)$summary['paid_leave_days'] === 1.0, "Got {$summary['paid_leave_days']}");
assertTest("Absences is 1", (float)$summary['absences'] === 1.0, "Got {$summary['absences']}");

// Clean up
$pdo->exec("DELETE FROM attendance_logs WHERE employee_id = 7777;");
$pdo->exec("DELETE FROM leave_requests WHERE employee_id = 7777;");
$pdo->exec("DELETE FROM holidays WHERE date LIKE '2026-11-%';");
$pdo->exec("DELETE FROM employees WHERE id = 7777;");
$pdo->exec("DELETE FROM leave_types WHERE code IN ('VAC');");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "\nTests Completed. Failed: {$failed}\n";
if ($failed > 0) {
    exit(1);
}
