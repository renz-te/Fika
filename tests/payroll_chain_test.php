<?php
/**
 * CLI Test Suite: BHR -> BA -> BM Payroll Approval Workflow & Checksum Tamper Lock
 *
 * Location: tests/payroll_chain_test.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$rootDir = dirname(__DIR__);
chdir($rootDir);

$config = require $rootDir . '/cafe_hrms/config.php';
$pdo = new PDO("mysql:host={$config['db']['host']};dbname={$config['db']['name']}", $config['db']['user'], $config['db']['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

require_once $rootDir . '/cafe_hrms/includes/payroll_chain.php';

$failures = [];
$passes = [];

function assert_true($cond, $msg) {
    global $failures, $passes;
    if ($cond) {
        $passes[] = $msg;
        echo "[PASS] $msg\n";
    } else {
        $failures[] = $msg;
        echo "[FAIL] $msg\n";
    }
}

function run_eval($phpCode) {
    global $rootDir;
    $tmp = sys_get_temp_dir() . '/eval_test_' . uniqid() . '.php';
    $code = "<?php\nchdir(" . var_export($rootDir, true) . ");\nsession_start();\n" . $phpCode;
    file_put_contents($tmp, $code);
    $out = shell_exec("php " . escapeshellarg($tmp) . " 2>&1");
    @unlink($tmp);
    return $out;
}

echo "============================================================\n";
echo "1. DATABASE SCHEMA & IDEMPOTENT MIGRATION VERIFICATION\n";
echo "============================================================\n";

$cols = $pdo->query("SHOW COLUMNS FROM payroll")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($cols, 'Field');

assert_true(in_array('submitted_by', $colNames), 'Column submitted_by exists on payroll');
assert_true(in_array('approved_by', $colNames), 'Column approved_by exists on payroll');
assert_true(in_array('released_by', $colNames), 'Column released_by exists on payroll');
assert_true(in_array('rejected_by', $colNames), 'Column rejected_by exists on payroll');
assert_true(in_array('rejection_reason', $colNames), 'Column rejection_reason exists on payroll');
assert_true(in_array('approved_total_centavos', $colNames), 'Column approved_total_centavos exists on payroll');
assert_true(in_array('approved_checksum', $colNames), 'Column approved_checksum exists on payroll');
assert_true(in_array('approved_at', $colNames), 'Column approved_at exists on payroll');
assert_true(in_array('released_at', $colNames), 'Column released_at exists on payroll');

$statusCol = null;
foreach ($cols as $c) {
    if ($c['Field'] === 'status') {
        $statusCol = $c['Type'];
        break;
    }
}
assert_true(strpos($statusCol, 'Pending Approval') !== false, 'status enum includes Pending Approval');
assert_true(strpos($statusCol, 'Approved') !== false, 'status enum includes Approved');
assert_true(strpos($statusCol, 'Released') !== false, 'status enum includes Released');
assert_true(strpos($statusCol, 'Rejected') !== false, 'status enum includes Rejected');

echo "\n============================================================\n";
echo "2. TEST SEEDING DRAFT RECORD FOR WORKFLOW\n";
echo "============================================================\n";

// Ensure test users 2, 3, 4 exist for activity logging foreign keys
$pdo->exec("INSERT IGNORE INTO users (id, name, username, email, password, role_id, branch_id) VALUES 
    (2, 'Test BHR', 'test_bhr', 'bhr@test.local', 'dummy', 2, 1),
    (3, 'Test BA', 'test_ba', 'ba@test.local', 'dummy', 2, 1),
    (4, 'Test BM', 'test_bm', 'bm@test.local', 'dummy', 2, 1)
");

// Ensure employee exists
$empId = $pdo->query("SELECT id FROM employees WHERE branch_id = 1 LIMIT 1")->fetchColumn();
if (!$empId) {
    $pdo->exec("INSERT INTO employees (employee_id, first_name, last_name, email, phone, branch_id, status, hourly_rate) 
                VALUES ('EMP-TEST-01', 'Test', 'Employee', 'test.emp@cafehrms.local', '09123456789', 1, 'Active', 100.00)");
    $empId = $pdo->lastInsertId();
}

// Clean any old test records for period 2026-10-01 - 2026-10-15
$pdo->exec("DELETE FROM payroll WHERE employee_id = $empId AND period_start = '2026-10-01' AND period_end = '2026-10-15'");

// Insert test record in Draft status
$ins = $pdo->prepare("INSERT INTO payroll (employee_id, branch_id, hourly_rate, regular_hours, gross_pay, deductions, net_pay, status, period_start, period_end) 
                      VALUES (?, 1, 100.00, 80.00, 8000.00, 800.00, 7200.00, 'Draft', '2026-10-01', '2026-10-15')");
$ins->execute([$empId]);
$testId = (int)$pdo->lastInsertId();
assert_true($testId > 0, "Created test payroll draft ID: $testId");

echo "\n============================================================\n";
echo "3. BHR ROLE TESTS (Default Tab, Stepper, Submit, Guards)\n";
echo "============================================================\n";

// 3.1 BHR default view
$bhrView = run_eval(<<<PHP
\$_SESSION['user'] = ['id' => 2, 'role' => 'Branch HR', 'branch_id' => 1];
\$_SESSION['csrf_token'] = 'token_abc';
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SERVER['PHP_SELF'] = 'payroll.php';
ob_start();
require 'cafe_hrms/payroll.php';
\$html = ob_get_clean();

echo json_encode([
    'has_drafts_panel' => strpos(\$html, 'Generate Semi-Monthly Payroll Draft') !== false,
    'has_submit_btn' => strpos(\$html, 'Submit for Approval') !== false,
    'has_stepper' => strpos(\$html, 'BA Review') !== false && strpos(\$html, 'BM Release') !== false
]);
PHP);
$bhrViewData = json_decode($bhrView, true);
assert_true($bhrViewData['has_drafts_panel'] ?? false, 'BHR defaults to Drafts tab and sees generation panel');
assert_true($bhrViewData['has_submit_btn'] ?? false, 'BHR sees Submit for Approval button in Drafts tab');
assert_true($bhrViewData['has_stepper'] ?? false, 'Visual Stepper is rendered on table rows');

// 3.2 BHR calls 'release' (Guard test: must return 403 Forbidden)
$bhrReleaseGuard = run_eval(<<<PHP
\$_SESSION['user'] = ['id' => 2, 'role' => 'Branch HR', 'branch_id' => 1];
\$_SESSION['csrf_token'] = 'token_abc';
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_POST['csrf_token'] = 'token_abc';
\$_POST['action'] = 'release';
\$_POST['payroll_id'] = $testId;
\$_SERVER['PHP_SELF'] = 'payroll.php';
require 'cafe_hrms/payroll.php';
PHP);
assert_true(strpos($bhrReleaseGuard, 'Access denied: Branch HR cannot release payroll.') !== false, 'BHR calling release returns 403 Forbidden');

// 3.3 BHR executes 'submit_approval' (Draft -> Pending Approval)
$bhrSubmit = run_eval(<<<PHP
\$_SESSION['user'] = ['id' => 2, 'role' => 'Branch HR', 'branch_id' => 1];
\$_SESSION['csrf_token'] = 'token_abc';
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_POST['csrf_token'] = 'token_abc';
\$_POST['action'] = 'submit_approval';
\$_POST['payroll_id'] = $testId;
\$_SERVER['PHP_SELF'] = 'payroll.php';
require 'cafe_hrms/payroll.php';
PHP);

$stAfterSubmit = $pdo->query("SELECT status, submitted_by FROM payroll WHERE id = $testId")->fetch();
assert_true($stAfterSubmit['status'] === 'Pending Approval', 'Status transitioned to Pending Approval');
assert_true((int)$stAfterSubmit['submitted_by'] === 2, 'submitted_by was recorded as BHR user ID 2');

echo "\n============================================================\n";
echo "4. BA ROLE TESTS (Pending Tab, Reject with Reason, Approve, Guards)\n";
echo "============================================================\n";

// 4.1 BA default view
$baView = run_eval(<<<PHP
\$_SESSION['user'] = ['id' => 3, 'role' => 'Branch Accountant', 'branch_id' => 1];
\$_SESSION['csrf_token'] = 'token_abc';
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SERVER['PHP_SELF'] = 'payroll.php';
ob_start();
require 'cafe_hrms/payroll.php';
\$html = ob_get_clean();

echo json_encode([
    'no_draft_panel' => strpos(\$html, 'Generate Semi-Monthly Payroll Draft') === false,
    'has_approve_btn' => strpos(\$html, 'Approve & Send to BM') !== false,
    'has_reject_btn' => strpos(\$html, 'openRejectModal') !== false
]);
PHP);
$baViewData = json_decode($baView, true);
assert_true($baViewData['no_draft_panel'] ?? false, 'BA defaults to Pending tab with generation panel hidden');
assert_true($baViewData['has_approve_btn'] ?? false, 'BA sees Approve & Send to BM button');
assert_true($baViewData['has_reject_btn'] ?? false, 'BA sees Reject button');

// 4.2 BA calls 'generate_drafts' (Guard: must return 403 Forbidden)
$baGenGuard = run_eval(<<<PHP
\$_SESSION['user'] = ['id' => 3, 'role' => 'Branch Accountant', 'branch_id' => 1];
\$_SESSION['csrf_token'] = 'token_abc';
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_POST['csrf_token'] = 'token_abc';
\$_POST['action'] = 'generate_drafts';
\$_POST['payroll_period'] = '2026-10-01|2026-10-15';
\$_SERVER['PHP_SELF'] = 'payroll.php';
require 'cafe_hrms/payroll.php';
PHP);
assert_true(strpos($baGenGuard, 'Access denied: Only HR roles can generate payroll drafts.') !== false, 'BA calling generate_drafts returns 403 Forbidden');

// 4.3 BA calls 'reject' without reason (Guard: must return 400 Bad Request)
$baRejectNoReason = run_eval(<<<PHP
\$_SESSION['user'] = ['id' => 3, 'role' => 'Branch Accountant', 'branch_id' => 1];
\$_SESSION['csrf_token'] = 'token_abc';
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_POST['csrf_token'] = 'token_abc';
\$_POST['action'] = 'reject';
\$_POST['payroll_id'] = $testId;
\$_POST['rejection_reason'] = '';
\$_SERVER['PHP_SELF'] = 'payroll.php';
require 'cafe_hrms/payroll.php';
PHP);
assert_true(strpos($baRejectNoReason, 'Bad Request: A rejection reason is required.') !== false, 'BA reject without reason returns 400 Bad Request');

// 4.4 BA calls 'reject' with valid reason (Pending Approval -> Rejected)
$baRejectWithReason = run_eval(<<<PHP
\$_SESSION['user'] = ['id' => 3, 'role' => 'Branch Accountant', 'branch_id' => 1];
\$_SESSION['csrf_token'] = 'token_abc';
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_POST['csrf_token'] = 'token_abc';
\$_POST['action'] = 'reject';
\$_POST['payroll_id'] = $testId;
\$_POST['rejection_reason'] = 'Incorrect overtime calculation for holiday shift';
\$_SERVER['PHP_SELF'] = 'payroll.php';
require 'cafe_hrms/payroll.php';
PHP);

$stAfterReject = $pdo->query("SELECT status, rejected_by, rejection_reason FROM payroll WHERE id = $testId")->fetch();
assert_true($stAfterReject['status'] === 'Rejected', 'Status transitioned to Rejected');
assert_true((int)$stAfterReject['rejected_by'] === 3, 'rejected_by was recorded as BA user ID 3');
assert_true($stAfterReject['rejection_reason'] === 'Incorrect overtime calculation for holiday shift', 'Rejection reason recorded in database');

// 4.5 BHR views rejected record in Drafts tab and sees red reason banner & Resubmit button
$bhrViewRejected = run_eval(<<<PHP
\$_SESSION['user'] = ['id' => 2, 'role' => 'Branch HR', 'branch_id' => 1];
\$_SESSION['csrf_token'] = 'token_abc';
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_GET['tab'] = 'drafts';
\$_SERVER['PHP_SELF'] = 'payroll.php';
ob_start();
require 'cafe_hrms/payroll.php';
\$html = ob_get_clean();

echo json_encode([
    'has_reason_banner' => strpos(\$html, 'Incorrect overtime calculation for holiday shift') !== false,
    'has_resubmit_btn' => strpos(\$html, 'Resubmit for Approval') !== false
]);
PHP);
$bhrRejData = json_decode($bhrViewRejected, true);
assert_true($bhrRejData['has_reason_banner'] ?? false, 'BHR sees rejection reason banner in Drafts tab');
assert_true($bhrRejData['has_resubmit_btn'] ?? false, 'BHR sees Resubmit for Approval button');

// 4.6 BHR resubmits (Rejected -> Pending Approval)
run_eval(<<<PHP
\$_SESSION['user'] = ['id' => 2, 'role' => 'Branch HR', 'branch_id' => 1];
\$_SESSION['csrf_token'] = 'token_abc';
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_POST['csrf_token'] = 'token_abc';
\$_POST['action'] = 'submit_approval';
\$_POST['payroll_id'] = $testId;
\$_SERVER['PHP_SELF'] = 'payroll.php';
require 'cafe_hrms/payroll.php';
PHP);
$stAfterResubmit = $pdo->query("SELECT status, rejection_reason FROM payroll WHERE id = $testId")->fetch();
assert_true($stAfterResubmit['status'] === 'Pending Approval', 'Status transitioned back to Pending Approval');
assert_true(empty($stAfterResubmit['rejection_reason']), 'Rejection reason cleared upon resubmission');

// 4.7 BA approves (Pending Approval -> Approved) with Checksum Lock
run_eval(<<<PHP
\$_SESSION['user'] = ['id' => 3, 'role' => 'Branch Accountant', 'branch_id' => 1];
\$_SESSION['csrf_token'] = 'token_abc';
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_POST['csrf_token'] = 'token_abc';
\$_POST['action'] = 'approve';
\$_POST['payroll_id'] = $testId;
\$_SERVER['PHP_SELF'] = 'payroll.php';
require 'cafe_hrms/payroll.php';
PHP);
$stAfterApprove = $pdo->query("SELECT status, approved_by, approved_checksum, approved_total_centavos, approved_at FROM payroll WHERE id = $testId")->fetch();
assert_true($stAfterApprove['status'] === 'Approved', 'Status transitioned to Approved');
assert_true((int)$stAfterApprove['approved_by'] === 3, 'approved_by was recorded as BA user ID 3');
assert_true(!empty($stAfterApprove['approved_checksum']) && strlen($stAfterApprove['approved_checksum']) === 64, 'approved_checksum is 64-char SHA256 string');
assert_true((int)$stAfterApprove['approved_total_centavos'] === 720000, 'approved_total_centavos recorded as 720000 centavos (Php 7,200.00)');
assert_true(!empty($stAfterApprove['approved_at']), 'approved_at timestamp is populated');

echo "\n============================================================\n";
echo "5. CHECKSUM LOCK & DATA TAMPER DETECTION TEST\n";
echo "============================================================\n";

// 5.1 Manually tamper with net_pay in the database
$originalNetPay = 7200.00;
$tamperedNetPay = 99999.00;
$pdo->exec("UPDATE payroll SET net_pay = $tamperedNetPay WHERE id = $testId");

// Attempt release by BM on tampered record
$tamperReleaseAttempt = run_eval(<<<PHP
\$_SESSION['user'] = ['id' => 4, 'role' => 'Branch Manager', 'branch_id' => 1];
\$_SESSION['csrf_token'] = 'token_abc';
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_POST['csrf_token'] = 'token_abc';
\$_POST['action'] = 'release';
\$_POST['payroll_id'] = $testId;
\$_POST['payment_method'] = 'Bank Transfer';
\$_SERVER['PHP_SELF'] = 'payroll.php';
require 'cafe_hrms/payroll.php';
PHP);

assert_true(strpos($tamperReleaseAttempt, 'Data tampering detected. Payroll totals do not match approved values.') !== false, 'BM release aborted with HTTP 400 on tampered checksum');

// Verify that payroll was NOT released and status remains Approved
$stAfterTamper = $pdo->query("SELECT status, net_pay FROM payroll WHERE id = $testId")->fetch();
assert_true($stAfterTamper['status'] === 'Approved', 'Tampered payroll status was NOT modified (remains Approved)');

// Verify audit log has recorded the tamper detection event
$tamperLog = $pdo->query("SELECT action, details FROM activity_logs WHERE action = 'payroll_tamper_detected' ORDER BY id DESC LIMIT 1")->fetch();
assert_true($tamperLog && strpos($tamperLog['details'], 'Data tampering detected') !== false, 'Audit log captured payroll_tamper_detected event');

// 5.2 Restore the genuine net_pay value
$pdo->exec("UPDATE payroll SET net_pay = $originalNetPay WHERE id = $testId");

echo "\n============================================================\n";
echo "6. BM ROLE TESTS (Release & State Guards)\n";
echo "============================================================\n";

// 6.1 BA calls 'release' on Approved record (Guard: must return 403 Forbidden)
$baReleaseGuard = run_eval(<<<PHP
\$_SESSION['user'] = ['id' => 3, 'role' => 'Branch Accountant', 'branch_id' => 1];
\$_SESSION['csrf_token'] = 'token_abc';
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_POST['csrf_token'] = 'token_abc';
\$_POST['action'] = 'release';
\$_POST['payroll_id'] = $testId;
\$_SERVER['PHP_SELF'] = 'payroll.php';
require 'cafe_hrms/payroll.php';
PHP);
assert_true(strpos($baReleaseGuard, 'Access denied: Branch Accountants cannot release payroll.') !== false, 'BA calling release returns 403 Forbidden');

// 6.2 Release on record that is NOT Approved (Guard: must return 400 Bad Request)
$pdo->exec("UPDATE payroll SET status = 'Draft' WHERE id = $testId");
$bmReleaseNotApproved = run_eval(<<<PHP
\$_SESSION['user'] = ['id' => 4, 'role' => 'Branch Manager', 'branch_id' => 1];
\$_SESSION['csrf_token'] = 'token_abc';
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_POST['csrf_token'] = 'token_abc';
\$_POST['action'] = 'release';
\$_POST['payroll_id'] = $testId;
\$_SERVER['PHP_SELF'] = 'payroll.php';
require 'cafe_hrms/payroll.php';
PHP);
assert_true(strpos($bmReleaseNotApproved, "Bad Request: Payroll record must be in 'Approved' state before it can be Released.") !== false, 'Releasing an unapproved record returns 400 Bad Request');

// Restore to Approved for valid release
$pdo->exec("UPDATE payroll SET status = 'Approved' WHERE id = $testId");

// 6.3 BM default view (Ready for Release tab)
$bmView = run_eval(<<<PHP
\$_SESSION['user'] = ['id' => 4, 'role' => 'Branch Manager', 'branch_id' => 1];
\$_SESSION['csrf_token'] = 'token_abc';
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SERVER['PHP_SELF'] = 'payroll.php';
ob_start();
require 'cafe_hrms/payroll.php';
\$html = ob_get_clean();

echo json_encode([
    'defaults_to_release' => strpos(\$html, 'Ready for Release') !== false,
    'has_release_btn' => strpos(\$html, 'Release Payroll') !== false
]);
PHP);
$bmViewData = json_decode($bmView, true);
assert_true($bmViewData['defaults_to_release'] ?? false, 'BM defaults to Ready for Release tab');
assert_true($bmViewData['has_release_btn'] ?? false, 'BM sees Release Payroll button');

// 6.4 BM executes valid 'release' (Approved -> Released)
run_eval(<<<PHP
\$_SESSION['user'] = ['id' => 4, 'role' => 'Branch Manager', 'branch_id' => 1];
\$_SESSION['csrf_token'] = 'token_abc';
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_POST['csrf_token'] = 'token_abc';
\$_POST['action'] = 'release';
\$_POST['payroll_id'] = $testId;
\$_POST['payment_method'] = 'Bank Transfer';
\$_SERVER['PHP_SELF'] = 'payroll.php';
require 'cafe_hrms/payroll.php';
PHP);

$stAfterRelease = $pdo->query("SELECT status, released_by, released_at, payment_method FROM payroll WHERE id = $testId")->fetch();
assert_true($stAfterRelease['status'] === 'Released', 'Status transitioned to Released');
assert_true((int)$stAfterRelease['released_by'] === 4, 'released_by recorded as BM user ID 4');
assert_true(!empty($stAfterRelease['released_at']), 'released_at timestamp is populated');
assert_true($stAfterRelease['payment_method'] === 'Bank Transfer', 'Payment method saved as Bank Transfer');

echo "\n============================================================\n";
echo "7. BRANCH SCOPING CONSTRAINT TESTS\n";
echo "============================================================\n";

// Ensure Branch 2 employee exists
$empBranch2 = $pdo->query("SELECT id FROM employees WHERE branch_id = 2 LIMIT 1")->fetchColumn();
if (!$empBranch2) {
    $pdo->exec("INSERT IGNORE INTO branches (id, name, status) VALUES (2, 'Uptown Branch', 'Active')");
    $pdo->exec("INSERT INTO employees (employee_id, first_name, last_name, email, phone, branch_id, status, hourly_rate) 
                VALUES ('EMP-TEST-B2', 'Jane', 'BranchTwo', 'jane@b2.local', '09999999999', 2, 'Active', 100.00)");
    $empBranch2 = (int)$pdo->lastInsertId();
}

$pdo->exec("INSERT INTO payroll (employee_id, branch_id, hourly_rate, gross_pay, deductions, net_pay, status, period_start, period_end) 
            VALUES ($empBranch2, 2, 100.00, 5000.00, 500.00, 4500.00, 'Draft', '2026-10-01', '2026-10-15')");
$b2PayrollId = (int)$pdo->lastInsertId();

// Branch HR assigned to Branch 1 should NOT see Branch 2 record
$bhrBranchCheck = run_eval(<<<PHP
\$_SESSION['user'] = ['id' => 2, 'role' => 'Branch HR', 'branch_id' => 1];
\$_SESSION['csrf_token'] = 'token_abc';
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_GET['tab'] = 'drafts';
\$_SERVER['PHP_SELF'] = 'payroll.php';
ob_start();
require 'cafe_hrms/payroll.php';
\$html = ob_get_clean();

echo json_encode([
    'sees_branch2' => strpos(\$html, 'Jane BranchTwo') !== false
]);
PHP);
$bhrScopeData = json_decode($bhrBranchCheck, true);
assert_true(($bhrScopeData['sees_branch2'] ?? true) === false, 'Branch HR (Branch 1) does NOT see Branch 2 records (Branch Scoped)');

// Central HR / Admin CAN see all branches
$centralHrBranchCheck = run_eval(<<<PHP
\$_SESSION['user'] = ['id' => 1, 'role' => 'Central HR', 'branch_id' => 1];
\$_SESSION['csrf_token'] = 'token_abc';
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_GET['tab'] = 'drafts';
\$_SERVER['PHP_SELF'] = 'payroll.php';
ob_start();
require 'cafe_hrms/payroll.php';
\$html = ob_get_clean();

echo json_encode([
    'sees_branch2' => strpos(\$html, 'Jane BranchTwo') !== false
]);
PHP);
$centralScopeData = json_decode($centralHrBranchCheck, true);
assert_true(($centralScopeData['sees_branch2'] ?? false) === true, 'Central HR can see records across all branches');

// Clean up test records
$pdo->exec("DELETE FROM payroll WHERE id IN ($testId, $b2PayrollId)");

echo "\n============================================================\n";
echo "SUMMARY\n";
echo "Passed: " . count($passes) . "\n";
echo "Failed: " . count($failures) . "\n";
echo "============================================================\n";

if (count($failures) > 0) {
    exit(1);
}
echo "ALL PAYROLL WORKFLOW & CHECKSUM CHAIN TESTS PASSED!\n";
