<?php

class AuditLog {
    /**
     * Write an audit log for an action.
     * 
     * @param int|null $userId The user who performed the action
     * @param string $action The action name (e.g. 'CREATE_ORDER')
     * @param string $details JSON or text details of the action
     * @param string|null $ipAddress
     */
    public static function log(?int $userId, string $action, string $details = '', ?string $ipAddress = null): void {
        $pdo = Database::getConnection();
        $ip = $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? null;
        
        // This relies on having an activity_logs table based on the original schema
        // which typically has: id, user_id, action, details, ip_address, created_at
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $action, $details, $ip]);
    }
}
