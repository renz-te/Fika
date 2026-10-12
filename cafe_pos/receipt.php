<?php
/**
 * Standalone Receipt Viewer & Print Page (BIR 58mm Thermal Compliant)
 * Supports: Cashier print, Reprint (?reprint=1), and Void/Refund (?void=1)
 */
require_once __DIR__ . '/database.php';
if (file_exists(__DIR__ . '/../cafe_hrms/functions.php')) {
    require_once __DIR__ . '/../cafe_hrms/functions.php';
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$order_number = isset($_GET['order_no']) ? trim($_GET['order_no']) : '';
$is_reprint = !empty($_GET['reprint']);
$is_void_requested = !empty($_GET['void']);
$void_reason_input = isset($_GET['reason']) ? trim($_GET['reason']) : '';

$order = null;
if ($order_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
} elseif (!empty($order_number)) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ?");
    $stmt->execute([$order_number]);
    $order = $stmt->fetch();
}

if (!$order) {
    // If no order specified, provide dummy or 404
    http_response_code(404);
    echo "<!DOCTYPE html><html><head><title>Receipt Not Found</title></head><body style='font-family: monospace; padding: 20px;'><h3>Receipt Not Found</h3><p>Please specify a valid order ID or Order Number.</p></body></html>";
    exit;
}

$branch_id = (int)($order['branch_id'] ?? 1);
$branchName = 'Slow Hours Cafe';
$branchAddr = 'Bayan, Dasmarinas City, Cavite';
$branchPhone = 'Tel: (555) 123-4567';
$birTin = 'TIN: 123-456-789-00000 VAT';

try {
    if (function_exists('get_setting')) {
        $branchName = get_setting($pdo, 'company_name', $branchName);
        $branchAddr = get_setting($pdo, 'company_address', $branchAddr);
        $birTin = get_setting($pdo, 'bir_tin', $birTin);
    }
    $stmtBr = $pdo->prepare("SELECT name, address, contact_number FROM branches WHERE id = ?");
    $stmtBr->execute([$branch_id]);
    $brRow = $stmtBr->fetch();
    if ($brRow) {
        if (!empty($brRow['name'])) $branchName = $brRow['name'];
        if (!empty($brRow['address'])) $branchAddr = $brRow['address'];
        if (!empty($brRow['contact_number'])) $branchPhone = 'Tel: ' . $brRow['contact_number'];
    }
} catch (\Exception $e) {}

// Fetch Order Items
$stmtItems = $pdo->prepare("
    SELECT oi.*, p.name AS product_name, p.price AS product_price
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmtItems->execute([$order['id']]);
$items = $stmtItems->fetchAll();

foreach ($items as &$it) {
    $stmtMods = $pdo->prepare("
        SELECT m.name, m.price_adjustment
        FROM order_item_modifiers oim
        JOIN modifiers m ON oim.modifier_id = m.id
        WHERE oim.order_item_id = ?
    ");
    $stmtMods->execute([$it['id']]);
    $it['modifiers'] = $stmtMods->fetchAll();
}
unset($it);

// Void / Refund Check
$is_void = $is_void_requested || in_array($order['order_status'], ['VOID', 'REFUNDED']);
$void_title = ($order['order_status'] === 'REFUNDED') ? '** REFUND RECEIPT **' : '** VOID RECEIPT **';
$void_reason = $void_reason_input;
$void_supervisor = 'Supervisor';

if ($is_void) {
    try {
        $stmtAudit = $pdo->prepare("
            SELECT oal.*, u.username, u.name AS supervisor_name
            FROM order_audit_logs oal
            LEFT JOIN users u ON oal.supervisor_id = u.id
            WHERE oal.order_id = ? AND oal.action IN ('VOID', 'REFUND')
            ORDER BY oal.id DESC LIMIT 1
        ");
        $stmtAudit->execute([$order['id']]);
        $audit = $stmtAudit->fetch();
        if ($audit) {
            if (!empty($audit['reason'])) $void_reason = $audit['reason'];
            if (!empty($audit['supervisor_name'])) $void_supervisor = $audit['supervisor_name'];
            elseif (!empty($audit['username'])) $void_supervisor = $audit['username'];
            if ($audit['action'] === 'REFUND') $void_title = '** REFUND RECEIPT **';
        }
    } catch (\Exception $e) {}
    if (empty($void_reason)) $void_reason = 'Cancelled by Cashier/Supervisor';
}

// Financial calculations
$gross_total = 0;
foreach ($items as $it) {
    $gross_total += (float)$it['subtotal'];
}
$discount_amount = (float)($order['discount_amount'] ?? 0);
$net_payable = (float)$order['total_price'];
$vat_amount = (float)($order['vat_amount'] ?? 0);

if ($vat_amount > 0) {
    $vatable = $net_payable - $vat_amount;
    $vat_exempt = 0.00;
} elseif ($discount_amount > 0) {
    $vatable = 0.00;
    $vat_exempt = $net_payable;
} else {
    $vatable = $net_payable / 1.12;
    $vat_amount = $net_payable - $vatable;
    $vat_exempt = 0.00;
}
$zero_rated = 0.00;

$date_str = date('m/d/Y', strtotime($order['created_at']));
$time_str = date('h:i A', strtotime($order['created_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= htmlspecialchars($order['order_number']) ?><?= $is_reprint ? ' (REPRINT)' : '' ?></title>
    <link rel="stylesheet" href="assets/css/receipt.css">
    <style>
        body {
            background: #f1f5f9;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
        }
        .screen-wrapper {
            background: #ffffff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            max-width: 380px;
            width: 100%;
        }
        .action-bar {
            padding: 10px;
            background: #1e293b;
            display: flex;
            gap: 10px;
            justify-content: space-between;
        }
        .action-btn {
            background: #2ecc71;
            color: white;
            border: none;
            padding: 10px 16px;
            cursor: pointer;
            font-weight: bold;
            font-family: inherit;
            border-radius: 4px;
            flex: 1;
        }
        .action-btn-back {
            background: #475569;
        }
    </style>
</head>
<body>
    <div class="screen-wrapper">
        <div class="action-bar no-print">
            <button class="action-btn action-btn-back" onclick="window.history.back()">Back</button>
            <button class="action-btn" onclick="window.print()">Print Receipt</button>
        </div>

        <div class="receipt-container" id="receipt-print-area">
            <!-- Header -->
            <div class="receipt-header">
                <div class="receipt-title"><?= htmlspecialchars($branchName) ?></div>
                <div class="receipt-subtitle"><?= htmlspecialchars($branchAddr) ?></div>
                <div class="receipt-subtitle"><?= htmlspecialchars($branchPhone) ?></div>
                <div class="receipt-tin"><?= htmlspecialchars($birTin) ?></div>
            </div>

            <!-- Dynamic Markers -->
            <?php if ($is_reprint): ?>
            <div class="receipt-marker-box receipt-marker-reprint">
                ** REPRINT **
            </div>
            <?php endif; ?>

            <?php if ($is_void): ?>
            <div class="receipt-marker-box receipt-marker-void" style="background: #fff5f5; color: #c0392b; border-color: #c0392b;">
                <div style="font-size: 13px; font-weight: bold;"><?= htmlspecialchars($void_title) ?></div>
                <div class="receipt-void-details" style="margin-top: 4px;">
                    <div>Original Receipt #: <strong><?= htmlspecialchars($order['order_number']) ?></strong></div>
                    <div>Reason: <span><?= htmlspecialchars($void_reason) ?></span></div>
                    <div>Supervisor: <span><?= htmlspecialchars($void_supervisor) ?></span></div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($order['is_test'])): ?>
            <div class="receipt-marker-box receipt-marker-training" style="background: #fff3cd; color: #856404; border-color: #856404;">
                *** TRAINING MODE ***<br>NOT AN OFFICIAL RECEIPT
            </div>
            <?php endif; ?>

            <div class="receipt-divider"></div>

            <!-- Metadata Table -->
            <table class="receipt-meta-table">
                <tr>
                    <td class="receipt-meta-left">Receipt #: <strong><?= htmlspecialchars($order['order_number']) ?></strong></td>
                    <td class="receipt-meta-right"><?= htmlspecialchars($date_str) ?></td>
                </tr>
                <tr>
                    <td class="receipt-meta-left">Cashier: <?= htmlspecialchars($order['cashier_name'] ?? 'Cashier') ?></td>
                    <td class="receipt-meta-right"><?= htmlspecialchars($time_str) ?></td>
                </tr>
                <tr>
                    <td class="receipt-meta-left">Shift ID: #<?= htmlspecialchars((string)($order['pos_session_id'] ?? 1)) ?></td>
                    <td class="receipt-meta-right">Station: <?= htmlspecialchars($order['table_source'] ?? 'POS-01') ?></td>
                </tr>
            </table>

            <!-- Line Items Table (qty × unit price = amount) -->
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
                        $qty = (int)$it['quantity'];
                        $price = (float)$it['subtotal'];
                        $unitPrice = $qty > 0 ? ($price / $qty) : 0;
                        $isItemVoid = $is_void || $qty < 0;
                    ?>
                    <tr>
                        <td class="receipt-col-item <?= $isItemVoid ? 'receipt-item-voided' : '' ?>">
                            <div><?= htmlspecialchars($it['product_name'] ?? 'Item') ?></div>
                            <div class="receipt-item-formula"><?= $qty ?> x ₱<?= number_format($unitPrice, 2) ?> = ₱<?= number_format($price, 2) ?></div>
                            <?php if (!empty($it['modifiers'])): 
                                $modList = array_map(fn($m) => $m['name'], $it['modifiers']);
                            ?>
                                <div class="receipt-item-mod">+ <?= htmlspecialchars(implode(', ', $modList)) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="receipt-col-qty <?= $isItemVoid ? 'receipt-item-voided' : '' ?>"><?= $qty ?></td>
                        <td class="receipt-col-amt <?= $isItemVoid ? 'receipt-item-voided' : '' ?>">₱<?= number_format($price, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="receipt-divider"></div>

            <!-- Summary Breakdown Table -->
            <table class="receipt-summary-table">
                <tr>
                    <td class="receipt-summary-label">Subtotal (Gross)</td>
                    <td class="receipt-summary-val">₱<?= number_format($gross_total, 2) ?></td>
                </tr>
                <?php if ($discount_amount > 0): ?>
                <tr style="color: #c0392b;">
                    <td class="receipt-summary-label">Discount Applied</td>
                    <td class="receipt-summary-val">-₱<?= number_format($discount_amount, 2) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="receipt-summary-label">VATable Sales</td>
                    <td class="receipt-summary-val">₱<?= number_format($vatable, 2) ?></td>
                </tr>
                <tr>
                    <td class="receipt-summary-label">VAT Amount (12%)</td>
                    <td class="receipt-summary-val">₱<?= number_format($vat_amount, 2) ?></td>
                </tr>
                <tr>
                    <td class="receipt-summary-label">VAT-Exempt Sales</td>
                    <td class="receipt-summary-val">₱<?= number_format($vat_exempt, 2) ?></td>
                </tr>
                <tr>
                    <td class="receipt-summary-label">Zero-Rated Sales</td>
                    <td class="receipt-summary-val">₱<?= number_format($zero_rated, 2) ?></td>
                </tr>
                <tr class="receipt-total-row">
                    <td class="receipt-summary-label" style="font-size: 13px;">TOTAL AMOUNT DUE</td>
                    <td class="receipt-summary-val" style="font-size: 13px; font-weight: bold;">₱<?= number_format($net_payable, 2) ?></td>
                </tr>
                <tr>
                    <td class="receipt-summary-label"><?= $order['payment_method'] === 'CASH' ? 'Cash Received' : 'Paid (' . htmlspecialchars($order['payment_method']) . ')' ?></td>
                    <td class="receipt-summary-val">₱<?= number_format($net_payable, 2) ?></td>
                </tr>
                <tr>
                    <td class="receipt-summary-label">Change</td>
                    <td class="receipt-summary-val">₱0.00</td>
                </tr>
            </table>

            <!-- BIR Statutory Notice -->
            <div class="receipt-footer">
                <div class="receipt-divider"></div>
                <div class="receipt-notice">THIS SERVES AS YOUR OFFICIAL RECEIPT</div>
                <div class="receipt-thanks">Thank you for your visit!</div>
            </div>
        </div>
    </div>
</body>
</html>
