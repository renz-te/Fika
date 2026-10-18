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

// Emulate hitting an endpoint requiring payroll.view
$wrapper = __DIR__ . '/wrapper_list_' . uniqid() . '.php';
$code = "<?php
require_once '" . addslashes(__DIR__ . '/../../app/bootstrap.php') . "';
session_start();
\$_SESSION['user'] = ['id' => 9999, 'role_id' => 9999, 'branch_id' => null, 'employee_id' => null];
Rbac::require_permission('payroll.view');
";
file_put_contents($wrapper, $code);
$output = shell_exec(PHP_BINARY . " " . escapeshellarg($wrapper));
unlink($wrapper);

$is403 = strpos($output, 'Forbidden. Missing permission: payroll.view') !== false;
assertTest("User without payroll.view gets 403", $is403, "Got output: {$output}");

// Clean up
$pdo->exec("DELETE FROM role_permissions WHERE role_id = 9999;");
$pdo->exec("DELETE FROM users WHERE id = 9999;");
$pdo->exec("DELETE FROM roles WHERE id = 9999;");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "\nTests Completed. Failed: {$failed}\n";
if ($failed > 0) {
    exit(1);
}
