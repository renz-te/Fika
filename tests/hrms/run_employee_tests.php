<?php
require_once __DIR__ . '/../../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.");
}

echo "Running HRMS Employee Tests...\n\n";
$failed = 0;

global $pdo;

// Prepare Test Data
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM role_permissions WHERE role_id = 999 OR role_id = 998;");
$pdo->exec("DELETE FROM roles WHERE id = 999 OR id = 998;");
$pdo->exec("DELETE FROM users WHERE id = 999 OR id = 998;");
$pdo->exec("DELETE FROM branches WHERE id = 999 OR id = 998;");

// Seed test branches
$pdo->exec("INSERT INTO branches (id, name) VALUES (998, 'Test Branch A'), (999, 'Test Branch B')");

// Seed test roles
$pdo->exec("INSERT INTO roles (id, name) VALUES (998, 'TEST_BM_X'), (999, 'TEST_HR_X')");
$pdo->exec("INSERT INTO role_permissions (role_id, permission_name) VALUES (998, 'hr.employee.view'), (999, 'hr.employee.view')");

// Seed test users
$pdo->exec("INSERT INTO users (id, username, password, role_id, branch_id) VALUES (998, 'test_bm_a', 'hash', 998, 998), (999, 'test_hr', 'hash', 999, NULL)");

function assertScope($name, $userId, $expectedCondition) {
    global $failed;
    
    // Mock Auth
    $user = (new PDO('mysql:host=127.0.0.1;dbname=fika_unified;charset=utf8mb4', 'root', ''))->query("SELECT * FROM users WHERE id = $userId")->fetch();
    Auth::mockLogin($user);
    
    list($sql, $params) = Rbac::branch_scope('e');
    
    if ($sql === $expectedCondition) {
        echo "[PASS] {$name}\n";
    } else {
        echo "[FAIL] {$name}\n";
        echo "  Expected condition: {$expectedCondition}\n";
        echo "  Got condition: {$sql}\n";
        $failed++;
    }
}

// Ensure session is started for tests
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['user'] = ['id' => 998, 'branch_id' => 998, 'role_id' => 998];
list($sql, $params) = Rbac::branch_scope('e');
if ($sql === " AND e.branch_id = ?" && $params[0] == 998) {
    echo "[PASS] Branch Manager A sees only Branch A\n";
} else {
    echo "[FAIL] Branch Manager A scope mismatch: $sql\n";
    $failed++;
}

$_SESSION['user'] = ['id' => 999, 'branch_id' => null, 'role_id' => 999];

list($sql, $params) = Rbac::branch_scope('e');
if ($sql === "" && empty($params)) {
    echo "[PASS] Global HR sees all branches\n";
} else {
    echo "[FAIL] Global HR scope mismatch: $sql\n";
    $failed++;
}

// Cleanup
$pdo->exec("DELETE FROM users WHERE id = 999 OR id = 998;");
$pdo->exec("DELETE FROM roles WHERE id = 999 OR id = 998;");
$pdo->exec("DELETE FROM role_permissions WHERE role_id = 999 OR role_id = 998;");
$pdo->exec("DELETE FROM branches WHERE id = 999 OR id = 998;");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "\nTests Completed. Failed: {$failed}\n";
if ($failed > 0) {
    exit(1);
}
