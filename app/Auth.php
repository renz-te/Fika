<?php

class Auth {
    public static function check(): bool {
        return isset($_SESSION['user']);
    }

    public static function requireLogin(): void {
        if (!self::check()) {
            $loginUrl = config('app.base_url') . '/fika_hrms_login.php';
            header("Location: " . $loginUrl);
            exit();
        }
    }

    public static function requireRole(array $allowedRoles): void {
        self::requireLogin();
        $userRole = $_SESSION['user']['role'] ?? '';
        if (!in_array($userRole, $allowedRoles, true)) {
            http_response_code(403);
            die("Forbidden: Insufficient permissions.");
        }
    }

    public static function user(): ?array {
        return $_SESSION['user'] ?? null;
    }

    public static function generateCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrfToken(?string $token): bool {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function requireCsrf(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if (!self::verifyCsrfToken($token)) {
                http_response_code(419);
                die("Page Expired. CSRF token invalid.");
            }
        }
    }
}
