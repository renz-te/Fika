<?php
// POS Sessions API (Open / Close Register)
error_reporting(0);
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'current_session') {
        $stmt = $pdo->query("SELECT * FROM cash_sessions WHERE status = 'OPEN' ORDER BY opened_at DESC LIMIT 1");
        $session = $stmt->fetch();
        if ($session) {
            try {
                $stmtUser = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                $stmtUser->execute([$session['cashier_id']]);
                $session['head_barista_username'] = $stmtUser->fetchColumn();
            } catch (Exception $e) {}
            echo json_encode(['success' => true, 'session' => $session]);
        } else {
            echo json_encode(['success' => true, 'session' => null]);
        }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if ($action === 'open_shift') {
        $hbId = $data['head_barista_id'] ?? null;
        $startingCash = $data['starting_cash'] ?? 0;
        $branchId = $data['branch_id'] ?? 1;

        if (!$hbId) {
            echo json_encode(['success' => false, 'error' => 'Head Barista/Cashier ID required']);
            exit;
        }

        // Check if a shift is already open
        $stmt = $pdo->query("SELECT id FROM cash_sessions WHERE status = 'OPEN' LIMIT 1");
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'A register shift is already open.']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO cash_sessions (branch_id, cashier_id, opening_float, status) VALUES (?, ?, ?, 'OPEN')");
        if ($stmt->execute([$branchId, $hbId, $startingCash])) {
            echo json_encode(['success' => true, 'session_id' => $pdo->lastInsertId()]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to open shift']);
        }
        exit;
    }

    if ($action === 'close_shift') {
        $sessionId = $data['session_id'] ?? null;
        $actualCash = $data['actual_cash'] ?? null;

        if (!$sessionId || $actualCash === null) {
            echo json_encode(['success' => false, 'error' => 'Missing session ID or actual cash amount']);
            exit;
        }

        // Calculate expected cash (Starting Cash + Cash Sales - Refunds for this session)
        $stmt = $pdo->prepare("SELECT opening_float FROM cash_sessions WHERE id = ? AND status = 'OPEN'");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();

        if (!$session) {
            echo json_encode(['success' => false, 'error' => 'Session not found or already closed']);
            exit;
        }

        // Calculate cash sales
        $salesStmt = $pdo->prepare("SELECT SUM(total_price) FROM orders WHERE pos_session_id = ? AND payment_status = 'PAID' AND payment_method = 'CASH' AND order_status IN ('COMPLETED')");
        $salesStmt->execute([$sessionId]);
        $cashSales = $salesStmt->fetchColumn() ?: 0;
        
        // Calculate cash refunds
        $refundStmt = $pdo->prepare("SELECT SUM(total_price) FROM orders WHERE pos_session_id = ? AND payment_status = 'PAID' AND payment_method = 'CASH' AND order_status = 'REFUNDED'");
        $refundStmt->execute([$sessionId]);
        $cashRefunds = $refundStmt->fetchColumn() ?: 0;

        // Calculate expected cash strictly from physical cash transactions:
        // expected_cash = opening_float + cash_sales - cash_refunds
        $expectedCash = (float)$session['opening_float'] + (float)$cashSales - (float)$cashRefunds;
        $variance = (float)$actualCash - $expectedCash;

        // Separate Digital Settlement calculation (GCash, Maya, Card)
        $digitalGross = 0.00;
        $digitalFee = 0.00;
        $digitalNet = 0.00;
        $digitalCount = 0;

        try {
            $digStmt = $pdo->prepare("
                SELECT COUNT(*) as d_cnt, 
                       COALESCE(SUM(gross_amount), 0) as d_gross, 
                       COALESCE(SUM(fee_amount), 0) as d_fee, 
                       COALESCE(SUM(net_amount), 0) as d_net
                FROM payments 
                WHERE shift_id = ? AND status = 'paid'
            ");
            $digStmt->execute([$sessionId]);
            $digData = $digStmt->fetch();
            if ($digData) {
                $digitalCount = (int)$digData['d_cnt'];
                $digitalGross = (float)$digData['d_gross'];
                $digitalFee = (float)$digData['d_fee'];
                $digitalNet = (float)$digData['d_net'];
            }
        } catch (Exception $e) {}

        $updateStmt = $pdo->prepare("
            UPDATE cash_sessions 
            SET closed_at = CURRENT_TIMESTAMP, 
                expected_cash = ?, 
                closing_cash = ?, 
                variance = ?, 
                status = 'CLOSED' 
            WHERE id = ?
        ");
        
        $syncPosStmt = $pdo->prepare("
            UPDATE pos_sessions 
            SET closed_at = CURRENT_TIMESTAMP, 
                expected_cash = ?, 
                closing_cash = ?, 
                variance = ?, 
                status = 'CLOSED' 
            WHERE id = ?
        ");

        if ($updateStmt->execute([$expectedCash, $actualCash, $variance, $sessionId])) {
            try { $syncPosStmt->execute([$expectedCash, $actualCash, $variance, $sessionId]); } catch(Exception $e){}
            echo json_encode([
                'success' => true, 
                'expected' => $expectedCash, 
                'actual' => (float)$actualCash, 
                'variance' => $variance,
                'digital_settlement' => [
                    'count' => $digitalCount,
                    'gross' => $digitalGross,
                    'fee'   => $digitalFee,
                    'net'   => $digitalNet
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to close shift']);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
