<?php

class Withholding {
    /**
     * Pure function to calculate Withholding Tax (TRAIN Law - Semi-monthly).
     * Inputs and outputs are strictly in integer centavos.
     */
    public static function calculateTax(int $taxableIncomeCentavos, string $period = 'semi-monthly'): int {
        if ($period !== 'semi-monthly') {
            throw new InvalidArgumentException("Only semi-monthly tax brackets implemented.");
        }
        
        // Semi-monthly brackets in centavos (2023+ rates)
        if ($taxableIncomeCentavos <= 1041667) { 
            // 10,416.67 PHP
            return 0;
        } elseif ($taxableIncomeCentavos <= 1666667) {
            // 16,666.67 PHP
            return (int) round(0.15 * ($taxableIncomeCentavos - 1041667));
        } elseif ($taxableIncomeCentavos <= 3333333) {
            // 33,333.33 PHP
            return 93750 + (int) round(0.20 * ($taxableIncomeCentavos - 1666667));
        } elseif ($taxableIncomeCentavos <= 8333333) {
            // 83,333.33 PHP
            return 427083 + (int) round(0.25 * ($taxableIncomeCentavos - 3333333));
        } elseif ($taxableIncomeCentavos <= 33333333) {
            // 333,333.33 PHP
            return 1677083 + (int) round(0.30 * ($taxableIncomeCentavos - 8333333));
        } else {
            return 9177083 + (int) round(0.35 * ($taxableIncomeCentavos - 33333333));
        }
    }
}
