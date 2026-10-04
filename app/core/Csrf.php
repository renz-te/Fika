<?php

class Csrf {
    /**
     * Generate or retrieve the CSRF token for the session
     */
    public static function getToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify a given CSRF token against the session
     */
    public static function verify(?string $token): bool {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Terminate execution if a valid CSRF token is not provided in a POST request
     */
    public static function requireValid(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if (!self::verify($token)) {
                http_response_code(419); // 419 Page Expired
                die(json_encode(['error' => 'CSRF token validation failed. Request aborted.']));
            }
        }
    }
}
