<?php
require_once __DIR__ . '/../../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.\n");
}

echo "=======================================================\n";
echo "Running Phase 15B — Shift Board Scheduling Tests\n";
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

function runEndpoint(string $scriptRelPath, array $sessionUser, array $get = [], array $post = [], ?string $rawJson = null): array {
    $temp = sys_get_temp_dir() . '/shift_test_wrap_' . uniqid() . '.php';
    $bootstrapPath = addslashes(__DIR__ . '/../../app/bootstrap.php');
    $targetPath = addslashes(__DIR__ . '/../../' . $scriptRelPath);

    $sessExport = var_export($sessionUser, true);
    $getExport = var_export($get, true);
    $postExport = var_export($post, true);
    $jsonExport = var_export($rawJson, true);

    $isPost = ($rawJson !== null || !empty($post));
    $methodStr = $isPost ? "'POST'" : "'GET'";

    $code = "<?php
\$_GET = {$getExport};
\$_POST = {$postExport};
\$_FILES = [];
\$GLOBALS['RAW_INPUT'] = {$jsonExport};
\$_SERVER['REQUEST_METHOD'] = {$methodStr};
\$_SERVER['HTTP_ACCEPT'] = 'application/json';

require_once '{$bootstrapPath}';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
\$_SESSION['user'] = {$sessExport};
\$_SESSION['csrf_token'] = 'shift_csrf_secret';
\$_POST['csrf_token'] = 'shift_csrf_secret';
\$_SERVER['HTTP_X_CSRF_TOKEN'] = 'shift_csrf_secret';

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
    $rawOutput = shell_exec(PHP_BINARY . " " . escapeshellarg($temp));
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

// 0. Cleanup fixture IDs
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM role_permissions WHERE role_id IN (9201, 9202);");
$pdo->exec("DELETE FROM users WHERE id IN (9201, 9202, 9203, 9204);");
$pdo->exec("DELETE FROM open_shifts WHERE branch_id IN (9201, 9202);");
$pdo->exec("DELETE FROM shifts WHERE branch_id IN (9201, 9202);");
$pdo->exec("DELETE FROM leave_requests WHERE employee_id IN (9201, 9202, 9203);");
$pdo->exec("DELETE FROM employees WHERE id IN (9201, 9202, 9203);");
$pdo->exec("DELETE FROM branches WHERE id IN (9201, 9202);");
$pdo->exec("DELETE FROM roles WHERE id IN (9201, 9202);");

// 1. Branches
$pdo->exec("INSERT INTO branches (id, name) VALUES (9201, 'Shift Branch 1'), (9202, 'Shift Branch 2');");

// 2. Roles & Permissions
$pdo->exec("INSERT INTO roles (id, name) VALUES (9201, 'Shift BM'), (9202, 'Shift Staff');");
$pdo->exec("INSERT INTO role_permissions (role_id, permission_name) VALUES 
    (9201, 'schedule.view'),
    (9201, 'schedule.manage'),
    (9201, 'schedule.claim'),
    (9202, 'schedule.view'),
    (9202, 'schedule.claim');");

// 3. Employees
$pdo->exec("INSERT INTO employees (id, employee_code, first_name, last_name, email, branch_id, status) VALUES 
    (9201, 'EMP_SH_A', 'Alice', 'Shift', 'alice@shift.local', 9201, 'ACTIVE'),
    (9202, 'EMP_SH_B', 'Bob', 'Shift', 'bob@shift.local', 9201, 'ACTIVE'),
    (9203, 'EMP_SH_C', 'Charlie', 'Shift', 'charlie@shift.local', 9202, 'ACTIVE');");

// 4. Users
$passHash = password_hash('Pass123456', PASSWORD_DEFAULT);
$pdo->exec("INSERT INTO users (id, username, password, role_id, employee_id, branch_id) VALUES 
    (9201, 'bm_user', '{$passHash}', 9201, 9201, 9201),
    (9202, 'emp_a_user', '{$passHash}', 9202, 9201, 9201),
    (9203, 'emp_b_user', '{$passHash}', 9202, 9202, 9201),
    (9204, 'emp_c_user', '{$passHash}', 9202, 9203, 9202);");

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

$sessionBM = [
    'id' => 9201,
    'role_id' => 9201,
    'employee_id' => 9201,
    'branch_id' => 9201
];

$sessionEmpA = [
    'id' => 9202,
    'role_id' => 9202,
    'employee_id' => 9201,
    'branch_id' => 9201
];

$sessionEmpB = [
    'id' => 9203,
    'role_id' => 9202,
    'employee_id' => 9202,
    'branch_id' => 9201
];

$sessionEmpC = [
    'id' => 9204,
    'role_id' => 9202,
    'employee_id' => 9203,
    'branch_id' => 9202
];

// =========================================================================
// Case 1: BM creates a shift for Employee A. Employee A sees it in ESS.
// =========================================================================
$targetDate1 = date('Y-m-d', strtotime('+1 day'));
$resCreate1 = runEndpoint('fika_hrms/api/fika_hrms_shift_save.php', $sessionBM, [], [
    'action' => 'create',
    'branch_id' => 9201,
    'employee_id' => 9201,
    'shift_date' => $targetDate1,
    'start_time' => '08:00',
    'end_time' => '16:00',
    'shift_type' => 'Opening',
    'status' => 'PUBLISHED'
]);
$createJson1 = json_decode($resCreate1['output'], true);
$shiftId1 = (int)($createJson1['shift_id'] ?? 0);

$resEssA1 = runEndpoint('fika_hrms/fika_hrms_ess.php', $sessionEmpA);
$case1Passed = ($resCreate1['code'] === 200)
    && ($shiftId1 > 0)
    && (strpos($resEssA1['output'], date('l, M d, Y', strtotime($targetDate1))) !== false)
    && (strpos($resEssA1['output'], '08:00 AM') !== false);

assertTest(
    "Case 1: BM creates a shift for Employee A. Employee A sees it in ESS",
    $case1Passed,
    "Failed to create or view shift: HTTP {$resCreate1['code']}, Output: {$resCreate1['output']}"
);

// =========================================================================
// Case 2: BM assigns an overlapping shift to Employee A -> 400 error.
// =========================================================================
// Existing shift: 08:00 to 16:00 on $targetDate1. Attempting 12:00 to 20:00 (overlaps)
$resOverlap = runEndpoint('fika_hrms/api/fika_hrms_shift_save.php', $sessionBM, [], [
    'action' => 'create',
    'branch_id' => 9201,
    'employee_id' => 9201,
    'shift_date' => $targetDate1,
    'start_time' => '12:00',
    'end_time' => '20:00',
    'shift_type' => 'Mid',
    'status' => 'PUBLISHED'
]);
$case2Passed = ($resOverlap['code'] === 400)
    && (strpos($resOverlap['output'], 'Overlapping shift exists.') !== false);

assertTest(
    "Case 2: BM assigns an overlapping shift to Employee A -> 400 error",
    $case2Passed,
    "Expected HTTP 400 Overlapping shift exists. Got HTTP {$resOverlap['code']}, Output: {$resOverlap['output']}"
);

// =========================================================================
// Case 3: DRAFT shift does NOT appear in ESS until published.
// =========================================================================
$targetDateDraft = date('Y-m-d', strtotime('+2 days'));
$resCreateDraft = runEndpoint('fika_hrms/api/fika_hrms_shift_save.php', $sessionBM, [], [
    'action' => 'create',
    'branch_id' => 9201,
    'employee_id' => 9201,
    'shift_date' => $targetDateDraft,
    'start_time' => '10:00',
    'end_time' => '18:00',
    'shift_type' => 'Mid',
    'status' => 'DRAFT'
]);
$draftJson = json_decode($resCreateDraft['output'], true);
$draftShiftId = (int)($draftJson['shift_id'] ?? 0);

// Check ESS before publish: Must NOT appear
$resEssDraftBefore = runEndpoint('fika_hrms/fika_hrms_ess.php', $sessionEmpA);
$hiddenInEss = (strpos($resEssDraftBefore['output'], date('l, M d, Y', strtotime($targetDateDraft))) === false);

// Publish the draft shift
$resPublish = runEndpoint('fika_hrms/api/fika_hrms_shift_save.php', $sessionBM, [], [
    'action' => 'publish',
    'shift_id' => $draftShiftId
]);

// Check ESS after publish: MUST appear
$resEssDraftAfter = runEndpoint('fika_hrms/fika_hrms_ess.php', $sessionEmpA);
$visibleAfterPublish = (strpos($resEssDraftAfter['output'], date('l, M d, Y', strtotime($targetDateDraft))) !== false);

$case3Passed = ($resCreateDraft['code'] === 200) && $hiddenInEss && ($resPublish['code'] === 200) && $visibleAfterPublish;

assertTest(
    "Case 3: DRAFT shift does NOT appear in ESS until published",
    $case3Passed,
    "Draft visibility test failed. HiddenBefore: " . ($hiddenInEss ? 'YES' : 'NO') . ", VisibleAfter: " . ($visibleAfterPublish ? 'YES' : 'NO')
);

// =========================================================================
// Case 4: BM for Branch 1 cannot schedule shifts for Branch 2 (403).
// =========================================================================
$targetDateBranch2 = date('Y-m-d', strtotime('+3 days'));
$resCrossBranch = runEndpoint('fika_hrms/api/fika_hrms_shift_save.php', $sessionBM, [], [
    'action' => 'create',
    'branch_id' => 9202, // Branch 2 (BM is assigned to Branch 9201)
    'employee_id' => 9203, // Employee C (belongs to Branch 9202)
    'shift_date' => $targetDateBranch2,
    'start_time' => '08:00',
    'end_time' => '16:00',
    'status' => 'PUBLISHED'
]);
$case4Passed = ($resCrossBranch['code'] === 403);

assertTest(
    "Case 4: BM for Branch 1 cannot schedule shifts for Branch 2 (403)",
    $case4Passed,
    "Expected HTTP 403. Got HTTP {$resCrossBranch['code']}, Output: {$resCrossBranch['output']}"
);

// =========================================================================
// Case 5: Employee A drops a shift -> appears in open_shifts. Employee B claims it -> BM approves -> shift now belongs to B.
// =========================================================================
// 1. Employee A drops shiftId1
$resDrop = runEndpoint('fika_hrms/api/fika_hrms_shift_claim.php', $sessionEmpA, [], [
    'action' => 'drop',
    'shift_id' => $shiftId1,
    'reason' => 'Doctor appointment'
]);
$dropJson = json_decode($resDrop['output'], true);
$openShiftId = (int)($dropJson['open_shift_id'] ?? 0);

// Check open_shifts table: status is OPEN, shift table employee_id is still 9201 (Emp A)
$openRow = $pdo->query("SELECT * FROM open_shifts WHERE id = {$openShiftId}")->fetch();
$shiftRowAfterDrop = $pdo->query("SELECT employee_id FROM shifts WHERE id = {$shiftId1}")->fetchColumn();
$dropStateOk = ($resDrop['code'] === 200) 
    && ($openRow !== false) 
    && ($openRow['status'] === 'OPEN')
    && ((int)$shiftRowAfterDrop === 9201);

// 2. Employee B claims the open shift
$resClaim = runEndpoint('fika_hrms/api/fika_hrms_shift_claim.php', $sessionEmpB, [], [
    'action' => 'claim',
    'open_shift_id' => $openShiftId
]);
$openRowAfterClaim = $pdo->query("SELECT * FROM open_shifts WHERE id = {$openShiftId}")->fetch();
$shiftRowAfterClaim = $pdo->query("SELECT employee_id FROM shifts WHERE id = {$shiftId1}")->fetchColumn();
$claimStateOk = ($resClaim['code'] === 200)
    && ($openRowAfterClaim['status'] === 'PENDING_APPROVAL')
    && ((int)$openRowAfterClaim['claimed_by'] === 9202)
    && ((int)$shiftRowAfterClaim === 9201); // Still on Emp A until approved!

// 3. Manager approves the claim
$resApprove = runEndpoint('fika_hrms/api/fika_hrms_shift_claim.php', $sessionBM, [], [
    'action' => 'decide',
    'open_shift_id' => $openShiftId,
    'decision' => 'APPROVE'
]);
$openRowAfterApprove = $pdo->query("SELECT * FROM open_shifts WHERE id = {$openShiftId}")->fetch();
$shiftRowAfterApprove = $pdo->query("SELECT employee_id FROM shifts WHERE id = {$shiftId1}")->fetchColumn();
$approveStateOk = ($resApprove['code'] === 200)
    && ($openRowAfterApprove['status'] === 'APPROVED')
    && ((int)$shiftRowAfterApprove === 9202); // Shift successfully reassigned to Employee B!

$case5Passed = $dropStateOk && $claimStateOk && $approveStateOk;

assertTest(
    "Case 5: Employee A drops a shift -> appears in open_shifts. Employee B claims it -> BM approves -> shift now belongs to B",
    $case5Passed,
    "State machine failed. DropOk: " . ($dropStateOk ? 'YES' : 'NO') . ", ClaimOk: " . ($claimStateOk ? 'YES' : 'NO') . ", ApproveOk: " . ($approveStateOk ? 'YES' : 'NO')
);

// =========================================================================
// Case 6: Leave Conflict Validation (Constraint 1 & 5)
// =========================================================================
$leaveDate = date('Y-m-d', strtotime('+5 days'));
// 1. Insert an APPROVED leave for Employee A
$pdo->exec("INSERT INTO leave_requests (employee_id, type, date_from, date_to, days, reason, status) VALUES 
    (9201, 'VACATION', '{$leaveDate}', '{$leaveDate}', 1, 'Approved Vacation', 'APPROVED');");

// 2. BM attempts to assign shift on $leaveDate to Employee A
$resLeaveConflict = runEndpoint('fika_hrms/api/fika_hrms_shift_save.php', $sessionBM, [], [
    'action' => 'create',
    'branch_id' => 9201,
    'employee_id' => 9201,
    'shift_date' => $leaveDate,
    'start_time' => '09:00',
    'end_time' => '17:00',
    'status' => 'PUBLISHED'
]);

$case6Passed = ($resLeaveConflict['code'] === 400)
    && (strpos($resLeaveConflict['output'], 'Employee is on approved leave during this date.') !== false);

assertTest(
    "Case 6: Leave Conflict Validation: Cannot assign shift to employee on approved leave (400)",
    $case6Passed,
    "Expected HTTP 400 Employee is on approved leave during this date. Got HTTP {$resLeaveConflict['code']}, Output: {$resLeaveConflict['output']}"
);

// 0. Cleanup fixture data
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM role_permissions WHERE role_id IN (9201, 9202);");
$pdo->exec("DELETE FROM users WHERE id IN (9201, 9202, 9203, 9204);");
$pdo->exec("DELETE FROM open_shifts WHERE branch_id IN (9201, 9202);");
$pdo->exec("DELETE FROM shifts WHERE branch_id IN (9201, 9202);");
$pdo->exec("DELETE FROM leave_requests WHERE employee_id IN (9201, 9202, 9203);");
$pdo->exec("DELETE FROM employees WHERE id IN (9201, 9202, 9203);");
$pdo->exec("DELETE FROM branches WHERE id IN (9201, 9202);");
$pdo->exec("DELETE FROM roles WHERE id IN (9201, 9202);");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "\n=======================================================\n";
echo "Shift Board Test Summary: " . ($failed === 0 ? "ALL PASS" : "{$failed} FAILED") . "\n";
echo "=======================================================\n";

if ($failed > 0) {
    exit(1);
}
