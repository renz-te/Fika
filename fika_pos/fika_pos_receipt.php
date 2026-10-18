<?php
require_once __DIR__ . '/../app/bootstrap.php';
class_exists('Output');

$orderId = (int)($_GET['order_id'] ?? $_GET['id'] ?? 0);
$kioskToken = $_SERVER['HTTP_X_KIOSK_TOKEN'] ?? $_GET['kiosk_token'] ?? null;

if ($orderId <= 0) {
    http_response_code(400);
    echo "<!DOCTYPE html><html><head><title>Invalid Request</title></head><body style='font-family: monospace; padding: 20px;'><h3>Invalid Request</h3><p>Valid Order ID is required.</p></body></html>";
    exit;
}

global $pdo;

// 1. Fetch Order
$orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$orderStmt->execute([$orderId]);
$order = $orderStmt->fetch();

if (!$order) {
    http_response_code(404);
    echo "<!DOCTYPE html><html><head><title>Order Not Found</title></head><body style='font-family: monospace; padding: 20px;'><h3>Order Not Found</h3><p>The specified order could not be located.</p></body></html>";
    exit;
}

// 2. Authorization: Cashier of the same branch OR valid Kiosk Token
$isAuthorized = false;

if (Auth::check()) {
    $user = Auth::user();
    if (Rbac::can('pos.access') && ((int)$user['branch_id'] === (int)$order['branch_id'] || empty($user['branch_id']))) {
        $isAuthorized = true;
    }
}

if (!$isAuthorized && !empty($kioskToken)) {
    $tokStmt = $pdo->prepare("
        SELECT token_hash, expires_at 
        FROM order_kiosk_tokens 
        WHERE order_id = ? 
        ORDER BY id DESC LIMIT 1
    ");
    $tokStmt->execute([$orderId]);
    $tok = $tokStmt->fetch();
    if ($tok && hash_equals($tok['token_hash'], hash('sha256', $kioskToken))) {
        $isAuthorized = true;
    }
}

if (!$isAuthorized) {
    http_response_code(403);
    echo "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body style='font-family: monospace; padding: 20px;'><h3>403 Forbidden</h3><p>You are not authorized to view this receipt.</p></body></html>";
    exit;
}

// 3. Settings & BIR Configuration
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $settingsStmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$businessName = $settings['receipt_business_name'] ?? 'Fika Coffee & Pastries';
$businessAddress = $settings['receipt_address'] ?? '123 Coffee St., BGC, Taguig City';
$businessTin = $settings['receipt_tin'] ?? 'TIN: 123-456-789-00000 VAT';
$businessFooter = $settings['receipt_footer'] ?? 'Thank you for your visit!';

// Check Branch details for contact or specific address
$branchStmt = $pdo->prepare("SELECT name, address, contact_number FROM branches WHERE id = ?");
$branchStmt->execute([$order['branch_id']]);
$branch = $branchStmt->fetch();
$businessPhone = $branch['contact_number'] ? 'Tel: ' . $branch['contact_number'] : '';

$isBirRegistered = false;
if (isset($settings['bir_registered']) && (string)$settings['bir_registered'] === '1') {
    $isBirRegistered = true;
} elseif (isset($settings['receipt_bir_registered']) && (string)$settings['receipt_bir_registered'] === '1') {
    $isBirRegistered = true;
}

// 4. Reprint Detection (Check if audit log already exists before rendering)
$auditCountStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM audit_logs 
    WHERE action = 'RECEIPT_PRINTED' 
      AND details LIKE ?
");
$auditCountStmt->execute(["%Order ID: {$order['id']},%"]);
$priorPrintCount = (int)$auditCountStmt->fetchColumn();
$isReprint = ($priorPrintCount > 0);

// 5. Void / Refund Information
$isVoid = in_array($order['order_status'], ['VOID', 'REFUNDED']) || $order['payment_status'] === 'REFUNDED';
$voidType = $order['order_status'] === 'REFUNDED' || $order['payment_status'] === 'REFUNDED' ? 'REFUNDED' : 'VOID';
$voidAudit = null;
if ($isVoid) {
    $vaStmt = $pdo->prepare("
        SELECT oa.*, u.username AS supervisor_name 
        FROM order_audit oa
        LEFT JOIN users u ON u.id = oa.authorized_by
        WHERE oa.order_id = ? AND oa.action IN ('VOID', 'REFUND') 
        ORDER BY oa.id DESC LIMIT 1
    ");
    $vaStmt->execute([$order['id']]);
    $voidAudit = $vaStmt->fetch();
}

// 6. Cashier Information
$cashierName = 'Kiosk / Self-Service';
if (!empty($order['cashier_id'])) {
    $cashierStmt = $pdo->prepare("
        SELECT u.username, e.first_name, e.last_name 
        FROM users u
        LEFT JOIN employees e ON e.id = u.employee_id
        WHERE u.id = ?
    ");
    $cashierStmt->execute([$order['cashier_id']]);
    $cashierUser = $cashierStmt->fetch();
    if ($cashierUser) {
        $fullName = trim(($cashierUser['first_name'] ?? '') . ' ' . ($cashierUser['last_name'] ?? ''));
        $cashierName = $fullName ?: $cashierUser['username'];
    }
}

// 7. Order Items (All money values strictly from price_snapshot_cents)
$itemsStmt = $pdo->prepare("
    SELECT oi.*, p.name AS catalog_product_name 
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = ?
");
$itemsStmt->execute([$order['id']]);
$rawItems = $itemsStmt->fetchAll();

$items = [];
$computedGrossCents = 0;

foreach ($rawItems as $ri) {
    // Read price strictly from price_snapshot_cents
    $unitPriceCents = isset($ri['price_snapshot_cents']) && (int)$ri['price_snapshot_cents'] > 0
        ? (int)$ri['price_snapshot_cents']
        : Money::toCentavos($ri['price_snapshot']);
    
    $qty = (int)$ri['quantity'];
    $itemSubtotalCents = $unitPriceCents * $qty;

    // Fetch modifiers
    $modStmt = $pdo->prepare("
        SELECT oim.*, m.name AS mod_name 
        FROM order_item_modifiers oim
        LEFT JOIN modifiers m ON m.id = oim.modifier_id
        WHERE oim.order_item_id = ?
    ");
    $modStmt->execute([$ri['id']]);
    $mods = $modStmt->fetchAll();

    foreach ($mods as $mod) {
        $modPriceCents = Money::toCentavos($mod['price_snapshot']);
        $itemSubtotalCents += ($modPriceCents * $qty);
    }

    $computedGrossCents += $itemSubtotalCents;

    $items[] = [
        'name' => $ri['catalog_product_name'] ?? 'Item #' . $ri['product_id'],
        'qty' => $qty,
        'unit_price_cents' => $unitPriceCents,
        'line_total_cents' => $itemSubtotalCents,
        'modifiers' => $mods
    ];
}

// Financial Calculations
$discountCents = Money::toCentavos($order['discount_amount']);
$finalCents = Money::toCentavos($order['final_amount']);
if ($finalCents <= 0 && $computedGrossCents > 0) {
    $finalCents = max(0, $computedGrossCents - $discountCents);
}

// VAT Calculations (12% VAT Inclusive)
$vatableSalesCents = (int) round($finalCents / 1.12);
$vatAmountCents = $finalCents - $vatableSalesCents;
$vatExemptCents = 0;
$zeroRatedCents = 0;

// 8. Payment Details
$payStmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
$payStmt->execute([$order['id']]);
$payment = $payStmt->fetch();

$paymentMethod = $payment['payment_method'] ?? ($order['payment_status'] === 'PAID' ? 'DIGITAL' : 'UNPAID');
$amountPaidCents = $payment ? Money::toCentavos($payment['amount_paid']) : $finalCents;
$changeCents = $payment ? Money::toCentavos($payment['change_amount']) : 0;

$orderDate = date('Y-m-d', strtotime($order['created_at']));
$orderTime = date('h:i A', strtotime($order['created_at']));
$csrfToken = Csrf::getToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= e($order['order_number']) ?> - Fika</title>
    <link rel="stylesheet" href="assets/css/receipt.css">
</head>
<body class="receipt-body">
    <div class="receipt-screen-wrapper">
        <!-- Screen Action Bar (Hidden during print) -->
        <div class="receipt-action-bar no-print">
            <button type="button" class="receipt-btn receipt-btn-back" onclick="window.history.back()">Back</button>
            <button type="button" class="receipt-btn receipt-btn-print" onclick="triggerPrint()">Print Receipt</button>
        </div>

        <!-- 58mm Receipt Container -->
        <div class="receipt-container" id="receipt-print-area">
            <!-- Header -->
            <div class="receipt-header">
                <div class="receipt-title"><?= e($businessName) ?></div>
                <div class="receipt-subtitle"><?= e($businessAddress) ?></div>
                <?php if (!empty($businessPhone)): ?>
                    <div class="receipt-subtitle"><?= e($businessPhone) ?></div>
                <?php endif; ?>
                <div class="receipt-tin"><?= e($businessTin) ?></div>
            </div>

            <!-- Dynamic Markers -->
            <?php if ($isReprint): ?>
            <div class="receipt-marker-box receipt-marker-reprint">
                *** REPRINT ***
            </div>
            <?php endif; ?>

            <?php if ($isVoid): ?>
            <div class="receipt-marker-box receipt-marker-<?= $voidType === 'REFUNDED' ? 'refund' : 'void' ?>">
                <div style="font-size: 13px; font-weight: bold;">*** <?= e($voidType) ?> ***</div>
                <div class="receipt-void-details">
                    <div>Original Receipt #: <strong><?= e($order['order_number']) ?></strong></div>
                    <?php if ($voidAudit): ?>
                        <div>Reason: <span><?= e($voidAudit['reason'] ?? 'N/A') ?></span></div>
                        <div>Supervisor: <span><?= e($voidAudit['supervisor_name'] ?? 'Authorized') ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($order['is_test'])): ?>
            <div class="receipt-marker-box" style="background-color: #fff3cd; color: #856404; border-color: #856404;">
                *** TRAINING MODE ***<br>NOT AN OFFICIAL RECEIPT
            </div>
            <?php endif; ?>

            <div class="receipt-divider"></div>

            <!-- Metadata Table -->
            <table class="receipt-meta-table">
                <tr>
                    <td class="receipt-meta-left">Receipt #: <strong><?= e($order['order_number']) ?></strong></td>
                    <td class="receipt-meta-right"><?= e($orderDate) ?></td>
                </tr>
                <tr>
                    <td class="receipt-meta-left">Cashier: <?= e($cashierName) ?></td>
                    <td class="receipt-meta-right"><?= e($orderTime) ?></td>
                </tr>
                <tr>
                    <td class="receipt-meta-left">Shift ID: #<?= e((string)($order['pos_session_id'] ?? 1)) ?></td>
                    <td class="receipt-meta-right">Station: POS-01</td>
                </tr>
            </table>

            <!-- Line Items Table -->
            <table class="receipt-items-table">
                <thead>
                    <tr>
                        <th class="receipt-col-item">Item Description</th>
                        <th class="receipt-col-qty">Qty</th>
                        <th class="receipt-col-amt">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): 
                        $unitPriceFormatted = Money::toDecimal($it['unit_price_cents']);
                        $lineTotalFormatted = Money::toDecimal($it['line_total_cents']);
                    ?>
                    <tr>
                        <td class="receipt-col-item <?= $isVoid ? 'receipt-item-voided' : '' ?>">
                            <div><?= e($it['name']) ?></div>
                            <div class="receipt-item-formula"><?= (int)$it['qty'] ?> x ₱<?= $unitPriceFormatted ?> = ₱<?= $lineTotalFormatted ?></div>
                            <?php if (!empty($it['modifiers'])): ?>
                                <?php foreach ($it['modifiers'] as $mod): ?>
                                    <div class="receipt-item-mod">+ <?= e($mod['mod_name'] ?? 'Modifier') ?></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td class="receipt-col-qty <?= $isVoid ? 'receipt-item-voided' : '' ?>"><?= (int)$it['qty'] ?></td>
                        <td class="receipt-col-amt <?= $isVoid ? 'receipt-item-voided' : '' ?>">₱<?= $lineTotalFormatted ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="receipt-divider"></div>

            <!-- Financial Summary Table -->
            <table class="receipt-summary-table">
                <tr>
                    <td class="receipt-summary-label">Subtotal (Gross)</td>
                    <td class="receipt-summary-val">₱<?= Money::toDecimal($computedGrossCents) ?></td>
                </tr>
                <?php if ($discountCents > 0): ?>
                <tr style="color: #c0392b;">
                    <td class="receipt-summary-label">Discount</td>
                    <td class="receipt-summary-val">-₱<?= Money::toDecimal($discountCents) ?></td>
                </tr>
                <?php endif; ?>

                <?php if ($isBirRegistered): ?>
                <tr>
                    <td class="receipt-summary-label">VATable Sales</td>
                    <td class="receipt-summary-val">₱<?= Money::toDecimal($vatableSalesCents) ?></td>
                </tr>
                <tr>
                    <td class="receipt-summary-label">VAT (12%)</td>
                    <td class="receipt-summary-val">₱<?= Money::toDecimal($vatAmountCents) ?></td>
                </tr>
                <tr>
                    <td class="receipt-summary-label">VAT-Exempt Sales</td>
                    <td class="receipt-summary-val">₱<?= Money::toDecimal($vatExemptCents) ?></td>
                </tr>
                <tr>
                    <td class="receipt-summary-label">Zero-Rated Sales</td>
                    <td class="receipt-summary-val">₱<?= Money::toDecimal($zeroRatedCents) ?></td>
                </tr>
                <?php endif; ?>

                <tr class="receipt-total-row">
                    <td class="receipt-summary-label">TOTAL AMOUNT</td>
                    <td class="receipt-summary-val">₱<?= Money::toDecimal($finalCents) ?></td>
                </tr>
                <tr>
                    <td class="receipt-summary-label">
                        Payment Method: <?= e($paymentMethod) ?>
                    </td>
                    <td class="receipt-summary-val">
                        ₱<?= Money::toDecimal($amountPaidCents) ?>
                    </td>
                </tr>
                <?php if (strtoupper($paymentMethod) === 'CASH' || $changeCents > 0): ?>
                <tr>
                    <td class="receipt-summary-label">Change</td>
                    <td class="receipt-summary-val">₱<?= Money::toDecimal($changeCents) ?></td>
                </tr>
                <?php endif; ?>
            </table>

            <!-- BIR Statutory Notice & Footer -->
            <div class="receipt-footer">
                <div class="receipt-divider"></div>
                <?php if ($isBirRegistered): ?>
                    <div class="receipt-notice">THIS SERVES AS YOUR OFFICIAL RECEIPT</div>
                <?php endif; ?>
                <div class="receipt-thanks"><?= e($businessFooter) ?></div>
            </div>
        </div>
    </div>

    <script>
        async function triggerPrint() {
            try {
                await fetch('api/fika_pos_receipt_log.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': <?= json_encode($csrfToken) ?>
                    },
                    body: JSON.stringify({
                        order_id: <?= (int)$order['id'] ?>,
                        kiosk_token: <?= json_encode($kioskToken) ?>
                    })
                });
            } catch (err) {
                console.error('Failed to log print audit:', err);
            }
            window.print();
        }
    </script>
</body>
</html>
