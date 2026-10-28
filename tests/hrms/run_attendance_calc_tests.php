<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/attendance_calc.php';
require_once __DIR__ . '/../../app/lib/attendance_summary.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.");
}

echo "Running HRMS Attendance Calc Tests...\n\n";
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

// Ensure Manila timezone
date_default_timezone_set('Asia/Manila');

// 1. Standard 8-hour shift, no late, no ND, no OT.
// Clock In: 08:00 AM Manila -> UTC 00:00:00
// Clock Out: 04:00 PM Manila -> UTC 08:00:00
$res1 = AttendanceCalc::calculate('2026-10-06 00:00:00', '2026-10-06 08:00:00', '2026-10-06 00:00:00', 15);
assertTest("Standard 8h Shift - Worked Mins", $res1['worked_minutes'] === 480, "Got {$res1['worked_minutes']}");
assertTest("Standard 8h Shift - Late", $res1['late_minutes'] === 0, "Got {$res1['late_minutes']}");
assertTest("Standard 8h Shift - OT", $res1['overtime_minutes'] === 0, "Got {$res1['overtime_minutes']}");
assertTest("Standard 8h Shift - ND", $res1['night_diff_minutes'] === 0, "Got {$res1['night_diff_minutes']}");

// 2. Late within grace period (10 minutes late)
// Clock In: 08:10 AM Manila -> UTC 00:10:00
$res2 = AttendanceCalc::calculate('2026-10-06 00:10:00', '2026-10-06 08:00:00', '2026-10-06 00:00:00', 15);
assertTest("Late within grace - Late", $res2['late_minutes'] === 0, "Got {$res2['late_minutes']}");

// 3. Late beyond grace period (20 minutes late)
// Clock In: 08:20 AM Manila -> UTC 00:20:00
$res3 = AttendanceCalc::calculate('2026-10-06 00:20:00', '2026-10-06 08:00:00', '2026-10-06 00:00:00', 15);
assertTest("Late beyond grace - Late", $res3['late_minutes'] === 20, "Got {$res3['late_minutes']} instead of 20");

// 4. Overtime (10 hours total = 600 mins -> 120 mins OT)
$res4 = AttendanceCalc::calculate('2026-10-06 00:00:00', '2026-10-06 10:00:00', '2026-10-06 00:00:00', 15);
assertTest("Overtime computation", $res4['overtime_minutes'] === 120, "Got {$res4['overtime_minutes']}");

// 5. Midnight Crossing & Night Diff
// 8:00 PM (Manila) to 5:00 AM (Manila) next day.
// 20:00 Manila -> UTC 12:00:00 (Day 1)
// 05:00 Manila -> UTC 21:00:00 (Day 1) -> wait, 05:00 Manila is -8h = 21:00 previous day!
// So:
// In: 2026-10-06 12:00:00 UTC (20:00 Manila)
// Out: 2026-10-06 21:00:00 UTC (05:00 Manila next day)
$res5 = AttendanceCalc::calculate('2026-10-06 12:00:00', '2026-10-06 21:00:00', '2026-10-06 12:00:00', 15);
// Total worked: 9 hours = 540 mins
// OT: 9h - 8h = 1h = 60 mins
// ND (22:00 to 05:00): 7 hours = 420 mins
assertTest("Midnight crossing - Worked Mins", $res5['worked_minutes'] === 540, "Got {$res5['worked_minutes']}");
assertTest("Midnight crossing - OT", $res5['overtime_minutes'] === 60, "Got {$res5['overtime_minutes']}");
assertTest("Midnight crossing - ND", $res5['night_diff_minutes'] === 420, "Got {$res5['night_diff_minutes']}");

// 6. Afternoon/Closing Shift (14:00 Start) - Early Clock-in (13:57)
// Scheduled: 14:00 Manila (06:00 UTC)
// Clock In: 13:57 Manila (05:57 UTC)
// Clock Out: 22:02 Manila (14:02 UTC)
$res6 = AttendanceCalc::calculate('2026-10-06 05:57:00', '2026-10-06 14:02:00', '2026-10-06 06:00:00', 15);
assertTest("14:00 Shift - Early clock-in (13:57) has 0 late minutes", $res6['late_minutes'] === 0, "Got {$res6['late_minutes']}");

// 7. Afternoon/Closing Shift (14:00 Start) - Clock-in within grace (14:10)
$res7 = AttendanceCalc::calculate('2026-10-06 06:10:00', '2026-10-06 14:02:00', '2026-10-06 06:00:00', 15);
assertTest("14:00 Shift - Clock-in within grace (14:10) has 0 late minutes", $res7['late_minutes'] === 0, "Got {$res7['late_minutes']}");

// 8. Afternoon/Closing Shift (14:00 Start) - Late beyond grace period (14:20)
// Clock In: 14:20 Manila (06:20 UTC) -> 20 minutes late (5m beyond 15m grace)
$res8 = AttendanceCalc::calculate('2026-10-06 06:20:00', '2026-10-06 14:02:00', '2026-10-06 06:00:00', 15);
assertTest("14:00 Shift - Late clock-in (14:20) calculates 20 late minutes", $res8['late_minutes'] === 20, "Got {$res8['late_minutes']}");

// 9. Integration Test: attendance_summary() with dynamic shift start_time from DB
global $pdo;
$testEmpId = 8889;
$pdo->beginTransaction();
try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("DELETE FROM attendance_logs WHERE employee_id = {$testEmpId};");
    $pdo->exec("DELETE FROM shifts WHERE employee_id = {$testEmpId};");
    $pdo->exec("DELETE FROM employees WHERE id = {$testEmpId};");

    $pdo->exec("INSERT INTO employees (id, employee_code, first_name, last_name, email, branch_id, status) VALUES ({$testEmpId}, 'EMP_TEST_14', 'Test', 'Shift', 't14@fika.local', 1, 'ACTIVE');");
    
    // Scheduled Closing shift: 14:00:00 to 22:00:00
    $pdo->exec("INSERT INTO shifts (branch_id, employee_id, shift_date, start_time, end_time, shift_type, status, created_by) VALUES (1, {$testEmpId}, '2026-10-15', '14:00:00', '22:00:00', 'Closing', 'PUBLISHED', 1);");

    // Test A: Early clock in at 13:57 for 14:00 shift -> late_minutes = 0
    $pdo->exec("INSERT INTO attendance_logs (employee_id, branch_id, clock_in, clock_out, work_date, status) VALUES ({$testEmpId}, 1, '2026-10-15 13:57:00', '2026-10-15 22:02:00', '2026-10-15', 'CLOSED');");
    $sumEarly = attendance_summary($testEmpId, '2026-10-15', '2026-10-15');
    assertTest("attendance_summary() - 13:57 clock-in on 14:00 shift has 0 late minutes", $sumEarly['late_minutes'] === 0, "Got {$sumEarly['late_minutes']}");

    // Test B: Late clock in at 14:20 for 14:00 shift -> late_minutes = 20
    $pdo->exec("UPDATE attendance_logs SET clock_in = '2026-10-15 14:20:00' WHERE employee_id = {$testEmpId};");
    $sumLate = attendance_summary($testEmpId, '2026-10-15', '2026-10-15');
    assertTest("attendance_summary() - 14:20 clock-in on 14:00 shift calculates 20 late minutes", $sumLate['late_minutes'] === 20, "Got {$sumLate['late_minutes']}");

    // Clean up
    $pdo->rollBack();
} catch (\Throwable $e) {
    $pdo->rollBack();
    echo "[FAIL] Integration test exception: " . $e->getMessage() . "\n";
    $failed++;
} finally {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
}

echo "\nTests Completed. Failed: {$failed}\n";
if ($failed > 0) {
    exit(1);
}
