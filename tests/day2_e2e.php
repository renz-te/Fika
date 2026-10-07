<?php
require_once __DIR__ . '/../app/bootstrap.php';
global $pdo;

function callApi($method, $scriptPath, $payload = [], $userId = 1, $isGet = false) {
    global $pdo;
    
    // Auth user session setup
    $sess = [];
    if ($userId) {
        $uStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $user = $uStmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $sess['user'] = [
                'id' => $user['id'],
                'role_id' => $user['role_id'],
                'branch_id' => $user['branch_id'],
                'employee_id' => $user['employee_id']
            ];
        }
    }
    
    $token = bin2hex(random_bytes(32));
    $sess['csrf_token'] = $token;
    $sess['csrf_token_time'] = time();
    if (!$isGet) {
        $payload['csrf_token'] = $token;
    }

    $wrapper = __DIR__ . '/wrapper_' . uniqid() . '.php';
    $jsonPayload = json_encode($payload);
    
    // Determine query parameters if GET
    $gets = '';
    if ($isGet && !empty($payload)) {
        $gets = http_build_query($payload);
        if (strpos($scriptPath, '?') === false) {
            $scriptPath .= '?' . $gets;
        } else {
            $scriptPath .= '&' . $gets;
        }
    }
    
    $parts = explode('?', $scriptPath);
    $script = $parts[0];
    $getArray = [];
    if (isset($parts[1])) {
        parse_str($parts[1], $getArray);
    }
    
    $code = "<?php
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    session_id('" . session_id() . "');
    session_start();
    \$_SESSION = " . var_export($sess, true) . ";
    \$_GET = " . var_export($getArray, true) . ";
    \$_SERVER['REQUEST_METHOD'] = '$method';
    \$_SERVER['HTTP_X_CSRF_TOKEN'] = '$token';
    ";
    
    if (!$isGet) {
        $code .= "
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', 'MockPhpStream');
        class MockPhpStream {
            private \$position = 0;
            private \$data = '';
            public function stream_open(\$path, \$mode, \$options, &\$opened_path) {
                \$this->data = base64_decode('" . base64_encode($jsonPayload) . "');
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
        ";
    }
    
    $code .= "
    try {
        ob_start();
        require __DIR__ . '/../fika_hrms/' . '$script';
    } catch (Exception \$e) {
        echo \$e->getMessage();
    } catch (Error \$e) {
        echo \$e->getMessage();
    }
    ";
    
    file_put_contents($wrapper, $code);
    
    $out = shell_exec(PHP_BINARY . " " . escapeshellarg($wrapper) . " 2>&1");
    unlink($wrapper);
    
    // Find JSON response
    preg_match('/\{.*\}/s', $out, $matches);
    $json = $matches[0] ?? '{}';
    $body = json_decode($json, true);
    
    $status = isset($body['error']) ? 400 : 200;
    if (isset($body['error']) && strpos($out, '403') !== false) {
        $status = 403;
    }
    
    return ['status' => $status, 'body' => $body, 'out' => $out];
}

$failed = 0;
function assertE2E($desc, $cond, $ctx = '') {
    global $failed;
    if ($cond) {
        echo "[PASS] $desc\n";
    } else {
        echo "[FAIL] $desc\nContext: $ctx\n";
        $failed++;
    }
}

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("DELETE FROM payroll_items WHERE employee_id = 9999");
    $pdo->exec("DELETE FROM payroll_runs WHERE branch_id = 8888");
    $pdo->exec("DELETE FROM attendance_certifications WHERE branch_id = 8888");
    $pdo->exec("DELETE FROM leave_requests WHERE employee_id = 9999");
    $pdo->exec("DELETE FROM attendance_logs WHERE employee_id = 9999");
    $pdo->exec("DELETE FROM users WHERE id IN (8001, 8002, 8003)");
    $pdo->exec("DELETE FROM employees WHERE id IN (9999, 9001, 9002, 9003)");
    $pdo->exec("DELETE FROM branches WHERE id IN (8888, 8889)");

    // Create branch
    $pdo->exec("INSERT INTO branches (id, name) VALUES (8888, 'E2E Branch')");
    
    // Create employee
    $pdo->exec("INSERT INTO employees (id, employee_code, branch_id, first_name, last_name, email, employment_type, basic_salary, hourly_rate, status, attendance_mode, date_hired) 
                VALUES (9999, 'E2E-1', 8888, 'E2E', 'Employee', 'e2e@fika.test', 'REGULAR', 20000.00, 0, 'Active', 'CLOCK', '2026-01-01')");

    // Create users (BM, BHR, BA)
    // BM = role_id 1 for simplicity, we just use ADMIN for all but give them different employee_ids so Maker-Checker treats them as different people
    // We put them in a different branch (8889) so they don't get included in the 8888 payroll run.
    $pdo->exec("INSERT INTO branches (id, name) VALUES (8889, 'Admin Branch')");
    $pdo->exec("INSERT INTO employees (id, employee_code, branch_id, first_name, last_name, email) VALUES (9001, 'BM-1', 8889, 'Branch', 'Manager', 'bm@fika.test')");
    $pdo->exec("INSERT INTO employees (id, employee_code, branch_id, first_name, last_name, email) VALUES (9002, 'BHR-1', 8889, 'Branch', 'HR', 'bhr@fika.test')");
    $pdo->exec("INSERT INTO employees (id, employee_code, branch_id, first_name, last_name, email) VALUES (9003, 'BA-1', 8889, 'Branch', 'Accountant', 'ba@fika.test')");
    
    $pdo->exec("INSERT INTO users (id, username, password, role_id, branch_id, employee_id) VALUES (8001, 'e2ebm', '...', 1, NULL, 9001)"); // BM
    $pdo->exec("INSERT INTO users (id, username, password, role_id, branch_id, employee_id) VALUES (8002, 'e2ebhr', '...', 1, NULL, 9002)"); // BHR
    $pdo->exec("INSERT INTO users (id, username, password, role_id, branch_id, employee_id) VALUES (8003, 'e2eba', '...', 1, NULL, 9003)"); // BA

    // 1. Clock in/out over several days
    for ($i=1; $i<=5; $i++) {
        $date = "2026-11-0$i";
        // Call API
        $res = callApi('POST', 'api/fika_hrms_attendance_action.php', [
            'action' => 'CLOCK_IN',
            'employee_code' => 'E2E-1'
        ], 8001); // Mock doing it via terminal or just manually inserting
        
        // Actually the attendance action relies on the frontend pos terminal. Let's just insert to database to simulate exact times.
        $pdo->exec("INSERT INTO attendance_logs (employee_id, branch_id, work_date, clock_in, clock_out, status) 
                    VALUES (9999, 8888, '$date', '$date 08:00:00', '$date 17:00:00', 'CLOSED')");
    }

    // 3. Leave
    $leaveDate = "2026-11-06";
    $pdo->exec("INSERT INTO leave_requests (employee_id, type, date_from, date_to, days, reason, status) 
                VALUES (9999, 'SICK', '$leaveDate', '$leaveDate', 1, 'Sick', 'APPROVED')");

    // 4. Certify Attendance (BM)
    $resCert = callApi('POST', 'api/fika_hrms_attendance_certify_action.php', [
        'scope' => 'BRANCH',
        'branch_id' => 8888,
        'period_start' => '2026-11-01',
        'period_end' => '2026-11-15'
    ], 8001);
    assertE2E("Attendance Certification (BM)", $resCert['status'] === 200, json_encode($resCert));

    // 5. Generate (BHR)
    $resGen = callApi('POST', 'api/fika_hrms_payroll_generate.php', [
        'scope' => 'BRANCH',
        'branch_id' => 8888,
        'period_start' => '2026-11-01',
        'period_end' => '2026-11-15',
        'cutoff_number' => 1
    ], 8002);
    assertE2E("Payroll Generation (BHR)", $resGen['status'] === 200, json_encode($resGen));
    $runId = $resGen['body']['payroll_run_id'] ?? 0;

    // 6. Review / Recompute checks (BHR tries to approve)
    $resBhrApprove = callApi('POST', 'api/fika_hrms_payroll_approve.php', [
        'payroll_run_id' => $runId
    ], 8002);
    assertE2E("Maker-Checker: Drafter cannot approve", $resBhrApprove['status'] !== 200, json_encode($resBhrApprove));

    // 7. Approve (BA)
    $resBaApprove = callApi('POST', 'api/fika_hrms_payroll_approve.php', [
        'payroll_run_id' => $runId
    ], 8003);
    assertE2E("Approval by independent checker (BA)", $resBaApprove['status'] === 200, json_encode($resBaApprove));

    // 8. Release (BA)
    $resBaRelease = callApi('POST', 'api/fika_hrms_payroll_release.php', [
        'payroll_run_id' => $runId
    ], 8003);
    assertE2E("Release by BA", $resBaRelease['status'] === 200, json_encode($resBaRelease));

    // 9. Payslip Load
    $itemId = $pdo->query("SELECT id FROM payroll_items WHERE payroll_run_id = $runId LIMIT 1")->fetchColumn();
    $resPayslip = callApi('GET', "fika_hrms_payslip.php?item=$itemId", [], 8003, true);
    assertE2E("Payslip View Loads", strpos($resPayslip['out'], 'Payslip - E2E Employee') !== false, "HTML missing expected content");

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    echo "\nE2E Completed. Failed: $failed\n";
    if ($failed > 0) exit(1);
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    exit(1);
}
