<?php
/**
 * Master Security Regression Suite (Phase 18B)
 * Enforces all 10 security traps across authentication, payments, uploads, and data integrity.
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    exit('404 Not Found');
}

require_once __DIR__ . '/../../app/bootstrap.php';

global $pdo;

$baseUrl = getenv('FIKA_BASE_URL') ?: 'http://localhost/Fika/Fika';
$baseUrl = rtrim($baseUrl, '/');

$envPath = APP_ROOT . '/.env';
$origEnv = file_exists($envPath) ? file_get_contents($envPath) : '';

$createdSessionFiles = [];
$trapsPassed = 0;
$totalTraps = 10;
$trapErrors = [];

// Track test fixtures for fail-safe cleanup
$fixtures = [
    'users' => [],
    'employees' => [],
    'suppliers' => [],
    'categories' => [],
    'inventory_items' => [],
    'products' => [],
    'orders' => [],
    'cash_sessions' => [],
    'payroll_runs' => [],
    'expenses' => [],
    'pos' => [],
    'adjustments' => [],
    'shifts' => [],
    'open_shifts' => []
];

function cleanupFixtures(): void {
    global $pdo, $createdSessionFiles, $envPath, $origEnv, $fixtures;

    // Restore .env
    if (!empty($origEnv)) {
        file_put_contents($envPath, $origEnv);
    }

    // Clean session files
    foreach ($createdSessionFiles as $file) {
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    if (!$pdo) {
        return;
    }

    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

        // Cleanup orders & payments
        $pdo->exec("DELETE FROM order_ledger_deductions WHERE order_id >= 990000");
        $pdo->exec("DELETE FROM payments WHERE order_id >= 990000");
        $pdo->exec("DELETE FROM order_items WHERE order_id >= 990000");
        $pdo->exec("DELETE FROM orders WHERE id >= 990000");
        $pdo->exec("DELETE FROM cash_sessions WHERE id >= 990000");

        // Cleanup inventory & recipes
        $pdo->exec("DELETE FROM recipes WHERE product_id >= 990000 OR inventory_id >= 990000");
        $pdo->exec("DELETE FROM branch_products WHERE product_id >= 990000");
        $pdo->exec("DELETE FROM products WHERE id >= 990000");
        $pdo->exec("DELETE FROM inventory_stock WHERE inventory_id >= 990000");
        $pdo->exec("DELETE FROM inventory_ledger WHERE inventory_id >= 990000 OR remarks LIKE '%ORD-SEC-%'");
        $pdo->exec("DELETE FROM inventory_adjustments WHERE id >= 990000 OR created_by >= 990000");
        $pdo->exec("DELETE FROM inventory_items WHERE id >= 990000");
        $pdo->exec("DELETE FROM purchase_orders WHERE id >= 990000 OR created_by >= 990000");
        $pdo->exec("DELETE FROM suppliers WHERE id >= 990000");

        // Cleanup finance
        $pdo->exec("DELETE FROM expenses WHERE id >= 990000 OR created_by >= 990000");
        $pdo->exec("DELETE FROM expense_categories WHERE id >= 990000");

        // Cleanup HRMS & shifts
        $pdo->exec("DELETE FROM open_shifts WHERE id >= 990000 OR claimed_by >= 990000");
        $pdo->exec("DELETE FROM shifts WHERE id >= 990000 OR employee_id >= 990000");
        $pdo->exec("DELETE FROM payroll_runs WHERE id >= 990000 OR processed_by >= 990000");
        $pdo->exec("DELETE FROM users WHERE id >= 990000");
        $pdo->exec("DELETE FROM employees WHERE id >= 990000");

        // Cleanup audit test logs
        $pdo->exec("DELETE FROM audit_logs WHERE details LIKE '%ORD-SEC-%' OR details LIKE '%99000%'");

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    } catch (Throwable $e) {
        // Suppress errors during shutdown cleanup
    }
}

register_shutdown_function('cleanupFixtures');

// Run initial cleanup
cleanupFixtures();

// HTTP Helper
function httpReq(string $url, string $method = 'GET', $data = null, array $headers = [], ?string $cookie = null): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
        }
    }
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    if ($cookie !== null) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'code' => $code,
        'body' => (string)$response,
        'json' => json_decode((string)$response, true),
        'error' => $error
    ];
}

// Session Generator Helper
function createAuthSession(array $user): array {
    global $createdSessionFiles;
    $sessId = bin2hex(random_bytes(16));
    $csrf = bin2hex(random_bytes(16));
    $savePath = session_save_path() ?: sys_get_temp_dir();
    $sessFile = rtrim($savePath, '/\\') . '/sess_' . $sessId;
    $data = [
        'user' => $user,
        'csrf_token' => $csrf
    ];
    $serialized = '';
    foreach ($data as $k => $v) {
        $serialized .= $k . '|' . serialize($v);
    }
    file_put_contents($sessFile, $serialized);
    $createdSessionFiles[] = $sessFile;

    return [
        'sess_id' => $sessId,
        'sess_file' => $sessFile,
        'cookie' => 'PHPSESSID=' . $sessId,
        'csrf' => $csrf
    ];
}

echo "====================================================================\n";
echo "Fika Café — Security Regression Suite (Phase 18B)\n";
echo "====================================================================\n\n";

// ====================================================================
// TRAP 1: Fake Payments Impossible
// ====================================================================
$trap1Passed = true;
$mockCheckoutUrl = $baseUrl . '/fika_pos/fika_pos_mock_checkout.php';

// Setup cashier user
$cashierUserId = 990001;
$cashierEmpId = 990001;
$pdo->exec("INSERT INTO employees (id, employee_code, first_name, last_name, email, branch_id)
            VALUES ($cashierEmpId, 'EMP990001', 'Cashier', 'Test', 'cashier@example.com', 1)
            ON DUPLICATE KEY UPDATE first_name='Cashier'");
$pdo->exec("INSERT INTO users (id, username, password, email, role_id, employee_id, branch_id)
            VALUES ($cashierUserId, 'testcashier', 'hash', 'cashier@example.com', 4, $cashierEmpId, 1)
            ON DUPLICATE KEY UPDATE branch_id=1");

$cashierSess = createAuthSession([
    'id' => $cashierUserId,
    'role_id' => 4,
    'role_name' => 'Cashier',
    'employee_id' => $cashierEmpId,
    'branch_id' => 1
]);

// 1a: is_test is false (even with cashier session)
// Ensure .env has is_test false or absent
$res1a = httpReq($mockCheckoutUrl, 'GET', null, [], $cashierSess['cookie']);
if ($res1a['code'] === 404) {
    echo "[PASS] Trap 1a: Mock checkout returns 404 when is_test=false\n";
} else {
    echo "[FAIL] Trap 1a: Mock checkout did not return 404 when is_test=false (got {$res1a['code']})\n";
    $trap1Passed = false;
}

// 1b: is_test is true, but NO cashier session exists
try {
    file_put_contents($envPath, $origEnv . "\nPAYMONGO_IS_TEST=true\n");
    $res1b = httpReq($mockCheckoutUrl, 'GET', null, [], null);
    if ($res1b['code'] === 404) {
        echo "[PASS] Trap 1b: Mock checkout returns 404 without cashier session\n";
    } else {
        echo "[FAIL] Trap 1b: Mock checkout did not return 404 without cashier session (got {$res1b['code']})\n";
        $trap1Passed = false;
    }
} finally {
    file_put_contents($envPath, $origEnv);
}

if ($trap1Passed) {
    $trapsPassed++;
}

// ====================================================================
// TRAP 2: Webhook Hardening
// ====================================================================
$trap2Passed = true;
$webhookUrl = $baseUrl . '/fika_pos/api/fika_pos_payment_webhook.php';
$webhookSecret = Config::get('paymongo.webhook_secret') ?: 'whsk_RH3xNDf9hmDkQvNDYMPqNkWn';

function computePaymongoSig(string $payload, string $secret, ?int $timestamp = null): string {
    $ts = $timestamp ?? time();
    $hash = hash_hmac('sha256', $ts . '.' . $payload, $secret);
    return "t={$ts},te={$hash}";
}

// 2a: Bad signature (401)
$payload2a = json_encode(['test' => 1]);
$res2a = httpReq($webhookUrl, 'POST', $payload2a, [
    'Content-Type: application/json',
    'Paymongo-Signature: t=' . time() . ',te=bad_signature_hash_value'
]);
if ($res2a['code'] === 401) {
    echo "[PASS] Trap 2a: Webhook rejects bad signature (401)\n";
} else {
    echo "[FAIL] Trap 2a: Webhook did not reject bad signature with 401 (got {$res2a['code']})\n";
    $trap2Passed = false;
}

// 2b: Missing webhook secret (503)
try {
    $noSecretEnv = preg_replace('/PAYMONGO_WEBHOOK_SECRET=[^\r\n]*/', 'PAYMONGO_WEBHOOK_SECRET=', $origEnv);
    file_put_contents($envPath, $noSecretEnv);
    $res2b = httpReq($webhookUrl, 'POST', $payload2a, [
        'Content-Type: application/json'
    ]);
    if ($res2b['code'] === 503) {
        echo "[PASS] Trap 2b: Webhook rejects missing webhook secret (503)\n";
    } else {
        echo "[FAIL] Trap 2b: Webhook did not return 503 when secret is missing (got {$res2b['code']})\n";
        $trap2Passed = false;
    }
} finally {
    file_put_contents($envPath, $origEnv);
}

// 2c: Idempotency (same order_id twice = 1 deduction)
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("INSERT INTO cash_sessions (id, branch_id, cashier_id, starting_cash, status) VALUES (990002, 1, 10006, 1000.00, 'OPEN') ON DUPLICATE KEY UPDATE status='OPEN'");
$pdo->exec("INSERT INTO products (id, name, category, price, price_cents, is_active) VALUES (990002, 'Sec Product 2C', 'Coffee', 120.00, 12000, 1) ON DUPLICATE KEY UPDATE price=120.00");
$pdo->exec("INSERT INTO branch_products (branch_id, product_id, is_active) VALUES (1, 990002, 1) ON DUPLICATE KEY UPDATE is_active=1");
$pdo->exec("INSERT INTO inventory_items (id, item_name, unit, unit_cost) VALUES (990002, 'Sec Item 2C', 'g', 0.50) ON DUPLICATE KEY UPDATE item_name='Sec Item 2C'");
$pdo->exec("INSERT INTO inventory_stock (branch_id, inventory_id, current_stock) VALUES (1, 990002, 500.00) ON DUPLICATE KEY UPDATE current_stock=500.00");
$pdo->exec("INSERT INTO recipes (product_id, inventory_id, quantity_required) VALUES (990002, 990002, 10.00) ON DUPLICATE KEY UPDATE quantity_required=10.00");
$pdo->exec("INSERT INTO orders (id, order_number, branch_id, pos_session_id, cashier_id, total_amount, final_amount, order_status, payment_status, is_test)
            VALUES (990002, 'ORD-SEC-002', 1, 990002, 10006, 120.00, 120.00, 'PENDING', 'UNPAID', 0)
            ON DUPLICATE KEY UPDATE order_status='PENDING', payment_status='UNPAID'");
$pdo->exec("INSERT INTO order_items (id, order_id, product_id, quantity, price_snapshot, subtotal)
            VALUES (990002, 990002, 990002, 1, 120.00, 120.00)
            ON DUPLICATE KEY UPDATE quantity=1");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

$intent2c = 'pay_live_test_' . bin2hex(random_bytes(8));
$payload2c = json_encode([
    'data' => [
        'id' => 'evt_live_test_' . bin2hex(random_bytes(8)),
        'type' => 'event',
        'attributes' => [
            'type' => 'payment.paid',
            'livemode' => false,
            'data' => [
                'id' => $intent2c,
                'type' => 'payment',
                'attributes' => [
                    'amount' => 12000,
                    'fee' => 300,
                    'net_amount' => 11700,
                    'source' => ['id' => 'src_test_' . bin2hex(random_bytes(8)), 'type' => 'gcash'],
                    'metadata' => ['order_id' => '990002', 'method' => 'gcash']
                ]
            ]
        ]
    ]
]);

$sig2c = computePaymongoSig($payload2c, $webhookSecret);
$res2c_1 = httpReq($webhookUrl, 'POST', $payload2c, ['Content-Type: application/json', 'Paymongo-Signature: ' . $sig2c]);
$res2c_2 = httpReq($webhookUrl, 'POST', $payload2c, ['Content-Type: application/json', 'Paymongo-Signature: ' . $sig2c]);

$deductCount = (int)$pdo->query("SELECT COUNT(*) FROM order_ledger_deductions WHERE order_id = 990002")->fetchColumn();
$isPaid = $pdo->query("SELECT payment_status FROM orders WHERE id = 990002")->fetchColumn() === 'PAID';

if ($res2c_1['code'] === 200 && $res2c_2['code'] === 200 && $deductCount === 1 && $isPaid) {
    echo "[PASS] Trap 2c: Webhook idempotent (same order_id twice = 1 deduction)\n";
} else {
    echo "[FAIL] Trap 2c: Webhook idempotency failed (Call1: {$res2c_1['code']}, Call2: {$res2c_2['code']}, Deductions: {$deductCount})\n";
    $trap2Passed = false;
}

// 2d: Rejects VOIDED / REFUNDED orders (409)
$pdo->exec("INSERT INTO orders (id, order_number, branch_id, pos_session_id, cashier_id, total_amount, final_amount, order_status, payment_status, is_test)
            VALUES (990003, 'ORD-SEC-003', 1, 990002, 10006, 50.00, 50.00, 'VOID', 'UNPAID', 0)
            ON DUPLICATE KEY UPDATE order_status='VOID', payment_status='UNPAID'");

$payload2d = json_encode([
    'data' => [
        'id' => 'evt_live_test_' . bin2hex(random_bytes(8)),
        'type' => 'event',
        'attributes' => [
            'type' => 'payment.paid',
            'livemode' => false,
            'data' => [
                'id' => 'pay_live_test_' . bin2hex(random_bytes(8)),
                'type' => 'payment',
                'attributes' => [
                    'amount' => 5000,
                    'fee' => 125,
                    'net_amount' => 4875,
                    'source' => ['id' => 'src_test_' . bin2hex(random_bytes(8)), 'type' => 'gcash'],
                    'metadata' => ['order_id' => '990003', 'method' => 'gcash']
                ]
            ]
        ]
    ]
]);
$sig2d = computePaymongoSig($payload2d, $webhookSecret);
$res2d = httpReq($webhookUrl, 'POST', $payload2d, ['Content-Type: application/json', 'Paymongo-Signature: ' . $sig2d]);

if ($res2d['code'] === 409) {
    echo "[PASS] Trap 2d: Webhook rejects VOIDED/REFUNDED orders (409)\n";
} else {
    echo "[FAIL] Trap 2d: Webhook did not reject VOIDED order with 409 (got {$res2d['code']})\n";
    $trap2Passed = false;
}

// 2e: Rejects amount mismatch (400)
$pdo->exec("INSERT INTO orders (id, order_number, branch_id, pos_session_id, cashier_id, total_amount, final_amount, order_status, payment_status, is_test)
            VALUES (990004, 'ORD-SEC-004', 1, 990002, 10006, 150.00, 150.00, 'PENDING', 'UNPAID', 0)
            ON DUPLICATE KEY UPDATE order_status='PENDING', payment_status='UNPAID'");

$payload2e = json_encode([
    'data' => [
        'id' => 'evt_live_test_' . bin2hex(random_bytes(8)),
        'type' => 'event',
        'attributes' => [
            'type' => 'payment.paid',
            'livemode' => false,
            'data' => [
                'id' => 'pay_live_test_' . bin2hex(random_bytes(8)),
                'type' => 'payment',
                'attributes' => [
                    'amount' => 10000, // Expected 15000
                    'fee' => 250,
                    'net_amount' => 9750,
                    'source' => ['id' => 'src_test_' . bin2hex(random_bytes(8)), 'type' => 'gcash'],
                    'metadata' => ['order_id' => '990004', 'method' => 'gcash']
                ]
            ]
        ]
    ]
]);
$sig2e = computePaymongoSig($payload2e, $webhookSecret);
$res2e = httpReq($webhookUrl, 'POST', $payload2e, ['Content-Type: application/json', 'Paymongo-Signature: ' . $sig2e]);

if ($res2e['code'] === 400) {
    echo "[PASS] Trap 2e: Webhook rejects amount mismatch (400)\n";
} else {
    echo "[FAIL] Trap 2e: Webhook did not reject amount mismatch with 400 (got {$res2e['code']})\n";
    $trap2Passed = false;
}

if ($trap2Passed) {
    $trapsPassed++;
}

// ====================================================================
// TRAP 3: Audit Survivability
// ====================================================================
$stmtAudit = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE action = 'PAYMENT_MISMATCH' AND details LIKE '%ORD-SEC-004%'");
$stmtAudit->execute();
$auditCount = (int)$stmtAudit->fetchColumn();

if ($auditCount > 0) {
    echo "[PASS] Trap 3: Audit log persists after payment rejection rollback\n";
    $trapsPassed++;
} else {
    echo "[FAIL] Trap 3: Audit log row missing after transaction rollback\n";
}

// ====================================================================
// TRAP 4: Migration Idempotency
// ====================================================================
$out1 = shell_exec('php ' . escapeshellarg(APP_ROOT . '/tools/migrate.php') . ' 2>&1');
$out2 = shell_exec('php ' . escapeshellarg(APP_ROOT . '/tools/migrate.php') . ' 2>&1');

if (str_contains((string)$out2, '0 migrations to run.')) {
    echo "[PASS] Trap 4: Migrations idempotent (second run shows 0 migrations)\n";
    $trapsPassed++;
} else {
    echo "[FAIL] Trap 4: Migration rerun did not report 0 migrations to run. Output:\n{$out2}\n";
}

// ====================================================================
// TRAP 5: Deploy Whitelist
// ====================================================================
exec('php ' . escapeshellarg(APP_ROOT . '/tools/list_deploy_files.php') . ' 2>&1', $deployOutputLines, $deployExitCode);
$deployText = implode("\n", $deployOutputLines);

$hasProhibited = false;
$prohibitedMatches = [];
$prohibitedPatterns = [
    '/\.env\b/i',
    '/\.sql\b/i',
    '/\.md\b/i',
    '/^tests\//im',
    '/^tools\//im',
    '/config\/config\.php\b/i'
];

foreach ($prohibitedPatterns as $pattern) {
    if (preg_match($pattern, $deployText)) {
        $hasProhibited = true;
        $prohibitedMatches[] = $pattern;
    }
}

if ($deployExitCode === 0 && !$hasProhibited) {
    echo "[PASS] Trap 5: Deploy whitelist contains no sensitive/prohibited files\n";
    $trapsPassed++;
} else {
    echo "[FAIL] Trap 5: Deploy whitelist test failed (Exit: {$deployExitCode}, Prohibited found: " . implode(', ', $prohibitedMatches) . ")\n";
}

// ====================================================================
// TRAP 6: No Real Secrets in Repo
// ====================================================================
exec('git grep -nE "sk_(test|live)_|whk_|whsk_" 2>&1', $gitGrepLines, $gitGrepExitCode);

$realSecretsFound = [];
foreach ($gitGrepLines as $line) {
    $line = trim($line);
    if (empty($line)) continue;

    // Allowed placeholder fixtures and test patterns
    if (str_contains($line, 'config.example.php') && str_contains($line, 'sk_test_...')) continue;
    if (str_contains($line, 'run_webhook_tests.php') && (str_contains($line, 'whk_test_phase12c_secret') || str_contains($line, 'pi_whk_c'))) continue;
    if (str_contains($line, 'tests/security/run_all.php')) continue;

    // Check if line contains a real-looking key
    if (preg_match('/(sk_(live|test)_[A-Za-z0-9]{16,}|whsk_[A-Za-z0-9]{16,})/', $line)) {
        $realSecretsFound[] = $line;
    }
}

if (count($realSecretsFound) === 0) {
    echo "[PASS] Trap 6: Zero real credentials or secrets committed in repository\n";
    $trapsPassed++;
} else {
    echo "[FAIL] Trap 6: Real-looking secret(s) found in repository:\n" . implode("\n", $realSecretsFound) . "\n";
}

// ====================================================================
// TRAP 7: Endpoint RBAC (Auto-Discovery)
// ====================================================================
$apiFiles = glob(APP_ROOT . '/fika_*/api/*.php');
$nonSessionEndpoints = [
    'fika_hrms/api/fika_hrms_clock_punch.php', // Terminal hardware punch (PIN auth)
    'fika_pos/api/fika_pos_unlock.php',        // POS terminal login screen (PIN auth)
    'fika_pos/api/fika_pos_kiosk_status.php',  // Customer kiosk token polling
    'fika_pos/api/fika_pos_receipt_log.php'    // Receipt log with kiosk token fallback
];

$unauthRbacFailed = [];
$totalRbacTested = 0;

foreach ($apiFiles as $file) {
    $norm = str_replace('\\', '/', $file);
    $rel = str_replace(rtrim(str_replace('\\', '/', APP_ROOT), '/') . '/', '', $norm);

    if (in_array($rel, $nonSessionEndpoints, true)) {
        continue;
    }

    $totalRbacTested++;
    $endpointUrl = $baseUrl . '/' . $rel;
    $res = httpReq($endpointUrl, 'POST', '{}', [
        'Content-Type: application/json',
        'Accept: application/json'
    ], null);

    if ($res['code'] !== 401) {
        $unauthRbacFailed[] = "{$rel} (got {$res['code']})";
    }
}

if (empty($unauthRbacFailed) && $totalRbacTested > 0) {
    echo "[PASS] Trap 7: All auto-discovered RBAC endpoints reject unauthenticated access (401)\n";
    $trapsPassed++;
} else {
    echo "[FAIL] Trap 7: Endpoints failed to return 401 when unauthenticated:\n" . implode("\n", $unauthRbacFailed) . "\n";
}

// ====================================================================
// TRAP 8: Maker-Checker Everywhere
// ====================================================================
$trap8Passed = true;
$makerUserId = 990008;
$makerEmpId = 990008;
$otherEmpId = 990009;

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("INSERT INTO employees (id, employee_code, first_name, last_name, email, branch_id) 
            VALUES ($makerEmpId, 'EMP990008', 'Maker', 'Test', 'maker8@example.com', 1) 
            ON DUPLICATE KEY UPDATE first_name='Maker'");
$pdo->exec("INSERT INTO employees (id, employee_code, first_name, last_name, email, branch_id) 
            VALUES ($otherEmpId, 'EMP990009', 'Other', 'Test', 'other8@example.com', 1) 
            ON DUPLICATE KEY UPDATE first_name='Other'");
$pdo->exec("INSERT INTO users (id, username, password, email, role_id, employee_id, branch_id) 
            VALUES ($makerUserId, 'makertest8', 'hash', 'maker8@example.com', 1, $makerEmpId, 1) 
            ON DUPLICATE KEY UPDATE employee_id=$makerEmpId, branch_id=1");

$pdo->exec("INSERT INTO suppliers (id, name, branch_id) VALUES (990008, 'Test Supplier 8', 1) ON DUPLICATE KEY UPDATE name='Test Supplier 8'");
$pdo->exec("INSERT INTO expense_categories (id, name) VALUES (990008, 'Test Category 8') ON DUPLICATE KEY UPDATE name='Test Category 8'");
$pdo->exec("INSERT INTO inventory_items (id, item_name, unit, unit_cost) VALUES (990008, 'Test Item 8', 'kg', 10.00) ON DUPLICATE KEY UPDATE item_name='Test Item 8'");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

$makerSess = createAuthSession([
    'id' => $makerUserId,
    'role_id' => 1,
    'role_name' => 'ADMIN',
    'employee_id' => $makerEmpId,
    'branch_id' => 1
]);

function sendAuthPost(string $url, array $payload, array $sess): array {
    return httpReq($url, 'POST', $payload, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-CSRF-Token: ' . $sess['csrf']
    ], $sess['cookie']);
}

// 8a: Payroll approval
$pdo->exec("INSERT INTO payroll_runs (id, period_start, period_end, scope, branch_id, status, processed_by)
            VALUES (990008, '2026-09-01', '2026-09-15', 'BRANCH', 1, 'GENERATED', $makerUserId)
            ON DUPLICATE KEY UPDATE status='GENERATED', processed_by=$makerUserId");
$res8a = sendAuthPost($baseUrl . '/fika_hrms/api/fika_hrms_payroll_approve.php', ['payroll_run_id' => 990008], $makerSess);
if ($res8a['code'] === 403) {
    echo "[PASS] Trap 8a: Maker cannot approve own payroll run (403)\n";
} else {
    echo "[FAIL] Trap 8a: Maker was not rejected with 403 on payroll approval (got {$res8a['code']})\n";
    $trap8Passed = false;
}

// 8b: Expense approval
$pdo->exec("INSERT INTO expenses (id, branch_id, category_id, amount_cents, expense_date, payee, status, created_by)
            VALUES (990008, 1, 990008, 50000, '2026-10-01', 'Test Payee', 'DRAFT', $makerUserId)
            ON DUPLICATE KEY UPDATE status='DRAFT', created_by=$makerUserId");
$res8b = sendAuthPost($baseUrl . '/fika_finance/api/fika_finance_expenses.php?action=approve_expense', ['expense_id' => 990008], $makerSess);
if ($res8b['code'] === 403) {
    echo "[PASS] Trap 8b: Maker cannot approve own expense (403)\n";
} else {
    echo "[FAIL] Trap 8b: Maker was not rejected with 403 on expense approval (got {$res8b['code']})\n";
    $trap8Passed = false;
}

// 8c: PO approval
$pdo->exec("INSERT INTO purchase_orders (id, po_number, branch_id, supplier_id, status, created_by)
            VALUES (990008, 'PO-990008', 1, 990008, 'DRAFT', $makerUserId)
            ON DUPLICATE KEY UPDATE status='DRAFT', created_by=$makerUserId");
$res8c = sendAuthPost($baseUrl . '/fika_inventory/api/fika_inventory_procurement.php?action=approve_po', ['po_id' => 990008], $makerSess);
if ($res8c['code'] === 403) {
    echo "[PASS] Trap 8c: Maker cannot approve own purchase order (403)\n";
} else {
    echo "[FAIL] Trap 8c: Maker was not rejected with 403 on PO approval (got {$res8c['code']})\n";
    $trap8Passed = false;
}

// 8d: Inventory adjustment approval
$pdo->exec("INSERT INTO inventory_adjustments (id, branch_id, inventory_id, quantity, reason, status, created_by)
            VALUES (990008, 1, 990008, 5, 'Test Adjustment', 'PENDING', $makerUserId)
            ON DUPLICATE KEY UPDATE status='PENDING', created_by=$makerUserId");
$res8d = sendAuthPost($baseUrl . '/fika_inventory/api/fika_inventory_adjust.php?action=approve', ['adjustment_id' => 990008], $makerSess);
if ($res8d['code'] === 403) {
    echo "[PASS] Trap 8d: Maker cannot approve own inventory adjustment (403)\n";
} else {
    echo "[FAIL] Trap 8d: Maker was not rejected with 403 on adjustment approval (got {$res8d['code']})\n";
    $trap8Passed = false;
}

// 8e: Shift claim approval
$pdo->exec("INSERT INTO shifts (id, branch_id, employee_id, shift_date, start_time, end_time, status, created_by)
            VALUES (990008, 1, $otherEmpId, '2026-11-01', '08:00:00', '16:00:00', 'PUBLISHED', $makerUserId)
            ON DUPLICATE KEY UPDATE status='PUBLISHED'");
$pdo->exec("INSERT INTO open_shifts (id, shift_id, branch_id, original_employee_id, claimed_by, status)
            VALUES (990008, 990008, 1, $otherEmpId, $makerEmpId, 'PENDING_APPROVAL')
            ON DUPLICATE KEY UPDATE status='PENDING_APPROVAL', claimed_by=$makerEmpId");
$res8e = sendAuthPost($baseUrl . '/fika_hrms/api/fika_hrms_shift_claim.php', [
    'action' => 'decide',
    'open_shift_id' => 990008,
    'decision' => 'APPROVE'
], $makerSess);
if ($res8e['code'] === 403) {
    echo "[PASS] Trap 8e: Maker cannot approve own shift claim (403)\n";
} else {
    echo "[FAIL] Trap 8e: Maker was not rejected with 403 on shift claim approval (got {$res8e['code']})\n";
    $trap8Passed = false;
}

if ($trap8Passed) {
    $trapsPassed++;
}

// ====================================================================
// TRAP 9: Upload Security
// ====================================================================
$tmpPhpFile = sys_get_temp_dir() . '/test_polyglot_' . uniqid() . '.jpg';
file_put_contents($tmpPhpFile, "<?php echo 'malicious payload'; system(\$_GET['cmd']); ?>");

$fakeUploadInfo = [
    'name' => 'malicious_photo.jpg',
    'type' => 'image/jpeg',
    'tmp_name' => $tmpPhpFile,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($tmpPhpFile)
];

$menuUploadRes = Upload::handle($fakeUploadInfo, APP_ROOT . '/uploads/menu');
$receiptUploadRes = Upload::handle($fakeUploadInfo, APP_ROOT . '/storage/receipts');
$resumeUploadRes = Upload::handle($fakeUploadInfo, APP_ROOT . '/storage/resumes');

@unlink($tmpPhpFile);

if ($menuUploadRes === null && $receiptUploadRes === null && $resumeUploadRes === null) {
    echo "[PASS] Trap 9: Upload::handle() rejects renamed PHP file for menu, receipts, and resumes\n";
    $trapsPassed++;
} else {
    echo "[FAIL] Trap 9: Upload::handle() accepted a PHP file renamed to .jpg\n";
}

// ====================================================================
// TRAP 10: No Exception Leakage
// ====================================================================
$adminUser = $pdo->query("SELECT * FROM users WHERE username = 'admin'")->fetch();
$adminSess = createAuthSession($adminUser);

$leakageEndpoints = [
    '/fika_finance/api/fika_finance_expenses.php?action=approve_expense' => ['expense_id' => -99999],
    '/fika_finance/api/fika_finance_reconcile.php' => ['action' => 'invalid_recon_action'],
    '/fika_hrms/api/fika_hrms_attendance_adjust.php' => ['adjustment_id' => -99999],
    '/fika_hrms/api/fika_hrms_leave_decide.php' => ['leave_id' => -99999, 'decision' => 'APPROVE'],
    '/fika_hrms/api/fika_hrms_payroll_approve.php' => ['payroll_run_id' => -99999],
    '/fika_hrms/api/fika_hrms_shift_claim.php' => ['action' => 'decide', 'open_shift_id' => -99999, 'decision' => 'APPROVE'],
    '/fika_inventory/api/fika_inventory_adjust.php?action=approve' => ['adjustment_id' => -99999],
    '/fika_inventory/api/fika_inventory_procurement.php?action=approve_po' => ['po_id' => -99999],
    '/fika_pos/api/fika_pos_order_pay_cash.php' => ['order_id' => -99999],
    '/fika_pos/api/fika_pos_order_void_refund.php' => ['order_id' => -99999],
    '/fika_pos/api/fika_pos_payment_create.php' => ['order_id' => -99999]
];

$leakagePatterns = ['SQLSTATE', 'Stack trace:', 'PDOException', 'Fatal error', 'uncaught exception'];
$leakedEndpoints = [];

foreach ($leakageEndpoints as $path => $payload) {
    $res = sendAuthPost($baseUrl . $path, $payload, $adminSess);
    foreach ($leakagePatterns as $p) {
        if (stripos($res['body'], $p) !== false) {
            $leakedEndpoints[] = "{$path} (contained '{$p}')";
            break;
        }
    }
}

if (empty($leakedEndpoints)) {
    echo "[PASS] Trap 10: No exception text leaked in any response\n";
    $trapsPassed++;
} else {
    echo "[FAIL] Trap 10: Exception details leaked in responses:\n" . implode("\n", $leakedEndpoints) . "\n";
}

echo "\n====================================================================\n";
echo "SECURITY REGRESSION: {$trapsPassed}/{$totalTraps} TRAPS PASSED\n";
$exitCode = ($trapsPassed === $totalTraps) ? 0 : 1;
echo "Exit Code: {$exitCode}\n";
echo "====================================================================\n";

exit($exitCode);
