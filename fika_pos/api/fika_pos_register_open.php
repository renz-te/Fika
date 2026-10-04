<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('pos.access');

$startingCashRaw = $_POST['starting_cash'] ?? 0;
$notes = $_POST['notes'] ?? '';
$user = Auth::user();
$branchId = $user['branch_id'];

if (!$branchId) {
    http_response_code(400);
    exit(json_encode(["error" => "User is not assigned to a branch."]));
}

$startingCashCentavos = Money::toCentavos($startingCashRaw);
$startingCashDecimal = Money::toDecimal($startingCashCentavos);

global $pdo;

// Check if an open session already exists for this branch/cashier combo
$stmt = $pdo->prepare("SELECT id FROM cash_sessions WHERE branch_id = ? AND status = 'OPEN'");
$stmt->execute([$branchId]);
if ($stmt->fetch()) {
    http_response_code(409);
    exit(json_encode(["error" => "An active register session is already open for this branch."]));
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO cash_sessions (branch_id, cashier_id, starting_cash, expected_cash, status, opened_at, notes) 
        VALUES (?, ?, ?, ?, 'OPEN', NOW(), ?)
    ");
    $stmt->execute([
        $branchId,
        $user['id'],
        $startingCashDecimal,
        $startingCashDecimal, // expected cash starts identical to starting float
        $notes
    ]);
    
    $sessionId = $pdo->lastInsertId();
    $_SESSION['pos_session_id'] = $sessionId;
    
    Audit::log('POS_REGISTER_OPEN', "Session ID: {$sessionId}, Float: {$startingCashDecimal}");
    
    echo json_encode(["success" => true, "session_id" => $sessionId]);
} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode(["error" => "Failed to open register: " . $e->getMessage()]));
}
