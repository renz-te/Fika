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
        float $hoursWorked,
        float $overtimeHours,
        float $standardHoursPerPeriod,
        int $bonusCentavos,
        bool $deductContributions
    ): array {
        $periodBasePayCentavos = (int) ($monthlyBasePayCentavos / 2);
        
        // Standardized Daily/Hourly computation based on DOLE (22 working days/mo, 8 hrs/day = 176 hrs/mo)
        $hourlyRateCentavos = (int) ($monthlyBasePayCentavos / 176); 

        // Basic Pay computation (prorated if hours worked < standard)
        if ($hoursWorked >= $standardHoursPerPeriod) {
            $basicPayCentavos = $periodBasePayCentavos;
        } else {
            $basicPayCentavos = (int) ($hoursWorked * $hourlyRateCentavos);
        }
        
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
            $totalContributions = $contribs['employee'];
            $breakdown = $contribs['breakdown'];
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
                'sss' => $breakdown['sss']['employee'] ?? 0,
                'philhealth' => $breakdown['philhealth']['employee'] ?? 0,
                'pagibig' => $breakdown['pagibig']['employee'] ?? 0,
                'withholding_tax' => $taxCentavos
            ],
            'net_pay' => $netPayCentavos,
            'hourly_rate' => $hourlyRateCentavos
        ];
    }
}
