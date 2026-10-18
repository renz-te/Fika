<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

// 1. Fail-closed: Webhook secret must be configured
$webhookSecret = Config::get('paymongo.webhook_secret');
if (empty($webhookSecret)) {
    http_response_code(503);
    echo json_encode(['error' => 'Webhook secret not configured.']);
    exit;
}

$rawBody = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

if (empty($sigHeader)) {
    http_response_code(401);
    echo json_encode(['error' => 'Missing signature header.']);
    exit;
}

// 2. Parse Signature Header (format: t=<timestamp>,te=<hash> or li=<hash>)
$parts = explode(',', $sigHeader);
$parsed = [];
foreach ($parts as $part) {
    if (str_contains($part, '=')) {
        [$k, $v] = explode('=', trim($part), 2);
        $parsed[$k] = $v;
    }
}

if (!isset($parsed['t']) || (!isset($parsed['te']) && !isset($parsed['li']))) {
    http_response_code(401);
    echo json_encode(['error' => 'Malformed signature header.']);
    exit;
}

$timestamp = (int)$parsed['t'];
$signature = $parsed['te'] ?? $parsed['li'] ?? '';

// 3. Timestamp skew tolerance window: 300 seconds
if (abs(time() - $timestamp) > 300) {
    http_response_code(401);
    echo json_encode(['error' => 'Timestamp skew exceeds tolerance window (300s).']);
    exit;
}

// 4. Verify HMAC-SHA256 signature
$expectedSignature = hash_hmac('sha256', $timestamp . '.' . $rawBody, $webhookSecret);
if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature.']);
    exit;
}

// 5. Parse Event Payload
$eventData = json_decode($rawBody, true);
if (!is_array($eventData) || empty($eventData['data']['attributes'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid event payload structure.']);
    exit;
}

$eventType = $eventData['data']['attributes']['type'] ?? '';
$paymentInner = $eventData['data']['attributes']['data'] ?? [];
$paymentAttrs = $paymentInner['attributes'] ?? [];

$eventId = (string)($eventData['data']['id'] ?? '');
$intentId = (string)($paymentInner['id'] ?? '');
$sourceId = (string)($paymentAttrs['source']['id'] ?? '');

// 6. Production Mode Mock Protection
$isTestMode = Config::get('paymongo.is_test') === true;
$hasMockId = str_starts_with($eventId, 'evt_mock_')
    || str_starts_with($intentId, 'pay_mock_')
    || str_starts_with($intentId, 'cs_test_')
    || str_starts_with($sourceId, 'src_mock_');

if (!$isTestMode && $hasMockId) {
    Audit::log('MOCK_PAYMENT_REJECTED', "Mock event/payment ID {$intentId} rejected in production mode.");
    http_response_code(400);
    echo json_encode(['error' => 'Mock payment IDs are strictly forbidden in production mode.']);
    exit;
}

$orderId = (int)($paymentAttrs['metadata']['order_id'] ?? 0);
$method = strtolower((string)($paymentAttrs['source']['type'] ?? $paymentAttrs['metadata']['method'] ?? 'gcash'));
$providerPaymentId = $eventId;
$hasAmountField = array_key_exists('amount', $paymentAttrs);
$amountInCents = $hasAmountField && $paymentAttrs['amount'] !== null ? (int)$paymentAttrs['amount'] : null;
$feeInCents = (int)($paymentAttrs['fee'] ?? 0);

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing valid order_id in payment metadata.']);
    exit;
}

global $pdo;

try {
    $pdo->beginTransaction();

    // 7. Lock Order Row FOR UPDATE
    $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE");
    $orderStmt->execute([$orderId]);
    $lockedOrder = $orderStmt->fetch();

    if (!$lockedOrder) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Order not found.']);
        exit;
    }

    // Lock Payment Row FOR UPDATE
    $payStmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? OR intent_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $payStmt->execute([$orderId, $intentId]);
    $lockedPayment = $payStmt->fetch();

    $orderStatus = strtoupper((string)($lockedOrder['order_status'] ?? ''));
    $paymentStatus = strtoupper((string)($lockedOrder['payment_status'] ?? ''));
    $orderPayMethod = strtoupper((string)($lockedOrder['payment_method'] ?? ''));

    // Gating 1: VOID / VOIDED order
    if (in_array($orderStatus, ['VOID', 'VOIDED'], true)) {
        $pdo->rollBack();
        Audit::log('VOID_ORDER_PAYMENT_REJECTED', "Order #{$lockedOrder['order_number']} is voided, payment rejected.");
        http_response_code(409);
        echo json_encode(['error' => 'Order is voided.']);
        exit;
    }

    // Gating 2: REFUNDED order
    if ($orderStatus === 'REFUNDED' || $paymentStatus === 'REFUNDED') {
        $pdo->rollBack();
        Audit::log('REFUNDED_ORDER_PAYMENT_REJECTED', "Order #{$lockedOrder['order_number']} is refunded, payment rejected.");
        http_response_code(409);
        echo json_encode(['error' => 'Order is refunded.']);
        exit;
    }

    // Gating 3: Already paid by CASH
    if ($paymentStatus === 'PAID' && $orderPayMethod === 'CASH') {
        $pdo->rollBack();
        Audit::log('CASH_ORDER_PAYMENT_REJECTED', "Order #{$lockedOrder['order_number']} was already paid in cash.");
        http_response_code(409);
        echo json_encode(['error' => 'Order was already paid in cash.']);
        exit;
    }

    // Gating 4: Already PAID (Idempotent replay or payment.failed after payment.paid)
    if ($paymentStatus === 'PAID') {
        $pdo->rollBack();
        http_response_code(200);
        echo json_encode([
            'status' => 'noop',
            'message' => 'Order is already marked as PAID (idempotent no-op).',
            'intent_id' => $intentId
        ]);
        exit;
    }

    // Gating 5: payment.failed event alone on unpaid order
    if ($eventType === 'payment.failed') {
        if ($lockedPayment) {
            $pdo->prepare("UPDATE payments SET status = 'failed', provider_payment_id = COALESCE(?, provider_payment_id), raw_payload = ? WHERE id = ?")
                ->execute([$providerPaymentId, $rawBody, $lockedPayment['id']]);
        } else {
            $pdo->prepare("
                INSERT INTO payments (
                    order_id, shift_id, intent_id, provider_payment_id, method, payment_method,
                    gross_amount, amount_paid, fee_amount, net_amount, status, raw_payload, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, 
                    0.00, 0.00, 0.00, 0.00, 'failed', ?, NOW()
                )
            ")->execute([
                $orderId, $lockedOrder['pos_session_id'], $intentId, $providerPaymentId, $method, strtoupper($method), $rawBody
            ]);
        }
        $pdo->commit();
        Audit::log('PAYMENT_FAILED', "Payment failed recorded for Order #{$lockedOrder['order_number']} [Intent: {$intentId}]");
        http_response_code(200);
        echo json_encode(['status' => 'failed_recorded', 'message' => 'Payment failure recorded.']);
        exit;
    }

    // Only payment.paid can mark an order paid
    if ($eventType !== 'payment.paid') {
        $pdo->rollBack();
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'message' => "Event type {$eventType} ignored."]);
        exit;
    }

    // Gating 6: Missing or Non-Positive Amount
    if (!$hasAmountField || $amountInCents === null || $amountInCents <= 0) {
        $pdo->rollBack();
        if ($lockedPayment) {
            $pdo->prepare("UPDATE payments SET status = 'failed', raw_payload = ? WHERE id = ?")->execute([$rawBody, $lockedPayment['id']]);
        }
        Audit::log('PAYMENT_AMOUNT_INVALID', "Order #{$lockedOrder['order_number']} payload amount missing or non-positive ({$amountInCents} cents).");
        http_response_code(400);
        echo json_encode(['error' => 'Payment amount missing or invalid.']);
        exit;
    }

    // Gating 7: Amount Mismatch (Integer Centavos)
    $expectedCents = Money::toCentavos($lockedOrder['final_amount'] ?? $lockedOrder['total_amount']);
    if ($amountInCents !== $expectedCents) {
        $pdo->rollBack();
        if ($lockedPayment) {
            $pdo->prepare("UPDATE payments SET status = 'failed', raw_payload = ? WHERE id = ?")->execute([$rawBody, $lockedPayment['id']]);
        }
        Audit::log('PAYMENT_MISMATCH', "Order #{$lockedOrder['order_number']} amount mismatch: got {$amountInCents} cents, expected {$expectedCents} cents.");
        http_response_code(400);
        echo json_encode(['error' => 'Amount mismatch flagged. Order will not be marked as paid.']);
        exit;
    }

    // 8. Happy Path: Process Payment & Update Order
    $grossDecimal = Money::toDecimal($amountInCents);
    $feeDecimal = Money::toDecimal($feeInCents);
    $netDecimal = Money::toDecimal($amountInCents - $feeInCents);
    $shiftId = $lockedPayment['shift_id'] ?? $lockedOrder['pos_session_id'] ?? null;

    if ($lockedPayment) {
        $pdo->prepare("
            UPDATE payments 
            SET status = 'paid', 
                paid_at = NOW(), 
                gross_amount = ?, 
                amount_paid = ?,
                fee_amount = ?, 
                net_amount = ?, 
                provider_payment_id = COALESCE(?, provider_payment_id), 
                raw_payload = ? 
            WHERE id = ?
        ")->execute([$grossDecimal, $grossDecimal, $feeDecimal, $netDecimal, $providerPaymentId, $rawBody, $lockedPayment['id']]);
    } else {
        $pdo->prepare("
            INSERT INTO payments (
                order_id, shift_id, intent_id, provider_payment_id, method, payment_method,
                gross_amount, amount_paid, fee_amount, net_amount, status, paid_at, raw_payload, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, 'paid', NOW(), ?, NOW()
            )
        ")->execute([
            $orderId, $shiftId, $intentId, $providerPaymentId, $method, strtoupper($method),
            $grossDecimal, $grossDecimal, $feeDecimal, $netDecimal, $rawBody
        ]);
    }

    $pdo->prepare("
        UPDATE orders 
        SET payment_status = 'PAID', 
            order_status = 'PAID', 
            payment_method = ?, 
            payment_reference = ? 
        WHERE id = ?
    ")->execute([strtoupper($method), $intentId, $orderId]);

    // 9. Constraint 1: Inventory Deduction with Idempotency
    if (empty($lockedOrder['is_test'])) {
        $branchId = (int)$lockedOrder['branch_id'];
        $systemUserId = (int)($lockedOrder['cashier_id'] ?: 1);

        InventoryDeduction::deductOrder($orderId, $branchId, $systemUserId, $lockedOrder['order_number']);
    }

    $pdo->commit();

    // 10. Audit log after commit
    Audit::log('PAYMENT_CONFIRMED', "Payment confirmed via PayMongo webhook for Order #{$lockedOrder['order_number']} [Intent: {$intentId}, Amount: {$grossDecimal} PHP]");

    http_response_code(200);
    echo json_encode([
        'success'   => true,
        'status'    => 'success',
        'message'   => 'Payment confirmed and order marked PAID.',
        'order_id'  => $orderId,
        'intent_id' => $intentId,
        'amount'    => $grossDecimal
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Webhook processing error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Webhook processing failed.']);
}
