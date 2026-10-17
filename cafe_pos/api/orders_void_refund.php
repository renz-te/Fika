<?php
// POS Void and Refund Governance API
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$order_id = $input['order_id'] ?? null;
$supervisor_id = $input['supervisor_id'] ?? null;
$reason = $input['reason'] ?? '';

$pin = $input['pin'] ?? $input['supervisor_pin'] ?? null;

// Per-IP Rate Limiting (max 5 failed attempts per 10 minutes)
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$ipHash = md5($clientIp);
$rateLimitFile = sys_get_temp_dir() . '/pin_auth_limit_' . $ipHash . '.json';

$maxAttempts = 5;
$decaySeconds = 600; // 10 minutes
$now = time();

$attempts = [];
if (file_exists($rateLimitFile)) {
    $rawAttempts = @file_get_contents($rateLimitFile);
    $data = json_decode((string)$rawAttempts, true);
    if (is_array($data)) {
        $attempts = array_filter($data, function($timestamp) use ($now, $decaySeconds) {
            return ($now - $timestamp) < $decaySeconds;
        });
    }
}

if (count($attempts) >= $maxAttempts) {
    http_response_code(429);
    echo json_encode([
        'success' => false, 
        'error' => 'Too many failed authorization attempts. Please try again after 10 minutes.'
    ]);
    exit;
}

// Authenticate supervisor strictly by PIN via password_verify()
$supervisor = null;
if (!empty($pin)) {
    if ($supervisor_id) {
        $stmtSup = $pdo->prepare("
            SELECT u.id, u.username, u.password, u.role_id, u.branch_id, r.name as role 
            FROM hrms.users u 
            JOIN hrms.roles r ON u.role_id = r.id 
            WHERE u.id = ? AND r.name IN ('Super Admin', 'Branch Manager', 'Head Barista', 'Branch Admin')
        ");
        $stmtSup->execute([$supervisor_id]);
        $su = $stmtSup->fetch();
        if ($su && password_verify((string)$pin, $su['password'])) {
            $supervisor = $su;
        }
    } else {
        $supStmt = $pdo->prepare("
            SELECT u.id, u.username, u.password, u.role_id, u.branch_id, r.name as role 
            FROM hrms.users u 
            JOIN hrms.roles r ON u.role_id = r.id 
            WHERE r.name IN ('Super Admin', 'Branch Manager', 'Head Barista', 'Branch Admin')
        ");
        $supStmt->execute();
        $supUsers = $supStmt->fetchAll();
        foreach ($supUsers as $su) {
            if (password_verify((string)$pin, $su['password'])) {
                $supervisor = $su;
                $supervisor_id = $su['id'];
                break;
            }
        }
    }
}

if (!$supervisor || !in_array($supervisor['role'], ['Super Admin', 'Branch Manager', 'Head Barista', 'Branch Admin'])) {
    // 1. Record failed attempt for rate limiting
    $attempts[] = $now;
    @file_put_contents($rateLimitFile, json_encode(array_values($attempts)));

    // 2. Audit Log on failed PIN attempt (never log the submitted PIN!)
    $auditBranchId = 1;
    if ($order_id) {
        $bStmt = $pdo->prepare("SELECT branch_id FROM orders WHERE id = ?");
        $bStmt->execute([$order_id]);
        $foundBranch = $bStmt->fetchColumn();
        if ($foundBranch) {
            $auditBranchId = (int)$foundBranch;
        }
    }

    try {
        $pdo->prepare("
            INSERT INTO order_audit_logs (branch_id, order_id, action, supervisor_id, reason, created_at)
            VALUES (?, ?, 'AUTH_FAIL', ?, ?, NOW())
        ")->execute([
            $auditBranchId,
            $order_id ?: null,
            $supervisor_id ?: null,
            'Failed supervisor authorization attempt'
        ]);
    } catch (\Throwable $e) {
        // Suppress audit log failure to guarantee 403 response
    }

    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Supervisor authorization required. Invalid or missing supervisor PIN.']);
    exit;
}

// On successful authentication, reset rate limit attempts
if (file_exists($rateLimitFile)) {
    @unlink($rateLimitFile);
}

if (!in_array($action, ['VOID', 'REFUND']) || !$order_id || empty(trim($reason))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Action, order_id, supervisor PIN, and reason are required.']);
    exit;
}

// 1. Fetch Order and Payment details outside transaction
$stmtOrder = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmtOrder->execute([$order_id]);
$order = $stmtOrder->fetch();

if (!$order) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Order not found.']);
    exit;
}

// 2. Branch Ownership Check
$supBranch = (int)($supervisor['branch_id'] ?? 0);
if ($supervisor['role'] !== 'Super Admin' && $supBranch > 0 && $supBranch !== (int)$order['branch_id']) {
    try {
        $pdo->prepare("
            INSERT INTO order_audit_logs (branch_id, order_id, action, supervisor_id, reason, created_at)
            VALUES (?, ?, 'AUTH_FAIL', ?, ?, NOW())
        ")->execute([
            (int)$order['branch_id'],
            $order_id,
            $supervisor['id'],
            "Cross-branch access denied: Supervisor branch {$supBranch} against order branch {$order['branch_id']}"
        ]);
    } catch (\Throwable $t) {}

    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: wrong branch']);
    exit;
}

if ($order['order_status'] === 'VOID' || $order['order_status'] === 'REFUNDED') {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'Order is already voided or refunded.']);
    exit;
}

$branch_id = (int)$order['branch_id'];

try {

    // Check if this was a digital payment (PayMongo / GCash / Maya)
    $payStmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? AND status = 'paid' ORDER BY id DESC LIMIT 1");
    $payStmt->execute([$order_id]);
    $digitalPayment = $payStmt->fetch();

    $isDigital = $digitalPayment || in_array(strtoupper((string)$order['payment_method']), ['GCASH', 'PAYMAYA', 'MAYA', 'EWALLET', 'CARD']);

    // 2. If Digital Refund, call PayMongo Refunds API BEFORE starting DB transaction
    if ($action === 'REFUND' && $isDigital) {
        $paymongoConfig = file_exists(__DIR__ . '/../../config/paymongo.php') ? require __DIR__ . '/../../config/paymongo.php' : [];
        $secKey = $paymongoConfig['secret_key'] ?? '';

        if (empty($secKey)) {
            $pdo->prepare("
                INSERT INTO order_audit_logs (branch_id, order_id, action, supervisor_id, reason, created_at)
                VALUES (?, ?, 'REFUND_FAILED', ?, ?, NOW())
            ")->execute([
                $branch_id,
                $order_id,
                $supervisor['id'],
                "Refund failed: Missing PayMongo secret key"
            ]);

            http_response_code(503);
            echo json_encode(['success' => false, 'error' => 'PayMongo refund failed: Secret key not configured.']);
            exit;
        }

        // Determine provider_payment_id (the pay_... ID)
        $payId = $digitalPayment['provider_payment_id'] ?? null;
        if (empty($payId) && !empty($digitalPayment['intent_id']) && str_starts_with($digitalPayment['intent_id'], 'pay_')) {
            $payId = $digitalPayment['intent_id'];
        }

        if (empty($payId)) {
            $pdo->prepare("
                INSERT INTO order_audit_logs (branch_id, order_id, action, supervisor_id, reason, created_at)
                VALUES (?, ?, 'REFUND_FAILED', ?, ?, NOW())
            ")->execute([
                $branch_id,
                $order_id,
                $supervisor['id'],
                "Refund failed: Missing provider_payment_id (pay_...)"
            ]);

            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'PayMongo refund failed: Missing provider_payment_id (pay_...) for this order.']);
            exit;
        }

        $amountCents = (int)round((float)($digitalPayment['gross_amount'] ?? $order['total_price']) * 100);

        // Call PayMongo Refunds API
        $ch = curl_init('https://api.paymongo.com/v1/refunds');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'data' => [
                'attributes' => [
                    'amount'     => $amountCents,
                    'payment_id' => $payId,
                    'reason'     => 'requested_by_customer',
                    'notes'      => $reason
                ]
            ]
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($secKey . ':')
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $apiResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $decodedApi = json_decode((string)$apiResponse, true);

        // Check HTTP status: PayMongo returns 200/201 on success
        if ($curlErr || $httpCode < 200 || $httpCode >= 300) {
            $errDetail = $decodedApi['errors'][0]['detail'] ?? ($curlErr ?: 'Gateway rejected refund request');
            $logReason = "PayMongo API Error (HTTP {$httpCode}): {$errDetail}";

            $pdo->prepare("
                INSERT INTO order_audit_logs (branch_id, order_id, action, supervisor_id, reason, created_at)
                VALUES (?, ?, 'REFUND_FAILED', ?, ?, NOW())
            ")->execute([
                $branch_id,
                $order_id,
                $supervisor['id'],
                $logReason
            ]);

            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => "PayMongo refund failed (HTTP {$httpCode}): {$errDetail}"
            ]);
            exit;
        }
    }

    // 3. Database writes in a single atomic transaction ONLY AFTER PayMongo API call succeeds
    $pdo->beginTransaction();

    $origShiftId = $digitalPayment ? $digitalPayment['shift_id'] : ($order['pos_session_id'] ?: 1);
    $auditNotes = $isDigital ? "Digital Refund (Shift #{$origShiftId}, Method: {$order['payment_method']})" : "Cash Drawer Void/Refund";
    
    $stmtAudit = $pdo->prepare("INSERT INTO order_audit_logs (branch_id, order_id, action, supervisor_id, reason, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmtAudit->execute([$branch_id, $order_id, $action, $supervisor['id'], $reason . " | " . $auditNotes]);

    if ($action === 'VOID') {
        $stmtUpdate = $pdo->prepare("UPDATE orders SET order_status = 'VOID', payment_status = 'UNPAID' WHERE id = ?");
        $stmtUpdate->execute([$order_id]);
        if ($digitalPayment) {
            $pdo->prepare("UPDATE payments SET status = 'failed' WHERE id = ?")->execute([$digitalPayment['id']]);
        }
    } elseif ($action === 'REFUND') {
        $stmtUpdate = $pdo->prepare("UPDATE orders SET order_status = 'REFUNDED' WHERE id = ?");
        $stmtUpdate->execute([$order_id]);

        if ($digitalPayment) {
            $pdo->prepare("UPDATE payments SET status = 'refunded' WHERE id = ?")->execute([$digitalPayment['id']]);
        }

        // Revert Inventory Transactions
        if (!$order['is_test']) {
            $stmtItems = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $stmtItems->execute([$order_id]);
            $items = $stmtItems->fetchAll();

            foreach ($items as $itemData) {
                $qty = $itemData['quantity'];
                
                // For the product
                $stmtRecipe = $pdo->prepare("SELECT inventory_id, quantity FROM recipes WHERE product_id = ?");
                $stmtRecipe->execute([$itemData['product_id']]);
                $recipes = $stmtRecipe->fetchAll();
                
                foreach ($recipes as $r) {
                    $refundAmount = (float)$r['quantity'] * $qty;
                    
                    // Update Stock
                    $updStock = $pdo->prepare("UPDATE inventory_stock SET stock = stock + ? WHERE inventory_id = ? AND branch_id = ?");
                    $updStock->execute([$refundAmount, $r['inventory_id'], $branch_id]);
                    
                    // Get unit cost snapshot to log reversal
                    $stmtInv = $pdo->prepare("SELECT name, unit_cost FROM pos_inventory WHERE id = ?");
                    $stmtInv->execute([$r['inventory_id']]);
                    $inv = $stmtInv->fetch();
                    
                    if ($inv) {
                        $updTrans = $pdo->prepare("INSERT INTO pos_inventory_transactions (branch_id, inventory_id, item_name, type, quantity, cost, unit_cost_snapshot, status, logged_by) VALUES (?, ?, ?, 'Restock', ?, ?, ?, 'Completed', ?)");
                        $updTrans->execute([$branch_id, $r['inventory_id'], $inv['name'], $refundAmount, 0, $inv['unit_cost'], $supervisor['id']]);
                    }
                }
                
                // For Modifiers
                $stmtMod = $pdo->prepare("SELECT modifier_id FROM order_item_modifiers WHERE order_item_id IN (SELECT id FROM order_items WHERE order_id = ? AND product_id = ?)");
                $stmtMod->execute([$order_id, $itemData['product_id']]);
                $mods = $stmtMod->fetchAll();
                
                foreach ($mods as $mod) {
                    $stmtRecipeMod = $pdo->prepare("SELECT inventory_id, quantity FROM recipes WHERE modifier_id = ?");
                    $stmtRecipeMod->execute([$mod['modifier_id']]);
                    $modRecipes = $stmtRecipeMod->fetchAll();
                    
                    foreach ($modRecipes as $mr) {
                        $refundAmount = (float)$mr['quantity'] * $qty;
                        
                        $updStock = $pdo->prepare("UPDATE inventory_stock SET stock = stock + ? WHERE inventory_id = ? AND branch_id = ?");
                        $updStock->execute([$refundAmount, $mr['inventory_id'], $branch_id]);
                        
                        $stmtInv = $pdo->prepare("SELECT name, unit_cost FROM pos_inventory WHERE id = ?");
                        $stmtInv->execute([$mr['inventory_id']]);
                        $inv = $stmtInv->fetch();
                        
                        if ($inv) {
                            $updTrans = $pdo->prepare("INSERT INTO pos_inventory_transactions (branch_id, inventory_id, item_name, type, quantity, cost, unit_cost_snapshot, status, logged_by) VALUES (?, ?, ?, 'Restock', ?, ?, ?, 'Completed', ?)");
                            $updTrans->execute([$branch_id, $mr['inventory_id'], $inv['name'], $refundAmount, 0, $inv['unit_cost'], $supervisor['id']]);
                        }
                    }
                }
            }
        }
    }

    $pdo->commit();
    // $pdo commit handles everything

    echo json_encode([
        'success' => true,
        'message' => "Order {$order['order_number']} successfully {$action}ED.",
        'order_id' => $order['id'],
        'order_number' => $order['order_number'],
        'action' => $action,
        'reason' => $reason,
        'is_digital' => $isDigital,
        'refund_note' => $isDigital ? 'Digital refund recorded against PayMongo payment and original shift; zero drawer cash deducted.' : 'Cash drawer refund'
    ]);

} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    try {
        $pdo->prepare("
            INSERT INTO order_audit_logs (branch_id, order_id, action, supervisor_id, reason, created_at)
            VALUES (?, ?, 'OPERATION_FAILED', ?, ?, NOW())
        ")->execute([
            $branch_id ?? 1,
            $order_id ?? null,
            $supervisor['id'] ?? null,
            "Void/refund failed: " . $e->getMessage()
        ]);
    } catch (\Throwable $t) {}

    error_log("Void/Refund error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An error occurred while processing the request.']);
}
?>
