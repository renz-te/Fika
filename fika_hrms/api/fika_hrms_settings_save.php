<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('settings.manage');

$input = json_decode(file_get_contents('php://input'), true);
$grace = (int) ($input['late_grace_minutes'] ?? 15);
$leaves = $input['leave_types'] ?? [];

$user = Auth::user();

global $pdo;

try {
    $pdo->beginTransaction();
    
    // Save settings
    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ?, updated_by = ?, updated_at = NOW() WHERE setting_key = 'late_grace_minutes'");
    $stmt->execute([(string)$grace, $user['id']]);
    
    // Save leave types
    $leaveStmt = $pdo->prepare("UPDATE leave_types SET name = ?, is_paid = ?, annual_days = ?, min_service_months = ?, statutory = ? WHERE code = ?");
    foreach ($leaves as $code => $l) {
        $leaveStmt->execute([
            $l['name'],
            empty($l['is_paid']) ? 0 : 1,
            (int)$l['annual_days'],
            (int)$l['min_service_months'],
            empty($l['statutory']) ? 0 : 1,
            $code
        ]);
    }
    
    Audit::log('SETTINGS_UPDATED', "User {$user['id']} updated global settings and leave types.");
    
    $pdo->commit();
    echo json_encode(["success" => true, "message" => "Settings saved successfully."]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    exit(json_encode(["error" => $e->getMessage()]));
}
