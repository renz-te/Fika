<?php
// tools/stress_orders.php
if (php_sapi_name() !== 'cli') {
    die("Must be run from CLI.");
}

echo "Starting stress test: 20 parallel order creations...\n";

$workers = [];
$phpBin = $_SERVER['_'] ?? 'php'; // Fallback, but we will pass it explicitly if possible
if (isset($argv[1])) {
    $phpBin = $argv[1];
}

for ($i = 0; $i < 20; $i++) {
    $descriptorSpec = [
        1 => ['pipe', 'w'], // stdout
        2 => ['pipe', 'w']  // stderr
    ];
    $process = proc_open("$phpBin " . __DIR__ . '/worker_order.php', $descriptorSpec, $pipes);
    if (is_resource($process)) {
        $workers[] = [
            'process' => $process,
            'pipes' => $pipes
        ];
    }
}

$results = [];
foreach ($workers as $worker) {
    $stdout = stream_get_contents($worker['pipes'][1]);
    $stderr = stream_get_contents($worker['pipes'][2]);
    fclose($worker['pipes'][1]);
    fclose($worker['pipes'][2]);
    proc_close($worker['process']);
    
    $out = trim($stdout . $stderr);
    if (!empty($out)) {
        $results[] = $out;
    }
}

// Check for duplicates
$unique = array_unique($results);
echo "Total orders created: " . count($results) . "\n";
echo "Unique order numbers generated: " . count($unique) . "\n";

if (count($results) !== count($unique)) {
    echo "FAILED: Duplicate order numbers were generated!\n";
    print_r(array_diff_assoc($results, $unique));
    exit(1);
} else {
    echo "SUCCESS: No duplicates found. FOR UPDATE locks worked perfectly.\n";
    // Print a few samples
    echo "Samples: " . implode(", ", array_slice($results, 0, 5)) . "...\n";
}
