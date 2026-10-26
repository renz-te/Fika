<?php

class Audit {
    /**
     * Write an audit log for an action.
     * 
     * @param string $action The action name (e.g. 'CREATE_ORDER')
     * @param string $details JSON or text details of the action
     * @param int|null $userId The user who performed the action (defaults to current session user)
     * @param int|null $branchId The branch ID this action affects (defaults to current session branch if available)
     */
    public static function log(string $action, string $details = '', ?int $userId = null, ?int $branchId = null): void {
        global $pdo;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        if (Auth::check()) {
            $currentUser = Auth::user();
            if ($userId === null) {
                $userId = $currentUser['id'] ?? null;
            }
            if ($branchId === null && !empty($currentUser['branch_id'])) {
                $branchId = (int)$currentUser['branch_id'];
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, branch_id, action, details, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $branchId, $action, $details, $ip]);
    }
}
