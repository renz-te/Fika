<?php
/**
 * tools/list_deploy_files.php
 *
 * CLI inspection and verification utility for the rebuild/core FTP deployment whitelist.
 * Mirrors the exact inclusion and exclusion rules defined in .github/workflows/ftp-deploy.yml.
 *
 * Usage:
 *   php tools/list_deploy_files.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    exit(1);
}

$repoRoot = dirname(__DIR__);
chdir($repoRoot);

$rawFiles = shell_exec('git ls-files');
if ($rawFiles === null) {
    fwrite(STDERR, "Error: Failed to execute git ls-files\n");
    exit(1);
}

$gitFiles = array_filter(explode("\n", trim($rawFiles)), fn($line) => trim($line) !== '');

// Whitelist include patterns (rebuild/core structure)
$includePatterns = [
    'app/**',
    'fika_hrms/**',
    'fika_pos/**',
    'fika_finance/**',
    'fika_inventory/**',
    'public/**',
    'index.php',
    'login.php',
    'careers.php',
    'applicant_form.php',
    'config/permissions.php',
    'config/paymongo.php',
    'uploads/.htaccess',
    'uploads/menu/.htaccess',
    'storage/.htaccess',
    '.htaccess',
    '**/.htaccess',
];

// Re-exclude patterns (safety overrides: secrets, tests, legacy folders, docs, db dumps)
$excludePatterns = [
    'config/config.php',
    'config/config.example.php',
    '.env*',
    '**/.env*',
    'tests/**',
    '**/tests/**',
    'tools/**',
    '**/tools/**',
    'migrations/**',
    '**/migrations/**',
    'docs/**',
    '**/docs/**',
    '*.md',
    '**/*.md',
    '*.sql',
    '**/*.sql',
    '*.txt',
    '**/*.txt',
    '*_fix.php',
    '**/*_fix.php',
    '*_report.md',
    '**/*_report.md',
    'temp_*',
    '**/temp_*',
    'cafe_hrms/**',
    'cafe_pos/**',
];

function matchesPattern(string $pattern, string $path): bool {
    if ($pattern === $path || fnmatch($pattern, $path)) {
        return true;
    }
    if (fnmatch($pattern, basename($path))) {
        return true;
    }
    if (str_starts_with($pattern, '**/')) {
        $sub = substr($pattern, 3);
        if (fnmatch($sub, $path) || fnmatch($sub, basename($path))) {
            return true;
        }
    }
    return false;
}

$deployFiles = [];
foreach ($gitFiles as $file) {
    $normalized = str_replace('\\', '/', trim($file));

    // Must match at least one include pattern
    $isIncluded = false;
    foreach ($includePatterns as $pattern) {
        if (matchesPattern($pattern, $normalized)) {
            $isIncluded = true;
            break;
        }
    }
    if (!$isIncluded) {
        continue;
    }

    // Must not match any re-exclude pattern
    $isExcluded = false;
    foreach ($excludePatterns as $pattern) {
        if (matchesPattern($pattern, $normalized)) {
            $isExcluded = true;
            break;
        }
    }
    if ($isExcluded) {
        continue;
    }

    $deployFiles[] = $normalized;
}

sort($deployFiles);

foreach ($deployFiles as $file) {
    echo $file . "\n";
}

echo "--------------------------------------------------------------------\n";
echo "Total deployable files: " . count($deployFiles) . "\n";

// ====================================================================
// Integrity & Safety Verification Tests
// ====================================================================

// Test 1: config/paymongo.php must be in deploy whitelist
if (!in_array('config/paymongo.php', $deployFiles, true)) {
    fwrite(STDERR, "CRITICAL: config/paymongo.php missing from deploy whitelist\n");
    exit(1);
}

// Test 2: config/permissions.php must be in deploy whitelist
if (!in_array('config/permissions.php', $deployFiles, true)) {
    fwrite(STDERR, "CRITICAL: config/permissions.php missing from deploy whitelist\n");
    exit(1);
}

// Test 3: config/config.php must NEVER be in deploy whitelist
if (in_array('config/config.php', $deployFiles, true)) {
    fwrite(STDERR, "CRITICAL: config/config.php found in deploy whitelist!\n");
    exit(1);
}

// Test 4: .env must NEVER be in deploy whitelist
foreach ($deployFiles as $file) {
    if (str_contains($file, '.env')) {
        fwrite(STDERR, "CRITICAL: Environment file {$file} found in deploy whitelist!\n");
        exit(1);
    }
}
