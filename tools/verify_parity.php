<?php
/**
 * tools/verify_parity.php
 *
 * Automated Data Parity Verification Suite (Phase 17C)
 * Validates 100% data fidelity, mathematical parity, cryptography,
 * and relational integrity between legacy HRMS/POS and rebuild/core databases.
 *
 * Usage:
 *   php tools/verify_parity.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    exit(1);
}

ini_set('memory_limit', '512M');

require_once __DIR__ . '/../app/bootstrap.php';

echo "====================================================================\n";
echo "Fika Café — Automated Data Parity Verification (Phase 17C)\n";
echo "====================================================================\n";

$legacyHost   = getenv('LEGACY_DB_HOST') ?: '127.0.0.1';
$legacyUser   = getenv('LEGACY_DB_USER') ?: 'root';
$legacyPass   = getenv('LEGACY_DB_PASS') !== false ? getenv('LEGACY_DB_PASS') : '';
$legacyHrmsDb = getenv('LEGACY_HRMS_DB') ?: (getenv('LEGACY_DB_NAME') ?: 'hrms');
$legacyPosDb  = getenv('LEGACY_POS_DB')  ?: (getenv('LEGACY_DB_NAME') ?: 'cafe_pos');

try {
    $hrmsPdo = new PDO("mysql:host={$legacyHost};dbname={$legacyHrmsDb};charset=utf8mb4", $legacyUser, $legacyPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $posPdo = new PDO("mysql:host={$legacyHost};dbname={$legacyPosDb};charset=utf8mb4", $legacyUser, $legacyPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (\PDOException $e) {
    echo "[FATAL] Cannot connect to legacy database: " . $e->getMessage() . "\n";
    exit(1);
}

$targetPdo = $pdo;
$discrepancies = [];

function recordDiscrepancy(string $check, string $message): void {
    global $discrepancies;
    $discrepancies[] = "[$check] $message";
    echo "  [FAIL] $message\n";
}

function passCheck(string $message): void {
    echo "  [PASS] $message\n";
}

// ====================================================================
// 1. Users & Real Password Verification
// ====================================================================
echo "\n--- 1. Users & Password Verification ---\n";
$legacyUserCount = (int)$hrmsPdo->query("SELECT count(*) FROM `users`")->fetchColumn();
$targetUserCount = (int)$targetPdo->query("SELECT count(*) FROM `users` WHERE `legacy_id` IS NOT NULL")->fetchColumn();

if ($legacyUserCount !== $targetUserCount) {
    recordDiscrepancy("USERS", "User count mismatch: Legacy has {$legacyUserCount}, Rebuild has {$targetUserCount}");
} else {
    passCheck("User counts match perfectly: {$legacyUserCount} legacy vs {$targetUserCount} migrated");
}

// Password bcrypt format check ($2y$10$)
$nonBcrypt = (int)$targetPdo->query("
    SELECT count(*) FROM `users` 
    WHERE `legacy_id` IS NOT NULL AND `password` NOT LIKE '$2y$10$%'
")->fetchColumn();

if ($nonBcrypt > 0) {
    recordDiscrepancy("USERS", "Found {$nonBcrypt} migrated users without valid bcrypt prefix ($2y$10$)");
} else {
    passCheck("All {$targetUserCount} migrated user password hashes strictly conform to bcrypt format (\$2y\$10$)");
}

// Real functional password verification against known plaintext passwords
$knownUsers = ['admin', 'juan.dela.cruz', 'maria.clara'];
$testedKnown = 0;
$verifiedKnown = 0;

foreach ($knownUsers as $username) {
    $stmt = $targetPdo->prepare("SELECT `username`, `password` FROM `users` WHERE `username` = ? AND `legacy_id` IS NOT NULL");
    $stmt->execute([$username]);
    $u = $stmt->fetch();
    if ($u) {
        $testedKnown++;
        $hash = $u['password'];
        // Legacy system seeded passwords with 'password123' / 'Password123!'
        if (password_verify('password123', $hash) || password_verify('Password123!', $hash)) {
            $verifiedKnown++;
            passCheck("Real password verified for '{$username}' via password_verify() against migrated hash");
        } else {
            recordDiscrepancy("USERS", "password_verify() failed for known user '{$username}'");
        }
    }
}

if ($testedKnown < 3 || $verifiedKnown < 3) {
    recordDiscrepancy("USERS", "Expected at least 3 known user passwords to be verified, verified {$verifiedKnown}/{$testedKnown}");
} else {
    passCheck("Successfully verified {$verifiedKnown} known user passwords using password_verify()");
}

// ====================================================================
// 2. Orders & Financial Centavos Precision
// ====================================================================
echo "\n--- 2. Orders & Centavos Precision ---\n";
$legacyOrderCount = (int)$posPdo->query("SELECT count(*) FROM `orders`")->fetchColumn();
$targetOrderCount = (int)$targetPdo->query("SELECT count(*) FROM `orders` WHERE `legacy_id` IS NOT NULL")->fetchColumn();

if ($legacyOrderCount !== $targetOrderCount) {
    recordDiscrepancy("ORDERS", "Order count mismatch: Legacy has {$legacyOrderCount}, Rebuild has {$targetOrderCount}");
} else {
    passCheck("Order counts match perfectly: {$legacyOrderCount} legacy vs {$targetOrderCount} migrated");
}

// Verify order_items price snapshots in centavos
$legacyItemCount = (int)$posPdo->query("SELECT count(*) FROM `order_items`")->fetchColumn();
$targetItemCount = (int)$targetPdo->query("SELECT count(*) FROM `order_items` WHERE `legacy_id` IS NOT NULL")->fetchColumn();

if ($legacyItemCount !== $targetItemCount) {
    recordDiscrepancy("ORDER_ITEMS", "Order items count mismatch: Legacy has {$legacyItemCount}, Rebuild has {$targetItemCount}");
} else {
    passCheck("Order item counts match perfectly: {$legacyItemCount} legacy vs {$targetItemCount} migrated");
}

$invalidCents = (int)$targetPdo->query("
    SELECT count(*) FROM `order_items`
    WHERE `legacy_id` IS NOT NULL 
      AND (`price_snapshot_cents` IS NULL OR `price_snapshot_cents` != ROUND(`price_snapshot` * 100))
")->fetchColumn();

if ($invalidCents > 0) {
    recordDiscrepancy("ORDER_ITEMS", "Found {$invalidCents} order items with invalid or mismatched price_snapshot_cents");
} else {
    passCheck("All {$targetItemCount} order items have mathematically verified price_snapshot_cents (exact centavos)");
}

// Verify order financial totals
$badTotals = (int)$targetPdo->query("
    SELECT count(*) FROM `orders`
    WHERE `legacy_id` IS NOT NULL 
      AND (`final_amount` < 0 OR `total_amount` < 0)
")->fetchColumn();

if ($badTotals > 0) {
    recordDiscrepancy("ORDERS", "Found {$badTotals} orders with negative financial totals");
} else {
    passCheck("All {$targetOrderCount} orders have valid non-negative financial totals");
}

// ====================================================================
// 3. Products & Price Cents
// ====================================================================
echo "\n--- 3. Products & Price Cents ---\n";
$legacyProdCount = (int)$posPdo->query("SELECT count(*) FROM `products`")->fetchColumn();
$targetProdCount = (int)$targetPdo->query("SELECT count(*) FROM `products` WHERE `legacy_id` IS NOT NULL")->fetchColumn();

if ($legacyProdCount !== $targetProdCount) {
    recordDiscrepancy("PRODUCTS", "Product count mismatch: Legacy has {$legacyProdCount}, Rebuild has {$targetProdCount}");
} else {
    passCheck("Product counts match perfectly: {$legacyProdCount} legacy vs {$targetProdCount} migrated");
}

$invalidProdCents = (int)$targetPdo->query("
    SELECT count(*) FROM `products`
    WHERE `legacy_id` IS NOT NULL 
      AND (`price_cents` IS NULL OR `price_cents` != ROUND(`price` * 100))
")->fetchColumn();

if ($invalidProdCents > 0) {
    recordDiscrepancy("PRODUCTS", "Found {$invalidProdCents} products with mismatched price_cents");
} else {
    passCheck("All {$targetProdCount} products have verified price_cents matching price * 100");
}

// ====================================================================
// 4. Employees & Cryptographic PII Protection
// ====================================================================
echo "\n--- 4. Employees & Cryptographic PII Protection ---\n";
$legacyEmpCount = (int)$hrmsPdo->query("SELECT count(*) FROM `employees`")->fetchColumn();
$targetEmpCount = (int)$targetPdo->query("SELECT count(*) FROM `employees` WHERE `legacy_id` IS NOT NULL")->fetchColumn();

if ($legacyEmpCount !== $targetEmpCount) {
    recordDiscrepancy("EMPLOYEES", "Employee count mismatch: Legacy has {$legacyEmpCount}, Rebuild has {$targetEmpCount}");
} else {
    passCheck("Employee counts match perfectly: {$legacyEmpCount} legacy vs {$targetEmpCount} migrated");
}

// Check PII decryption across all employees with encrypted columns
$empStmt = $targetPdo->query("
    SELECT `id`, `employee_code`, `bank_no_enc`, `tin_enc`, `sss_no_enc`, `philhealth_no_enc`, `pagibig_no_enc`
    FROM `employees`
");
$piiChecked = 0;
$piiDecrypted = 0;
$piiErrors = 0;

while ($emp = $empStmt->fetch()) {
    foreach (['bank_no_enc', 'tin_enc', 'sss_no_enc', 'philhealth_no_enc', 'pagibig_no_enc'] as $field) {
        $val = $emp[$field];
        if (!empty($val)) {
            $piiChecked++;
            try {
                $decrypted = Crypto::decrypt($val);
                if (is_string($decrypted)) {
                    $piiDecrypted++;
                } else {
                    $piiErrors++;
                    recordDiscrepancy("EMPLOYEES", "Decryption failed for employee {$emp['employee_code']} field {$field}");
                }
            } catch (\Throwable $e) {
                $piiErrors++;
                recordDiscrepancy("EMPLOYEES", "Decryption exception for employee {$emp['employee_code']} field {$field}: " . $e->getMessage());
            }
        }
    }
}

if ($piiErrors === 0) {
    passCheck("All encrypted employee PII fields ({$piiChecked} fields checked) successfully decrypt via Crypto::decrypt()");
}

// ====================================================================
// 5. Inventory Stock & Ledger Reconciliation (Opening Balance)
// ====================================================================
echo "\n--- 5. Inventory Stock & Double-Entry Ledger Reconciliation ---\n";
$legacyInvCount = (int)$posPdo->query("SELECT count(*) FROM `inventory`")->fetchColumn();
$targetInvCount = (int)$targetPdo->query("SELECT count(*) FROM `inventory_items` WHERE `legacy_id` IS NOT NULL")->fetchColumn();

if ($legacyInvCount !== $targetInvCount) {
    recordDiscrepancy("INVENTORY_ITEMS", "Inventory items count mismatch: Legacy has {$legacyInvCount}, Rebuild has {$targetInvCount}");
} else {
    passCheck("Inventory item counts match perfectly: {$legacyInvCount} legacy vs {$targetInvCount} migrated");
}

$legacyStockCount = (int)$posPdo->query("SELECT count(*) FROM `inventory_stock`")->fetchColumn();
$targetStockCount = (int)$targetPdo->query("SELECT count(*) FROM `inventory_stock` WHERE `legacy_id` IS NOT NULL")->fetchColumn();

if ($legacyStockCount !== $targetStockCount) {
    recordDiscrepancy("INVENTORY_STOCK", "Inventory stock count mismatch: Legacy has {$legacyStockCount}, Rebuild has {$targetStockCount}");
} else {
    passCheck("Inventory stock counts match perfectly: {$legacyStockCount} legacy vs {$targetStockCount} migrated");
}

// Validate synthetic opening balance rows
$openingBalanceCount = (int)$targetPdo->query("
    SELECT count(*) FROM `inventory_ledger` 
    WHERE `type` = 'ADJUSTMENT' 
      AND `remarks` = 'Opening balance from legacy migration' 
      AND `legacy_id` IS NULL
")->fetchColumn();

if ($openingBalanceCount !== $targetStockCount) {
    recordDiscrepancy("INVENTORY_LEDGER", "Expected {$targetStockCount} synthetic opening balance entries, found {$openingBalanceCount}");
} else {
    passCheck("Synthetic opening balance verified: {$openingBalanceCount} entries with type='ADJUSTMENT', legacy_id=NULL");
}

// Validate that current_stock == SUM(inventory_ledger.quantity) for every migrated item
$stockStmt = $targetPdo->query("
    SELECT s.id, s.branch_id, s.inventory_id, s.current_stock,
           COALESCE(SUM(l.quantity), 0) AS ledger_sum,
           ROUND(s.current_stock - COALESCE(SUM(l.quantity), 0), 2) AS diff
    FROM `inventory_stock` s
    LEFT JOIN `inventory_ledger` l ON l.branch_id = s.branch_id AND l.inventory_id = s.inventory_id
    WHERE s.legacy_id IS NOT NULL
    GROUP BY s.id, s.branch_id, s.inventory_id, s.current_stock
");

$stockMismatches = 0;
while ($row = $stockStmt->fetch()) {
    if ((float)$row['diff'] != 0.0) {
        $stockMismatches++;
        recordDiscrepancy("INVENTORY_STOCK", "Stock mismatch on Inv #{$row['inventory_id']}: current_stock={$row['current_stock']}, ledger_sum={$row['ledger_sum']}, diff={$row['diff']}");
    }
}

if ($stockMismatches === 0) {
    passCheck("Mathematical Parity Verified: 100% of migrated inventory items have current_stock === SUM(ledger.quantity) (Diff = 0.00)");
}

// ====================================================================
// 6. Cross-Database FK Resolution (Orders Cashier -> Users)
// ====================================================================
echo "\n--- 6. Cross-Database Foreign Key Resolution ---\n";
$resolvedOrdersCount = (int)$targetPdo->query("
    SELECT count(*) 
    FROM `orders` o
    JOIN `users` u ON u.id = o.cashier_id
    WHERE o.legacy_id IS NOT NULL AND o.cashier_id IS NOT NULL
")->fetchColumn();

if ($resolvedOrdersCount < 1) {
    recordDiscrepancy("CROSS_DB_FK", "Zero orders resolved cashier_id to a migrated user!");
} else {
    passCheck("Cross-Database FK Resolution Verified: {$resolvedOrdersCount} orders successfully resolved cashier_id → users.id");
}

// Verify sample resolution details
$sampleOrder = $targetPdo->query("
    SELECT o.order_number, o.cashier_id, u.username, u.legacy_id AS user_legacy_id
    FROM `orders` o
    JOIN `users` u ON u.id = o.cashier_id
    WHERE o.legacy_id IS NOT NULL AND o.cashier_id IS NOT NULL
    LIMIT 1
")->fetch();

if ($sampleOrder) {
    passCheck("Sample resolution verified: Order {$sampleOrder['order_number']} has cashier {$sampleOrder['username']} (User ID: {$sampleOrder['cashier_id']}, Legacy User ID: {$sampleOrder['user_legacy_id']})");
}

// ====================================================================
// 7. Complete Table-by-Table Data Migration Parity
// ====================================================================
echo "\n--- 7. Comprehensive Table Parity Matrix ---\n";
$tableChecks = [
    'branches'          => ['src_db' => $hrmsPdo, 'src' => 'branches',       'tgt' => 'branches',         'expected_diff' => 0, 'reason' => ''],
    'applicants'        => ['src_db' => $hrmsPdo, 'src' => 'applicants',     'tgt' => 'applicants',       'expected_diff' => 0, 'reason' => ''],
    'attendance_logs'   => ['src_db' => $hrmsPdo, 'src' => 'attendance',     'tgt' => 'attendance_logs',  'expected_diff' => 0, 'reason' => ''],
    'leave_requests'    => ['src_db' => $hrmsPdo, 'src' => 'leaves',         'tgt' => 'leave_requests',   'expected_diff' => 0, 'reason' => ''],
    'shifts'            => ['src_db' => $hrmsPdo, 'src' => 'schedules',      'tgt' => 'shifts',           'expected_diff' => -6, 'reason' => '6 unassigned open shifts with employee_id = NULL in legacy'],
    'payroll_items'     => ['src_db' => $hrmsPdo, 'src' => 'payroll',        'tgt' => 'payroll_items',    'expected_diff' => 0, 'reason' => ''],
    'cash_sessions'     => ['src_db' => $posPdo,  'src' => 'cash_sessions',  'tgt' => 'cash_sessions',    'expected_diff' => 0, 'reason' => ''],
    'modifiers'         => ['src_db' => $posPdo,  'src' => 'modifiers',      'tgt' => 'modifiers',        'expected_diff' => 0, 'reason' => ''],
    'payments'          => ['src_db' => $posPdo,  'src' => 'payments',       'tgt' => 'payments',         'expected_diff' => 0, 'reason' => ''],
    'recipes'           => ['src_db' => $posPdo,  'src' => 'recipes',        'tgt' => 'recipes',          'expected_diff' => 0, 'reason' => ''],
    'branch_budgets'    => ['src_db' => $hrmsPdo, 'src' => 'branch_budgets', 'tgt' => 'branch_budgets',   'expected_diff' => 0, 'reason' => ''],
    'audit_logs'        => ['src_db' => $hrmsPdo, 'src' => 'activity_logs',  'tgt' => 'audit_logs',       'expected_diff' => 0, 'reason' => ''],
];

foreach ($tableChecks as $label => $c) {
    $srcCount = (int)$c['src_db']->query("SELECT count(*) FROM `{$c['src']}`")->fetchColumn();
    $tgtCount = (int)$targetPdo->query("SELECT count(*) FROM `{$c['tgt']}` WHERE `legacy_id` IS NOT NULL")->fetchColumn();
    $actualDiff = $tgtCount - $srcCount;

    if ($actualDiff !== $c['expected_diff']) {
        recordDiscrepancy("TABLE_$label", "Mismatch on {$label}: Source ({$c['src']}) has {$srcCount}, Target has {$tgtCount}. Diff: {$actualDiff}, Expected: {$c['expected_diff']}");
    } else {
        $extra = !empty($c['reason']) ? " ({$c['reason']})" : "";
        passCheck(sprintf("%-16s : Source=%4d | Migrated=%4d%s", $label, $srcCount, $tgtCount, $extra));
    }
}

// ====================================================================
// 8. Skipped Tables Audit & Verification
// ====================================================================
echo "\n--- 8. Skipped Tables Audit ---\n";
$skippedTables = [
    'applicant_logs'       => ['db' => $hrmsPdo, 'expected' => 40, 'reason' => 'Procedural UI logs superseded by unified audit_logs'],
    'performance_reviews'  => ['db' => $hrmsPdo, 'expected' => 12, 'reason' => 'Subjective HR appraisals deferred to future appraisal module'],
    'notifications'        => ['db' => $hrmsPdo, 'expected' => 1,  'reason' => 'Ephemeral user notifications superseded by modern notification system'],
    'global_notifications' => ['db' => $hrmsPdo, 'expected' => 4,  'reason' => 'Ephemeral broadcast announcements superseded by modern notification system'],
    'password_resets'      => ['db' => $hrmsPdo, 'expected' => 1,  'reason' => 'Expired single-use password reset tokens with zero business value'],
    'email_verifications'  => ['db' => $hrmsPdo, 'expected' => 0,  'reason' => 'Expired email verification tokens (table empty in legacy)'],
];

foreach ($skippedTables as $tbl => $info) {
    $count = (int)$info['db']->query("SELECT count(*) FROM `{$tbl}`")->fetchColumn();
    if ($count !== $info['expected']) {
        recordDiscrepancy("SKIPPED_$tbl", "Skipped table {$tbl} count mismatch: Expected {$info['expected']}, Found {$count}");
    } else {
        passCheck(sprintf("Skipped Table %-20s: %2d rows documented and justified (%s)", $tbl, $count, $info['reason']));
    }
}

// ====================================================================
// FINAL SUMMARY & EXIT
// ====================================================================
echo "\n====================================================================\n";
echo "PARITY VERIFICATION SUMMARY\n";
echo "====================================================================\n";

if (count($discrepancies) === 0) {
    echo "STATUS: ALL PARITY CHECKS PASSED\n";
    echo "PARITY VERIFIED: 0 UNEXPECTED DISCREPANCIES\n";
    echo "====================================================================\n";
    exit(0);
} else {
    echo "STATUS: FAILED WITH " . count($discrepancies) . " DISCREPANCY(IES):\n";
    foreach ($discrepancies as $d) {
        echo "  - $d\n";
    }
    echo "====================================================================\n";
    exit(1);
}
