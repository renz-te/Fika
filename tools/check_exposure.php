<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    exit;
}

$rootDir = dirname(__DIR__);
$failures = [];

echo "====================================================================\n";
echo "Exposure & Security Posture Verification Check\n";
echo "Root Directory: {$rootDir}\n";
echo "====================================================================\n\n";

// 1. Check: .env must not be tracked by git
$gitLsOutput = [];
$gitStatus = 0;
exec('git ls-files', $gitLsOutput, $gitStatus);

if ($gitStatus !== 0) {
    $failures[] = "Execution of 'git ls-files' failed with exit code {$gitStatus}.";
    echo "[FAIL] Check 1: Unable to inspect git tracked files.\n";
} else {
    $envTracked = false;
    foreach ($gitLsOutput as $file) {
        $normalized = trim(str_replace('\\', '/', $file));
        if ($normalized === '.env' || str_ends_with($normalized, '/.env') || (str_starts_with($normalized, '.env') && $normalized !== '.env.example')) {
            $envTracked = true;
            $failures[] = "Git is tracking sensitive environment file: {$file}";
            break;
        }
    }
    if (!$envTracked) {
        echo "[PASS] Check 1: .env is not tracked by git.\n";
    } else {
        echo "[FAIL] Check 1: .env is tracked by git.\n";
    }
}

// 2. Check: Root .htaccess must exist and contain deny rule for .env
$htaccessPath = $rootDir . '/.htaccess';
if (!file_exists($htaccessPath)) {
    $failures[] = "Root .htaccess file is missing.";
    echo "[FAIL] Check 2: Root .htaccess file not found.\n";
} else {
    $htaccessContent = (string)file_get_contents($htaccessPath);
    $hasEnvDeny = (bool)preg_match('/<FilesMatch\s+["\'][^"\']*(\.env|\\\.env)[^"\']*["\']>\s*[\r\n\s]*Require\s+all\s+denied/i', $htaccessContent)
               || (str_contains($htaccessContent, '.env') && str_contains($htaccessContent, 'Require all denied'));

    if ($hasEnvDeny) {
        echo "[PASS] Check 2: Root .htaccess contains deny rule for .env.\n";
    } else {
        $failures[] = "Root .htaccess does not contain an effective deny rule for .env.";
        echo "[FAIL] Check 2: Root .htaccess missing deny rule for .env.\n";
    }
}

// 3. Check: No real-looking secret pattern appears in any tracked file
// Pattern: (sk_live_|sk_test_|pk_live_|pk_test_|whk_|whsk_) followed by 15+ characters
$prefixParts = [
    'sk_' . 'live_',
    'sk_' . 'test_',
    'pk_' . 'live_',
    'pk_' . 'test_',
    'whk_',
    'whsk_'
];
$escapedPrefixes = array_map(fn($p) => preg_quote($p, '/'), $prefixParts);
$secretRegex = '/(?:' . implode('|', $escapedPrefixes) . ')[a-zA-Z0-9_\-]{15,}/';

$exposedFiles = [];
if (!empty($gitLsOutput)) {
    foreach ($gitLsOutput as $file) {
        $normalized = trim(str_replace('\\', '/', $file));
        // Skip check_exposure.php itself
        if ($normalized === 'tools/check_exposure.php') {
            continue;
        }

        $filePath = $rootDir . '/' . $normalized;
        if (!file_exists($filePath) || is_dir($filePath)) {
            continue;
        }

        $content = file_get_contents($filePath);
        if ($content !== false && preg_match($secretRegex, $content, $matches)) {
            $maskedSecret = substr($matches[0], 0, 7) . '...' . substr($matches[0], -4);
            $exposedFiles[] = "{$normalized} (matches pattern: {$maskedSecret})";
        }
    }
}

if (empty($exposedFiles)) {
    echo "[PASS] Check 3: No real-looking secrets (15+ chars) found in tracked files.\n";
} else {
    echo "[FAIL] Check 3: Real-looking secret patterns detected in tracked files:\n";
    foreach ($exposedFiles as $ef) {
        echo "       - {$ef}\n";
        $failures[] = "Secret pattern found in {$ef}";
    }
}

echo "\n--------------------------------------------------------------------\n";
if (!empty($failures)) {
    echo "Summary: " . count($failures) . " check(s) FAILED.\n";
    exit(1);
} else {
    echo "Summary: All exposure checks PASSED.\n";
    exit(0);
}
