<?php

class Money {
    /**
     * Convert float/string decimal representation (e.g., from UI or DB DECIMAL) into integer centavos
     */
    public static function toCentavos(string|float|int $amount): int {
        return (int) round(((float) $amount) * 100);
    }

    /**
     * Convert integer centavos into decimal string (suitable for DB DECIMAL(12,2) insertion)
     */
    public static function toDecimal(int $centavos): string {
        return sprintf('%.2f', $centavos / 100);
    }

    /**
     * Format integer centavos as localized currency (e.g., PHP 150.00)
     */
    public static function format(int $centavos, string $currency = '₱'): string {
        return $currency . number_format($centavos / 100, 2);
    }
}
