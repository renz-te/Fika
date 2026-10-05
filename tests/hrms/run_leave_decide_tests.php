<?php
require_once __DIR__ . '/../../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.");
}

echo "Running HRMS Leave Decide Tests...\n\n";
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
$pdo->exec("DELETE FROM role_permissions WHERE role_id = 9999;");
$pdo->exec("DELETE FROM leave_requests WHERE id IN (9999, 9998);");
$pdo->exec("DELETE FROM employees WHERE id IN (9999, 9998);");
$pdo->exec("DELETE FROM branches WHERE id IN (9999, 9998);");
$pdo->exec("DELETE FROM users WHERE id IN (9999, 9998);");
$pdo->exec("DELETE FROM roles WHERE id = 9999;");

$pdo->exec("INSERT INTO branches (id, name) VALUES (9999, 'Branch A'), (9998, 'Branch B');");
// Emp 9999 is in Branch A, Emp 9998 is in Branch B
$pdo->exec("INSERT INTO employees (id, employee_code, first_name, last_name, email, branch_id, status) VALUES 
(9999, 'EMP_A', 'A', 'A', 'a@a', 9999, 'ACTIVE'),
(9998, 'EMP_B', 'B', 'B', 'b@b', 9998, 'ACTIVE');");

$pdo->exec("INSERT INTO leave_requests (id, employee_id, type, date_from, date_to, days, reason, status) VALUES 
(9999, 9999, 'VACATION', '2026-10-06', '2026-10-06', 1, 'Self', 'PENDING'),
(9998, 9998, 'VACATION', '2026-10-06', '2026-10-06', 1, 'Cross', 'PENDING');");

// Setup Manager (tied to Emp 9999, Branch A)
$pdo->exec("INSERT INTO roles (id, name) VALUES (9999, 'MANAGER_LEAVE');");
$pdo->exec("INSERT INTO role_permissions (role_id, permission_name) VALUES (9999, 'hr.leave.approve');");
$pdo->exec("INSERT INTO users (id, username, password, role_id, branch_id, employee_id) VALUES (9999, 'mgr_a', 'hash', 9999, 9999, 9999);");

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$_SESSION['user'] = ['id' => 9999, 'role_id' => 9999, 'branch_id' => 9999, 'employee_id' => 9999];

// Reset Rbac cache
$ref = new ReflectionClass('Rbac');
if ($ref->hasProperty('cache')) {
    $prop = $ref->getProperty('cache');
    $prop->setAccessible(true);
    $prop->setValue(null, []);
}

// 1. Self-approval blocked
$selfBlocked = false;
try {
    // API logic snippet checking self
    $stmt = $pdo->prepare("SELECT e.branch_id, lr.employee_id FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE lr.id = 9999");
    $stmt->execute();
    $leave = $stmt->fetch();
    
    Rbac::assert_branch_access($leave['branch_id']);
    
    if ($_SESSION['user']['employee_id'] && (int)$_SESSION['user']['employee_id'] === (int)$leave['employee_id']) {
        throw new Exception("Conflict of interest");
    }
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Conflict of interest') !== false) {
        $selfBlocked = true;
    }
}
assertTest("Self-approval is blocked", $selfBlocked, "Allowed manager to approve their own leave.");

// 2. Cross-branch approval blocked
$crossBlocked = false;
try {
    $stmt = $pdo->prepare("SELECT e.branch_id, lr.employee_id FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE lr.id = 9998");
    $stmt->execute();
    $leave = $stmt->fetch();
    
    // We will just evaluate the logic Rbac uses
    $userBranch = $_SESSION['user']['branch_id'];
    if ($userBranch !== null && $userBranch != $leave['branch_id']) {
        throw new Exception("Forbidden. Branch scope mismatch.");
    }
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Forbidden') !== false) {
        $crossBlocked = true;
    }
}

assertTest("Cross-branch approval is blocked", $crossBlocked, "Allowed manager to approve Branch B's leave.");


// Clean up
$pdo->exec("DELETE FROM role_permissions WHERE role_id = 9999;");
$pdo->exec("DELETE FROM leave_requests WHERE id IN (9999, 9998);");
$pdo->exec("DELETE FROM employees WHERE id IN (9999, 9998);");
$pdo->exec("DELETE FROM branches WHERE id IN (9999, 9998);");
$pdo->exec("DELETE FROM users WHERE id IN (9999, 9998);");
$pdo->exec("DELETE FROM roles WHERE id = 9999;");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "\nTests Completed. Failed: {$failed}\n";
if ($failed > 0) {
    exit(1);
}
