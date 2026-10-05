<?php
require_once __DIR__ . '/contributions.php';
require_once __DIR__ . '/withholding.php';

class PayrollEngine {
    /**
     * Pure function to calculate a semi-monthly payslip.
     * Guaranteed to be 100% deterministic with NO database reads or side-effects.
     * All monetary inputs and outputs must be integer centavos to avoid float rounding bugs.
     * 
     * @param int $monthlyBasePayCentavos Full monthly salary.
     * @param float $hoursWorked Total basic hours rendered in this cutoff.
     * @param float $overtimeHours Total overtime hours rendered.
     * @param float $standardHoursPerPeriod The exact standard hours for this period (e.g. 104 for a 13-day cutoff).
     * @param int $bonusCentavos Pre-tax allowances or bonuses.
     * @param bool $deductContributions True if SSS, PhilHealth, PagIBIG should be deducted this cutoff.
     * @return array Calculated breakdown of the payslip.
     */
    public static function calculate_payslip(
        int $monthlyBasePayCentavos,
        float $hoursWorked, // Keep name for compat, used as worked units if HOURLY
        float $overtimeHours,
        float $standardHoursPerPeriod, // Ignored for MONTHLY now
        int $bonusCentavos,
        bool $deductContributions,
        string $payType = 'MONTHLY',
        float $absentDays = 0.0,
        int $lateMinutes = 0,
        int $cutoffNumber = 1
    ): array {
        $periodBasePayCentavos = (int) ($monthlyBasePayCentavos / 2);
        
        // DOLE: 22 working days/mo, 8 hrs/day = 176 hrs/mo
        $dailyRateCentavos = (int) ($monthlyBasePayCentavos / 22);
        $hourlyRateCentavos = (int) ($monthlyBasePayCentavos / 176); 

        if ($payType === 'MONTHLY') {
            // Formula: period base pay minus (absent days x daily rate) minus late/undertime at the hourly rate
            $absentDeduction = (int) ($absentDays * $dailyRateCentavos);
            $lateDeduction = (int) ($lateMinutes * ($hourlyRateCentavos / 60));
            $basicPayCentavos = $periodBasePayCentavos - $absentDeduction - $lateDeduction;
        } else {
            // DAILY/HOURLY: days or hours worked x rate
            $basicPayCentavos = (int) ($hoursWorked * $hourlyRateCentavos);
        }
        
        if ($basicPayCentavos < 0) $basicPayCentavos = 0;
        
        // Overtime (125% of basic hourly rate)
        $otRateCentavos = (int) ($hourlyRateCentavos * 1.25);
        $overtimePayCentavos = (int) ($overtimeHours * $otRateCentavos);

        // Gross Pay
        $grossPayCentavos = $basicPayCentavos + $overtimePayCentavos + $bonusCentavos;

        // Contributions computation
        $totalContributions = 0;
        $breakdown = [];
        if ($deductContributions) {
            $contribs = Contributions::calculateTotalDeductions($monthlyBasePayCentavos);
            
            $employeeTotal = $contribs['employee'];
            $sssTotal = $contribs['breakdown']['sss']['employee'];
            $phTotal = $contribs['breakdown']['philhealth']['employee'];
            $piTotal = $contribs['breakdown']['pagibig']['employee'];

            if ($cutoffNumber === 1) {
                $totalContributions = (int)floor($employeeTotal / 2);
                $breakdown = [
                    'sss' => (int)floor($sssTotal / 2),
                    'philhealth' => (int)floor($phTotal / 2),
                    'pagibig' => (int)floor($piTotal / 2)
                ];
            } else { // Cutoff 2 handles any rounding remainder
                $firstTotal = (int)floor($employeeTotal / 2);
                $totalContributions = $employeeTotal - $firstTotal;
                
                $breakdown = [
                    'sss' => $sssTotal - (int)floor($sssTotal / 2),
                    'philhealth' => $phTotal - (int)floor($phTotal / 2),
                    'pagibig' => $piTotal - (int)floor($piTotal / 2)
                ];
            }
        }

        // Taxable Income (Simplified: Gross - Contributions)
        $taxableIncomeCentavos = $grossPayCentavos - $totalContributions;
        if ($taxableIncomeCentavos < 0) {
            $taxableIncomeCentavos = 0;
        }

        // Withholding Tax
        $taxCentavos = Withholding::calculateTax($taxableIncomeCentavos, 'semi-monthly');

        // Final Net Pay
        $netPayCentavos = $grossPayCentavos - $totalContributions - $taxCentavos;
        
        return [
            'gross_pay' => $grossPayCentavos,
            'basic_pay' => $basicPayCentavos,
            'overtime_pay' => $overtimePayCentavos,
            'bonus_pay' => $bonusCentavos,
            'deductions' => [
                'total_contributions' => $totalContributions,
                'sss' => $breakdown['sss'] ?? 0,
                'philhealth' => $breakdown['philhealth'] ?? 0,
                'pagibig' => $breakdown['pagibig'] ?? 0,
                'withholding_tax' => $taxCentavos
            ],
            'net_pay' => $netPayCentavos,
            'hourly_rate' => $hourlyRateCentavos,
            'daily_rate' => $dailyRateCentavos
        ];
    }
}
