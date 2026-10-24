<?php
/**
 * CLI Tool: Seed Today's Demo Data for Presentation
 *
 * Populates attendance log for Juan Dela Cruz and 3 completed POS orders
 * for Branch 1 so the HRMS Operations Dashboard displays live metrics.
 */

require_once __DIR__ . '/../app/bootstrap.php';

global $pdo;

echo "=======================================================\n";
echo "Fika Café — Seeding Today's Demo Data\n";
echo "=======================================================\n\n";

// 1. Dynamic Employee Lookup (Juan Dela Cruz)
$empStmt = $pdo->query("SELECT id, branch_id, first_name, last_name, employee_code FROM employees WHERE legacy_id = 1 OR employee_code = 'EMP-001' LIMIT 1");
$employee = $empStmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    die("[ERROR] Employee Juan Dela Cruz (legacy_id = 1 or EMP-001) not found in database.\n");
}

$employeeId = (int)$employee['id'];
$branchId = (int)($employee['branch_id'] ?: 1);
echo "[INFO] Found employee: {$employee['first_name']} {$employee['last_name']} (ID: {$employeeId}, Code: {$employee['employee_code']})\n";

// Check if attendance log for today already exists for Juan
$attCheckStmt = $pdo->prepare("SELECT id FROM attendance_logs WHERE employee_id = ? AND work_date = CURDATE() AND clock_out IS NULL LIMIT 1");
$attCheckStmt->execute([$employeeId]);
$existingAttId = $attCheckStmt->fetchColumn();

if ($existingAttId) {
    echo "[INFO] Attendance log for employee #{$employeeId} already exists for today (Log ID: {$existingAttId}). Skipping insert.\n";
} else {
    $attInsert = $pdo->prepare("
        INSERT INTO attendance_logs (employee_id, branch_id, work_date, clock_in, clock_out, source, status, created_at, updated_at)
        VALUES (?, ?, CURDATE(), NOW() - INTERVAL 2 HOUR, NULL, 'MANUAL', 'OPEN', NOW(), NOW())
    ");
    $attInsert->execute([$employeeId, $branchId]);
    $newAttId = $pdo->lastInsertId();
    echo "[SUCCESS] Inserted attendance log #{$newAttId} for {$employee['first_name']} {$employee['last_name']} (Clocked in 2 hours ago).\n";
}

// 2. Lookup Cashier User & Products
$cashierStmt = $pdo->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
$cashierId = (int)($cashierStmt ? $cashierStmt->fetchColumn() : null) ?: null;

// Query real products dynamically
$latteStmt = $pdo->query("SELECT id, name, price FROM products WHERE name = 'Hot Latte' LIMIT 1");
$latte = $latteStmt->fetch(PDO::FETCH_ASSOC);

$espressoStmt = $pdo->query("SELECT id, name, price FROM products WHERE name = 'Espresso' LIMIT 1");
$espresso = $espressoStmt->fetch(PDO::FETCH_ASSOC);

if (!$latte || !$espresso) {
    // Fallback query any active products
    $allProds = $pdo->query("SELECT id, name, price FROM products ORDER BY id ASC LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
    $latte = $allProds[0] ?? null;
    $espresso = $allProds[1] ?? $allProds[0] ?? null;
}

if (!$latte || !$espresso) {
    die("[ERROR] Could not find products in database.\n");
}

echo "[INFO] Using products: {$latte['name']} (#{$latte['id']}, ₱{$latte['price']}), {$espresso['name']} (#{$espresso['id']}, ₱{$espresso['price']})\n";

// 3. Seed 3 Demo Orders for Branch 1 (Idempotent)
$demoOrders = [
    [
        'order_number' => 'DEMO-TODAY-1',
        'total' => 150.00,
        'items' => [
            [
                'product_id' => (int)$latte['id'],
                'quantity' => 1,
                'price' => 150.00,
                'price_cents' => 15000,
                'subtotal' => 150.00
            ]
        ]
    ],
    [
        'order_number' => 'DEMO-TODAY-2',
        'total' => 200.00,
        'items' => [
            [
                'product_id' => (int)$espresso['id'],
                'quantity' => 2,
                'price' => 100.00,
                'price_cents' => 10000,
                'subtotal' => 200.00
            ]
        ]
    ],
    [
        'order_number' => 'DEMO-TODAY-3',
        'total' => 120.00,
        'items' => [
            [
                'product_id' => (int)$latte['id'],
                'quantity' => 1,
                'price' => 120.00,
                'price_cents' => 12000,
                'subtotal' => 120.00
            ]
        ]
    ]
];

$orderCheckStmt = $pdo->prepare("SELECT id FROM orders WHERE order_number = ? AND DATE(created_at) = CURDATE() LIMIT 1");
$orderInsertStmt = $pdo->prepare("
    INSERT INTO orders (
        order_number, branch_id, cashier_id, total_amount, discount_amount, 
        vat_amount, final_amount, order_status, payment_status, payment_method, 
        is_test, created_at, updated_at
    ) VALUES (
        ?, 1, ?, ?, 0.00, 
        0.00, ?, 'COMPLETED', 'PAID', 'CASH', 
        0, NOW(), NOW()
    )
");

$itemInsertStmt = $pdo->prepare("
    INSERT INTO order_items (
        order_id, product_id, quantity, price_snapshot, price_snapshot_cents, subtotal, created_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?, NOW()
    )
");

foreach ($demoOrders as $orderDef) {
    $orderNumber = $orderDef['order_number'];
    $orderCheckStmt->execute([$orderNumber]);
    $existingOrderId = $orderCheckStmt->fetchColumn();

    if ($existingOrderId) {
        echo "[INFO] Order {$orderNumber} already exists for today (Order ID: {$existingOrderId}). Skipping.\n";
        continue;
    }

    $orderInsertStmt->execute([
        $orderNumber,
        $cashierId,
        $orderDef['total'],
        $orderDef['total']
    ]);
    $orderId = (int)$pdo->lastInsertId();

    foreach ($orderDef['items'] as $item) {
        $itemInsertStmt->execute([
            $orderId,
            $item['product_id'],
            $item['quantity'],
            $item['price'],
            $item['price_cents'],
            $item['subtotal']
        ]);
    }

    echo "[SUCCESS] Inserted order {$orderNumber} (ID: {$orderId}, Total: ₱{$orderDef['total']}) with " . count($orderDef['items']) . " item(s).\n";
}

echo "\n=======================================================\n";
echo "Verification Queries Output:\n";
echo "=======================================================\n";

// Print attendance logs for Juan today
echo "\n--- ATTENDANCE LOG (Juan Dela Cruz) ---\n";
$attPrint = $pdo->prepare("
    SELECT id, employee_id, branch_id, work_date, clock_in, clock_out, status 
    FROM attendance_logs 
    WHERE employee_id = ? AND work_date = CURDATE()
");
$attPrint->execute([$employeeId]);
while ($row = $attPrint->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}

// Print seeded orders for today
echo "\n--- SEEDED ORDERS TODAY ---\n";
$ordPrint = $pdo->query("
    SELECT id, order_number, branch_id, total_amount, final_amount, order_status, payment_status, created_at 
    FROM orders 
    WHERE order_number LIKE 'DEMO-TODAY-%' AND DATE(created_at) = CURDATE()
");
while ($row = $ordPrint->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}

// Print dashboard KPI summary
echo "\n--- DASHBOARD KPI SUMMARY ---\n";
$kpiClock = (int)$pdo->query("SELECT COUNT(DISTINCT employee_id) FROM attendance_logs WHERE work_date = CURDATE() AND clock_in IS NOT NULL AND clock_out IS NULL")->fetchColumn();
$kpiSales = (float)$pdo->query("SELECT COALESCE(SUM(final_amount), 0) FROM orders WHERE DATE(created_at) = CURDATE() AND payment_status = 'PAID'")->fetchColumn();

echo "Clocked In Today: {$kpiClock}\n";
echo "Today's Sales: " . Money::format(Money::toCentavos($kpiSales)) . " (raw: {$kpiSales})\n";
echo "\nDemo seed complete.\n";
