<?php
require_once __DIR__ . '/attendance_calc.php';

function attendance_summary(int $employee_id, string $date_from, string $date_to): array {
    global $pdo;

    // 1. Get late grace settings
    $graceMins = 15;
    $setStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'late_grace_minutes'");
    if ($val = $setStmt->fetchColumn()) {
        $graceMins = (int)$val;
    }

    // 2. Fetch all valid attendance logs
    $stmt = $pdo->prepare("
        SELECT * FROM attendance_logs 
        WHERE employee_id = ? 
        AND work_date BETWEEN ? AND ? 
        AND status IN ('CLOSED', 'ADJUSTED')
    ");
    $stmt->execute([$employee_id, $date_from, $date_to]);
    $logs = $stmt->fetchAll();

    $daysWorked = 0;
    $lateMinutes = 0;
    $otMinutes = 0;
    $ndMinutes = 0;

    $workedDates = [];

    foreach ($logs as $log) {
        if (!in_array($log['work_date'], $workedDates)) {
            $workedDates[] = $log['work_date'];
            $daysWorked++;
        }

        // Heuristic: Guess scheduled start by rounding clock_in to the nearest hour
        // (Since we have no shift schedule table, this reasonably assumes shifts start on the hour)
        $ts = strtotime($log['clock_in'] . ' UTC');
        $roundedTs = round($ts / 3600) * 3600;
        $assumedSchedule = gmdate('Y-m-d H:i:s', $roundedTs);
        
        $calc = AttendanceCalc::calculate($log['clock_in'], $log['clock_out'], $assumedSchedule, $graceMins);
        
        $lateMinutes += $calc['late_minutes'];
        $otMinutes += $calc['overtime_minutes'];
        $ndMinutes += $calc['night_diff_minutes'];
    }

    // 3. Fetch Leaves
    // To simplify, we sum the `days` of APPROVED leaves that START within the cutoff period.
    // A robust system would split the days across cutoffs, but for this slice, this is acceptable.
    $leaveStmt = $pdo->prepare("
        SELECT lr.days, lt.is_paid 
        FROM leave_requests lr
        JOIN leave_types lt ON lr.type = lt.code
        WHERE lr.employee_id = ? 
        AND lr.status = 'APPROVED'
        AND lr.date_from BETWEEN ? AND ?
    ");
    $leaveStmt->execute([$employee_id, $date_from, $date_to]);
    $leaves = $leaveStmt->fetchAll();

    $paidLeaveDays = 0;
    $unpaidLeaveDays = 0;
    foreach ($leaves as $l) {
        if ($l['is_paid']) {
            $paidLeaveDays += (float)$l['days'];
        } else {
            $unpaidLeaveDays += (float)$l['days'];
        }
    }

    // 4. Fetch Holidays in the period
    $holStmt = $pdo->prepare("
        SELECT COUNT(*) FROM holidays 
        WHERE date BETWEEN ? AND ?
    ");
    $holStmt->execute([$date_from, $date_to]);
    $holidayDays = (int) $holStmt->fetchColumn();

    // 5. Absences
    // If a period is 13 days, and they worked 10, had 1 paid leave, absences = 13 - (10 + 1) = 2.
    // We don't have standard working days in DB, so we calculate total weekdays (Mon-Fri) in period.
    // Or we simply return 0 and let HR adjust. The prompt requests the key 'absences'.
    // Let's compute business days (Mon-Fri) just as a rough estimate.
    $absences = 0;
    $begin = new DateTime($date_from);
    $end = new DateTime($date_to);
    $end->modify('+1 day'); // inclusive
    $interval = DateInterval::createFromDateString('1 day');
    $period = new DatePeriod($begin, $interval, $end);
    
    $businessDays = 0;
    foreach ($period as $dt) {
        if ($dt->format('N') < 6) { // 1-5 are Mon-Fri
            $businessDays++;
        }
    }
    
    $totalAccounted = $daysWorked + $paidLeaveDays + $unpaidLeaveDays + $holidayDays;
    $abs = $businessDays - $totalAccounted;
    $absences = $abs > 0 ? $abs : 0;

    return [
        'days_worked' => $daysWorked,
        'late_minutes' => $lateMinutes,
        'ot_minutes' => $otMinutes,
        'night_diff_minutes' => $ndMinutes,
        'holiday_days' => $holidayDays,
        'paid_leave_days' => $paidLeaveDays,
        'unpaid_leave_days' => $unpaidLeaveDays,
        'absences' => $absences,
    ];
}
