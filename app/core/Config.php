<?php

class Config {
    /**
     * Get a configuration value by 'file.key' syntax.
     * E.g. Config::get('paymongo.secret_key')
     */
    public static function get(string $key, mixed $default = null): mixed {
        [$file, $item] = array_pad(explode('.', $key, 2), 2, null);
        
        $configDir = dirname(__DIR__, 2) . '/config';
        $path = $configDir . '/' . $file . '.php';

        if (file_exists($path)) {
            $data = require $path;
            if ($item === null) {
                return $data ?? $default;
            }
            return $data[$item] ?? $default;
        }

        if (function_exists('config')) {
            return config($key, $default);
        }

        return $default;
    }
}
