<?php
// tools/list_deploy_files.php — CLI utility to inspect files deployable under the FTP whitelist

if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    exit;
}

$repoRoot = dirname(__DIR__);
chdir($repoRoot);

$rawFiles = shell_exec('git ls-files');
if ($rawFiles === null) {
    fwrite(STDERR, "Error: Failed to execute git ls-files\n");
    exit(1);
}

$gitFiles = array_filter(explode("\n", trim($rawFiles)), fn($line) => trim($line) !== '');

// Whitelist include patterns
$includePatterns = [
    'cafe_hrms/**',
    'cafe_pos/**',
    'config/**',
    'assets/**',
    'index.php',
    'login.php',
    '.htaccess',
    'uploads/menu/.htaccess',
];

// Re-exclude patterns
$excludePatterns = [
    'config/config.php',
    'config/config.example.php',
    'cafe_hrms/config.php',
    'cafe_pos/tests/**',
    '*.md',
    '**/*.md',
    '*.sql',
    '**/*.sql',
    '*.txt',
    '**/*.txt',
    '.env*',
    '**/.env*',
    '**/fixtures/**',
];

function matchesPattern(string $pattern, string $path): bool {
    // 1. Exact or fnmatch on relative path
    if ($pattern === $path || fnmatch($pattern, $path)) {
        return true;
    }
    // 2. fnmatch on basename for extension / filename globs
    if (fnmatch($pattern, basename($path))) {
        return true;
    }
    // 3. Handle leading **/ wildcard if fnmatch doesn't match root level
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
