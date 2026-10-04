<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('pos.access');

$input = json_decode(file_get_contents('php://input'), true);
$actualCashRaw = $input['actual_cash'] ?? 0;
$notes = $input['notes'] ?? '';

$user = Auth::user();
$sessionId = $_SESSION['pos_session_id'] ?? null;

if (!$sessionId) {
    http_response_code(400);
    exit(json_encode(["error" => "No active POS session to close."]));
}

global $pdo;

try {
    $pdo->beginTransaction();
    
    // Lock Session
    $sessionStmt = $pdo->prepare("SELECT * FROM cash_sessions WHERE id = ? FOR UPDATE");
    $sessionStmt->execute([$sessionId]);
    $posSession = $sessionStmt->fetch();

    if (!$posSession || $posSession['status'] !== 'OPEN') {
        throw new Exception("Session is invalid or already closed.");
    }
    if ((int)$posSession['cashier_id'] !== (int)$user['id']) {
        throw new Exception("You cannot close a session opened by another cashier.");
    }

    // Compute exact Expected Cash natively on the server side
    // expected = starting_cash + sum(cash payments from PAID orders) - sum(refunds from REFUNDED orders)
    
    $paidStmt = $pdo->prepare("
        SELECT COALESCE(SUM(p.amount_paid - p.change_amount), 0)
        FROM payments p
        JOIN orders o ON o.id = p.order_id
        WHERE o.pos_session_id = ? AND o.payment_status = 'PAID' AND p.payment_method = 'CASH'
    ");
    $paidStmt->execute([$sessionId]);
    $totalCashSalesCentavos = Money::toCentavos($paidStmt->fetchColumn());
    
    $refundStmt = $pdo->prepare("
        SELECT COALESCE(SUM(o.final_amount), 0)
        FROM orders o
        WHERE o.pos_session_id = ? AND o.payment_status = 'REFUNDED'
    ");
    $refundStmt->execute([$sessionId]);
    $totalCashRefundsCentavos = Money::toCentavos($refundStmt->fetchColumn());

    $startingCashCentavos = Money::toCentavos($posSession['starting_cash']);
    $expectedCashCentavos = $startingCashCentavos + $totalCashSalesCentavos - $totalCashRefundsCentavos;
    
    $actualCashCentavos = Money::toCentavos($actualCashRaw);
    
    // Update Session
    $closeStmt = $pdo->prepare("
        UPDATE cash_sessions 
        SET expected_cash = ?, ending_cash = ?, status = 'CLOSED', closed_at = NOW(), notes = CONCAT(COALESCE(notes,''), '\n', ?) 
        WHERE id = ?
    ");
    $closeStmt->execute([
        Money::toDecimal($expectedCashCentavos),
        Money::toDecimal($actualCashCentavos),
        trim("Close Notes: " . $notes),
        $sessionId
    ]);

    $varianceCentavos = $actualCashCentavos - $expectedCashCentavos;
    
    Audit::log('POS_REGISTER_CLOSE', "Session ID {$sessionId} closed. Variance: " . Money::toDecimal($varianceCentavos));
    
    unset($_SESSION['pos_session_id']);
    
    $pdo->commit();

    echo json_encode([
        "success" => true,
        "expected" => Money::toDecimal($expectedCashCentavos),
        "actual" => Money::toDecimal($actualCashCentavos),
        "variance" => Money::toDecimal($varianceCentavos)
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    exit(json_encode(["error" => $e->getMessage()]));
}
