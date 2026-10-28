<?php
/**
 * tools/demo_seed/phase_0.php
 *
 * Phase 0: Seeding Framework Verification Harness
 * Inserts exactly ONE harmless tracked row into safe lookup table `contribution_rates`
 * to prove the framework lifecycle (dry-run, apply, idempotency, and reset).
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

global $pdo;

echo "Seeding Phase 0: Framework Verification...\n";

$testRow = [
    'type'           => 'SSS',
    'lower_bound'    => 999990.00,
    'upper_bound'    => 999999.00,
    'employee_share' => 500.00,
    'employer_share' => 1000.00,
];

$naturalKey = [
    'type'        => 'SSS',
    'lower_bound' => 999990.00,
    'upper_bound' => 999999.00,
];

demo_insert($pdo, 'contribution_rates', $testRow, $naturalKey);
