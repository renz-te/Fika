<?php
/**
 * Unified BIR-Compliant Thermal Receipt Partial (58mm)
 * Used across: Cashier checkout, Reprint, and Void/Refund paths.
 */
$branchName = 'Slow Hours Cafe';
$branchAddr = 'Bayan, Dasmarinas City, Cavite';
$branchPhone = 'Tel: (555) 123-4567';
$birTin = 'TIN: 123-456-789-00000 VAT';

if (isset($pdo)) {
    try {
        if (function_exists('get_setting')) {
            $branchName = get_setting($pdo, 'company_name', $branchName);
            $branchAddr = get_setting($pdo, 'company_address', $branchAddr);
            $birTin = get_setting($pdo, 'bir_tin', $birTin);
        }
        if (!empty($branch_id)) {
            $stmtBr = $pdo->prepare("SELECT name, address, contact_number FROM branches WHERE id = ?");
            $stmtBr->execute([$branch_id]);
            $brRow = $stmtBr->fetch();
            if ($brRow) {
                if (!empty($brRow['name'])) $branchName = $brRow['name'];
                if (!empty($brRow['address'])) $branchAddr = $brRow['address'];
                if (!empty($brRow['contact_number'])) $branchPhone = 'Tel: ' . $brRow['contact_number'];
            }
        }
    } catch (\Exception $e) {}
}
?>
<!-- Unified Receipt Modal -->
<div class="modal-overlay" id="receipt-modal" style="z-index: 2005;">
    <div class="modal-content" style="background: #ffffff; color: #000000; max-width: 400px; padding: 0; border-radius: 0; box-shadow: 0 10px 30px rgba(0,0,0,0.6);">
        <div class="receipt-container" id="receipt-print-area">
            <!-- Header -->
            <div class="receipt-header">
                <div class="receipt-title" id="receipt-branch-name"><?= htmlspecialchars($branchName) ?></div>
                <div class="receipt-subtitle" id="receipt-branch-address"><?= htmlspecialchars($branchAddr) ?></div>
                <div class="receipt-subtitle" id="receipt-branch-phone"><?= htmlspecialchars($branchPhone) ?></div>
                <div class="receipt-tin" id="receipt-tin"><?= htmlspecialchars($birTin) ?></div>
            </div>

            <!-- Dynamic Markers -->
            <div id="receipt-marker-reprint" class="receipt-marker-box receipt-marker-reprint" style="display: none;">
                ** REPRINT **
            </div>

            <div id="receipt-marker-void" class="receipt-marker-box receipt-marker-void" style="display: none; background: #fff5f5; color: #c0392b; border-color: #c0392b;">
                <div id="receipt-void-title" style="font-size: 13px; font-weight: bold;">** VOID RECEIPT **</div>
                <div class="receipt-void-details" style="margin-top: 4px;">
                    <div>Original Receipt #: <strong id="receipt-void-orig-no">-</strong></div>
                    <div>Reason: <span id="receipt-void-reason">-</span></div>
                    <div>Supervisor: <span id="receipt-void-supervisor">-</span></div>
                </div>
            </div>

            <div id="receipt-marker-training" class="receipt-marker-box receipt-marker-training" style="display: none; background: #fff3cd; color: #856404; border-color: #856404;">
                *** TRAINING MODE ***<br>NOT AN OFFICIAL RECEIPT
            </div>

            <div class="receipt-divider"></div>

            <!-- Metadata Table -->
            <table class="receipt-meta-table">
                <tr>
                    <td class="receipt-meta-left">Receipt #: <strong id="receipt-order-no">-</strong></td>
                    <td class="receipt-meta-right" id="receipt-date">-</td>
                </tr>
                <tr>
                    <td class="receipt-meta-left">Cashier: <span id="receipt-cashier">-</span></td>
                    <td class="receipt-meta-right" id="receipt-time">-</td>
                </tr>
                <tr>
                    <td class="receipt-meta-left">Shift ID: #<span id="receipt-shift-id">-</span></td>
                    <td class="receipt-meta-right">Station: <span id="receipt-station-type">POS-01</span></td>
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
                <tbody id="receipt-items">
                    <!-- Populated dynamically by JS -->
                </tbody>
            </table>

            <div class="receipt-divider"></div>

            <!-- Summary Breakdown Table -->
            <table class="receipt-summary-table">
                <tr>
                    <td class="receipt-summary-label">Subtotal (Gross)</td>
                    <td class="receipt-summary-val" id="receipt-subtotal">₱0.00</td>
                </tr>
                <tr id="receipt-discount-row" style="display: none; color: #c0392b;">
                    <td class="receipt-summary-label">Discount Applied</td>
                    <td class="receipt-summary-val" id="receipt-discount">-₱0.00</td>
                </tr>
                <tr>
                    <td class="receipt-summary-label">VATable Sales</td>
                    <td class="receipt-summary-val" id="receipt-vatable">₱0.00</td>
                </tr>
                <tr>
                    <td class="receipt-summary-label">VAT Amount (12%)</td>
                    <td class="receipt-summary-val" id="receipt-vat">₱0.00</td>
                </tr>
                <tr>
                    <td class="receipt-summary-label">VAT-Exempt Sales</td>
                    <td class="receipt-summary-val" id="receipt-vat-exempt">₱0.00</td>
                </tr>
                <tr>
                    <td class="receipt-summary-label">Zero-Rated Sales</td>
                    <td class="receipt-summary-val" id="receipt-zero-rated">₱0.00</td>
                </tr>
                <tr class="receipt-total-row">
                    <td class="receipt-summary-label" style="font-size: 13px;">TOTAL AMOUNT DUE</td>
                    <td class="receipt-summary-val" id="receipt-total" style="font-size: 13px; font-weight: bold;">₱0.00</td>
                </tr>
                <tr>
                    <td class="receipt-summary-label" id="receipt-tender-label">Cash Received</td>
                    <td class="receipt-summary-val" id="receipt-tender">₱0.00</td>
                </tr>
                <tr id="receipt-change-row">
                    <td class="receipt-summary-label">Change</td>
                    <td class="receipt-summary-val" id="receipt-change">₱0.00</td>
                </tr>
            </table>

            <!-- BIR Statutory Notice -->
            <div class="receipt-footer">
                <div class="receipt-divider"></div>
                <div class="receipt-notice">THIS SERVES AS YOUR OFFICIAL RECEIPT</div>
                <div class="receipt-thanks">Thank you for your visit!</div>
            </div>
        </div>

        <!-- Action Button (Hidden on Print) -->
        <div class="receipt-btn-container no-print" style="padding: 0;">
            <button class="add-to-cart-btn" id="btn-close-receipt" style="width: 100%; border-radius: 0; padding: 16px; background: #2ecc71; font-weight: bold; text-transform: uppercase;">
                <?= htmlspecialchars($receipt_button_text ?? 'Print & New Order') ?>
            </button>
        </div>
    </div>
</div>
