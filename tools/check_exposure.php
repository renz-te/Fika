<?php
if (php_sapi_name() === 'cli') {
    // CLI mode: We check if the root has any exposed PHP files.
    // The prompt says: "git ls-files '*.php' shows no PHP at the repo root except intended entry pages"
    $files = glob("*.php");
    $exposed = [];
    foreach ($files as $f) {
        if (!in_array($f, ['index.php'])) {
            $exposed[] = $f;
        }
    }
    if (count($exposed) > 0) {
        echo "[FAIL] Root PHP files exposed: " . implode(', ', $exposed) . "\n";
        exit(1);
    }
    
    // Also check that the deploy whitelist exists
    $yaml = @file_get_contents('.github/workflows/ftp-deploy.yml');
    if ($yaml && strpos($yaml, '!fika_hrms/**') !== false && strpos($yaml, 'exclude:') !== false) {
        echo "[PASS] Workflow uses whitelist deploy.\n";
    } else {
        echo "[FAIL] Workflow does not use whitelist deploy.\n";
        exit(1);
    }
    
    echo "[PASS] Exposure check completed.\n";
    exit(0);
}

// Security check file
// If this file can be executed from a web browser, it means the `tools/` directory 
// (and likely other core directories) are improperly exposed to the public web.
http_response_code(403);
header('Content-Type: application/json');
echo json_encode([
    'status' => 'vulnerable',
    'message' => 'CRITICAL SECURITY ALERT: Core directories are publicly accessible.'
]);
exit;
