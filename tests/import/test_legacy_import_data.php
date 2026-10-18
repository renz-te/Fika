<?php
require_once __DIR__ . '/../../app/bootstrap.php';

echo "=== Verifying Legacy Import Data Integrity & Transformations ===\n\n";

$passCount = 0;
$failCount = 0;

function assertCondition(bool $cond, string $message) {
    global $passCount, $failCount;
    if ($cond) {
        echo "  [PASS] {$message}\n";
        $passCount++;
    } else {
        echo "  [FAIL] {$message}\n";
        $failCount++;
    }
}

// 1. Verify Encrypted PII
$emp = $pdo->query("SELECT * FROM `employees` WHERE legacy_id = 201")->fetch(PDO::FETCH_ASSOC);
assertCondition($emp !== false, "Employee legacy_id 201 found in target database");
$tin = Crypto::decrypt($emp['tin_enc'] ?? '');
$sss = Crypto::decrypt($emp['sss_no_enc'] ?? '');
$philhealth = Crypto::decrypt($emp['philhealth_no_enc'] ?? '');
$pagibig = Crypto::decrypt($emp['pagibig_no_enc'] ?? '');
$bank = Crypto::decrypt($emp['bank_no_enc'] ?? '');
assertCondition($tin === '123-456-789-000', "TIN decrypted correctly: {$tin}");
assertCondition($sss === '04-1234567-8', "SSS decrypted correctly: {$sss}");
assertCondition($philhealth === '12-345678901-2', "PhilHealth decrypted correctly: {$philhealth}");
assertCondition($pagibig === '1234-5678-9012', "Pag-IBIG decrypted correctly: {$pagibig}");
assertCondition($bank === 'BDO-123456789', "Bank Account decrypted correctly: {$bank}");

// 2. Verify Orphan Employee
$orphanEmp = $pdo->query("SELECT * FROM `employees` WHERE legacy_id = 299")->fetch(PDO::FETCH_ASSOC);
assertCondition($orphanEmp !== false, "Orphan employee legacy_id 299 found in target database");
assertCondition($orphanEmp['branch_id'] === null, "Orphan employee branch_id gracefully set to NULL");

// 3. Verify Bcrypt Passwords
$user = $pdo->query("SELECT * FROM `users` WHERE legacy_id = 301")->fetch(PDO::FETCH_ASSOC);
assertCondition($user !== false, "User legacy_id 301 found in target database");
assertCondition(password_verify('Password123!', $user['password']), "Legacy bcrypt password verifies cleanly with password_verify()");

// 4. Verify Applicant Interview Notes
$app = $pdo->query("SELECT * FROM `applicants` WHERE legacy_id = 401")->fetch(PDO::FETCH_ASSOC);
assertCondition($app !== false, "Applicant legacy_id 401 found in target database");
assertCondition(str_contains($app['notes'], 'Legacy candidate applied online.'), "Original applicant notes preserved");
assertCondition(str_contains($app['notes'], 'Tech: 4/5'), "Interview technical rating 4/5 preserved in notes");
assertCondition(str_contains($app['notes'], 'Comm: 5/5'), "Interview communication rating 5/5 preserved in notes");
assertCondition(str_contains($app['notes'], 'Culture: 4/5'), "Interview culture fit rating 4/5 preserved in notes");
assertCondition(str_contains($app['notes'], 'Excellent latte art calibration'), "Interview scorecard remarks preserved in notes");

// 5. Verify Money in Centavos
$prod = $pdo->query("SELECT * FROM `products` WHERE legacy_id = 1401")->fetch(PDO::FETCH_ASSOC);
assertCondition($prod !== false, "Product legacy_id 1401 found in target database");
assertCondition((int)$prod['price_cents'] === 15000, "Product price_cents is 15000 (150.00 * 100)");

$budget = $pdo->query("SELECT * FROM `branch_budgets` WHERE legacy_id = 1901")->fetch(PDO::FETCH_ASSOC);
assertCondition($budget !== false, "Branch budget legacy_id 1901 found in target database");
assertCondition((int)$budget['allocated_amount_cents'] === 12000000, "Branch budget allocated_amount_cents is 12,000,000 (120,000.00 * 100)");

// 6. Verify Orphan Order
$orphanOrder = $pdo->query("SELECT * FROM `orders` WHERE legacy_id = 1699")->fetch(PDO::FETCH_ASSOC);
assertCondition($orphanOrder !== false, "Orphan order legacy_id 1699 found in target database");
assertCondition($orphanOrder['cashier_id'] === null, "Orphan order cashier_id gracefully set to NULL");

echo "\nSummary: {$passCount} PASSED, {$failCount} FAILED\n";

if ($failCount > 0) {
    exit(1);
}
