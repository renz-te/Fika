<?php

class Rbac {
    
    private static array $cache = [];

    /**
     * Check if current user has a specific permission
     */
    public static function can(string $permission): bool {
        if (!Auth::check()) {
            return false;
        }

        $user = Auth::user();
        $roleId = $user['role_id'];

        if (!isset(self::$cache[$roleId])) {
            global $pdo;
            $stmt = $pdo->prepare("SELECT permission_name FROM role_permissions WHERE role_id = ?");
            $stmt->execute([$roleId]);
            self::$cache[$roleId] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        return in_array($permission, self::$cache[$roleId], true);
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
}
