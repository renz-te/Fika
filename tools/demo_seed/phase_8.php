<?php
/**
 * tools/demo_seed/phase_8.php
 *
 * Phase D8: Sales (POS Orders) and Restocks
 *
 * Requirements:
 * 1. Target Active Branches: Branch 1 (General Trias), Branch 2 (Trece Martires), Branch 3 (Imus).
 * 2. Period: Seeded attendance timeframe (2026-09-28 to 2026-10-28).
 * 3. Order Volume: 40-80 orders per branch per week. Mostly cash (~70%), some GCash (~20%), Maya (~10%).
 * 4. Realistic Diurnal Distribution: Orders concentrated in morning and afternoon peak shifts.
 * 5. Order Details: Products and Modifiers from D3 with strict mathematical price calculation.
 * 6. Cashiers: Assigned exclusively to real Crew employees actively clocked in on that day and time.
 * 7. Stock Deduction: Real live class InventoryDeduction::deductOrder() invoked per order.
 * 8. Restocks: Periodic weekly replenishment in inventory_ledger (type = 'Restock') preventing negative stock.
 * 9. Idempotency: Second apply writes 0 rows.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

global $pdo, $DEMO_APPLY;
require_once APP_ROOT . '/app/core/InventoryDeduction.php';

if (!function_exists('demo_track_record')) {
    function demo_track_record(PDO $pdo, string $table, int $rowId, string $phase = 'phase_8'): void {
        $trackStmt = $pdo->prepare("
            INSERT INTO `demo_seed` (`phase`, `table_name`, `row_id`, `created_at`) 
            VALUES (?, ?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE `phase` = VALUES(`phase`)
        ");
        $trackStmt->execute([$phase, $table, $rowId]);
    }
}

echo "====================================================================\n";
echo "Phase D8: Sales (POS Orders) and Restocks\n";
echo "====================================================================\n";

// Target active operational branches
$branches = [
    1 => 'General Trias Branch',
    2 => 'Trece Martires Branch',
    3 => 'Imus Branch'
];

// Branch Managers for restock logging
$branchManagers = [
    1 => 10007, // Juan Dela Cruz (BM Branch 1)
    2 => 10015, // Andres Bonifacio (BM Branch 2)
    3 => 10023  // Jose Laurel (BM Branch 3)
];

// --------------------------------------------------------------------
// IDEMPOTENCY CHECK
// --------------------------------------------------------------------
$existingOrdersCount = (int)$pdo->query("SELECT COUNT(*) FROM demo_seed WHERE phase = 'phase_8' AND table_name = 'orders'")->fetchColumn();

if ($existingOrdersCount > 0) {
    echo "  [SKIPPED] Phase D8 demo orders and restocks already present in database ({$existingOrdersCount} orders tracked).\n";
    demo_stats_record('orders', 'skipped', $existingOrdersCount);
    $existingItems = (int)$pdo->query("SELECT COUNT(*) FROM demo_seed WHERE phase = 'phase_8' AND table_name = 'order_items'")->fetchColumn();
    demo_stats_record('order_items', 'skipped', $existingItems);
    $existingMods = (int)$pdo->query("SELECT COUNT(*) FROM demo_seed WHERE phase = 'phase_8' AND table_name = 'order_item_modifiers'")->fetchColumn();
    demo_stats_record('order_item_modifiers', 'skipped', $existingMods);
    $existingLedger = (int)$pdo->query("SELECT COUNT(*) FROM demo_seed WHERE phase = 'phase_8' AND table_name = 'inventory_ledger'")->fetchColumn();
    demo_stats_record('inventory_ledger', 'skipped', $existingLedger);
    $existingPayments = (int)$pdo->query("SELECT COUNT(*) FROM demo_seed WHERE phase = 'phase_8' AND table_name = 'payments'")->fetchColumn();
    demo_stats_record('payments', 'skipped', $existingPayments);
    $existingDeductions = (int)$pdo->query("SELECT COUNT(*) FROM demo_seed WHERE phase = 'phase_8' AND table_name = 'order_ledger_deductions'")->fetchColumn();
    demo_stats_record('order_ledger_deductions', 'skipped', $existingDeductions);

    // Proceed directly to verifications
    require __DIR__ . '/phase_8_verifications.php';
    return;
}

// --------------------------------------------------------------------
// SYNCHRONIZATION SAFEGUARD
// Ensure inventory_stock.current_stock is in exact sync with inventory_ledger
// --------------------------------------------------------------------
if ($DEMO_APPLY) {
    $pdo->exec("
        UPDATE inventory_stock s
        SET current_stock = (
            SELECT COALESCE(SUM(quantity), 0)
            FROM inventory_ledger l
            WHERE l.branch_id = s.branch_id AND l.inventory_id = s.inventory_id
        )
    ");
}

// --------------------------------------------------------------------
// TASK 1: Periodic Restock Replenishments
// --------------------------------------------------------------------
echo "[1/3] Planning & Seeding Periodic Inventory Restocks...\n";

// Restock dates: 1 in Sept, 4 in Oct (Mondays at 07:00 AM before weekly opening)
$restockDates = [
    '2026-09-28 07:00:00', // Delivery 1: Opening week replenishment
    '2026-10-05 07:00:00', // Delivery 2: Week 2 replenishment
    '2026-10-12 07:00:00', // Delivery 3: Week 3 replenishment
    '2026-10-19 07:00:00', // Delivery 4: Week 4 replenishment
    '2026-10-26 07:00:00', // Delivery 5: Week 5 replenishment
];

// Fetch active inventory catalog items
$invCatalogStmt = $pdo->query("SELECT id, item_name, unit, unit_cost FROM inventory_items WHERE deleted_at IS NULL ORDER BY id ASC");
$invCatalog = $invCatalogStmt->fetchAll(PDO::FETCH_ASSOC);

// Restock replenishment schedule per item per delivery event
$restockQuantities = [
    'Espresso beans'     => 2000.00,  // g
    'Fresh milk'         => 12000.00, // ml
    'Ice'                => 18000.00, // g
    'Matcha powder'      => 300.00,   // g
    'Condensed milk'     => 1000.00,  // g
    'Caramel syrup'      => 1000.00,  // ml
    'Chocolate syrup'    => 1000.00,  // ml
    'Strawberry puree'   => 1000.00,  // ml
    'Simple syrup'       => 1000.00,  // ml
    'Frappe base powder' => 800.00,   // g
    'Whipped cream'      => 800.00,   // ml
    'Black tea leaves'   => 500.00,   // g
    'Tapioca pearls'     => 1500.00,  // g
    'Non-dairy creamer'  => 1000.00,  // g
    'Water'              => 15000.00, // ml
    'Cup 16oz'           => 150.00,   // pcs
    'Cup 22oz'           => 150.00,   // pcs
    'Cup 12oz hot'       => 100.00,   // pcs
    'Cup 16oz hot'       => 100.00,   // pcs
    'Dome lid'           => 300.00,   // pcs
    'Hot lid'            => 200.00,   // pcs
    'Straw'              => 300.00,   // pcs
    'Croissant'          => 20.00,    // pcs
    'Blueberry Pie'      => 18.00,    // pcs
    'Lemon Bread'        => 18.00,    // pcs
    'Chocolate Cake'     => 18.00,    // pcs
    'Strawberry Cake'    => 18.00,    // pcs
];

$totalRestockTransactions = 0;

$restockLedgerStmt = $pdo->prepare("
    INSERT INTO inventory_ledger (branch_id, inventory_id, type, quantity, unit_cost_snapshot, user_id, remarks, created_at)
    VALUES (?, ?, 'Restock', ?, ?, ?, ?, ?)
");

$restockStockStmt = $pdo->prepare("
    UPDATE inventory_stock
    SET current_stock = current_stock + ?
    WHERE branch_id = ? AND inventory_id = ?
");

foreach (array_keys($branches) as $bId) {
    $bmUserId = $branchManagers[$bId];

    foreach ($restockDates as $rDate) {
        foreach ($invCatalog as $inv) {
            $invId = (int)$inv['id'];
            $name = $inv['item_name'];
            $cost = (float)$inv['unit_cost'];
            $qty = $restockQuantities[$name] ?? 100.00;

            if ($DEMO_APPLY) {
                $restockLedgerStmt->execute([
                    $bId,
                    $invId,
                    $qty,
                    $cost,
                    $bmUserId,
                    "Weekly inventory replenishment delivery",
                    $rDate
                ]);
                $rId = (int)$pdo->lastInsertId();
                demo_track_record($pdo, 'inventory_ledger', $rId, 'phase_8');
                demo_stats_record('inventory_ledger', 'created', 1);

                $restockStockStmt->execute([$qty, $bId, $invId]);
            } else {
                demo_stats_record('inventory_ledger', 'would_create', 1);
            }
            $totalRestockTransactions++;
        }
    }
}

if ($DEMO_APPLY) {
    echo "  [CREATED]  Seeded {$totalRestockTransactions} restock transactions across 3 branches.\n";
} else {
    echo "  [DRY-RUN] Would create {$totalRestockTransactions} restock transactions across 3 branches.\n";
}

// --------------------------------------------------------------------
// TASK 2: Order Generation with Strict Cashier Attendance Matching
// --------------------------------------------------------------------
echo "[2/3] Generating POS Sales Orders via Live InventoryDeduction Engine...\n";

// Fetch active attendance logs with user ID
$attStmt = $pdo->query("
    SELECT al.id, al.branch_id, al.employee_id, al.work_date, al.clock_in, al.clock_out, al.status, u.id as user_id, u.username
    FROM attendance_logs al
    JOIN users u ON u.employee_id = al.employee_id
    WHERE al.status != 'ABSENT' AND al.clock_in IS NOT NULL
    ORDER BY al.branch_id ASC, al.work_date ASC, al.clock_in ASC
");
$allAttendance = $attStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch catalog products mapped to recipes
$prodStmt = $pdo->query("
    SELECT p.id, p.name, p.category, p.price, p.price_cents
    FROM products p
    JOIN recipes r ON r.product_id = p.id
    WHERE p.is_active = 1 AND p.deleted_at IS NULL
    GROUP BY p.id
    ORDER BY p.id ASC
");
$products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

// Modifiers lookup & dynamic resolution by name
$modLookupStmt = $pdo->query("SELECT id, name, price FROM modifiers WHERE is_active = 1 AND deleted_at IS NULL");
$allMods = $modLookupStmt->fetchAll(PDO::FETCH_ASSOC);
$modLookup = [];
$modByName = [];
foreach ($allMods as $m) {
    $modLookup[(int)$m['id']] = $m;
    $modByName[strtolower(trim($m['name']))] = (int)$m['id'];
}

$coldSizes = [
    $modByName['size: m (cold)'] ?? 0,
    $modByName['size: l (cold)'] ?? 0
];
$hotSizes  = [
    $modByName['size: m (hot)'] ?? 0,
    $modByName['size: l (hot)'] ?? 0
];
$addOnMods = [
    $modByName['extra espresso shot'] ?? 0,
    $modByName['pearls'] ?? 0,
    $modByName['whipped cream'] ?? 0,
    $modByName['caramel drizzle'] ?? 0
];

// Prepare order creation statements
$orderInsertStmt = $pdo->prepare("
    INSERT INTO orders (
        order_number, branch_id, pos_session_id, cashier_id,
        order_status, payment_status, total_amount, discount_amount, vat_amount,
        final_amount, payment_method, payment_reference, is_test, created_at, updated_at
    ) VALUES (
        ?, ?, NULL, ?,
        'COMPLETED', 'PAID', ?, 0.00, 0.00,
        ?, ?, ?, 0, ?, ?
    )
");

$itemInsertStmt = $pdo->prepare("
    INSERT INTO order_items (
        order_id, product_id, quantity, price_snapshot, price_snapshot_cents, subtotal, created_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?
    )
");

$modInsertStmt = $pdo->prepare("
    INSERT INTO order_item_modifiers (
        order_item_id, modifier_id, price_snapshot, created_at
    ) VALUES (
        ?, ?, ?, ?
    )
");

$paymentInsertStmt = $pdo->prepare("
    INSERT INTO payments (
        order_id, method, payment_method, gross_amount, fee_amount, net_amount,
        amount_paid, change_amount, reference_number,
        status, paid_at, created_at
    ) VALUES (
        ?, ?, ?, ?, 0.00, ?,
        ?, 0.00, ?,
        'paid', ?, ?
    )
");

$updateLedgerTimestampStmt = $pdo->prepare("UPDATE inventory_ledger SET created_at = ? WHERE order_id = ?");
$updateDeductionTimestampStmt = $pdo->prepare("UPDATE order_ledger_deductions SET deducted_at = ? WHERE order_id = ?");

$orderGlobalSeq = 1;
$branchOrderCounts = [1 => 0, 2 => 0, 3 => 0];
$totalOrdersCreated = 0;
$totalItemsCreated = 0;
$totalModsCreated = 0;
$sampleOrders = [];

// Iterate through attendance logs and schedule orders during active cashier shifts
if ($DEMO_APPLY) {
    $pdo->beginTransaction();
}

try {
    foreach ($allAttendance as $attIndex => $att) {
        $bId = (int)$att['branch_id'];
        $date = $att['work_date'];
        $cashierUserId = (int)$att['user_id'];

        $cIn = strtotime($att['clock_in']);
        $cOut = $att['clock_out'] ? strtotime($att['clock_out']) : strtotime($date . ' 09:30:00');

        // On today (2026-10-28), clamp strictly before 09:30 AM
        if ($date === '2026-10-28') {
            $cOut = min($cOut, strtotime('2026-10-28 09:25:00'));
        }

        $duration = $cOut - $cIn;
        if ($duration < 1800) continue; // Skip shifts under 30 minutes

        // Orders per shift: full shift (6+ hrs) = 3 orders, medium shift = 2 orders, short shift = 1 order
        $numOrders = ($duration >= 21600) ? 3 : (($duration >= 10800) ? 2 : 1);
        $step = (int)($duration / ($numOrders + 1));

        for ($k = 1; $k <= $numOrders; $k++) {
            $orderTs = $cIn + ($step * $k) + (($cashierUserId * 17 + $k * 37 + $attIndex * 13) % 400) - 200;
            $orderTs = max($cIn + 300, min($cOut - 300, $orderTs));
            $orderDatetime = date('Y-m-d H:i:s', $orderTs);

            $branchOrderCounts[$bId]++;
            $bSeq = $branchOrderCounts[$bId];
            $orderNumber = sprintf("ORD-B%d-%s-%04d", $bId, str_replace('-', '', $date), $bSeq);

            // Payment method: 70% CASH, 20% GCASH, 10% MAYA
            $pmSeed = ($orderGlobalSeq * 17 + $bSeq * 11) % 100;
            if ($pmSeed < 70) {
                $paymentMethod = 'CASH';
                $paymentRef = sprintf("CASH-%08d", $orderGlobalSeq);
            } elseif ($pmSeed < 90) {
                $paymentMethod = 'GCASH';
                $paymentRef = sprintf("GCASH-%s-%04d", str_replace('-', '', $date), $orderGlobalSeq);
            } else {
                $paymentMethod = 'MAYA';
                $paymentRef = sprintf("MAYA-%s-%04d", str_replace('-', '', $date), $orderGlobalSeq);
            }

            // Order type
            $orderType = (($orderGlobalSeq % 3) === 0) ? 'Takeout' : 'Dine-in';

            // Items in order: 1 to 3 items
            $itemCount = (($orderGlobalSeq % 5) === 0) ? 3 : ((($orderGlobalSeq % 2) === 0) ? 2 : 1);
            $orderItems = [];
            $orderTotalCentavos = 0;

            for ($itemIdx = 0; $itemIdx < $itemCount; $itemIdx++) {
                $prod = $products[($orderGlobalSeq * 7 + $itemIdx * 11 + $bId * 3) % count($products)];
                $prodId = (int)$prod['id'];
                $basePriceCentavos = isset($prod['price_cents']) && (int)$prod['price_cents'] > 0
                    ? (int)$prod['price_cents']
                    : (int)round((float)$prod['price'] * 100);

                $isPastry = (stripos($prod['category'], 'Pastr') !== false);
                $isHot    = (stripos($prod['category'], 'Hot') !== false);

                $selectedModIds = [];
                $modsTotalCentavos = 0;

                if (!$isPastry) {
                    // Size modifier
                    if ($isHot) {
                        $sizeModId = $hotSizes[($orderGlobalSeq + $itemIdx) % count($hotSizes)];
                    } else {
                        $sizeModId = $coldSizes[($orderGlobalSeq + $itemIdx) % count($coldSizes)];
                    }
                    $selectedModIds[] = $sizeModId;
                    $modsTotalCentavos += (int)round((float)$modLookup[$sizeModId]['price'] * 100);

                    // Add-on modifier (33% probability)
                    if (($orderGlobalSeq + $itemIdx) % 3 === 0) {
                        $addModId = $addOnMods[($orderGlobalSeq + $itemIdx * 2) % count($addOnMods)];
                        $selectedModIds[] = $addModId;
                        $modsTotalCentavos += (int)round((float)$modLookup[$addModId]['price'] * 100);
                    }
                }

                $qty = 1;
                $lineSubtotalCentavos = ($basePriceCentavos + $modsTotalCentavos) * $qty;
                $orderTotalCentavos += $lineSubtotalCentavos;

                $orderItems[] = [
                    'product_id'          => $prodId,
                    'product_name'        => $prod['name'],
                    'category'            => $prod['category'],
                    'quantity'            => $qty,
                    'base_price'          => round($basePriceCentavos / 100, 2),
                    'base_price_cents'    => $basePriceCentavos,
                    'subtotal'            => round($lineSubtotalCentavos / 100, 2),
                    'modifiers'           => $selectedModIds
                ];
            }

            $finalAmount = round($orderTotalCentavos / 100, 2);

            if ($DEMO_APPLY) {
                // 1. Insert Order
                $orderInsertStmt->execute([
                    $orderNumber,
                    $bId,
                    $cashierUserId,
                    $finalAmount,
                    $finalAmount,
                    $paymentMethod,
                    $paymentRef,
                    $orderDatetime,
                    $orderDatetime
                ]);
                $orderId = (int)$pdo->lastInsertId();
                demo_track_record($pdo, 'orders', $orderId, 'phase_8');
                demo_stats_record('orders', 'created', 1);

                // 2. Insert Order Items & Modifiers
                foreach ($orderItems as $oi) {
                    $itemInsertStmt->execute([
                        $orderId,
                        $oi['product_id'],
                        $oi['quantity'],
                        $oi['base_price'],
                        $oi['base_price_cents'],
                        $oi['subtotal'],
                        $orderDatetime
                    ]);
                    $orderItemId = (int)$pdo->lastInsertId();
                    demo_track_record($pdo, 'order_items', $orderItemId, 'phase_8');
                    demo_stats_record('order_items', 'created', 1);
                    $totalItemsCreated++;

                    foreach ($oi['modifiers'] as $mId) {
                        $mPrice = round((float)$modLookup[$mId]['price'], 2);
                        $modInsertStmt->execute([
                            $orderItemId,
                            $mId,
                            $mPrice,
                            $orderDatetime
                        ]);
                        $orderModId = (int)$pdo->lastInsertId();
                        demo_track_record($pdo, 'order_item_modifiers', $orderModId, 'phase_8');
                        demo_stats_record('order_item_modifiers', 'created', 1);
                        $totalModsCreated++;
                    }
                }

                // 3. Insert Payment
                $paymentInsertStmt->execute([
                    $orderId,
                    $paymentMethod,
                    $paymentMethod,
                    $finalAmount,
                    $finalAmount,
                    $finalAmount,
                    $paymentRef,
                    $orderDatetime,
                    $orderDatetime
                ]);
                $paymentId = (int)$pdo->lastInsertId();
                demo_track_record($pdo, 'payments', $paymentId, 'phase_8');
                demo_stats_record('payments', 'created', 1);

                // 4. Call Live POS Inventory Deduction Engine
                InventoryDeduction::deductOrder($orderId, $bId, $cashierUserId, $orderNumber);

                // 5. Update Inventory Ledger & Deduction Timestamps to Match Order
                $updateLedgerTimestampStmt->execute([$orderDatetime, $orderId]);
                $updateDeductionTimestampStmt->execute([$orderDatetime, $orderId]);

                // Track inventory ledger deductions in demo_seed
                $deductId = (int)$pdo->query("SELECT id FROM order_ledger_deductions WHERE order_id = {$orderId}")->fetchColumn();
                if ($deductId > 0) {
                    demo_track_record($pdo, 'order_ledger_deductions', $deductId, 'phase_8');
                    demo_stats_record('order_ledger_deductions', 'created', 1);
                }

                $ledgerIds = $pdo->query("SELECT id FROM inventory_ledger WHERE order_id = {$orderId}")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($ledgerIds as $lId) {
                    demo_track_record($pdo, 'inventory_ledger', (int)$lId, 'phase_8');
                    demo_stats_record('inventory_ledger', 'created', 1);
                }

                // Save sample orders for report
                if (count($sampleOrders) < 5) {
                    $sampleOrders[] = [
                        'order_id'       => $orderId,
                        'order_number'   => $orderNumber,
                        'branch_id'      => $bId,
                        'branch_name'    => $branches[$bId],
                        'cashier_id'     => $cashierUserId,
                        'cashier_name'   => $att['username'],
                        'datetime'       => $orderDatetime,
                        'payment_method' => $paymentMethod,
                        'payment_ref'    => $paymentRef,
                        'total_amount'   => $finalAmount,
                        'items'          => $orderItems
                    ];
                }
            } else {
                demo_stats_record('orders', 'would_create', 1);
                demo_stats_record('order_items', 'would_create', count($orderItems));
                demo_stats_record('payments', 'would_create', 1);
                demo_stats_record('order_ledger_deductions', 'would_create', 1);
                demo_stats_record('inventory_ledger', 'would_create', 4); // ~4 ingredient deductions per order
            }

            $totalOrdersCreated++;
            $orderGlobalSeq++;
        }
    }

    if ($DEMO_APPLY) {
        $pdo->commit();
        echo "  [CREATED]  Seeded {$totalOrdersCreated} orders ({$totalItemsCreated} items, {$totalModsCreated} modifiers) across 3 branches.\n";
    } else {
        echo "  [DRY-RUN] Would create {$totalOrdersCreated} orders across 3 branches.\n";
    }
} catch (\Throwable $e) {
    if ($DEMO_APPLY && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "ERROR: Order seeding failed: " . $e->getMessage() . "\n");
    throw $e;
}

// --------------------------------------------------------------------
// RUN VERIFICATION & REPORTING SUITE
// --------------------------------------------------------------------
require __DIR__ . '/phase_8_verifications.php';

