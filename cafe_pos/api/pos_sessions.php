<?php
// POS Sessions API (Open / Close Register)
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'current_session') {
        $branchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;
        if ($branchId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM cash_sessions WHERE status = 'OPEN' AND branch_id = ? ORDER BY opened_at DESC LIMIT 1");
            $stmt->execute([$branchId]);
        } else {
            $stmt = $pdo->query("SELECT * FROM cash_sessions WHERE status = 'OPEN' ORDER BY opened_at DESC LIMIT 1");
        }
        $session = $stmt->fetch();
        if ($session) {
            try {
                $stmtUser = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                $stmtUser->execute([$session['cashier_id']]);
                $session['head_barista_username'] = $stmtUser->fetchColumn();
            } catch (\Throwable $e) {}
            echo json_encode(['success' => true, 'session' => $session]);
        } else {
            echo json_encode(['success' => true, 'session' => null]);
        }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 1. Mandatory Staff Session Authentication
    $isStaff = !empty($_SESSION['user']) && in_array(
        $_SESSION['user']['role'] ?? '',
        ['Cashier', 'Admin', 'Super Admin', 'Branch Manager', 'Head Barista', 'Branch Admin'],
        true
    );

    if (!$isStaff) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized: authentication required']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    try {
        if ($action === 'open_shift') {
            $userBranch = (int)($_SESSION['user']['branch_id'] ?? 0);
            $targetBranch = isset($data['branch_id']) ? (int)$data['branch_id'] : ($userBranch > 0 ? $userBranch : 1);

            // 2. Branch Ownership Check
            if ($userBranch > 0 && $targetBranch !== $userBranch) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Forbidden: wrong branch']);
                exit;
            }

            $branchId = $userBranch > 0 ? $userBranch : $targetBranch;
            $hbId = (int)$_SESSION['user']['id'];
            $startingCash = isset($data['starting_cash']) ? (float)$data['starting_cash'] : 0.0;

            // Check if a shift is already open for this branch
            $stmt = $pdo->prepare("SELECT id FROM cash_sessions WHERE status = 'OPEN' AND branch_id = ? LIMIT 1");
            $stmt->execute([$branchId]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'A register shift is already open for this branch.']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO cash_sessions (branch_id, cashier_id, opening_float, status) VALUES (?, ?, ?, 'OPEN')");
            if ($stmt->execute([$branchId, $hbId, $startingCash])) {
                echo json_encode(['success' => true, 'session_id' => (int)$pdo->lastInsertId()]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to open shift']);
            }
            exit;
        }

        if ($action === 'close_shift') {
            $sessionId = isset($data['session_id']) ? (int)$data['session_id'] : null;
            $actualCash = isset($data['actual_cash']) ? (float)$data['actual_cash'] : null;

            if (!$sessionId || $actualCash === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing session ID or actual cash amount']);
                exit;
            }

            // 1. Fetch session
            $stmt = $pdo->prepare("SELECT * FROM cash_sessions WHERE id = ? AND status = 'OPEN'");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch();

            if (!$session) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Session not found or already closed']);
                exit;
            }

            // 2. Branch Ownership Check
            $userBranch = (int)($_SESSION['user']['branch_id'] ?? 0);
            if ($userBranch > 0 && (int)$session['branch_id'] !== $userBranch) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Forbidden: wrong branch']);
                exit;
            }

            // Calculate expected cash (Starting Cash + Cash Sales - Refunds for this session)
            $salesStmt = $pdo->prepare("SELECT SUM(total_price) FROM orders WHERE pos_session_id = ? AND payment_status = 'PAID' AND payment_method = 'CASH' AND order_status IN ('COMPLETED')");
            $salesStmt->execute([$sessionId]);
            $cashSales = (float)($salesStmt->fetchColumn() ?: 0);
            
            $refundStmt = $pdo->prepare("SELECT SUM(total_price) FROM orders WHERE pos_session_id = ? AND payment_status = 'PAID' AND payment_method = 'CASH' AND order_status = 'REFUNDED'");
            $refundStmt->execute([$sessionId]);
            $cashRefunds = (float)($refundStmt->fetchColumn() ?: 0);

            $expectedCash = (float)$session['opening_float'] + $cashSales - $cashRefunds;
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
            } catch (\Throwable $e) {}

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
                try { $syncPosStmt->execute([$expectedCash, $actualCash, $variance, $sessionId]); } catch (\Throwable $e){}
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
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to close shift']);
            }
            exit;
        }
    } catch (\Throwable $e) {
        error_log("POS session error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'An error occurred while processing shift session.']);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
