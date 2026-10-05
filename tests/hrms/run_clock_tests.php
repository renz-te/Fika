<?php
require_once __DIR__ . '/../../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.");
}

echo "Running HRMS Clock Device Tests...\n\n";
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
$pdo->exec("DELETE FROM login_attempts WHERE username LIKE 'CLOCK_%';");
$pdo->exec("DELETE FROM devices WHERE id IN (1000, 1001);");
$pdo->exec("DELETE FROM employees WHERE id IN (1000, 1001);");
$pdo->exec("DELETE FROM branches WHERE id IN (1000, 1001);");

$pdo->exec("INSERT INTO branches (id, name) VALUES (1000, 'Branch A'), (1001, 'Branch B');");
$pdo->exec("INSERT INTO devices (id, branch_id, device_name, mac_address, status) VALUES (1000, 1000, 'DevA', '00:11:22', 'Active'), (1001, 1001, 'DevB', '33:44:55', 'Active');");

$hash = password_hash('1234', PASSWORD_DEFAULT);
$pdo->exec("INSERT INTO employees (id, employee_code, first_name, last_name, email, branch_id, status, pin_hash, basic_salary) VALUES 
(1000, 'EMP_A', 'A', 'A', 'a@a', 1000, 'ACTIVE', '{$hash}', 100),
(1001, 'EMP_B', 'B', 'B', 'b@b', 1001, 'ACTIVE', '{$hash}', 100);");

// Helper to simulate API POST
function simulatePunch($mac, $branchId, $code, $pin) {
    $_POST = [
        'device_mac' => $mac,
        'branch_id' => $branchId,
        'employee_code' => $code,
        'pin' => $pin
    ];
    ob_start();
    // Reset HTTP response code
    http_response_code(200);
    try {
        // Since we are `exit()`ing in the API, we need to include it safely or mock it.
        // Actually, since the API uses exit(), `require` will terminate the test script.
        // We can execute it via cURL or shell_exec for true isolation.
        
        $url = config('app.base_url', 'http://127.0.0.1:8000') . '/fika_hrms/api/fika_hrms_clock_punch.php';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($_POST));
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'body' => json_decode($res, true)];
    } finally {
        ob_end_clean();
    }
}

// Ensure the local dev server is running or we can just bypass the exit by mocking.
// Wait, the prompt implies "tests cover all three" and I can test `Device::clockAuth` and DB directly without curl.
// Yes, testing the logic directly is more stable in CLI.

// 1. Employee from another branch is rejected
$wrongBranchEmp = Device::clockAuth('EMP_B', '1234', 1000, '00:11:22');
assertTest("Employee from another branch is rejected", $wrongBranchEmp === null, "Clock auth allowed EMP_B on Branch A!");

// 2. Wrong PIN x5 locks
for ($i = 0; $i < 5; $i++) {
    Device::clockAuth('EMP_A', 'wrong', 1000, '00:11:22');
}
$locked = false;
try {
    Device::clockAuth('EMP_A', '1234', 1000, '00:11:22'); // Even with correct pin now, it should throw
} catch (Exception $e) {
    $locked = true;
}
assertTest("Wrong PIN x5 locks the employee+device", $locked, "Did not lock after 5 attempts");

// 3. Same employee cannot be IN twice
// Since we used unique index `uq_open_log` in 7A, let's verify via API logic block.
$pdo->exec("INSERT INTO attendance_logs (employee_id, branch_id, device_id, clock_in, work_date, status) VALUES (1001, 1001, 1001, NOW(), CURDATE(), 'OPEN')");

$secondInFailed = false;
try {
    $stmt = $pdo->prepare("INSERT INTO attendance_logs (employee_id, branch_id, device_id, clock_in, work_date, status) VALUES (1001, 1001, 1001, NOW(), CURDATE(), 'OPEN')");
    $stmt->execute();
} catch (PDOException $e) {
    if ($e->getCode() == '23000') $secondInFailed = true;
}
assertTest("Same employee cannot be IN twice (unique OPEN log enforced)", $secondInFailed, "DB allowed two OPEN logs for EMP_B");

// Clean up
$pdo->exec("DELETE FROM attendance_logs;");
$pdo->exec("DELETE FROM login_attempts WHERE username LIKE 'CLOCK_%';");
$pdo->exec("DELETE FROM devices WHERE id IN (1000, 1001);");
$pdo->exec("DELETE FROM employees WHERE id IN (1000, 1001);");
$pdo->exec("DELETE FROM branches WHERE id IN (1000, 1001);");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "\nTests Completed. Failed: {$failed}\n";
if ($failed > 0) {
    exit(1);
}
