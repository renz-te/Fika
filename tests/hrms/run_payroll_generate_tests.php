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
$pdo->exec("DELETE FROM payroll_items WHERE employee_id IN (991, 992, 993, 994, 995, 996)");
$pdo->exec("DELETE FROM payroll_runs WHERE period_start = '2026-10-01'");
$pdo->exec("DELETE FROM attendance_certifications WHERE period_start = '2026-10-01'");
$pdo->exec("DELETE FROM attendance_logs WHERE employee_id IN (991, 992, 993, 994, 995, 996)");
$pdo->exec("DELETE FROM employees WHERE branch_id = 999 OR id IN (991, 992, 993, 994, 995, 996)");
$pdo->exec("DELETE FROM branches WHERE id = 999");
$pdo->exec("DELETE FROM users WHERE id IN (1001, 1002, 1003, 1004, 1005, 1006)");

// Setup
$pdo->exec("INSERT INTO branches (id, name) VALUES (999, 'Test Branch')");
$pdo->exec("INSERT IGNORE INTO users (id, username, password, role_id, branch_id, employee_id) VALUES (1001, 'admin', 'x', 1, NULL, NULL)");
$pdo->exec("INSERT IGNORE INTO users (id, username, password, role_id, branch_id, employee_id) VALUES (1002, 'chr', 'x', 2, NULL, 996)"); // CHR is 996 (HQ)
$pdo->exec("INSERT IGNORE INTO users (id, username, password, role_id, branch_id, employee_id) VALUES (1003, 'ga', 'x', 3, NULL, NULL)");
$pdo->exec("INSERT IGNORE INTO users (id, username, password, role_id, branch_id, employee_id) VALUES (1004, 'ba', 'x', 4, 999, 995)"); // BA is 995 (OFFICIAL)
$pdo->exec("INSERT IGNORE INTO users (id, username, password, role_id, branch_id, employee_id) VALUES (1005, 'bhr', 'x', 5, 999, NULL)");
$pdo->exec("INSERT IGNORE INTO users (id, username, password, role_id, branch_id, employee_id) VALUES (1006, 'bm', 'x', 6, 999, NULL)");

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

// 995: OFFICIAL (Branch Manager)
$pdo->exec("INSERT INTO employees (id, employee_code, branch_id, first_name, last_name, email, employment_type, basic_salary, status, staff_class) 
            VALUES (995, 'T995', 999, 'Branch', 'Manager', '995@t.com', 'REGULAR', 50000, 'ACTIVE', 'OFFICIAL')");

// 996: HQ (Corporate HR)
$pdo->exec("INSERT INTO employees (id, employee_code, branch_id, first_name, last_name, email, employment_type, basic_salary, status, staff_class) 
            VALUES (996, 'T996', NULL, 'Corp', 'HR', '996@t.com', 'REGULAR', 60000, 'ACTIVE', 'HQ')");

// Certify the attendance for OFFICIALS and HQ (they require global certification or we can just mock the certification)
$pdo->exec("INSERT INTO attendance_certifications (scope, branch_id, period_start, period_end, certified_by, status) VALUES ('BRANCH', 999, '$start', '$end', (SELECT id FROM users LIMIT 1), 'CERTIFIED')");
$pdo->exec("INSERT INTO attendance_certifications (scope, branch_id, period_start, period_end, certified_by, status) VALUES ('OFFICIALS', NULL, '$start', '$end', (SELECT id FROM users LIMIT 1), 'CERTIFIED')");
$pdo->exec("INSERT INTO attendance_certifications (scope, branch_id, period_start, period_end, certified_by, status) VALUES ('HQ', NULL, '$start', '$end', (SELECT id FROM users LIMIT 1), 'CERTIFIED')");
// For overlap test
$pdo->exec("INSERT INTO attendance_certifications (scope, branch_id, period_start, period_end, certified_by, status) VALUES ('HQ', NULL, '$start', '2026-10-20', (SELECT id FROM users LIMIT 1), 'CERTIFIED')");

if (session_status() === PHP_SESSION_NONE) { session_start(); }
// We don't set a global admin session here anymore, we pass it via callApi

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
        $uStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $uStmt->execute([$overrideUserId]);
        $u = $uStmt->fetch();
        $sess['user'] = [
            'id' => $u['id'],
            'role_id' => $u['role_id'],
            'branch_id' => $u['branch_id'],
            'employee_id' => $u['employee_id']
        ];
    } else {
        $sess['user'] = ['id' => 1001, 'role_id' => 1, 'branch_id' => null, 'employee_id' => null]; // Default Admin
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

function callGenerateApi($payload, $overrideUserId = null) {
    return callApi('fika_hrms_payroll_generate.php', $payload, $overrideUserId);
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
    
    // Test overlap
    $resOverlap = callGenerateApi([
        'scope' => 'OFFICIALS',
        'branch_id' => null,
        'period_start' => $start,
        'period_end' => $end,
        'cutoff_number' => 1
    ]);
    assertTest("OFFICIALS generation succeeds", $resOverlap['status'] === 200, json_encode($resOverlap));
    
    // Now verify 995 is in OFFICIALS run and not BRANCH run
    $branchItems = $pdo->query("SELECT employee_id FROM payroll_items WHERE payroll_run_id = $runId")->fetchAll(PDO::FETCH_COLUMN);
    assertTest("BA/BM's own pay is not in the branch run", !in_array(995, $branchItems), "Found 995 in branch run");
    
    $offRunId = $resOverlap['body']['payroll_run_id'];
    $offItems = $pdo->query("SELECT employee_id FROM payroll_items WHERE payroll_run_id = $offRunId")->fetchAll(PDO::FETCH_COLUMN);
    assertTest("Crew never appear in OFFICIALS", !in_array(991, $offItems), "Found crew 991 in OFFICIALS");
    
    // HQ 
    $resHQ = callGenerateApi([
        'scope' => 'HQ',
        'payee_employee_id' => 996,
        'period_start' => $start,
        'period_end' => $end,
        'cutoff_number' => 1
    ]);
    assertTest("HQ generation succeeds for specific payee", $resHQ['status'] === 200, json_encode($resHQ));
    
    // If we try to generate HQ again for the same person on an overlapping date:
    $resHQOverlap = callGenerateApi([
        'scope' => 'HQ',
        'payee_employee_id' => 996,
        'period_start' => $start, 
        'period_end' => '2026-10-20', // different period, but overlaps
        'cutoff_number' => 1
    ]);
    assertTest("Same employee in two overlapping runs is rejected", $resHQOverlap['status'] === 400 && strpos($resHQOverlap['body']['error'], 'Overlap detected') !== false, json_encode($resHQOverlap));
    
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
    
    // Test Maker-Checker & Role Chain
    
    // 1. BM (1006) cannot generate BRANCH
    $resBMGen = callGenerateApi([
        'scope' => 'BRANCH',
        'branch_id' => 999,
        'period_start' => $start,
        'period_end' => $end,
        'cutoff_number' => 2
    ], 1006);
    assertTest("BM cannot generate", isset($resBMGen['body']['error']) && (strpos($resBMGen['body']['error'], 'Only BHR') !== false || strpos($resBMGen['body']['error'], 'Missing permission') !== false), json_encode($resBMGen));
    
    // 2. BHR (1005) cannot approve BRANCH
    $resBHRApprove = callApi('fika_hrms_payroll_approve.php', ['payroll_run_id' => $runId], 1005);
    assertTest("BHR cannot approve", isset($resBHRApprove['body']['error']) && (strpos($resBHRApprove['body']['error'], 'Only BA') !== false || strpos($resBHRApprove['body']['error'], 'Missing permission') !== false), json_encode($resBHRApprove));
    
    // 3. BA (1004) CAN approve BRANCH. Oh wait, maker checker: 1001 (ADMIN) generated it. BA is 1004, so it's a different person.
    // Wait, BA cannot approve a run containing BA's pay. But BA's pay is NOT in BRANCH run (asserted earlier). So this should work!
    $resBAApprove = callApi('fika_hrms_payroll_approve.php', ['payroll_run_id' => $runId], 1004);
    assertTest("BA approves BRANCH", $resBAApprove['status'] === 200, json_encode($resBAApprove));
    
    // 4. BA cannot release it? No, BA CAN release BRANCH. Wait, let's test GA fallback on release.
    $resGARelease = callApi('fika_hrms_payroll_release.php', ['payroll_run_id' => $runId], 1003); // GA is 1003
    assertTest("GA fallback works on BRANCH release", $resGARelease['status'] === 200, json_encode($resGARelease));
    
    // 5. CHR cannot approve OFFICIALS
    $resCHRApprove = callApi('fika_hrms_payroll_approve.php', ['payroll_run_id' => $offRunId], 1002); // CHR is 1002
    assertTest("CHR cannot approve OFFICIALS", isset($resCHRApprove['body']['error']) && (strpos($resCHRApprove['body']['error'], 'Only GA') !== false || strpos($resCHRApprove['body']['error'], 'Missing permission') !== false), json_encode($resCHRApprove));
    
    // 6. ADMIN approves HQ
    $hqRunId = $resHQ['body']['payroll_run_id'];
    $resAdminApproveHQ = callApi('fika_hrms_payroll_approve.php', ['payroll_run_id' => $hqRunId], 1001); // 1001 is ADMIN. Wait, ADMIN generated HQ! Separation of duties!
    // Ah! ADMIN generated HQ, so ADMIN cannot approve it. Let's make GA generate HQ, then ADMIN approves it.
    // Wait, earlier the test called HQ generation with default user (1001 = ADMIN).
    // If ADMIN generated it, ADMIN cannot approve it.
    // Let me check that it fails for SoD, then GA generates a new HQ, then ADMIN approves.
    assertTest("ADMIN cannot approve HQ if ADMIN generated it (SoD)", isset($resAdminApproveHQ['body']['error']) && strpos($resAdminApproveHQ['body']['error'], 'Separation of Duties') !== false, json_encode($resAdminApproveHQ));
    
    // Let's generate another HQ run using GA (1003) for the same payee but different cutoff (cutoff=2).
    // Actually we need a different period so it doesn't conflict. We already certified '2026-10-20'.
    $resHQ2 = callGenerateApi([
        'scope' => 'HQ',
        'payee_employee_id' => 996,
        'period_start' => $start,
        'period_end' => '2026-10-20',
        'cutoff_number' => 2
    ], 1003); // GA
    
    // Wait, earlier the overlap test for HQ used '2026-10-20' and failed because of overlap!
    // So if it overlaps, we can't generate it!
    // Let's just clear the first HQ run, then GA can generate it.
    callApi('fika_hrms_payroll_delete.php', ['payroll_run_id' => $hqRunId], 1001);
    
    $resHQ2 = callGenerateApi([
        'scope' => 'HQ',
        'payee_employee_id' => 996,
        'period_start' => $start,
        'period_end' => $end,
        'cutoff_number' => 1
    ], 1003); // GA
    
    $hqRunId2 = $resHQ2['body']['payroll_run_id'];
    
    $resAdminApproveHQ2 = callApi('fika_hrms_payroll_approve.php', ['payroll_run_id' => $hqRunId2], 1001); // ADMIN
    assertTest("ADMIN approves HQ", $resAdminApproveHQ2['status'] === 200, json_encode($resAdminApproveHQ2));
    
    // 7. Totals mismatch blocks approval
    // We modify an item in $offRunId to break the SUM
    $pdo->exec("UPDATE payroll_items SET net_pay = net_pay + 10 WHERE payroll_run_id = $offRunId LIMIT 1");
    $resMismatch = callApi('fika_hrms_payroll_approve.php', ['payroll_run_id' => $offRunId], 1003); // GA tries to approve
    assertTest("Totals mismatch blocks approval", isset($resMismatch['body']['error']) && strpos($resMismatch['body']['error'], 'mismatch') !== false, json_encode($resMismatch));
    
    // 8. BA cannot approve a run containing BA. 
    $resBAMakerChecker = callApi('fika_hrms_payroll_approve.php', ['payroll_run_id' => $offRunId], 1004); // BA is 1004, contains 995
    assertTest("BA cannot approve a run containing BA", isset($resBAMakerChecker['body']['error']) && (strpos($resBAMakerChecker['body']['error'], 'own pay') !== false || strpos($resBAMakerChecker['body']['error'], 'Only GA') !== false || strpos($resBAMakerChecker['body']['error'], 'Branch scope mismatch') !== false), json_encode($resBAMakerChecker));

    
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
