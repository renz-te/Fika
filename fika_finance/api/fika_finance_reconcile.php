<?php
/**
 * Fika Rebuild Core - Payment Reconciliation API Endpoint
 * Handles anomaly listing across 4 categories and Maker-Checker resolution actions.
 * Enforces Auth, Csrf, Rbac (finance.view), and branch scoping.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/core/Output.php';

header('Content-Type: application/json');

Auth::requireLogin();
Rbac::require_permission('finance.view');

$user = Auth::user();
$userId = (int)$user['id'];
$userBranchId = isset($user['branch_id']) ? (int)$user['branch_id'] : null;
$isAdminOrGa = in_array($user['role_name'] ?? '', ['ADMIN', 'Super Admin', 'GA', 'Global Accountant'], true) 
               || in_array((int)($user['role_id'] ?? 0), [1, 3], true);

// Parse input (supports JSON, POST, GET)
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true) ?? [];
$input = array_merge($_GET, $_POST, $jsonInput);

$action = trim($input['action'] ?? 'list_anomalies');
global $pdo;

// Branch Scope Resolution
$requestedBranchId = (isset($input['branch_id']) && $input['branch_id'] !== '') ? (int)$input['branch_id'] : null;
if (!$isAdminOrGa) {
    if ($requestedBranchId !== null && $requestedBranchId !== $userBranchId) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden: Cannot access reconciliation data for another branch']);
        exit;
    }
    $branchId = $userBranchId;
} else {
    $branchId = $requestedBranchId;
}

// =====================================================================
// ACTION: list_anomalies
// =====================================================================
if ($action === 'list_anomalies') {
    $startDate = trim($input['start_date'] ?? date('Y-m-01'));
    $endDate = trim($input['end_date'] ?? date('Y-m-d'));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        $startDate = date('Y-m-01');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        $endDate = date('Y-m-d');
    }

    $startDateTime = $startDate . ' 00:00:00';
    $endDateTime = $endDate . ' 23:59:59';

    // 1. Paid without Payment Row (PAID_WITHOUT_PAYMENT)
    // Order marked PAID with digital method but has NO matching row in payments
    $sql1 = "
        SELECT 
            o.id AS order_id,
            NULL AS payment_id,
            o.order_number,
            o.branch_id,
            b.name AS branch_name,
            o.cashier_id,
            u.username AS cashier_username,
            o.payment_method,
            o.final_amount AS order_amount,
            0.00 AS payment_amount,
            o.payment_status AS order_status,
            'MISSING' AS payment_status,
            o.created_at,
            'PAID_WITHOUT_PAYMENT' AS anomaly_type,
            'Order is marked PAID via digital method with no matching payment row' AS anomaly_desc
        FROM orders o
        JOIN branches b ON o.branch_id = b.id
        LEFT JOIN users u ON o.cashier_id = u.id
        LEFT JOIN payments p ON p.order_id = o.id
        LEFT JOIN reconciliation_logs rl ON rl.order_id = o.id AND rl.anomaly_type = 'PAID_WITHOUT_PAYMENT'
        WHERE o.payment_status = 'PAID'
          AND UPPER(o.payment_method) IN ('GCASH', 'MAYA', 'CARD', 'ONLINE', 'E-WALLET')
          AND o.is_test = 0
          AND p.id IS NULL
          AND rl.id IS NULL
          AND o.created_at BETWEEN ? AND ?
    ";
    $params1 = [$startDateTime, $endDateTime];
    if ($branchId !== null) {
        $sql1 .= " AND o.branch_id = ?";
        $params1[] = $branchId;
    }
    $sql1 .= " ORDER BY o.created_at DESC";

    $stmt1 = $pdo->prepare($sql1);
    $stmt1->execute($params1);
    $anomaliesMissingPayment = $stmt1->fetchAll();

    // 2. Payment Row without Paid Order (PAYMENT_WITHOUT_ORDER)
    // Row exists in payments with status = 'paid', but order is UNPAID (or order record missing)
    $sql2 = "
        SELECT 
            p.order_id,
            p.id AS payment_id,
            o.order_number,
            COALESCE(o.branch_id, p.shift_id) AS branch_id,
            COALESCE(b.name, 'Branch') AS branch_name,
            o.cashier_id,
            u.username AS cashier_username,
            COALESCE(p.method, p.payment_method) AS payment_method,
            COALESCE(o.final_amount, 0.00) AS order_amount,
            p.gross_amount AS payment_amount,
            COALESCE(o.payment_status, 'UNPAID') AS order_status,
            p.status AS payment_status,
            p.created_at,
            'PAYMENT_WITHOUT_ORDER' AS anomaly_type,
            'Payment row is paid but the corresponding order is still UNPAID' AS anomaly_desc
        FROM payments p
        LEFT JOIN orders o ON p.order_id = o.id
        LEFT JOIN branches b ON o.branch_id = b.id
        LEFT JOIN users u ON o.cashier_id = u.id
        LEFT JOIN reconciliation_logs rl ON rl.order_id = p.order_id AND rl.anomaly_type = 'PAYMENT_WITHOUT_ORDER'
        WHERE p.status = 'paid'
          AND (o.id IS NULL OR o.payment_status != 'PAID')
          AND rl.id IS NULL
          AND p.created_at BETWEEN ? AND ?
    ";
    $params2 = [$startDateTime, $endDateTime];
    if ($branchId !== null) {
        $sql2 .= " AND o.branch_id = ?";
        $params2[] = $branchId;
    }
    $sql2 .= " ORDER BY p.created_at DESC";

    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute($params2);
    $anomaliesUnpaidOrder = $stmt2->fetchAll();

    // 3. Amount Mismatch (AMOUNT_MISMATCH)
    // payments.gross_amount does not match orders.final_amount
    $sql3 = "
        SELECT 
            o.id AS order_id,
            p.id AS payment_id,
            o.order_number,
            o.branch_id,
            b.name AS branch_name,
            o.cashier_id,
            u.username AS cashier_username,
            COALESCE(p.method, p.payment_method) AS payment_method,
            o.final_amount AS order_amount,
            p.gross_amount AS payment_amount,
            o.payment_status AS order_status,
            p.status AS payment_status,
            p.created_at,
            'AMOUNT_MISMATCH' AS anomaly_type,
            'Payment gross_amount differs from order final_amount' AS anomaly_desc
        FROM payments p
        JOIN orders o ON p.order_id = o.id
        JOIN branches b ON o.branch_id = b.id
        LEFT JOIN users u ON o.cashier_id = u.id
        LEFT JOIN reconciliation_logs rl ON rl.order_id = o.id AND rl.anomaly_type = 'AMOUNT_MISMATCH'
        WHERE p.status = 'paid'
          AND o.is_test = 0
          AND ROUND(p.gross_amount * 100) != ROUND(o.final_amount * 100)
          AND rl.id IS NULL
          AND p.created_at BETWEEN ? AND ?
    ";
    $params3 = [$startDateTime, $endDateTime];
    if ($branchId !== null) {
        $sql3 .= " AND o.branch_id = ?";
        $params3[] = $branchId;
    }
    $sql3 .= " ORDER BY p.created_at DESC";

    $stmt3 = $pdo->prepare($sql3);
    $stmt3->execute($params3);
    $anomaliesMismatch = $stmt3->fetchAll();

    // 4. Stuck Pending (STUCK_PENDING)
    // payments.status = 'pending' and created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    // Constraint 4: paid_at IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    $sql4 = "
        SELECT 
            p.order_id,
            p.id AS payment_id,
            o.order_number,
            COALESCE(o.branch_id, p.shift_id) AS branch_id,
            COALESCE(b.name, 'Branch') AS branch_name,
            o.cashier_id,
            u.username AS cashier_username,
            COALESCE(p.method, p.payment_method) AS payment_method,
            COALESCE(o.final_amount, 0.00) AS order_amount,
            p.gross_amount AS payment_amount,
            COALESCE(o.payment_status, 'PENDING') AS order_status,
            p.status AS payment_status,
            p.created_at,
            'STUCK_PENDING' AS anomaly_type,
            'Payment stuck in pending state for over 30 minutes' AS anomaly_desc
        FROM payments p
        LEFT JOIN orders o ON p.order_id = o.id
        LEFT JOIN branches b ON o.branch_id = b.id
        LEFT JOIN users u ON o.cashier_id = u.id
        LEFT JOIN reconciliation_logs rl ON rl.order_id = p.order_id AND rl.anomaly_type = 'STUCK_PENDING'
        WHERE p.status = 'pending'
          AND p.paid_at IS NULL
          AND p.created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
          AND rl.id IS NULL
          AND p.created_at BETWEEN ? AND ?
    ";
    $params4 = [$startDateTime, $endDateTime];
    if ($branchId !== null) {
        $sql4 .= " AND o.branch_id = ?";
        $params4[] = $branchId;
    }
    $sql4 .= " ORDER BY p.created_at DESC";

    $stmt4 = $pdo->prepare($sql4);
    $stmt4->execute($params4);
    $anomaliesStuckPending = $stmt4->fetchAll();

    $totalCount = count($anomaliesMissingPayment) + count($anomaliesUnpaidOrder) + count($anomaliesMismatch) + count($anomaliesStuckPending);

    echo json_encode([
        'success' => true,
        'period' => [
            'start_date' => $startDate,
            'end_date' => $endDate
        ],
        'branch_id' => $branchId,
        'counts' => [
            'total' => $totalCount,
            'paid_without_payment' => count($anomaliesMissingPayment),
            'payment_without_order' => count($anomaliesUnpaidOrder),
            'amount_mismatch' => count($anomaliesMismatch),
            'stuck_pending' => count($anomaliesStuckPending)
        ],
        'anomalies' => [
            'paid_without_payment' => $anomaliesMissingPayment,
            'payment_without_order' => $anomaliesUnpaidOrder,
            'amount_mismatch' => $anomaliesMismatch,
            'stuck_pending' => $anomaliesStuckPending
        ]
    ]);
    exit;
}

// =====================================================================
// ACTION: resolve
// =====================================================================
if ($action === 'resolve') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed. POST required.']);
        exit;
    }

    // CSRF verification
    $csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!Csrf::verify($csrfToken)) {
        http_response_code(419);
        echo json_encode(['error' => 'CSRF verification failed']);
        exit;
    }

    $anomalyType = trim($input['anomaly_type'] ?? '');
    $orderId = (int)($input['order_id'] ?? 0);
    $paymentId = !empty($input['payment_id']) ? (int)$input['payment_id'] : null;
    $resolutionNote = trim($input['resolution_note'] ?? '');

    $validTypes = ['PAID_WITHOUT_PAYMENT', 'PAYMENT_WITHOUT_ORDER', 'AMOUNT_MISMATCH', 'STUCK_PENDING'];
    if (!in_array($anomalyType, $validTypes, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid anomaly type specified.']);
        exit;
    }

    if ($orderId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid order_id is required.']);
        exit;
    }

    if ($resolutionNote === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Resolution note is required.']);
        exit;
    }

    // Fetch order to verify existence, branch, and creator
    $oStmt = $pdo->prepare("SELECT id, order_number, branch_id, cashier_id FROM orders WHERE id = ?");
    $oStmt->execute([$orderId]);
    $order = $oStmt->fetch();

    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found.']);
        exit;
    }

    // Branch authorization check
    if (!$isAdminOrGa && (int)$order['branch_id'] !== $userBranchId) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden: Cannot resolve anomaly for another branch.']);
        exit;
    }

    // Maker-Checker Constraint (Constraint 2)
    // The user resolving the anomaly (resolved_by) cannot be the same user who created the order (cashier_id / created_by)
    $orderCreatorId = (int)($order['cashier_id'] ?? 0);
    if ($userId === $orderCreatorId) {
        http_response_code(403);
        echo json_encode([
            'error' => 'Maker-Checker violation: The user who created the order cannot resolve this anomaly.'
        ]);
        exit;
    }

    // Check if payment_id was supplied or can be resolved
    if ($paymentId !== null) {
        $pStmt = $pdo->prepare("SELECT id, order_id FROM payments WHERE id = ?");
        $pStmt->execute([$paymentId]);
        $payment = $pStmt->fetch();
        if (!$payment) {
            http_response_code(404);
            echo json_encode(['error' => 'Payment record not found.']);
            exit;
        }
    }

    // Check if already resolved
    $checkStmt = $pdo->prepare("SELECT id FROM reconciliation_logs WHERE order_id = ? AND anomaly_type = ?");
    $checkStmt->execute([$orderId, $anomalyType]);
    if ($checkStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'This anomaly has already been marked as resolved.']);
        exit;
    }

    // Insert into reconciliation_logs (Constraint 1)
    $insStmt = $pdo->prepare("
        INSERT INTO reconciliation_logs (anomaly_type, order_id, payment_id, resolved_by, resolution_note, resolved_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $insStmt->execute([
        $anomalyType,
        $orderId,
        $paymentId,
        $userId,
        $resolutionNote
    ]);
    $logId = (int)$pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Anomaly marked as resolved successfully.',
        'log_id' => $logId,
        'anomaly_type' => $anomalyType,
        'order_id' => $orderId,
        'payment_id' => $paymentId,
        'resolved_by' => $userId,
        'resolution_note' => $resolutionNote,
        'resolved_at' => date('Y-m-d H:i:s')
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action.']);
exit;
