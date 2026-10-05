<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/attendance_calc.php';

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

echo "\nTests Completed. Failed: {$failed}\n";
if ($failed > 0) {
    exit(1);
}
