<?php
require_once __DIR__ . '/../database.php';
// Include config to get the PayMongo keys
$config = require __DIR__ . '/../../cafe_hrms/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$rawBody = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

if (empty($signatureHeader)) {
    http_response_code(401);
    exit('Unauthorized: Missing signature');
}

// Parse paymongo-signature header: t=1603204323,te=test_sig,li=live_sig
$parts = explode(',', $signatureHeader);
$signatures = [];
foreach ($parts as $part) {
    list($key, $value) = explode('=', trim($part), 2);
    $signatures[$key] = $value;
}

if (!isset($signatures['t'])) {
    http_response_code(401);
    exit('Unauthorized: Invalid signature format');
}

$timestamp = $signatures['t'];
$signaturePayload = $timestamp . '.' . $rawBody;
$computedHash = hash_hmac('sha256', $signaturePayload, PAYMONGO_WEBHOOK_SECRET);

$isValid = false;
if (isset($signatures['te']) && hash_equals($computedHash, $signatures['te'])) {
    $isValid = true;
}
if (isset($signatures['li']) && hash_equals($computedHash, $signatures['li'])) {
    $isValid = true;
}

if (!$isValid) {
    http_response_code(401);
    exit('Unauthorized: Signature mismatch');
}

// Signature is valid, parse payload
$payload = json_decode($rawBody, true);
if (!$payload || !isset($payload['data']['type'])) {
    http_response_code(400);
    exit('Bad Request');
}

$eventType = $payload['data']['attributes']['type'] ?? $payload['data']['type'] ?? '';

// We are listening for 'payment.paid' or 'checkout_session.payment.paid'
if ($eventType === 'checkout_session.payment.paid') {
    $checkoutSession = $payload['data']['attributes']['data']['attributes'] ?? null;
    if (!$checkoutSession) {
        $checkoutSession = $payload['data']['attributes'] ?? null;
    }
    
    // Sometimes the structure varies slightly, but metadata should be accessible
    $metadata = $checkoutSession['metadata'] ?? [];
    $order_id = $metadata['order_id'] ?? null;
    
    $payments = $checkoutSession['payments'] ?? [];
    $transaction_id = null;
    $fee = 0;
    if (!empty($payments)) {
        $transaction_id = $payments[0]['id'] ?? null;
        $fee = ($payments[0]['attributes']['fee'] ?? 0) / 100;
    } else {
        // Fallback for payment.paid event
        $transaction_id = $payload['data']['attributes']['data']['id'] ?? $payload['data']['id'] ?? null;
        $fee = ($payload['data']['attributes']['data']['attributes']['fee'] ?? $payload['data']['attributes']['fee'] ?? 0) / 100;
    }
    
    if ($order_id) {
        try {
            $pdo->beginTransaction();
            
            // Check if order exists and is unpaid
            $stmt = $pdo->prepare("SELECT id, payment_status, is_test, branch_id FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();
            
            if ($order && $order['payment_status'] !== 'PAID') {
                $payment_method = 'eWallet';
                
                // Update order
                $update = $pdo->prepare("UPDATE orders SET payment_status = 'PAID', payment_method = ?, payment_reference = ?, created_at = CURRENT_TIMESTAMP WHERE id = ?");
                $update->execute([$payment_method, $transaction_id, $order_id]);
                
                // Deduct inventory (since it was unpaid)
                $is_test = $order['is_test'];
                $branch_id = $order['branch_id'];
                
                if (!$is_test) {
                    $stmtItems = $pdo->prepare("SELECT product_id, quantity, id FROM order_items WHERE order_id = ?");
                    $stmtItems->execute([$order_id]);
                    $items = $stmtItems->fetchAll();
                    
                    foreach ($items as $itemData) {
                        $qty = $itemData['quantity'];
                        
                        $stmtRec = $pdo->prepare("SELECT inventory_id, quantity FROM recipes WHERE product_id = ?");
                        $stmtRec->execute([$itemData['product_id']]);
                        foreach ($stmtRec->fetchAll() as $r) {
                            $deduct = (float)$r['quantity'] * $qty;
                            $upd = $pdo->prepare("UPDATE inventory_stock SET stock = stock - ? WHERE inventory_id = ? AND branch_id = ?");
                            $upd->execute([$deduct, $r['inventory_id'], $branch_id]);
                        }
                        
                        $stmtMod = $pdo->prepare("SELECT modifier_id FROM order_item_modifiers WHERE order_item_id = ?");
                        $stmtMod->execute([$itemData['id']]);
                        $mods = $stmtMod->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($mods as $mod_id) {
                            $stmtMRec = $pdo->prepare("SELECT inventory_id, quantity FROM recipes WHERE modifier_id = ?");
                            $stmtMRec->execute([$mod_id]);
                            foreach ($stmtMRec->fetchAll() as $mr) {
                                $deduct = (float)$mr['quantity'] * $qty;
                                $upd = $pdo->prepare("UPDATE inventory_stock SET stock = stock - ? WHERE inventory_id = ? AND branch_id = ?");
                                $upd->execute([$deduct, $mr['inventory_id'], $branch_id]);
                            }
                        }
                    }
                }
                
                // Optional: Store fee in reconciliation table if it exists
                $stmtCheckTable = $pdo->query("SHOW TABLES LIKE 'payment_reconciliations'");
                if ($stmtCheckTable->rowCount() > 0) {
                    $stmtIns = $pdo->prepare("INSERT INTO payment_reconciliations (order_id, transaction_id, fee_amount, provider) VALUES (?, ?, ?, 'PayMongo')");
                    $stmtIns->execute([$order_id, $transaction_id, $fee]);
                }
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("PayMongo Webhook Error: " . $e->getMessage());
            http_response_code(500);
            exit('Internal Server Error');
        }
    }
} elseif ($eventType === 'payment.paid') {
    // Also handle direct payment.paid if needed
    $payment = $payload['data']['attributes'] ?? null;
    $metadata = $payment['metadata'] ?? [];
    $order_id = $metadata['order_id'] ?? null;
    $transaction_id = $payload['data']['id'] ?? null;
    $fee = ($payment['fee'] ?? 0) / 100;
    
    if ($order_id) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT id, payment_status, is_test, branch_id FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();
            
            if ($order && $order['payment_status'] !== 'PAID') {
                $payment_method = 'eWallet';
                $update = $pdo->prepare("UPDATE orders SET payment_status = 'PAID', payment_method = ?, payment_reference = ?, created_at = CURRENT_TIMESTAMP WHERE id = ?");
                $update->execute([$payment_method, $transaction_id, $order_id]);
                
                $is_test = $order['is_test'];
                $branch_id = $order['branch_id'];
                
                if (!$is_test) {
                    $stmtItems = $pdo->prepare("SELECT product_id, quantity, id FROM order_items WHERE order_id = ?");
                    $stmtItems->execute([$order_id]);
                    $items = $stmtItems->fetchAll();
                    
                    foreach ($items as $itemData) {
                        $qty = $itemData['quantity'];
                        
                        $stmtRec = $pdo->prepare("SELECT inventory_id, quantity FROM recipes WHERE product_id = ?");
                        $stmtRec->execute([$itemData['product_id']]);
                        foreach ($stmtRec->fetchAll() as $r) {
                            $deduct = (float)$r['quantity'] * $qty;
                            $upd = $pdo->prepare("UPDATE inventory_stock SET stock = stock - ? WHERE inventory_id = ? AND branch_id = ?");
                            $upd->execute([$deduct, $r['inventory_id'], $branch_id]);
                        }
                        
                        $stmtMod = $pdo->prepare("SELECT modifier_id FROM order_item_modifiers WHERE order_item_id = ?");
                        $stmtMod->execute([$itemData['id']]);
                        $mods = $stmtMod->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($mods as $mod_id) {
                            $stmtMRec = $pdo->prepare("SELECT inventory_id, quantity FROM recipes WHERE modifier_id = ?");
                            $stmtMRec->execute([$mod_id]);
                            foreach ($stmtMRec->fetchAll() as $mr) {
                                $deduct = (float)$mr['quantity'] * $qty;
                                $upd = $pdo->prepare("UPDATE inventory_stock SET stock = stock - ? WHERE inventory_id = ? AND branch_id = ?");
                                $upd->execute([$deduct, $mr['inventory_id'], $branch_id]);
                            }
                        }
                    }
                }
                
                $stmtCheckTable = $pdo->query("SHOW TABLES LIKE 'payment_reconciliations'");
                if ($stmtCheckTable->rowCount() > 0) {
                    $stmtIns = $pdo->prepare("INSERT INTO payment_reconciliations (order_id, transaction_id, fee_amount, provider) VALUES (?, ?, ?, 'PayMongo')");
                    $stmtIns->execute([$order_id, $transaction_id, $fee]);
                }
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("PayMongo Webhook Error: " . $e->getMessage());
            http_response_code(500);
            exit('Internal Server Error');
        }
    }
}

http_response_code(200);
echo json_encode(['status' => 'success']);
