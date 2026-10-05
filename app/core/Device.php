<?php

class Device {
    
    /**
     * Verify if the device MAC address or a stored device cookie/token is registered and active
     */
    public static function authenticateDevice(string $identifier, ?int $branchId = null): bool {
        global $pdo;
        
        $sql = "SELECT id, status FROM devices WHERE mac_address = ? AND deleted_at IS NULL";
        $params = [$identifier];
        
        if ($branchId !== null) {
            $sql .= " AND branch_id = ?";
            $params[] = $branchId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $device = $stmt->fetch();
        
        return ($device && $device['status'] === 'Active');
    }

    /**
     * Quick PIN-based authentication for Cashiers/Baristas on the POS
     */
    public static function pinAuth(string $pin, int $branchId): ?array {
        global $pdo;
        
        // This assumes the `password` field or a dedicated `pin` field holds a hashed PIN
        // For simplicity, we assume users table holds a secure hash of the PIN as password for POS-only accounts.
        // We ensure they belong to the branch and have POS access.
        $stmt = $pdo->prepare("
            SELECT u.id, u.role_id, u.employee_id, u.branch_id, u.password 
            FROM users u
            JOIN role_permissions rp ON rp.role_id = u.role_id
            LEFT JOIN employees e ON e.id = u.employee_id
            WHERE u.branch_id = ? AND u.deleted_at IS NULL 
              AND rp.permission_name = 'pos.access'
              AND (e.id IS NULL OR e.status = 'ACTIVE')
        ");
        $stmt->execute([$branchId]);
        
        while ($user = $stmt->fetch()) {
            if (password_verify($pin, $user['password'])) {
                return $user;
            }
        }
        return null;
    }

    /**
     * Supervisor override logic. Requires a user with higher permissions (e.g., pos.void_order).
     */
    public static function supervisorOverride(string $username, string $password, int $branchId, string $requiredPermission): bool {
        global $pdo;
        
        $stmt = $pdo->prepare("
            SELECT u.id, u.password, u.role_id 
            FROM users u
            WHERE u.username = ? AND (u.branch_id = ? OR u.branch_id IS NULL) AND u.deleted_at IS NULL
        ");
        $stmt->execute([$username, $branchId]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Check if supervisor has the exact required permission
            $permStmt = $pdo->prepare("SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_name = ?");
            $permStmt->execute([$user['role_id'], $requiredPermission]);
            return (bool) $permStmt->fetchColumn();
        }
        return false;
    }

    /**
     * Authenticate an employee for the Clock device (Time & Attendance)
     */
    public static function clockAuth(string $employeeCode, string $pin, int $branchId, string $mac): ?array {
        global $pdo;

        $throttleKey = "CLOCK_{$employeeCode}_{$mac}";

        // 5 failures -> 5-minute lock
        $chkStmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE username = ? AND success = 0 AND created_at > (NOW() - INTERVAL 5 MINUTE)");
        $chkStmt->execute([$throttleKey]);
        if ((int) $chkStmt->fetchColumn() >= 5) {
            throw new Exception("Device locked for this employee due to too many failed attempts. Try again in 5 minutes.");
        }

        $stmt = $pdo->prepare("SELECT id, pin_hash, status FROM employees WHERE employee_code = ? AND branch_id = ? AND deleted_at IS NULL");
        $stmt->execute([$employeeCode, $branchId]);
        $emp = $stmt->fetch();

        if ($emp && $emp['status'] === 'ACTIVE' && password_verify($pin, $emp['pin_hash'])) {
            // Success
            $logStmt = $pdo->prepare("INSERT INTO login_attempts (username, ip_address, success, created_at) VALUES (?, ?, 1, NOW())");
            $logStmt->execute([$throttleKey, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
            return $emp;
        }

        // Failure
        $logStmt = $pdo->prepare("INSERT INTO login_attempts (username, ip_address, success, created_at) VALUES (?, ?, 0, NOW())");
        $logStmt->execute([$throttleKey, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
        
        return null;
    }
}
