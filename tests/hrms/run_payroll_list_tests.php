<?php
require_once __DIR__ . '/../../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.");
}

echo "Running Payroll List Access Tests...\n\n";
$failed = 0;

function assertTest($name, $condition, $failMsg) {
    global $failed;
    if ($condition) {
        echo "[PASS] {$name}\n";
    } else {
        echo "[FAIL] {$name}: {$failMsg}\n";
        $failed++;
    }
}

global $pdo;

// Prepare data
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM users WHERE id = 9999;");
$pdo->exec("DELETE FROM roles WHERE id = 9999;");

// Setup User without payroll.view
$pdo->exec("INSERT INTO roles (id, name) VALUES (9999, 'NO_PAYROLL');");
$pdo->exec("INSERT INTO role_permissions (role_id, permission_name) VALUES (9999, 'pos.access');");
$pdo->exec("INSERT INTO users (id, username, password, role_id, branch_id) VALUES (9999, 'no_payroll_u', 'hash', 9999, NULL);");

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$_SESSION['user'] = ['id' => 9999, 'role_id' => 9999, 'branch_id' => null, 'employee_id' => null];

// Reset Rbac cache
$ref = new ReflectionClass('Rbac');
if ($ref->hasProperty('cache')) {
    $prop = $ref->getProperty('cache');
    $prop->setAccessible(true);
    $prop->setValue(null, []);
}

// Emulate hitting fika_hrms_payroll.php
ob_start();
$is403 = false;
try {
    // Rbac::require_permission uses die() and http_response_code(403)
    Rbac::require_permission('payroll.view');
} catch (Exception $e) {
    // Not thrown
} catch (Error $e) {
    // Not thrown
}
$output = ob_get_clean();
$status = http_response_code();

assertTest("User without payroll.view gets 403", $status === 403, "Got HTTP status {$status}");

// Clean up
$pdo->exec("DELETE FROM role_permissions WHERE role_id = 9999;");
$pdo->exec("DELETE FROM users WHERE id = 9999;");
$pdo->exec("DELETE FROM roles WHERE id = 9999;");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "\nTests Completed. Failed: {$failed}\n";
if ($failed > 0) {
    exit(1);
}
