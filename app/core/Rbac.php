<?php

class Rbac {
    
    private static array $cache = [];

    private static ?array $knownPermissions = null;

    /**
     * Check if current user has a specific permission
     */
    public static function can(string $permission): bool {
        if (self::$knownPermissions === null) {
            self::$knownPermissions = require __DIR__ . '/../../config/permissions.php';
        }
        
        if (!in_array($permission, self::$knownPermissions, true)) {
            throw new \Exception("RBAC Error: Permission '{$permission}' is not declared in config/permissions.php");
        }
        if (!Auth::check()) {
            return false;
        }

        $user = Auth::user();
        $roleId = $user['role_id'];

        if (!isset(self::$cache[$roleId])) {
            global $pdo;
            $rStmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
            $rStmt->execute([$roleId]);
            $roleName = strtoupper($rStmt->fetchColumn() ?: '');

            $stmt = $pdo->prepare("SELECT permission_name FROM role_permissions WHERE role_id = ?");
            $stmt->execute([$roleId]);
            
            self::$cache[$roleId] = [
                'is_admin' => in_array($roleName, ['ADMIN', 'SUPER ADMIN']),
                'permissions' => $stmt->fetchAll(PDO::FETCH_COLUMN)
            ];
        }

        if (self::$cache[$roleId]['is_admin']) {
            return true;
        }

        return in_array($permission, self::$cache[$roleId]['permissions'], true);
    }

    /**
     * Terminate execution if user lacks permission
     */
    public static function require_permission(string $permission): void {
        Auth::requireLogin();
        if (!self::can($permission)) {
            http_response_code(403);
            die(json_encode(["error" => "Forbidden. Missing permission: {$permission}"]));
        }
    }

    /**
     * Check if user has access to a specific branch.
     * Super Admins typically have global access (branch_id = null).
     */
    public static function assert_branch_access(?int $targetBranchId): void {
        Auth::requireLogin();
        $user = Auth::user();
        
        // If user's branch_id is null, assume they have global access (e.g. Super Admin)
        if ($user['branch_id'] === null) {
            return;
        }

        if ((int)$user['branch_id'] !== (int)$targetBranchId) {
            http_response_code(403);
            die(json_encode(["error" => "Forbidden. Branch scope mismatch."]));
        }
    }

    /**
     * Returns the SQL condition and parameters for branch scoping.
     * Use in EVERY query on employee/attendance/payroll data.
     */
    public static function branch_scope(string $tablePrefix = ''): array {
        $user = Auth::user();
        if ($user['branch_id'] === null) {
            return ["", []];
        }
        $prefix = $tablePrefix !== '' ? $tablePrefix . '.' : '';
        return [" AND {$prefix}branch_id = ?", [$user['branch_id']]];
    }
}
