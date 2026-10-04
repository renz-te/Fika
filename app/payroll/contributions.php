<?php

class Contributions {
    /**
     * Pure function to calculate SSS contributions based on monthly base pay.
     * Using a simplified 4.5% Employee and 9.5% Employer rate.
     */
    public static function calculateSSS(int $monthlyBasePayCentavos): array {
        $employee = (int) ($monthlyBasePayCentavos * 0.045);
        $employer = (int) ($monthlyBasePayCentavos * 0.095);
        return ['employee' => $employee, 'employer' => $employer];
    }

    /**
     * Pure function to calculate PhilHealth contributions (5% split equally).
     */
    public static function calculatePhilHealth(int $monthlyBasePayCentavos): array {
        $share = (int) ($monthlyBasePayCentavos * 0.025);
        return ['employee' => $share, 'employer' => $share];
    }

    /**
     * Pure function to calculate Pag-IBIG. 
     * Simplified fixed max contribution of 100 PHP (10000 centavos) each.
     */
    public static function calculatePagIbig(int $monthlyBasePayCentavos): array {
        return ['employee' => 10000, 'employer' => 10000];
    }
    
    /**
     * Aggregates all contributions.
     */
    public static function calculateTotalDeductions(int $monthlyBasePayCentavos): array {
        $sss = self::calculateSSS($monthlyBasePayCentavos);
        $ph = self::calculatePhilHealth($monthlyBasePayCentavos);
        $pi = self::calculatePagIbig($monthlyBasePayCentavos);
        
        return [
            'employee' => $sss['employee'] + $ph['employee'] + $pi['employee'],
            'employer' => $sss['employer'] + $ph['employer'] + $pi['employer'],
            'breakdown' => [
                'sss' => $sss,
                'philhealth' => $ph,
                'pagibig' => $pi
            ]
        ];
    }
}
