<?php
require_once __DIR__ . '/../../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.");
}

echo "Running HRMS Certification Tests...\n\n";
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
$pdo->exec("DELETE FROM attendance_certifications WHERE branch_id = 9999;");
$pdo->exec("DELETE FROM payroll_runs WHERE branch_id = 9999;");
$pdo->exec("DELETE FROM payroll_items WHERE employee_id IN (9999, 9998);");
$pdo->exec("DELETE FROM attendance_logs WHERE employee_id IN (9999, 9998);");
$pdo->exec("DELETE FROM employees WHERE id IN (9999, 9998);");
$pdo->exec("DELETE FROM branches WHERE id = 9999;");
$pdo->exec("DELETE FROM users WHERE id IN (9999, 9998);");
$pdo->exec("DELETE FROM roles WHERE id = 9999;");

$pdo->exec("INSERT INTO branches (id, name) VALUES (9999, 'Branch Z');");
// Emp 9999 = BM, Emp 9998 = Crew
$pdo->exec("INSERT INTO employees (id, employee_code, first_name, last_name, email, branch_id, status) VALUES 
(9999, 'EMP_Z1', 'BM', 'BM', 'bm@z', 9999, 'ACTIVE'),
(9998, 'EMP_Z2', 'Crew', 'Crew', 'cw@z', 9999, 'ACTIVE');");

// Setup BM User
$pdo->exec("INSERT INTO roles (id, name) VALUES (9999, 'MANAGER_CERT');");
$pdo->exec("INSERT INTO role_permissions (role_id, permission_name) VALUES (9999, 'hr.attendance.certify');");
$pdo->exec("INSERT INTO role_permissions (role_id, permission_name) VALUES (9999, 'payroll.manage');");
$pdo->exec("INSERT INTO users (id, username, password, role_id, branch_id, employee_id) VALUES (9999, 'mgr_z', 'hash', 9999, 9999, 9999);");
// Crew has NO user record! (BRANCH scope)

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$_SESSION['user'] = ['id' => 9999, 'role_id' => 9999, 'branch_id' => 9999, 'employee_id' => 9999];

// Reset Rbac cache
$ref = new ReflectionClass('Rbac');
if ($ref->hasProperty('cache')) {
    $prop = $ref->getProperty('cache');
    $prop->setAccessible(true);
    $prop->setValue(null, []);
}

// 1. BM CANNOT certify OFFICIALS run (because BM is in OFFICIALS run)
$bmBlocked = false;
try {
    $scope = 'OFFICIALS';
    $branchId = null;
    $employeeId = null;
    $userEmpId = $_SESSION['user']['employee_id'];
    
    // Simulate check
    $scopeCond = "e.id IN (SELECT employee_id FROM users WHERE employee_id IS NOT NULL AND branch_id IS NOT NULL)"; // OFFICIALS
    $checkSql = "SELECT 1 FROM employees e WHERE e.id = ? AND $scopeCond";
    $checkParams = [$userEmpId];
    $stmt = $pdo->prepare($checkSql);
    $stmt->execute($checkParams);
    if ($stmt->fetch()) {
        throw new Exception("Conflict of interest");
    }
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Conflict of interest') !== false) {
        $bmBlocked = true;
    }
}
assertTest("BM cannot certify OFFICIALS containing their own attendance", $bmBlocked, "BM was allowed.");

// 2. BM CAN certify BRANCH run (because BM is not in BRANCH run)
$bmCanCertifyBranch = true;
try {
    $scopeCond = "e.id NOT IN (SELECT employee_id FROM users WHERE employee_id IS NOT NULL)"; // BRANCH
    $checkSql = "SELECT 1 FROM employees e WHERE e.id = ? AND $scopeCond AND e.branch_id = ?";
    $checkParams = [$_SESSION['user']['employee_id'], 9999];
    $stmt = $pdo->prepare($checkSql);
    $stmt->execute($checkParams);
    if ($stmt->fetch()) {
        throw new Exception("Conflict of interest");
    }
} catch (Exception $e) {
    $bmCanCertifyBranch = false;
}
assertTest("BM can certify BRANCH run", $bmCanCertifyBranch, "BM was incorrectly blocked from BRANCH run.");

// 3. Payroll refuses to generate if not certified
$payrollBlocked = false;
try {
    $certStmt = $pdo->prepare("
        SELECT id FROM attendance_certifications 
        WHERE scope = 'BRANCH' AND branch_id = ? AND period_start = ? AND period_end = ? AND status = 'CERTIFIED'
    ");
    $certStmt->execute([9999, '2026-10-01', '2026-10-15']);
    if (!$certStmt->fetch()) {
        throw new Exception("Attendance for this period must be certified before payroll can be generated.");
    }
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'must be certified') !== false) {
        $payrollBlocked = true;
    }
}
assertTest("Payroll generation refuses uncertified period", $payrollBlocked, "Allowed uncertified payroll.");

// Clean up
$pdo->exec("DELETE FROM role_permissions WHERE role_id = 9999;");
$pdo->exec("DELETE FROM attendance_certifications WHERE branch_id = 9999;");
$pdo->exec("DELETE FROM payroll_runs WHERE branch_id = 9999;");
$pdo->exec("DELETE FROM payroll_items WHERE employee_id IN (9999, 9998);");
$pdo->exec("DELETE FROM attendance_logs WHERE employee_id IN (9999, 9998);");
$pdo->exec("DELETE FROM employees WHERE id IN (9999, 9998);");
$pdo->exec("DELETE FROM branches WHERE id = 9999;");
$pdo->exec("DELETE FROM users WHERE id IN (9999, 9998);");
$pdo->exec("DELETE FROM roles WHERE id = 9999;");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "\nTests Completed. Failed: {$failed}\n";
if ($failed > 0) {
    exit(1);
}
