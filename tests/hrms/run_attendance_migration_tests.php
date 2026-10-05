<?php
require_once __DIR__ . '/../../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.");
}

echo "Running HRMS Attendance Migration Tests...\n\n";
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
$pdo->exec("DELETE FROM attendance_logs;");
$pdo->exec("DELETE FROM employees WHERE id = 1000;");
$pdo->exec("DELETE FROM branches WHERE id = 1000;");

$pdo->exec("INSERT INTO branches (id, name) VALUES (1000, 'Test Branch');");
$pdo->exec("INSERT INTO employees (id, employee_code, first_name, last_name, email, branch_id, status, basic_salary) VALUES (1000, 'TEST_ATT', 'Test', 'Att', 'test@test.com', 1000, 'ACTIVE', 100);");

// 1. Insert an OPEN log
$stmt = $pdo->prepare("INSERT INTO attendance_logs (employee_id, branch_id, clock_in, work_date, status) VALUES (1000, 1000, NOW(), CURDATE(), 'OPEN')");
$stmt->execute();
$log1 = $pdo->lastInsertId();

assertTest("First OPEN log succeeds", $log1 > 0, "Could not insert first open log");

// 2. Insert another OPEN log for the same employee -> SHOULD FAIL
$secondOpenFailed = false;
try {
    $stmt2 = $pdo->prepare("INSERT INTO attendance_logs (employee_id, branch_id, clock_in, work_date, status) VALUES (1000, 1000, NOW(), CURDATE(), 'OPEN')");
    $stmt2->execute();
} catch (PDOException $e) {
    // 23000 Integrity constraint violation
    if ($e->getCode() == '23000') {
        $secondOpenFailed = true;
    }
}
assertTest("DB blocks second OPEN log for same employee", $secondOpenFailed, "Database allowed two OPEN logs simultaneously!");

// 3. Insert a CLOSED log for the same employee -> SHOULD SUCCEED
$closedSuccess = true;
try {
    $stmt3 = $pdo->prepare("INSERT INTO attendance_logs (employee_id, branch_id, clock_in, clock_out, work_date, status) VALUES (1000, 1000, NOW(), NOW(), CURDATE(), 'CLOSED')");
    $stmt3->execute();
} catch (Exception $e) {
    $closedSuccess = false;
}
assertTest("DB allows a CLOSED log alongside an OPEN log", $closedSuccess, "Database rejected a CLOSED log");

// 4. Update the OPEN log to CLOSED -> SHOULD SUCCEED
$updateSuccess = true;
try {
    $stmt4 = $pdo->prepare("UPDATE attendance_logs SET status = 'CLOSED' WHERE id = ?");
    $stmt4->execute([$log1]);
} catch (Exception $e) {
    $updateSuccess = false;
}
assertTest("Can update OPEN log to CLOSED", $updateSuccess, "Update failed!");

// 5. Insert a new OPEN log -> SHOULD SUCCEED now that the first is CLOSED
$newOpenSuccess = true;
try {
    $stmt5 = $pdo->prepare("INSERT INTO attendance_logs (employee_id, branch_id, clock_in, work_date, status) VALUES (1000, 1000, NOW(), CURDATE(), 'OPEN')");
    $stmt5->execute();
} catch (Exception $e) {
    $newOpenSuccess = false;
}
assertTest("Can insert new OPEN log after closing the previous one", $newOpenSuccess, "Database rejected new open log!");

// Clean up
$pdo->exec("DELETE FROM attendance_logs;");
$pdo->exec("DELETE FROM employees WHERE id = 1000;");
$pdo->exec("DELETE FROM branches WHERE id = 1000;");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "\nTests Completed. Failed: {$failed}\n";
if ($failed > 0) {
    exit(1);
}
