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

    public static function getLoginUrl(): string {
        if (file_exists('login.php')) {
            return 'login.php';
        }
        if (file_exists('../login.php')) {
            return '../login.php';
        }
        if (file_exists('../../login.php')) {
            return '../../login.php';
        }
        return '../login.php';
    }

    public static function requireLogin(): void {
        if (!self::check()) {
            $isApi = (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
                || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'))
                || (isset($_SERVER['REQUEST_URI']) && str_contains($_SERVER['REQUEST_URI'], '/api/'))
                || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

            if ($isApi) {
                http_response_code(401);
                header('Content-Type: application/json');
                exit(json_encode(['error' => 'Unauthorized: authentication required.']));
            }

            $loginUrl = self::getLoginUrl();
            header("Location: {$loginUrl}");
            exit();
        }
    }

    public static function logout(): void {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies") && !headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        if (!headers_sent() && php_sapi_name() !== 'cli') {
            header("Location: " . self::getLoginUrl());
            exit();
        }
    }

    public static function user(): ?array {
        return $_SESSION['user'] ?? null;
    }

    public static function getRedirectUrl(?array $user = null): string {
        global $pdo;
        if ($user === null) {
            $user = self::user();
        }
        if (!$user) {
            return 'login.php';
        }

        $roleId = (int)($user['role_id'] ?? 0);
        $employeeId = $user['employee_id'] ?? null;

        // If employee_id is not in user session, try resolving from users table
        if (!$employeeId && !empty($user['id']) && $pdo) {
            $uStmt = $pdo->prepare("SELECT employee_id, role_id FROM users WHERE id = ? LIMIT 1");
            $uStmt->execute([$user['id']]);
            $uRow = $uStmt->fetch();
            if ($uRow) {
                $employeeId = $uRow['employee_id'];
                if (!$roleId) {
                    $roleId = (int)$uRow['role_id'];
                }
            }
        }

        $roleName = '';
        if ($roleId > 0 && $pdo) {
            $stmt = $pdo->prepare("SELECT name FROM roles WHERE id = ? LIMIT 1");
            $stmt->execute([$roleId]);
            $roleName = (string)$stmt->fetchColumn();
        }

        $position = '';
        if ($employeeId && $pdo) {
            $stmt = $pdo->prepare("SELECT position FROM employees WHERE id = ? LIMIT 1");
            $stmt->execute([$employeeId]);
            $position = (string)$stmt->fetchColumn();
        }

        $upperRole = strtoupper(trim($roleName));
        $upperPos = strtoupper(trim($position));

        // Admins, CHR, Branch HR & Branch Managers always go to HRMS management dashboard
        if (
            in_array($roleId, [1, 2, 5, 6], true) ||
            in_array($upperRole, ['ADMIN', 'SUPER ADMIN', 'BM', 'BRANCH MANAGER', 'CHR', 'CENTRAL HR', 'BHR', 'BRANCH HR'], true)
        ) {
            return 'fika_hrms/fika_hrms_dashboard.php';
        }

        // Financial roles
        if (in_array($upperRole, ['GA', 'BA', 'GLOBAL ACCOUNTANT', 'BRANCH ACCOUNTANT'], true)) {
            return 'fika_finance/fika_finance_reports.php';
        }

        // Staff / Barista / Head Barista / Cashier
        $staffRoles = ['STAFF', 'BARISTA', 'HEAD BARISTA', 'CASHIER', 'EMPLOYEE'];
        $staffPositions = ['HEAD BARISTA', 'BARISTA', 'CASHIER', 'STAFF'];

        if (
            in_array($upperRole, $staffRoles, true) ||
            in_array($upperPos, $staffPositions, true)
        ) {
            return 'fika_hrms/fika_hrms_ess.php';
        }

        // Default to dashboard for other admin/manager roles
        return 'fika_hrms/fika_hrms_dashboard.php';
    }
}

