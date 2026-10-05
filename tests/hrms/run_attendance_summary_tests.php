<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/attendance_summary.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.");
}

echo "Running HRMS Attendance Summary Tests...\n\n";
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
$pdo->exec("DELETE FROM attendance_logs WHERE employee_id = 8888;");
$pdo->exec("DELETE FROM leave_requests WHERE employee_id = 8888;");
$pdo->exec("DELETE FROM holidays WHERE date LIKE '2026-10-%';");
$pdo->exec("DELETE FROM employees WHERE id = 8888;");
$pdo->exec("DELETE FROM leave_types WHERE code IN ('VAC', 'SICK');");

$pdo->exec("INSERT INTO employees (id, employee_code, first_name, last_name, email, branch_id, status) VALUES (8888, 'EMP_SUM', 'A', 'A', 'a@a', 1, 'ACTIVE');");
$pdo->exec("INSERT INTO leave_types (code, name, is_paid, annual_days, min_service_months) VALUES ('VAC', 'Vacation', 1, 5, 12), ('SICK', 'Sick', 0, 5, 0);");

// Fixture: October 2026 (Oct 1 to Oct 15 cutoff)
// 1. Oct 1 (Thu) - Normal 8h
$pdo->exec("INSERT INTO attendance_logs (employee_id, branch_id, clock_in, clock_out, work_date, status) VALUES (8888, 1, '2026-10-01 00:00:00', '2026-10-01 08:00:00', '2026-10-01', 'CLOSED');");
// 2. Oct 2 (Fri) - Late by 20m, 1h OT
$pdo->exec("INSERT INTO attendance_logs (employee_id, branch_id, clock_in, clock_out, work_date, status) VALUES (8888, 1, '2026-10-02 00:20:00', '2026-10-02 09:20:00', '2026-10-02', 'CLOSED');");
// 3. Oct 5 (Mon) - Paid Leave (1 day)
$pdo->exec("INSERT INTO leave_requests (employee_id, type, date_from, date_to, days, reason, status) VALUES (8888, 'VAC', '2026-10-05', '2026-10-05', 1, 'Rest', 'APPROVED');");
// 4. Oct 6 (Tue) - Unpaid Leave (0.5 day)
$pdo->exec("INSERT INTO leave_requests (employee_id, type, date_from, date_to, days, reason, status) VALUES (8888, 'SICK', '2026-10-06', '2026-10-06', 0.5, 'Sick', 'APPROVED');");
// 5. Oct 7 (Wed) - Holiday
$pdo->exec("INSERT INTO holidays (date, name, type) VALUES ('2026-10-07', 'Special Holiday', 'SPECIAL');");
// 6. Oct 8 (Thu) - ND shift (Midnight crossing: 20:00 to 05:00) -> 9 hours total (1h OT, 7h ND)
$pdo->exec("INSERT INTO attendance_logs (employee_id, branch_id, clock_in, clock_out, work_date, status) VALUES (8888, 1, '2026-10-08 12:00:00', '2026-10-08 21:00:00', '2026-10-08', 'CLOSED');");

// Let's run summary
$summary = attendance_summary(8888, '2026-10-01', '2026-10-15');

/*
Expected for Oct 1 to 15 (11 Business Days: 1,2, 5,6,7,8,9, 12,13,14,15):
- days_worked: 3 (Oct 1, 2, 8)
- late_minutes: 20 (Oct 2)
- ot_minutes: 60 (Oct 2) + 60 (Oct 8) = 120
- night_diff_minutes: 420 (Oct 8)
- holiday_days: 1 (Oct 7)
- paid_leave_days: 1 (Oct 5)
- unpaid_leave_days: 0.5 (Oct 6)
- absences: 11 business days - (3 worked + 1 paid + 0.5 unpaid + 1 holiday) = 11 - 5.5 = 5.5
*/

assertTest("Summary has exactly the expected keys", array_keys($summary) === ['days_worked', 'late_minutes', 'ot_minutes', 'night_diff_minutes', 'holiday_days', 'paid_leave_days', 'unpaid_leave_days', 'absences'], "Keys mismatch");

assertTest("Days worked is 3", $summary['days_worked'] === 3, "Got {$summary['days_worked']}");
assertTest("Late minutes is 20", $summary['late_minutes'] === 20, "Got {$summary['late_minutes']}");
assertTest("OT minutes is 120", $summary['ot_minutes'] === 120, "Got {$summary['ot_minutes']}");
assertTest("ND minutes is 420", $summary['night_diff_minutes'] === 420, "Got {$summary['night_diff_minutes']}");
assertTest("Holiday days is 1", $summary['holiday_days'] === 1, "Got {$summary['holiday_days']}");
assertTest("Paid leave days is 1", (float)$summary['paid_leave_days'] === 1.0, "Got {$summary['paid_leave_days']}");
assertTest("Unpaid leave days is 0.5", (float)$summary['unpaid_leave_days'] === 0.5, "Got {$summary['unpaid_leave_days']}");
assertTest("Absences is 5.5", (float)$summary['absences'] === 5.5, "Got {$summary['absences']}");

// Clean up
$pdo->exec("DELETE FROM attendance_logs WHERE employee_id = 8888;");
$pdo->exec("DELETE FROM leave_requests WHERE employee_id = 8888;");
$pdo->exec("DELETE FROM holidays WHERE date LIKE '2026-10-%';");
$pdo->exec("DELETE FROM employees WHERE id = 8888;");
$pdo->exec("DELETE FROM leave_types WHERE code IN ('VAC', 'SICK');");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "\nTests Completed. Failed: {$failed}\n";
if ($failed > 0) {
    exit(1);
}
