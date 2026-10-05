<?php
require_once __DIR__ . '/../../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.");
}

echo "Running HRMS Settings & Admin Permissions Tests...\n\n";
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
$pdo->exec("DELETE FROM role_permissions WHERE role_id = 999 OR role_id = 998;");
$pdo->exec("DELETE FROM roles WHERE id = 999 OR id = 998;");
$pdo->exec("DELETE FROM users WHERE id = 999 OR id = 998;");

$adminRole = $pdo->query("SELECT id FROM roles WHERE name = 'Admin'")->fetchColumn();
if (!$adminRole) {
    // If not seeded, insert a temporary one
    $pdo->exec("INSERT INTO roles (id, name) VALUES (999, 'ADMIN_TEST_XXX')");
    $adminRole = 999;
}
$pdo->exec("INSERT INTO roles (id, name) VALUES (998, 'CASHIER_TEST_XXX')");
$pdo->exec("INSERT INTO users (id, username, password, role_id) VALUES (999, 'test_admin', 'hash', $adminRole), (998, 'test_cashier', 'hash', 998);");

// Helper to reset and mock user
function mockAuthForTest($userId) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    global $pdo;
    $u = $pdo->query("SELECT * FROM users WHERE id = $userId")->fetch(PDO::FETCH_ASSOC);
    $_SESSION['user'] = $u;
    
    // Reset Auth/Rbac caches if they exist
    $ref = new ReflectionClass('Rbac');
    if ($ref->hasProperty('cache')) {
        $prop = $ref->getProperty('cache');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }
}

// 1. Admin passes any permission
mockAuthForTest(999);
$adminCanViewEmp = Rbac::can('hr.employee.view');
$adminCanManageSet = Rbac::can('settings.manage');
$adminCanFake = Rbac::can('fake.permission.does.not.exist'); // Should pass because ADMIN gets everything

assertTest("Admin passes hr.employee.view", $adminCanViewEmp, "Admin rejected.");
assertTest("Admin passes settings.manage", $adminCanManageSet, "Admin rejected.");
assertTest("Admin passes ANY permission check", $adminCanFake, "Admin rejected for unknown permission.");

// 2. Non-Admin fails permission if not granted
mockAuthForTest(998);
$cashierCanManageSet = Rbac::can('settings.manage');
assertTest("Non-admin (Cashier) cannot access settings.manage", !$cashierCanManageSet, "Cashier allowed!");

$pdo->exec("DELETE FROM users WHERE id = 999 OR id = 998;");
$pdo->exec("DELETE FROM roles WHERE id = 999 OR id = 998;");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "\nTests Completed. Failed: {$failed}\n";
if ($failed > 0) {
    exit(1);
}
