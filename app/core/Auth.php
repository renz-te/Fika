<?php

class Auth {
    
    public static function attemptLogin(string $username, string $password): bool {
        global $pdo;

        // Rate limiting check
        if (self::isRateLimited($username)) {
            return false;
        }

        $stmt = $pdo->prepare("SELECT id, password, role_id, employee_id, branch_id FROM users WHERE username = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            self::logAttempt($username, true);
            self::loginUser($user);
            return true;
        }

        self::logAttempt($username, false);
        return false;
    }

    private static function isRateLimited(string $username): bool {
        global $pdo;
        // Check if there are 5 or more failed attempts in the last 15 minutes
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_attempts 
            WHERE username = ? AND success = 0 AND created_at > (NOW() - INTERVAL 15 MINUTE)
        ");
        $stmt->execute([$username]);
        return (int) $stmt->fetchColumn() >= 5;
    }

    private static function logAttempt(string $username, bool $success): void {
        global $pdo;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO login_attempts (username, ip_address, success, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$username, $ip, $success ? 1 : 0]);
    }

    private static function loginUser(array $user): void {
        // Prevent session fixation
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => $user['id'],
            'role_id' => $user['role_id'],
            'employee_id' => $user['employee_id'],
            'branch_id' => $user['branch_id']
        ];
        $_SESSION['last_activity'] = time();
    }

    public static function check(): bool {
        return isset($_SESSION['user']);
    }

    public static function requireLogin(): void {
        if (!self::check()) {
            http_response_code(401);
            $loginUrl = config('app.base_url') . '/fika_hrms_login.php';
            header("Location: {$loginUrl}");
            exit();
        }
    }

    public static function logout(): void {
        session_unset();
        session_destroy();
    }

    public static function user(): ?array {
        return $_SESSION['user'] ?? null;
    }
}
