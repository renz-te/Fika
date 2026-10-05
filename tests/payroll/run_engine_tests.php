<?php
require_once __DIR__ . '/../../app/payroll/payroll_engine.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.");
}

echo "Running Payroll Engine Tests vs FIXTURES.md...\n\n";
$failed = 0;
$changed = [];

function assertPayroll($name, $result, $expectedNetPayCentavos) {
    global $failed, $changed;
    if ($result['net_pay'] === $expectedNetPayCentavos) {
        echo "[PASS] {$name} => ₱" . number_format($result['net_pay']/100, 2) . "\n";
    } else {
        echo "[FAIL] {$name}\n";
        echo "  Expected Net: ₱" . number_format($expectedNetPayCentavos/100, 2) . "\n";
        echo "  Got Net:      ₱" . number_format($result['net_pay']/100, 2) . "\n";
        print_r($result);
        $failed++;
    }
}

// Scenario 1: Full-time (Cutoff 1)
$res1 = PayrollEngine::calculate_payslip(
    monthlyBasePayCentavos: 3000000,
    hoursWorked: 0,
    overtimeHours: 0,
    standardHoursPerPeriod: 0,
    bonusCentavos: 0,
    deductContributions: true,
    payType: 'MONTHLY',
    absentDays: 0,
    lateMinutes: 0,
    cutoffNumber: 1
);
assertPayroll("Scenario 1 (Full-time Cutoff 1)", $res1, 1337750);
$changed[] = "Scenario 1 (Full-time Cutoff 1)";

// Scenario 1b: Full-time (Cutoff 2)
$res1b = PayrollEngine::calculate_payslip(
    monthlyBasePayCentavos: 3000000,
    hoursWorked: 0,
    overtimeHours: 0,
    standardHoursPerPeriod: 0,
    bonusCentavos: 0,
    deductContributions: true,
    payType: 'MONTHLY',
    absentDays: 0,
    lateMinutes: 0,
    cutoffNumber: 2
);
assertPayroll("Scenario 1b (Full-time Cutoff 2)", $res1b, 1337750);
$changed[] = "Scenario 1b (Full-time Cutoff 2)";

// Cutoff check
if (($res1['deductions']['total_contributions'] + $res1b['deductions']['total_contributions']) === 220000) {
    echo "[PASS] Cutoff 1 + Cutoff 2 Contributions sum exactly to Monthly (220000)\n";
} else {
    echo "[FAIL] Cutoffs do not sum to monthly contribution!\n";
    $failed++;
}

// Scenario 2: 1 Absence
$res2 = PayrollEngine::calculate_payslip(
    monthlyBasePayCentavos: 3000000,
    hoursWorked: 0,
    overtimeHours: 0,
    standardHoursPerPeriod: 0,
    bonusCentavos: 0,
    deductContributions: true,
    payType: 'MONTHLY',
    absentDays: 1,
    lateMinutes: 0,
    cutoffNumber: 1
);
assertPayroll("Scenario 2 (1 Absence)", $res2, 1221841);
$changed[] = "Scenario 2 (1 Absence)";

// Scenario 3: 2 Absences
$res3 = PayrollEngine::calculate_payslip(
    monthlyBasePayCentavos: 3000000,
    hoursWorked: 0,
    overtimeHours: 0,
    standardHoursPerPeriod: 0,
    bonusCentavos: 0,
    deductContributions: true,
    payType: 'MONTHLY',
    absentDays: 2,
    lateMinutes: 0,
    cutoffNumber: 1
);
assertPayroll("Scenario 3 (2 Absences)", $res3, 1105933);
$changed[] = "Scenario 3 (2 Absences)";

// Property Check: Net pay never increases when absences increase
if ($res3['basic_pay'] <= $res2['basic_pay'] && $res2['basic_pay'] <= $res1['basic_pay']) {
    echo "[PASS] Property: Basic pay never increases when absences increase\n";
} else {
    echo "[FAIL] Property violated: Absences increased but basic pay increased!\n";
    $failed++;
}

// Scenario 4: Late Minutes
$res4 = PayrollEngine::calculate_payslip(
    monthlyBasePayCentavos: 3000000,
    hoursWorked: 0,
    overtimeHours: 0,
    standardHoursPerPeriod: 0,
    bonusCentavos: 0,
    deductContributions: true,
    payType: 'MONTHLY',
    absentDays: 0,
    lateMinutes: 45,
    cutoffNumber: 1
);
assertPayroll("Scenario 4 (Late Minutes)", $res4, 1326884);
$changed[] = "Scenario 4 (Late Minutes)";

// Scenario 5: Approved Unpaid Leave (Same as 1 absence)
$res5 = PayrollEngine::calculate_payslip(
    monthlyBasePayCentavos: 3000000,
    hoursWorked: 0,
    overtimeHours: 0,
    standardHoursPerPeriod: 0,
    bonusCentavos: 0,
    deductContributions: true,
    payType: 'MONTHLY',
    absentDays: 1,
    lateMinutes: 0,
    cutoffNumber: 1
);
assertPayroll("Scenario 5 (Approved Unpaid Leave)", $res5, 1221841);
$changed[] = "Scenario 5 (Approved Unpaid Leave)";

// Scenario 6: Approved Paid Leave (Same as full attendance)
$res6 = PayrollEngine::calculate_payslip(
    monthlyBasePayCentavos: 3000000,
    hoursWorked: 0,
    overtimeHours: 0,
    standardHoursPerPeriod: 0,
    bonusCentavos: 0,
    deductContributions: true,
    payType: 'MONTHLY',
    absentDays: 0,
    lateMinutes: 0,
    cutoffNumber: 1
);
assertPayroll("Scenario 6 (Approved Paid Leave)", $res6, 1337750);
$changed[] = "Scenario 6 (Approved Paid Leave)";

// Scenario 7: Hourly Part-timer (Unchanged)
$res7 = PayrollEngine::calculate_payslip(
    monthlyBasePayCentavos: 2000000,
    hoursWorked: 80,
    overtimeHours: 0,
    standardHoursPerPeriod: 104,
    bonusCentavos: 0,
    deductContributions: false,
    payType: 'HOURLY'
);
assertPayroll("Scenario 7 (Hourly Part-timer)", $res7, 909040);


echo "\n--- Changed Fixtures ---\n";
foreach ($changed as $c) {
    echo "- $c\n";
}

echo "\nTests Completed. Failed: {$failed}\n";
if ($failed > 0) {
    exit(1);
}
