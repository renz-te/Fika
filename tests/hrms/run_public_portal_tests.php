<?php
require_once __DIR__ . '/../../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.\n");
}

echo "Running Public Portal, Careers, ATS & Branding Tests...\n\n";

$failed = 0;
function assertTest($name, $condition, $failMsg = '') {
    global $failed;
    if ($condition) {
        echo "[PASS] {$name}\n";
    } else {
        echo "[FAIL] {$name}: {$failMsg}\n";
        $failed++;
    }
}

function executeScript(string $relPath, array $server = [], array $post = [], array $files = [], ?array $session = null): array {
    $runner = tempnam(sys_get_temp_dir(), 'portal_test_');
    $absPath = realpath(__DIR__ . '/../../' . ltrim($relPath, '/\\'));
    if (!$absPath || !file_exists($absPath)) {
        throw new RuntimeException("Target file not found: {$relPath}");
    }

    $serverExport = var_export($server, true);
    $postExport = var_export($post, true);
    $filesExport = var_export($files, true);
    $sessionExport = $session !== null ? var_export($session, true) : 'null';
    $targetExport = var_export($absPath, true);

    $code = "<?php
    \$_SERVER = array_merge([
        'HTTP_HOST' => 'localhost',
        'REQUEST_URI' => '/' . " . var_export($relPath, true) . ",
        'REQUEST_METHOD' => 'GET',
        'REMOTE_ADDR' => '127.0.0.1',
    ], {$serverExport});

    \$_GET = [];
    if (!empty(\$_SERVER['QUERY_STRING'])) {
        parse_str(\$_SERVER['QUERY_STRING'], \$_GET);
    }
    \$_POST = {$postExport};
    \$_FILES = {$filesExport};

    register_shutdown_function(function() {
        \$status = http_response_code() ?: 200;
        \$output = ob_get_clean();
        echo '---STATUS:' . \$status . '---\n';
        echo \$output;
    });

    ob_start();
    if ({$sessionExport} !== null) {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        \$_SESSION = {$sessionExport};
    } else {
        if (session_status() === PHP_SESSION_ACTIVE) { session_destroy(); }
        \$_SESSION = [];
    }

    require {$targetExport};
    ";

    file_put_contents($runner, $code);
    $raw = shell_exec(PHP_BINARY . ' ' . escapeshellarg($runner));
    if (file_exists($runner)) {
        unlink($runner);
    }

    $status = 200;
    if (preg_match('/---STATUS:(\d+)---/', $raw, $m)) {
        $status = (int)$m[1];
    }
    $body = preg_replace('/---STATUS:\d+---\s*/', '', $raw);
    return ['status' => $status, 'body' => $body];
}

// =========================================================================
// Case 1: Landing page loads with HTTP 200 and NO session
// =========================================================================
$resLanding = executeScript('index.php', [], [], [], null);
$c1Pass = ($resLanding['status'] === 200)
    && (strpos($resLanding['body'], 'FIKA') !== false)
    && (strpos($resLanding['body'], 'Slow Hours') !== false)
    && (strpos($resLanding['body'], 'Careers') !== false)
    && (strpos($resLanding['body'], 'Staff Login') !== false);
assertTest("Case 1: Landing page loads with HTTP 200 and NO session", $c1Pass, "HTTP status {$resLanding['status']} or missing content");

// =========================================================================
// Case 2: Careers page shows the seeded job openings
// =========================================================================
$resCareers = executeScript('careers.php', [], [], [], null);
$c2Pass = ($resCareers['status'] === 200)
    && (strpos($resCareers['body'], 'Head Barista') !== false)
    && (strpos($resCareers['body'], 'Barista') !== false)
    && (strpos($resCareers['body'], 'Branch Accountant') !== false)
    && (strpos($resCareers['body'], 'applicant_form.php') !== false);
assertTest("Case 2: Careers page shows the seeded job openings", $c2Pass, "HTTP status {$resCareers['status']} or openings not rendered");

// =========================================================================
// Case 3: Applicant form creates a record with status NEW and saves the resume
// =========================================================================
$validPdfPath = sys_get_temp_dir() . '/applicant_resume_' . uniqid() . '.pdf';
file_put_contents($validPdfPath, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R/Resources<<>>>>endobj\nxref\n0 4\n0000000000 65535 f\n0000000010 00000 n\n0000000053 00000 n\n0000000102 00000 n\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n180\n%%EOF");

$uniqueEmail = 'alex.reyes_' . uniqid() . '@example.com';
$postData = [
    'job_opening_id' => 1,
    'first_name' => 'Alexander',
    'last_name' => 'Reyes',
    'email' => $uniqueEmail,
    'phone' => '+639171234567'
];
$filesData = [
    'resume' => [
        'name' => 'alex_reyes_resume.pdf',
        'type' => 'application/pdf',
        'tmp_name' => $validPdfPath,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($validPdfPath)
    ]
];
$serverData = [
    'REQUEST_METHOD' => 'POST',
    'HTTP_ACCEPT' => 'application/json',
    'REMOTE_ADDR' => '198.51.100.' . rand(1, 254)
];

$resApp = executeScript('applicant_form.php', $serverData, $postData, $filesData, null);
if (file_exists($validPdfPath)) {
    @unlink($validPdfPath);
}

// Query DB to verify applicant record and file existence
global $pdo;
$chkStmt = $pdo->prepare("SELECT * FROM applicants WHERE email = ? ORDER BY id DESC LIMIT 1");
$chkStmt->execute([$uniqueEmail]);
$newApplicant = $chkStmt->fetch();

$savedFile = $newApplicant ? ($newApplicant['resume_path'] ?: $newApplicant['resume']) : null;
$storageFileExists = $savedFile && (
    file_exists(APP_ROOT . '/storage/resumes/' . $savedFile) ||
    file_exists(APP_ROOT . '/app/storage/resumes/' . $savedFile)
);

$c3Pass = ($newApplicant !== false)
    && ($newApplicant['status'] === 'NEW')
    && ($newApplicant['first_name'] === 'Alexander')
    && ($newApplicant['position'] === 'Head Barista')
    && $storageFileExists;

assertTest("Case 3: Applicant form creates a record with status NEW and saves the resume", $c3Pass, "Record not created or resume not saved on storage");

// =========================================================================
// Case 4: A PHP file renamed to .pdf is rejected by the upload handler,
//         AND fika_hrms_resume_serve.php returns 401 without session,
//         and 403 if user lacks hr.employee.view.
// =========================================================================
$fakePdfPath = sys_get_temp_dir() . '/exploit_' . uniqid() . '.pdf';
file_put_contents($fakePdfPath, "<?php phpinfo(); ?>");
$fakeUploadInfo = [
    'name' => 'exploit.pdf',
    'type' => 'application/pdf',
    'tmp_name' => $fakePdfPath,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($fakePdfPath)
];
$targetStorage = APP_ROOT . '/storage/resumes';
$fakeUploadResult = Upload::handle($fakeUploadInfo, $targetStorage);
if (file_exists($fakePdfPath)) {
    @unlink($fakePdfPath);
}
$part4aRejected = ($fakeUploadResult === null);

// 4b: Endpoint returns 401 without session
$appId = $newApplicant ? (int)$newApplicant['id'] : 1;
$resServeUnauth = executeScript('fika_hrms/api/fika_hrms_resume_serve.php', [
    'REQUEST_METHOD' => 'GET',
    'QUERY_STRING' => 'id=' . $appId
], [], [], null);
$part4b401 = ($resServeUnauth['status'] === 401);

// 4c: Endpoint returns 403 if user lacks hr.employee.view (e.g. role STAFF: role_id = 7)
$staffSession = [
    'user' => [
        'id' => 999,
        'role_id' => 7, // STAFF
        'employee_id' => null,
        'branch_id' => 1
    ]
];
$resServeForbidden = executeScript('fika_hrms/api/fika_hrms_resume_serve.php', [
    'REQUEST_METHOD' => 'GET',
    'QUERY_STRING' => 'id=' . $appId
], [], [], $staffSession);
$part4c403 = ($resServeForbidden['status'] === 403);

$c4Pass = $part4aRejected && $part4b401 && $part4c403;
assertTest(
    "Case 4: A PHP file renamed to .pdf is rejected by the upload handler",
    $c4Pass,
    "Rejected: " . ($part4aRejected ? 'YES' : 'NO') . ", Unauth 401: {$resServeUnauth['status']}, Forbidden 403: {$resServeForbidden['status']}"
);

// =========================================================================
// Case 5: HRMS Applicants page requires login (401 if unauthenticated)
// =========================================================================
$resApplicantsUnauth = executeScript('fika_hrms/fika_hrms_applicants.php', [], [], [], null);
$c5Pass = ($resApplicantsUnauth['status'] === 401);
assertTest("Case 5: HRMS Applicants page requires login (401 if unauthenticated)", $c5Pass, "Returned status {$resApplicantsUnauth['status']} instead of 401");

echo "\nTests Completed. Total Failures: {$failed}\n";
exit($failed > 0 ? 1 : 0);
