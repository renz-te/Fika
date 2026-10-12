<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../database.php';

// Load PayMongo configuration
$paymongoConfig = file_exists(__DIR__ . '/../../config/paymongo.php') 
    ? require __DIR__ . '/../../config/paymongo.php' 
    : [
        'webhook_secret' => getenv('PAYMONGO_WEBHOOK_SECRET') ?: 'whk_test_xxyz1234',
        'is_test' => true
    ];

$webhookSecret = $paymongoConfig['webhook_secret'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$rawPayload = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? $_SERVER['HTTP_SIGNATURE'] ?? '';

if (empty($signatureHeader)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: Missing Paymongo-Signature header']);
    exit;
}

// 1. Verify PayMongo signature: t=timestamp,te=test_sig,li=live_sig
$sigParts = explode(',', $signatureHeader);
$parsedSig = [];
foreach ($sigParts as $part) {
    $sub = explode('=', trim($part), 2);
    if (count($sub) === 2) {
        $parsedSig[trim($sub[0])] = trim($sub[1]);
    }
}

if (!isset($parsedSig['t'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: Malformed signature header']);
    exit;
}

$timestamp = $parsedSig['t'];
$signatureToVerify = $parsedSig['te'] ?? $parsedSig['li'] ?? null;
$expectedSignature = hash_hmac('sha256', $timestamp . '.' . $rawPayload, $webhookSecret);

if (!$signatureToVerify || !hash_equals($expectedSignature, $signatureToVerify)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: Signature mismatch']);
    exit;
}

// 2. Parse payload
$payload = json_decode($rawPayload, true);
if (!$payload || !isset($payload['data'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload structure']);
    exit;
}

$eventData = $payload['data'];
$eventType = $eventData['attributes']['type'] ?? $eventData['type'] ?? '';
$innerData = $eventData['attributes']['data'] ?? $eventData;
$innerAttr = $innerData['attributes'] ?? [];

// Resolve Intent ID, Metadata, and Amounts
$intentId = $innerData['id'] ?? null;
$metadata = $innerAttr['metadata'] ?? [];
$orderId = isset($metadata['order_id']) ? (int)$metadata['order_id'] : 0;
$method = strtolower($metadata['method'] ?? 'gcash');

// Extract provider payment ID (pay_...) if present
$providerPaymentId = null;
if (!empty($innerAttr['payments'][0]['id'])) {
    $providerPaymentId = $innerAttr['payments'][0]['id'];
} elseif (is_string($innerData['id']) && str_starts_with($innerData['id'], 'pay_')) {
    $providerPaymentId = $innerData['id'];
}

// Extract amount and fees in centavos
$amountInCents = 0;
$feeInCents = 0;

if (isset($innerAttr['amount'])) {
    $amountInCents = (int)$innerAttr['amount'];
    $feeInCents = (int)($innerAttr['fee'] ?? 0);
} elseif (!empty($innerAttr['payments'][0]['attributes'])) {
    $pAttr = $innerAttr['payments'][0]['attributes'];
    $amountInCents = (int)($pAttr['amount'] ?? 0);
    $feeInCents = (int)($pAttr['fee'] ?? 0);
    if (isset($pAttr['source']['type'])) {
        $method = strtolower($pAttr['source']['type']);
    }
}

// 3. Event-Type Gate
$isPaidEvent = in_array($eventType, ['payment.paid', 'checkout_session.payment.paid'], true);

if (!$isPaidEvent) {
    if ($eventType === 'payment.failed') {
        if (!$intentId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing intent_id in payload']);
            exit;
        }

        try {
            $payStmt = $pdo->prepare("SELECT * FROM payments WHERE intent_id = ?");
            $payStmt->execute([$intentId]);
            $existingPayment = $payStmt->fetch();

            if (!$existingPayment && $orderId > 0) {
                $payOrderStmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
                $payOrderStmt->execute([$orderId]);
                $existingPayment = $payOrderStmt->fetch();
            }

            if ($existingPayment) {
                $updFail = $pdo->prepare("UPDATE payments SET status = 'failed', raw_payload = ? WHERE id = ?");
                $updFail->execute([$rawPayload, $existingPayment['id']]);
            } else {
                $shiftId = (int)($metadata['shift_id'] ?? 1);
                $amountInCents = (int)($innerAttr['amount'] ?? 0);
                $grossDecimal = $amountInCents > 0 ? ($amountInCents / 100) : 0.00;

                $insPayment = $pdo->prepare("
                    INSERT INTO payments (
                        order_id, shift_id, intent_id, method, 
                        gross_amount, fee_amount, net_amount, status, raw_payload, created_at
                    ) VALUES (
                        ?, ?, ?, ?, 
                        ?, 0.00, ?, 'failed', ?, NOW()
                    )
                ");
                $insPayment->execute([
                    $orderId, $shiftId, $intentId, $method, 
                    $grossDecimal, $grossDecimal, $rawPayload
                ]);
            }

            http_response_code(200);
            echo json_encode([
                'status' => 'failed_recorded',
                'message' => 'Payment failure recorded.',
                'intent_id' => $intentId
            ]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to record failed payment: ' . $e->getMessage()]);
            exit;
        }
    }

    // Any other event type: return 200 {"status":"ignored"} with no DB writes
    http_response_code(200);
    echo json_encode([
        'status' => 'ignored',
        'message' => 'Unhandled event type ignored.',
        'event_type' => $eventType
    ]);
    exit;
}

if (!$intentId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing intent_id in payload']);
    exit;
}

try {
    // 4. Resolve payment record by intent_id or order_id fallback
    $payStmt = $pdo->prepare("SELECT * FROM payments WHERE intent_id = ?");
    $payStmt->execute([$intentId]);
    $existingPayment = $payStmt->fetch();

    // Fallback: If payment record not found by intent_id, find by order_id
    if (!$existingPayment && $orderId > 0) {
        $payOrderStmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $payOrderStmt->execute([$orderId]);
        $existingPayment = $payOrderStmt->fetch();
    }

    if (!$orderId && $existingPayment) {
        $orderId = (int)$existingPayment['order_id'];
    }

    // 1. After resolving order_id/intent_id fallback, return 200 no-op if already paid
    if ($existingPayment && $existingPayment['status'] === 'paid') {
        http_response_code(200);
        echo json_encode([
            'status' => 'noop', 
            'message' => 'Payment already processed and marked paid (idempotent no-op).',
            'intent_id' => $intentId
        ]);
        exit;
    }

    // 5. Fetch Order & Verify Amount
    $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch();

    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => "Order #{$orderId} not found"]);
        exit;
    }

    // If order itself is already marked PAID, return 200 no-op
    if ($order['payment_status'] === 'PAID') {
        http_response_code(200);
        echo json_encode([
            'status' => 'noop', 
            'message' => 'Order already processed and marked paid (idempotent no-op).',
            'intent_id' => $intentId
        ]);
        exit;
    }

    $expectedCents = (int)round((float)$order['total_price'] * 100);

    // Amount Verification: Discrepancy check
    if ($amountInCents > 0 && $amountInCents !== $expectedCents) {
        // Amount Mismatch: Flag it, do NOT mark paid
        if ($existingPayment) {
            $pdo->prepare("UPDATE payments SET status = 'failed', provider_payment_id = COALESCE(?, provider_payment_id), raw_payload = ? WHERE id = ?")
                ->execute([$providerPaymentId, $rawPayload, $existingPayment['id']]);
        }
        $pdo->prepare("INSERT INTO order_audit_logs (branch_id, order_id, action, reason) VALUES (?, ?, 'PAYMENT_MISMATCH', ?)")
            ->execute([$order['branch_id'], $orderId, "Amount mismatch: Webhook sent {$amountInCents} cents, expected {$expectedCents} cents"]);

        http_response_code(400);
        echo json_encode([
            'error' => 'Amount mismatch flagged. Order will NOT be marked as paid.',
            'webhook_cents' => $amountInCents,
            'expected_cents' => $expectedCents
        ]);
        exit;
    }

    // 6. Atomic Single Database Transaction with Pessimistic Locking
    $pdo->beginTransaction();

    // 2. Pessimistic lock on orders row
    $lockOrderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE");
    $lockOrderStmt->execute([$orderId]);
    $lockedOrder = $lockOrderStmt->fetch();

    if (!$lockedOrder) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => "Order #{$orderId} not found during lock acquisition"]);
        exit;
    }

    // Pessimistic lock on payments row
    $lockedPayment = null;
    if ($existingPayment) {
        $lockPayStmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? FOR UPDATE");
        $lockPayStmt->execute([$existingPayment['id']]);
        $lockedPayment = $lockPayStmt->fetch();
    } else {
        $lockPayStmt = $pdo->prepare("SELECT * FROM payments WHERE intent_id = ? OR order_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $lockPayStmt->execute([$intentId, $orderId]);
        $lockedPayment = $lockPayStmt->fetch();
    }

    // Re-check status immediately after acquiring lock before writing (race condition defense)
    if (($lockedPayment && $lockedPayment['status'] === 'paid') || ($lockedOrder['payment_status'] === 'PAID')) {
        $pdo->rollBack();
        http_response_code(200);
        echo json_encode([
            'status' => 'noop', 
            'message' => 'Payment already processed and marked paid (concurrent race avoided).',
            'intent_id' => $intentId
        ]);
        exit;
    }

    $grossDecimal = $amountInCents > 0 ? ($amountInCents / 100) : (float)$lockedOrder['total_price'];
    $feeDecimal = $feeInCents / 100;
    $netDecimal = $grossDecimal - $feeDecimal;
    $shiftId = $lockedPayment ? (int)$lockedPayment['shift_id'] : (int)($lockedOrder['pos_session_id'] ?: 1);

    // 3. Ensure that inventory deduction runs ONLY on the pending -> paid transition
    $wasPending = (!$lockedPayment || $lockedPayment['status'] === 'pending') && ($lockedOrder['payment_status'] !== 'PAID');

    // Update or Insert payments row
    if ($lockedPayment) {
        $updPayment = $pdo->prepare("
            UPDATE payments 
            SET status = 'paid', 
                paid_at = NOW(), 
                gross_amount = ?, 
                fee_amount = ?, 
                net_amount = ?, 
                provider_payment_id = COALESCE(?, provider_payment_id), 
                raw_payload = ? 
            WHERE id = ?
        ");
        $updPayment->execute([$grossDecimal, $feeDecimal, $netDecimal, $providerPaymentId, $rawPayload, $lockedPayment['id']]);
    } else {
        $insPayment = $pdo->prepare("
            INSERT INTO payments (
                order_id, shift_id, intent_id, provider_payment_id, method, 
                gross_amount, fee_amount, net_amount, status, paid_at, raw_payload, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, ?, 'paid', NOW(), ?, NOW()
            )
        ");
        $insPayment->execute([
            $orderId, $shiftId, $intentId, $providerPaymentId, $method, 
            $grossDecimal, $feeDecimal, $netDecimal, $rawPayload
        ]);
    }

    // Update order status = PAID and order_status = COMPLETED
    $formattedMethod = strtoupper($method === 'paymaya' ? 'MAYA' : $method);
    $updOrder = $pdo->prepare("
        UPDATE orders 
        SET payment_status = 'PAID', 
            order_status = 'COMPLETED', 
            payment_method = ?, 
            payment_reference = ? 
        WHERE id = ?
    ");
    $updOrder->execute([$formattedMethod, $intentId, $orderId]);

    // Inventory Deduction: Ensure that inventory deduction runs ONLY on the pending -> paid transition
    if ($wasPending && empty($lockedOrder['is_test'])) {
        $branchId = (int)$lockedOrder['branch_id'];
        $itemsStmt = $pdo->prepare("SELECT product_id, quantity, id FROM order_items WHERE order_id = ?");
        $itemsStmt->execute([$orderId]);
        $orderItems = $itemsStmt->fetchAll();

        foreach ($orderItems as $item) {
            $qty = (float)$item['quantity'];

            // 1. Product recipe ingredients
            $pRec = $pdo->prepare("SELECT inventory_id, quantity FROM recipes WHERE product_id = ?");
            $pRec->execute([$item['product_id']]);
            foreach ($pRec->fetchAll() as $r) {
                $deduct = (float)$r['quantity'] * $qty;
                $pdo->prepare("UPDATE inventory_stock SET stock = stock - ? WHERE inventory_id = ? AND branch_id = ?")
                    ->execute([$deduct, $r['inventory_id'], $branchId]);

                // Write inventory transaction ledger row
                $pdo->prepare("
                    INSERT INTO inventory_transactions (
                        branch_id, inventory_id, type, quantity, status, transaction_date
                    ) VALUES (
                        ?, ?, 'Usage', ?, 'Completed', NOW()
                    )
                ")->execute([$branchId, $r['inventory_id'], $deduct]);
            }

            // 2. Modifier recipe ingredients
            $mStmt = $pdo->prepare("SELECT modifier_id FROM order_item_modifiers WHERE order_item_id = ?");
            $mStmt->execute([$item['id']]);
            $mods = $mStmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($mods as $modId) {
                $mRec = $pdo->prepare("SELECT inventory_id, quantity FROM recipes WHERE modifier_id = ?");
                $mRec->execute([$modId]);
                foreach ($mRec->fetchAll() as $mr) {
                    $deduct = (float)$mr['quantity'] * $qty;
                    $pdo->prepare("UPDATE inventory_stock SET stock = stock - ? WHERE inventory_id = ? AND branch_id = ?")
                        ->execute([$deduct, $mr['inventory_id'], $branchId]);

                    $pdo->prepare("
                        INSERT INTO inventory_transactions (
                            branch_id, inventory_id, type, quantity, status, transaction_date
                        ) VALUES (
                            ?, ?, 'Usage', ?, 'Completed', NOW()
                        )
                    ")->execute([$branchId, $mr['inventory_id'], $deduct]);
                }
            }
        }
    }

    $pdo->commit();

    http_response_code(200);
    echo json_encode([
        'success'   => true,
        'status'    => 'success',
        'message'   => 'Payment confirmed and order marked PAID.',
        'order_id'  => $orderId,
        'shift_id'  => $shiftId,
        'intent_id' => $intentId,
        'amount'    => $grossDecimal
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Webhook processing failed: ' . $e->getMessage()]);
}
