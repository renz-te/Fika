<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('pos.access');

$input = json_decode(file_get_contents('php://input'), true);
$orderId = (int) ($input['order_id'] ?? 0);
$supervisorPin = $input['supervisor_pin'] ?? '';
$reason = $input['reason'] ?? '';
$action = $input['action'] ?? 'VOID'; // VOID or REFUND

$user = Auth::user();
$branchId = $user['branch_id'];

if (!$orderId || empty($supervisorPin) || empty($reason)) {
    http_response_code(400);
    exit(json_encode(["error" => "Order ID, Supervisor PIN, and Reason are required."]));
}
if (!in_array($action, ['VOID', 'REFUND'])) {
    http_response_code(400);
    exit(json_encode(["error" => "Invalid action."]));
}

global $pdo;

// Validate Supervisor PIN (Must have pos.void_order or pos.refund_order)
$requiredPerm = $action === 'VOID' ? 'pos.void_order' : 'pos.refund_order';
$stmt = $pdo->prepare("
    SELECT u.id, u.password 
    FROM users u
    JOIN role_permissions rp ON rp.role_id = u.role_id
    WHERE u.branch_id = ? AND u.deleted_at IS NULL AND rp.permission_name = ?
");
$stmt->execute([$branchId, $requiredPerm]);
$authManagerId = null;
while ($mgr = $stmt->fetch()) {
    if (password_verify($supervisorPin, $mgr['password'])) {
        $authManagerId = $mgr['id'];
        break;
    }
}

if (!$authManagerId) {
    http_response_code(403);
    Audit::log('POS_OVERRIDE_FAILED', "Failed override attempt for {$action} on Order {$orderId}");
    exit(json_encode(["error" => "Invalid Supervisor PIN or insufficient privileges."]));
}

try {
    $pdo->beginTransaction();
    
    // Lock Order
    $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch();

    if (!$order) {
        throw new Exception("Order not found.");
    }

    if (!empty($user['branch_id']) && (int)$order['branch_id'] !== (int)$user['branch_id']) {
        $pdo->rollBack();
        http_response_code(403);
        exit(json_encode(["error" => "Forbidden: wrong branch."]));
    }
    
    if ($action === 'VOID' && $order['order_status'] !== 'PENDING') {
        throw new Exception("Only PENDING orders can be VOIDED.");
    }
    if ($action === 'REFUND' && $order['payment_status'] !== 'PAID') {
        throw new Exception("Only PAID orders can be REFUNDED.");
    }

    $newState = $action === 'VOID' ? 'VOID' : 'REFUNDED';
    $newPayState = $action === 'VOID' ? 'UNPAID' : 'REFUNDED';

    // Update Order
    $pdo->prepare("UPDATE orders SET order_status = ?, payment_status = ? WHERE id = ?")
        ->execute([$newState, $newPayState, $orderId]);
        
    // Insert Audit
    $pdo->prepare("INSERT INTO order_audit (order_id, action, reason, authorized_by, created_at) VALUES (?, ?, ?, ?, NOW())")
        ->execute([$orderId, $action, $reason, $authManagerId]);

    // Restore stock if the order had deducted inventory (Constraint 1: restores both product & modifiers)
    InventoryDeduction::restoreOrder($orderId, (int)$order['branch_id'], $authManagerId, $order['order_number']);
    
    Audit::log("ORDER_{$action}", "Order ID {$orderId} {$action} by manager {$authManagerId}. Reason: {$reason}");
    $pdo->commit();

    echo json_encode(["success" => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    exit(json_encode(["error" => $e->getMessage()]));
}
