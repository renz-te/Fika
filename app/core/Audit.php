<?php

class Audit {
    /**
     * Write an audit log for an action.
     * 
     * @param string $action The action name (e.g. 'CREATE_ORDER')
     * @param string $details JSON or text details of the action
     * @param int|null $userId The user who performed the action (defaults to current session user)
     */
    public static function log(string $action, string $details = '', ?int $userId = null): void {
        global $pdo;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        if ($userId === null && Auth::check()) {
            $userId = Auth::user()['id'] ?? null;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $action, $details, $ip]);
    }
}
