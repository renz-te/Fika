<?php
require_once __DIR__ . '/../../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.");
}

echo "Running Payroll Generation Tests...\n\n";
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

// Cleanup old tests
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM payroll_items WHERE employee_id IN (991, 992, 993, 994)");
$pdo->exec("DELETE FROM payroll_runs WHERE branch_id = 999 AND period_start = '2026-10-01'");
$pdo->exec("DELETE FROM attendance_certifications WHERE branch_id = 999");
$pdo->exec("DELETE FROM attendance_logs WHERE employee_id IN (991, 992, 993, 994)");
$pdo->exec("DELETE FROM employees WHERE branch_id = 999");
$pdo->exec("DELETE FROM branches WHERE id = 999");
$pdo->exec("DELETE FROM users WHERE id IN (1001, 1002, 1003)");

// Setup
$pdo->exec("INSERT INTO branches (id, name) VALUES (999, 'Test Branch')");
$pdo->exec("INSERT IGNORE INTO users (id, username, password, role_id) VALUES (1001, 'test1', 'x', 1)");
$pdo->exec("INSERT IGNORE INTO users (id, username, password, role_id) VALUES (1002, 'test2', 'x', 1)");
$pdo->exec("INSERT IGNORE INTO users (id, username, password, role_id) VALUES (1003, 'test3', 'x', 1)");

// We test a 13-day period: 2026-10-01 to 2026-10-13.
// 10-01 is Thursday. Business days (Mon-Fri) in 10-01 to 10-13:
// Thu (1), Fri (2), Mon (5), Tue (6), Wed (7), Thu (8), Fri (9), Mon (12), Tue (13) = 9 days.
// Wait, if it's 9 days, 9 * 8 = 72 hours.
// In the fixture, Scenario 1 assumes full 104 hours for the period (13 days * 8).
// To fake exactly 13 business days (104 hours), let's use a date range that spans exactly 13 Mon-Fri days.
// e.g. 2026-10-01 to 2026-10-19:
// Oct 1-2 (2), 5-9 (5), 12-16 (5), 19 (1) = 13 days! Perfect.
$start = '2026-10-01';
$end = '2026-10-19';

// 991: Full-time MONTHLY (Scenario 1) - 30k, 13 days worked (104hrs), 0 absent, 0 late.
$pdo->exec("INSERT INTO employees (id, employee_code, branch_id, first_name, last_name, email, employment_type, basic_salary, status, attendance_mode) 
            VALUES (991, 'T991', 999, 'Full', 'Time', '991@t.com', 'REGULAR', 30000, 'ACTIVE', 'CLOCK')");
for ($i=0; $i<13; $i++) {
    // 1st to 19th skipping weekends
    $d = new DateTime("2026-10-01");
    $d->modify("+$i weekdays");
    $ds = $d->format('Y-m-d');
    $pdo->exec("INSERT INTO attendance_logs (employee_id, branch_id, work_date, clock_in, clock_out, status) VALUES (991, 999, '$ds', '$ds 08:00:00', '$ds 16:00:00', 'CLOSED')");
}

// 992: MONTHLY, 1 Absence (Scenario 2) - 30k, 12 days worked, 1 absence
$pdo->exec("INSERT INTO employees (id, employee_code, branch_id, first_name, last_name, email, employment_type, basic_salary, status, attendance_mode) 
            VALUES (992, 'T992', 999, 'One', 'Absent', '992@t.com', 'REGULAR', 30000, 'ACTIVE', 'CLOCK')");
for ($i=0; $i<12; $i++) {
    $d = new DateTime("2026-10-01");
    $d->modify("+$i weekdays");
    $ds = $d->format('Y-m-d');
    $pdo->exec("INSERT INTO attendance_logs (employee_id, branch_id, work_date, clock_in, clock_out, status) VALUES (992, 999, '$ds', '$ds 08:00:00', '$ds 16:00:00', 'CLOSED')");
}
$pdo->exec("INSERT INTO attendance_logs (employee_id, branch_id, work_date, clock_in, clock_out, status) VALUES (992, 999, '2026-10-19', '2026-10-19 00:00:00', '2026-10-19 00:00:00', 'ABSENT')");

// 993: MONTHLY, Late 45 mins (Scenario 4) - 30k, 13 days worked, late 45m on first day.
$pdo->exec("INSERT INTO employees (id, employee_code, branch_id, first_name, last_name, email, employment_type, basic_salary, status, attendance_mode) 
            VALUES (993, 'T993', 999, 'Late', 'Mins', '993@t.com', 'REGULAR', 30000, 'ACTIVE', 'CLOCK')");
for ($i=0; $i<13; $i++) {
    $d = new DateTime("2026-10-01");
    $d->modify("+$i weekdays");
    $ds = $d->format('Y-m-d');
    $in = ($i === 0) ? "$ds 08:45:00" : "$ds 08:00:00";
    $pdo->exec("INSERT INTO attendance_logs (employee_id, branch_id, work_date, clock_in, clock_out, status) VALUES (993, 999, '$ds', '$in', '$ds 16:00:00', 'CLOSED')");
}

// 994: HOURLY Part-timer (Scenario 7) - 20k, 10 days worked (80 hrs).
$pdo->exec("INSERT INTO employees (id, employee_code, branch_id, first_name, last_name, email, employment_type, basic_salary, status, attendance_mode) 
            VALUES (994, 'T994', 999, 'Part', 'Timer', '994@t.com', 'PART_TIME', 20000, 'ACTIVE', 'CLOCK')");
for ($i=0; $i<10; $i++) {
    $d = new DateTime("2026-10-01");
    $d->modify("+$i weekdays");
    $ds = $d->format('Y-m-d');
    $pdo->exec("INSERT INTO attendance_logs (employee_id, branch_id, work_date, clock_in, clock_out, status) VALUES (994, 999, '$ds', '$ds 08:00:00', '$ds 16:00:00', 'CLOSED')");
}

// Certify the attendance
$pdo->exec("INSERT INTO attendance_certifications (scope, branch_id, period_start, period_end, certified_by, status) VALUES ('BRANCH', 999, '$start', '$end', (SELECT id FROM users LIMIT 1), 'CERTIFIED')");

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$testUserId = $pdo->query("SELECT id FROM users LIMIT 1")->fetchColumn();
$_SESSION['user'] = ['id' => $testUserId, 'role_id' => 1, 'branch_id' => null, 'employee_id' => 99999]; // Admin

// Helper to simulate API call via CLI wrapper
function callApi($apiScript, $payload, $overrideUserId = null) {
    global $pdo;
    
    // Setup Csrf
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    $_SESSION['csrf_token_time'] = time();
    $payload['csrf_token'] = $token;
    
    $sess = $_SESSION;
    if ($overrideUserId) {
        $sess['user']['id'] = $overrideUserId;
    }
    
    $parts = explode('?', $apiScript);
    $script = $parts[0];
    if (isset($parts[1])) {
        parse_str($parts[1], $_GET);
    }
    
    $wrapper = __DIR__ . '/wrapper_' . uniqid() . '.php';
    $code = "<?php
    session_id('" . session_id() . "');
    session_start();
    \$_SESSION = " . var_export($sess, true) . ";
    \$_GET = " . var_export($_GET, true) . ";
    \$_SERVER['REQUEST_METHOD'] = 'POST';
    \$_SERVER['HTTP_X_CSRF_TOKEN'] = '" . $token . "';
    
    // Mock php://input
    stream_wrapper_unregister('php');
    stream_wrapper_register('php', 'MockPhpStream');
    class MockPhpStream {
        private \$position = 0;
        private \$data = '';
        public function stream_open(\$path, \$mode, \$options, &\$opened_path) {
            \$this->data = base64_decode('" . base64_encode(json_encode($payload)) . "');
            return true;
        }
        public function stream_read(\$count) {
            \$ret = substr(\$this->data, \$this->position, \$count);
            \$this->position += strlen(\$ret);
            return \$ret;
        }
        public function stream_eof() {
            return \$this->position >= strlen(\$this->data);
        }
        public function stream_stat() { return []; }
    }
    
    try {
        ob_start();
        require __DIR__ . '/../../fika_hrms/api/' . '$script';
    } catch (Exception \$e) {
    } catch (Error \$e) {}
    ";
    
    file_put_contents($wrapper, $code);
    
    $out = shell_exec(PHP_BINARY . " " . escapeshellarg($wrapper));
    unlink($wrapper);
    
    preg_match('/\{.*\}/s', $out, $matches);
    $json = $matches[0] ?? '{}';
    $body = json_decode($json, true);
    
    $status = isset($body['error']) ? 400 : 200;
    if (isset($body['error']) && strpos($out, '403') !== false) {
        $status = 403;
    }
    
    return ['status' => $status, 'body' => $body, 'out' => $out];
}

function callGenerateApi($payload) {
    return callApi('fika_hrms_payroll_generate.php', $payload);
}

$res1 = callGenerateApi([
    'scope' => 'BRANCH',
    'branch_id' => 999,
    'period_start' => $start,
    'period_end' => $end,
    'cutoff_number' => 1
]);

assertTest("First Generation succeeds", $res1['status'] === 200, json_encode($res1));
if ($res1['status'] === 200) {
    $runId = $res1['body']['payroll_run_id'];
    
    $res2 = callGenerateApi([
        'scope' => 'BRANCH',
        'branch_id' => 999,
        'period_start' => $start,
        'period_end' => $end,
        'cutoff_number' => 1
    ]);
    
    assertTest("Generating twice is rejected", $res2['status'] === 400 && strpos($res2['body']['error'], 'already exists') !== false, json_encode($res2));
    
    // Verify Items
    $items = $pdo->query("SELECT employee_id, net_pay FROM payroll_items WHERE payroll_run_id = $runId")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Scenario 1: 991 Net Pay 13377.50
    assertTest("Emp 991 (Full Time) Matches Fixture", isset($items[991]) && (float)$items[991] === 13377.50, "Got " . ($items[991]??'null'));
    
    // Scenario 2: 992 Net Pay 12218.41
    assertTest("Emp 992 (1 Absence) Matches Fixture", isset($items[992]) && (float)$items[992] === 12218.41, "Got " . ($items[992]??'null'));
    
    // Scenario 4: 993 Net Pay 13268.84
    assertTest("Emp 993 (Late 45m) Matches Fixture", isset($items[993]) && (float)$items[993] === 13268.84, "Got " . ($items[993]??'null'));
    
    // Scenario 7: 994 Net Pay 9090.40
    assertTest("Emp 994 (Hourly 80h) Matches Fixture", isset($items[994]) && (float)$items[994] === 9090.40, "Got " . ($items[994]??'null'));
    
    // Test 8D Maker-Checker: Generator cannot approve
    $resApproveMaker = callApi('fika_hrms_payroll_approve.php', ['payroll_run_id' => $runId]);
    assertTest("Generator approving their own run returns error", $resApproveMaker['status'] !== 200 && strpos($resApproveMaker['body']['error'], 'Maker-Checker') !== false || strpos($resApproveMaker['body']['error'], 'Separation of Duties') !== false, json_encode($resApproveMaker));
    
    // Test 8D Release Unapproved fails
    $resReleaseUnapproved = callApi('fika_hrms_payroll_release.php', ['payroll_run_id' => $runId], $testUserId + 1);
    assertTest("Releasing an unapproved run fails", $resReleaseUnapproved['status'] !== 200 && strpos($resReleaseUnapproved['body']['error'], 'Only APPROVED') !== false, json_encode($resReleaseUnapproved));
    
    // Approve it properly (using a different user ID, say 1002)
    $resApprove = callApi('fika_hrms_payroll_approve.php', ['payroll_run_id' => $runId], 1002);
    assertTest("Another user can approve the run", $resApprove['status'] === 200, json_encode($resApprove));
    
    // Release it properly (using user ID 1003)
    $resRelease = callApi('fika_hrms_payroll_release.php', ['payroll_run_id' => $runId], 1003);
    assertTest("A third user can release the run", $resRelease['status'] === 200, json_encode($resRelease));
    
    // Test 8D Adjusting attendance in a released period fails
    // Let's find an attendance log ID for Emp 991
    $logId = $pdo->query("SELECT id FROM attendance_logs WHERE employee_id = 991 LIMIT 1")->fetchColumn();
    $resAdjust = callApi('fika_hrms_attendance_adjust.php', [
        'log_id' => $logId,
        'clock_in' => '2026-10-01 07:00:00',
        'clock_out' => '2026-10-01 16:00:00',
        'reason' => 'Test adjustment'
    ]);
    assertTest("Adjusting attendance in a released period fails", $resAdjust['status'] !== 200 && strpos($resAdjust['body']['error'] ?? $resAdjust['out'], 'released (locked)') !== false, json_encode($resAdjust));

}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "\nTests Completed. Failed: {$failed}\n";
if ($failed > 0) exit(1);
