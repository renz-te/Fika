<?php

class Output {
    /**
     * Escape strings for safe HTML output
     */
    public static function e(?string $str): string {
        if ($str === null) return '';
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Escape data for safe JSON injection in inline script tags
     */
    public static function js(mixed $data): string {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }

    /**
     * Escape data for safe assignment inside HTML element attributes (e.g., data-* or onclick)
     */
    public static function attr_js(mixed $data): string {
        return htmlspecialchars(self::js($data), ENT_QUOTES, 'UTF-8');
    }
}

// Global shortcut for common template usage
if (!function_exists('e')) {
    function e(?string $str): string {
        return Output::e($str);
    }
}
