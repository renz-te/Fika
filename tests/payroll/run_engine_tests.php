<?php
require_once __DIR__ . '/../../app/payroll/payroll_engine.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.");
}

echo "Running Payroll Engine Tests vs FIXTURES.md...\n\n";
$failed = 0;

function assertPayroll($name, $result, $expectedNetPayCentavos) {
    global $failed;
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

// Scenario 1: Full-time
$res1 = PayrollEngine::calculate_payslip(
    monthlyBasePayCentavos: 3000000,
    hoursWorked: 104,
    overtimeHours: 0,
    standardHoursPerPeriod: 104,
    bonusCentavos: 0,
    deductContributions: true
);
assertPayroll("Scenario 1 (Full-time)", $res1, 1244250);

// Scenario 2: Part-time
$res2 = PayrollEngine::calculate_payslip(
    monthlyBasePayCentavos: 2000000,
    hoursWorked: 80,
    overtimeHours: 0,
    standardHoursPerPeriod: 104,
    bonusCentavos: 0,
    deductContributions: false
);
assertPayroll("Scenario 2 (Part-time)", $res2, 909040);

// Scenario 3: Overtime Power
$res3 = PayrollEngine::calculate_payslip(
    monthlyBasePayCentavos: 2000000,
    hoursWorked: 104,
    overtimeHours: 10,
    standardHoursPerPeriod: 104,
    bonusCentavos: 0,
    deductContributions: true
);
assertPayroll("Scenario 3 (Overtime Power)", $res3, 992030);

// Scenario 5: Mid-period Exit (Prorated)
$res5 = PayrollEngine::calculate_payslip(
    monthlyBasePayCentavos: 5000000,
    hoursWorked: 40,
    overtimeHours: 0,
    standardHoursPerPeriod: 104,
    bonusCentavos: 0,
    deductContributions: false
);
assertPayroll("Scenario 5 (Mid-period Exit)", $res5, 1122156);

echo "\nTests Completed. Failed: {$failed}\n";
if ($failed > 0) {
    exit(1);
}
