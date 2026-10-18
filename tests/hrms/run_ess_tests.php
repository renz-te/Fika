<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/attendance_calc.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.\n");
}

echo "=======================================================\n";
echo "Running Phase 15A — Employee Self-Service (ESS) Tests\n";
echo "=======================================================\n\n";

$failed = 0;

function assertTest(string $name, bool $condition, string $failMsg): void {
    global $failed;
    if ($condition) {
        echo "[PASS] {$name}\n";
    } else {
        echo "[FAIL] {$name}: {$failMsg}\n";
        $failed++;
    }
}

function runEndpoint(string $scriptRelPath, array $sessionUser, array $get = [], array $post = [], ?string $rawJson = null, array $files = []): array {
    $temp = sys_get_temp_dir() . '/ess_test_wrap_' . uniqid() . '.php';
    $bootstrapPath = addslashes(__DIR__ . '/../../app/bootstrap.php');
    $targetPath = addslashes(__DIR__ . '/../../' . $scriptRelPath);

    $sessExport = var_export($sessionUser, true);
    $getExport = var_export($get, true);
    $postExport = var_export($post, true);
    $filesExport = var_export($files, true);
    $jsonExport = var_export($rawJson, true);

    $isPost = ($rawJson !== null || !empty($post) || !empty($files));
    $methodStr = $isPost ? "'POST'" : "'GET'";

    $code = "<?php
\$_GET = {$getExport};
\$_POST = {$postExport};
\$_FILES = {$filesExport};
\$GLOBALS['RAW_INPUT'] = {$jsonExport};
\$_SERVER['REQUEST_METHOD'] = {$methodStr};
\$_SERVER['HTTP_ACCEPT'] = 'application/json';

require_once '{$bootstrapPath}';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
\$_SESSION['user'] = {$sessExport};
\$_SESSION['csrf_token'] = 'ess_csrf_token_secret';
\$_POST['csrf_token'] = 'ess_csrf_token_secret';
\$_SERVER['HTTP_X_CSRF_TOKEN'] = 'ess_csrf_token_secret';

// Reset Rbac cache
\$ref = new ReflectionClass('Rbac');
if (\$ref->hasProperty('cache')) {
    \$prop = \$ref->getProperty('cache');
    \$prop->setAccessible(true);
    \$prop->setValue(null, []);
}

register_shutdown_function(function() {
    \$output = ob_get_clean();
    \$httpCode = http_response_code() ?: 200;
    echo '---JSON_DELIMITER---' . json_encode(['code' => \$httpCode, 'output' => \$output]);
});

ob_start();
require '{$targetPath}';
";

    file_put_contents($temp, $code);

    $descriptors = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ];
    $process = proc_open(PHP_BINARY . " " . escapeshellarg($temp), $descriptors, $pipes);
    if (is_resource($process)) {
        if ($rawJson !== null) {
            fwrite($pipes[0], $rawJson);
        }
        fclose($pipes[0]);
        $rawOutput = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        proc_close($process);
    } else {
        $rawOutput = shell_exec(PHP_BINARY . " " . escapeshellarg($temp));
    }

    @unlink($temp);

    $parts = explode('---JSON_DELIMITER---', $rawOutput);
    if (count($parts) >= 2) {
        $res = json_decode(trim($parts[1]), true);
        if (is_array($res)) {
            return $res;
        }
    }

    return ['code' => 500, 'output' => $rawOutput];
}

global $pdo;

// Cleanup fixture IDs
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM role_permissions WHERE role_id IN (9101, 9102);");
$pdo->exec("DELETE FROM users WHERE id IN (9101, 9102);");
$pdo->exec("DELETE FROM leave_requests WHERE employee_id IN (9101, 9102);");
$pdo->exec("DELETE FROM attendance_logs WHERE employee_id IN (9101, 9102);");
$pdo->exec("DELETE FROM payroll_items WHERE id IN (9101, 9102, 9103);");
$pdo->exec("DELETE FROM payroll_runs WHERE id IN (9101, 9102, 9103);");
$pdo->exec("DELETE FROM employees WHERE id IN (9101, 9102);");
$pdo->exec("DELETE FROM branches WHERE id IN (9101, 9102);");
$pdo->exec("DELETE FROM roles WHERE id IN (9101, 9102);");
$pdo->exec("DELETE FROM audit_logs WHERE user_id IN (9101, 9102);");

// 1. Branches
$pdo->exec("INSERT INTO branches (id, name) VALUES (9101, 'ESS Branch North'), (9102, 'ESS Branch South');");

// 2. Roles
$pdo->exec("INSERT INTO roles (id, name) VALUES (9101, 'ESS Employee'), (9102, 'ESS Manager');");
$pdo->exec("INSERT INTO role_permissions (role_id, permission_name) VALUES 
    (9101, 'pos.access'),
    (9102, 'payroll.view');");

// 3. Employees
$bankAEnc = Crypto::encrypt('123456789012');
$bankBEnc = Crypto::encrypt('987654321098');
$pdo->prepare("
    INSERT INTO employees (id, employee_code, first_name, last_name, email, phone, branch_id, status, basic_salary, bank_no_enc, sick_leave_balance) 
    VALUES 
    (9101, 'EMP_ESS_A', 'Alice', 'Smith', 'alice@fika.local', '09111111111', 9101, 'ACTIVE', 25000.00, ?, 10),
    (9102, 'EMP_ESS_B', 'Bob', 'Jones', 'bob@fika.local', '09222222222', 9102, 'ACTIVE', 30000.00, ?, 10)
")->execute([$bankAEnc, $bankBEnc]);

// 4. Users
$passA = password_hash('OldPassword123', PASSWORD_DEFAULT);
$passB = password_hash('PassB123', PASSWORD_DEFAULT);
$pdo->exec("INSERT INTO users (id, username, password, email, role_id, employee_id, branch_id) VALUES 
    (9101, 'user_ess_a', '{$passA}', 'alice@fika.local', 9101, 9101, 9101),
    (9102, 'user_ess_b', '{$passB}', 'bob@fika.local', 9101, 9102, 9102);");

// 5. Payroll Runs
$pdo->exec("INSERT INTO payroll_runs (id, scope, branch_id, period_start, period_end, status, processed_by) VALUES 
    (9101, 'BRANCH', 9101, '2026-10-01', '2026-10-15', 'RELEASED', 9101),
    (9102, 'BRANCH', 9102, '2026-10-01', '2026-10-15', 'RELEASED', 9101),
    (9103, 'BRANCH', 9101, '2026-10-16', '2026-10-31', 'DRAFT', 9101);");

// 6. Payroll Items
$pdo->exec("INSERT INTO payroll_items (id, payroll_run_id, employee_id, basic_pay, overtime_pay, bonus_pay, gross_pay, sss_deduction, philhealth_deduction, pagibig_deduction, tax_deduction, net_pay) VALUES 
    (9101, 9101, 9101, 12500.00, 0, 0, 12500.00, 500.00, 250.00, 100.00, 650.00, 11000.00),
    (9102, 9102, 9102, 15000.00, 0, 0, 15000.00, 600.00, 300.00, 100.00, 800.00, 13200.00),
    (9103, 9103, 9101, 12500.00, 0, 0, 12500.00, 500.00, 250.00, 100.00, 650.00, 11000.00);");

// 7. Attendance Log for Employee A
$today = date('Y-m-d');
$pdo->exec("INSERT INTO attendance_logs (employee_id, branch_id, clock_in, clock_out, work_date, status) VALUES 
    (9101, 9101, '{$today} 08:00:00', '{$today} 17:00:00', '{$today}', 'CLOSED');");

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

$sessionUserA = [
    'id' => 9101,
    'role_id' => 9101,
    'employee_id' => 9101,
    'branch_id' => 9101
];

$sessionUserB = [
    'id' => 9102,
    'role_id' => 9101,
    'employee_id' => 9102,
    'branch_id' => 9102
];

// =========================================================================
// Case 1: Employee A can view their own profile and payslips.
// =========================================================================
$resPayslipA = runEndpoint('fika_hrms/fika_hrms_payslip.php', $sessionUserA, ['item' => 9101]);
$canViewOwnPayslip = ($resPayslipA['code'] === 200) 
    && (strpos($resPayslipA['output'], 'Alice Smith') !== false)
    && (strpos($resPayslipA['output'], 'EMP_ESS_A') !== false)
    && (strpos($resPayslipA['output'], '11,000.00') !== false);

// Check that ESS portal page renders Alice's details
$resEssPage = runEndpoint('fika_hrms/fika_hrms_ess.php', $sessionUserA);
$canViewOwnProfile = ($resEssPage['code'] === 200)
    && (strpos($resEssPage['output'], 'Alice Smith') !== false)
    && (strpos($resEssPage['output'], 'EMP_ESS_A') !== false)
    && (strpos($resEssPage['output'], 'Released Payslips') !== false);

assertTest(
    "Case 1: Employee A can view their own profile and payslips",
    $canViewOwnPayslip && $canViewOwnProfile,
    "Failed to view own payslip (HTTP {$resPayslipA['code']}) or profile (HTTP {$resEssPage['code']})"
);

// =========================================================================
// Case 2: Employee A attempting to access Employee B's payslip or profile gets 403/404.
// =========================================================================
// Employee A requests Employee B's payslip (item 9102)
$resPayslipCross = runEndpoint('fika_hrms/fika_hrms_payslip.php', $sessionUserA, ['item' => 9102]);
$crossBlocked = ($resPayslipCross['code'] === 403) || (strpos($resPayslipCross['output'], 'access denied') !== false);

// Employee A requests unreleased Draft payslip (item 9103)
$resDraftBlocked = runEndpoint('fika_hrms/fika_hrms_payslip.php', $sessionUserA, ['item' => 9103]);
$draftBlocked = ($resDraftBlocked['code'] === 403) || (strpos($resDraftBlocked['output'], 'access denied') !== false);

// Employee A passing foreign employee_id in URL to ESS portal
$resEssTamper = runEndpoint('fika_hrms/fika_hrms_ess.php', $sessionUserA, ['employee_id' => 9102]);
$urlParamIgnored = ($resEssTamper['code'] === 200)
    && (strpos($resEssTamper['output'], 'Alice Smith') !== false)
    && (strpos($resEssTamper['output'], 'Bob Jones') === false);

assertTest(
    "Case 2: Employee A attempting to access Employee B's payslip gets 403 and URL employee_id is ignored",
    $crossBlocked && $draftBlocked && $urlParamIgnored,
    "Cross-access was not blocked: Cross HTTP {$resPayslipCross['code']}, Draft HTTP {$resDraftBlocked['code']}, Tamper output leaked: " . substr($resEssTamper['output'], 0, 100)
);

// =========================================================================
// Case 3: Employee can update their own phone number.
// =========================================================================
$updatePhonePayload = [
    'action' => 'update_profile',
    'phone' => '09998887777',
    'address' => '123 Baker Street, Quezon City',
    'emergency_contact' => 'John Doe 09170000000'
];
$resUpdatePhone = runEndpoint('fika_hrms/api/fika_hrms_ess_save.php', $sessionUserA, [], $updatePhonePayload);

$empACheck = $pdo->query("SELECT phone, address, emergency_contact FROM employees WHERE id = 9101")->fetch();
$phoneUpdated = ($resUpdatePhone['code'] === 200)
    && ($empACheck['phone'] === '09998887777')
    && ($empACheck['address'] === '123 Baker Street, Quezon City');

assertTest(
    "Case 3: Employee can update their own phone number and contact details",
    $phoneUpdated,
    "Failed to update phone. HTTP {$resUpdatePhone['code']}, DB phone: '{$empACheck['phone']}'"
);

// =========================================================================
// Case 4: Employee cannot update their own salary or role (client-sent fields are rejected with 400).
// =========================================================================
$maliciousPayload = [
    'action' => 'update_profile',
    'phone' => '09991112222',
    'salary' => 999999.00,
    'role_id' => 1
];
$resMalicious = runEndpoint('fika_hrms/api/fika_hrms_ess_save.php', $sessionUserA, [], $maliciousPayload);
$maliciousJson = json_decode($resMalicious['output'], true);

$empACheckAfter = $pdo->query("SELECT basic_salary FROM employees WHERE id = 9101")->fetchColumn();
$userACheckAfter = $pdo->query("SELECT role_id FROM users WHERE id = 9101")->fetchColumn();

$salaryRejected = ($resMalicious['code'] === 400)
    && (strpos($maliciousJson['error'] ?? '', 'Forbidden field in payload: salary') !== false)
    && ((float)$empACheckAfter === 25000.00)
    && ((int)$userACheckAfter === 9101);

assertTest(
    "Case 4: Employee cannot update their own salary or role (rejected with HTTP 400)",
    $salaryRejected,
    "Expected HTTP 400 and unchanged salary/role. Got HTTP {$resMalicious['code']}, Response: {$resMalicious['output']}"
);

// =========================================================================
// Case 5: Employee can submit a leave request (status must be PENDING, and employee_id must match session).
// =========================================================================
$leavePayload = [
    'action' => 'request_leave',
    'type' => 'VACATION',
    'date_from' => '2026-11-10',
    'date_to' => '2026-11-12',
    'reason' => 'Annual family gathering',
    'employee_id' => 9102 // Maliciously trying to file for Employee B
];
$resLeave = runEndpoint('fika_hrms/api/fika_hrms_ess_save.php', $sessionUserA, [], $leavePayload);

$leaveRow = $pdo->query("SELECT * FROM leave_requests WHERE employee_id = 9101 ORDER BY id DESC LIMIT 1")->fetch();
$leaveFiledSuccessfully = ($resLeave['code'] === 200)
    && ($leaveRow !== false)
    && ($leaveRow['employee_id'] == 9101)
    && ($leaveRow['status'] === 'PENDING')
    && ((float)$leaveRow['days'] == 3.00);

// Ensure no leave was filed for Employee B
$leaveCountB = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE employee_id = 9102")->fetchColumn();

assertTest(
    "Case 5: Employee can submit a leave request (status PENDING, scoped to session employee_id)",
    $leaveFiledSuccessfully && ((int)$leaveCountB === 0),
    "Leave request failed or misattributed: HTTP {$resLeave['code']}, Output: {$resLeave['output']}"
);

// =========================================================================
// Case 6: Password Change, Leave Overlap, Bank Encryption, & File Upload Constraints
// =========================================================================
// Subcase 6a: Password change too short (< 8 chars) -> returns 400
$resShortPass = runEndpoint('fika_hrms/api/fika_hrms_ess_password.php', $sessionUserA, [], [
    'current_password' => 'OldPassword123',
    'new_password' => '123',
    'confirm_password' => '123'
]);
$shortPassBlocked = ($resShortPass['code'] === 400)
    && (strpos($resShortPass['output'], 'at least 8 characters') !== false);

// Subcase 6b: Password change success -> can log in with new password & audit logged
$resPassSuccess = runEndpoint('fika_hrms/api/fika_hrms_ess_password.php', $sessionUserA, [], [
    'current_password' => 'OldPassword123',
    'new_password' => 'NewSecurePass2026!',
    'confirm_password' => 'NewSecurePass2026!'
]);
$passAUpdated = $pdo->query("SELECT password FROM users WHERE id = 9101")->fetchColumn();
$newPassVerified = password_verify('NewSecurePass2026!', $passAUpdated);

$auditLogged = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE user_id = 9101 AND action = 'PASSWORD_CHANGED'")->fetchColumn() > 0;

// Subcase 6c: Employee sends raw JSON with forbidden "salary" field -> returns 400
$rawSalaryJson = json_encode([
    'action' => 'update_profile',
    'salary' => 1000000,
    'phone' => '09990001111'
]);
$resRawSalary = runEndpoint('fika_hrms/api/fika_hrms_ess_save.php', $sessionUserA, [], [], $rawSalaryJson);
$rawSalaryBlocked = ($resRawSalary['code'] === 400)
    && (strpos($resRawSalary['output'], 'Forbidden field in payload: salary') !== false);

// Subcase 6d: Overlapping leave request blocked -> returns 400 with "Overlapping leave request exists."
$overlapPayload = [
    'action' => 'request_leave',
    'type' => 'VACATION',
    'date_from' => '2026-11-11', // overlaps with existing 2026-11-10 to 2026-11-12
    'date_to' => '2026-11-15',
    'reason' => 'Conflicting trip'
];
$resOverlap = runEndpoint('fika_hrms/api/fika_hrms_ess_save.php', $sessionUserA, [], $overlapPayload);
$overlapBlocked = ($resOverlap['code'] === 400)
    && (strpos($resOverlap['output'], 'Overlapping leave request exists.') !== false);

// Subcase 6e: Bank account encrypted in DB & decrypted / masked in UI
$resBankUpdate = runEndpoint('fika_hrms/api/fika_hrms_ess_save.php', $sessionUserA, [], [
    'action' => 'update_profile',
    'bank_account' => '987654321012'
]);
$empBankRow = $pdo->query("SELECT bank_no_enc FROM employees WHERE id = 9101")->fetch();
$rawDbBank = $empBankRow['bank_no_enc'];
$isDbEncrypted = ($rawDbBank !== '987654321012') && (Crypto::decrypt($rawDbBank) === '987654321012');

// UI masking test
$resEssBankUi = runEndpoint('fika_hrms/fika_hrms_ess.php', $sessionUserA);
$uiMasked = (strpos($resEssBankUi['output'], '****1012') !== false)
    && (strpos($resEssBankUi['output'], '987654321012') === false);

// Subcase 6f: Upload::handle() rejects disguised PHP file (.pdf extension with PHP content)
$fakePhpFile = sys_get_temp_dir() . '/fake_cert_' . uniqid() . '.pdf';
file_put_contents($fakePhpFile, "<?php echo 'malicious payload'; ?>");
$fakeUploadInfo = [
    'name' => 'certificate.pdf',
    'type' => 'application/pdf',
    'tmp_name' => $fakePhpFile,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($fakePhpFile)
];
$targetUploadDir = sys_get_temp_dir() . '/test_uploads_' . uniqid();
$uploadResult = Upload::handle($fakeUploadInfo, $targetUploadDir);
@unlink($fakePhpFile);
$disguisedFileRejected = ($uploadResult === null);

$case6Passed = $shortPassBlocked && ($resPassSuccess['code'] === 200) && $newPassVerified && $auditLogged
    && $rawSalaryBlocked && $overlapBlocked && $isDbEncrypted && $uiMasked && $disguisedFileRejected;

assertTest(
    "Case 6: Password change, strict whitelist, leave overlap, bank encryption/masking, and upload security",
    $case6Passed,
    "Subcase failure details: " . json_encode([
        'shortPassBlocked' => $shortPassBlocked,
        'passUpdateSuccess' => $resPassSuccess['code'] === 200,
        'newPassVerified' => $newPassVerified,
        'auditLogged' => $auditLogged,
        'rawSalaryBlocked' => $rawSalaryBlocked,
        'overlapBlocked' => $overlapBlocked,
        'isDbEncrypted' => $isDbEncrypted,
        'uiMasked' => $uiMasked,
        'disguisedFileRejected' => $disguisedFileRejected
    ])
);

// Cleanup fixture data
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM role_permissions WHERE role_id IN (9101, 9102);");
$pdo->exec("DELETE FROM users WHERE id IN (9101, 9102);");
$pdo->exec("DELETE FROM leave_requests WHERE employee_id IN (9101, 9102);");
$pdo->exec("DELETE FROM attendance_logs WHERE employee_id IN (9101, 9102);");
$pdo->exec("DELETE FROM payroll_items WHERE id IN (9101, 9102, 9103);");
$pdo->exec("DELETE FROM payroll_runs WHERE id IN (9101, 9102, 9103);");
$pdo->exec("DELETE FROM employees WHERE id IN (9101, 9102);");
$pdo->exec("DELETE FROM branches WHERE id IN (9101, 9102);");
$pdo->exec("DELETE FROM roles WHERE id IN (9101, 9102);");
$pdo->exec("DELETE FROM audit_logs WHERE user_id IN (9101, 9102);");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "\n=======================================================\n";
echo "HRMS ESS Test Summary: " . ($failed === 0 ? "ALL PASS" : "{$failed} FAILED") . "\n";
echo "=======================================================\n";

if ($failed > 0) {
    exit(1);
}
