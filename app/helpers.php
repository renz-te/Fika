<?php

/**
 * Retrieve configuration value from config files.
 * Example: config('app.base_url') gets the 'base_url' key from config/app.php
 */
function config(string $key, $default = null) {
    static $cache = [];
    
    $parts = explode('.', $key);
    $fileKey = $parts[0];
    
    if (!isset($cache[$fileKey])) {
        $filePath = __DIR__ . '/../config/' . $fileKey . '.php';
        if (!file_exists($filePath)) {
            return $default;
        }
        $cache[$fileKey] = require $filePath;
    }
    
    $configData = $cache[$fileKey];
    
    if (count($parts) === 1) {
        return $configData;
    }
    
    $val = $configData;
    for ($i = 1; $i < count($parts); $i++) {
        if (!is_array($val) || !array_key_exists($parts[$i], $val)) {
            return $default;
        }
        $val = $val[$parts[$i]];
    }
    
    return $val;
}

/**
 * Convert money from DB DECIMAL(12,2) string/float to integer centavos in PHP.
 */
function from_db_money(string|float $dbAmount): int {
    return (int) round(((float) $dbAmount) * 100);
}

/**
 * Convert integer centavos to DB DECIMAL(12,2) string format.
 */
function to_db_money(int $centavos): string {
    return sprintf('%.2f', $centavos / 100);
}

/**
 * Escape HTML for output
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
