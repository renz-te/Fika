<?php
/**
 * tools/seed_demo.php
 *
 * Master CLI runner for Fika demo data seeding.
 *
 * Usage:
 *   php tools/seed_demo.php --phase=0             # Dry run Phase 0
 *   php tools/seed_demo.php --phase=0 --apply     # Execute Phase 0 seeding
 *   php tools/seed_demo.php --reset-demo --phase=0 --apply  # Reset Phase 0 demo data
 *   php tools/seed_demo.php --reset-demo --apply  # Reset all demo phases
 */

require_once __DIR__ . '/demo_seed/common.php';
require_once __DIR__ . '/../app/bootstrap.php';

global $pdo;

// Enforce local database connection
$configuredHost = config('db.host', '127.0.0.1');
assert_local_host($configuredHost, $pdo);

$args = parse_demo_args($argv);

// Handle Reset Mode
if ($args['reset_demo']) {
    demo_reset($pdo, $args['phase'], $args['apply']);
    exit(0);
}

// Require Phase Argument for Seeding
if ($args['phase'] === null) {
    echo "====================================================================\n";
    echo "Fika Café — Demo Data Seeding Framework\n";
    echo "====================================================================\n";
    echo "Usage:\n";
    echo "  php tools/seed_demo.php --phase=N            # Dry run for Phase N\n";
    echo "  php tools/seed_demo.php --phase=N --apply    # Apply seeding for Phase N\n";
    echo "  php tools/seed_demo.php --reset-demo --phase=N --apply # Reset Phase N\n\n";
    echo "Available phases:\n";
    $phaseFiles = glob(__DIR__ . '/demo_seed/phase_*.php');
    sort($phaseFiles);
    foreach ($phaseFiles as $pf) {
        $pName = basename($pf, '.php');
        echo "  - {$pName}\n";
    }
    echo "\nPlease specify a target phase (e.g. --phase=0).\n";
    exit(0);
}

$normalizedPhase = normalize_phase_name($args['phase']);
$phaseFile = __DIR__ . "/demo_seed/{$normalizedPhase}.php";

if (!file_exists($phaseFile)) {
    // Try raw phase argument if custom naming
    $phaseFile = __DIR__ . "/demo_seed/" . basename($args['phase']) . ".php";
}

if (!file_exists($phaseFile)) {
    fwrite(STDERR, "ERROR: Phase file not found: 'tools/demo_seed/{$normalizedPhase}.php'\n");
    exit(1);
}

// Set runtime state
$CURRENT_DEMO_PHASE = $normalizedPhase;
$DEMO_APPLY = $args['apply'];
demo_stats_reset();

$modeLabel = $DEMO_APPLY ? "APPLY (WRITES ENABLED)" : "DRY RUN (NO WRITES)";

echo "====================================================================\n";
echo "Fika Café — Demo Seeding Runner\n";
echo "Phase: {$normalizedPhase} | Mode: {$modeLabel}\n";
echo "Database: " . config('db.name') . " @ " . $configuredHost . "\n";
echo "====================================================================\n\n";

// Execute the phase script
require $phaseFile;

// Print Execution Summary
global $DEMO_STATS;
echo "\n--------------------------------------------------------------------\n";
echo "Execution Summary for {$normalizedPhase}:\n";
echo "--------------------------------------------------------------------\n";
if ($DEMO_APPLY) {
    echo "  Rows Created : {$DEMO_STATS['created']}\n";
    echo "  Rows Skipped : {$DEMO_STATS['skipped']}\n";
} else {
    echo "  Would Create : {$DEMO_STATS['would_create']}\n";
    echo "  Rows Skipped : {$DEMO_STATS['skipped']}\n";
    echo "\n  (Note: DRY RUN mode. No data written to database. Add --apply to write.)\n";
}
echo "--------------------------------------------------------------------\n";

if ($DEMO_APPLY && $DEMO_STATS['created'] > 0) {
    echo "SUCCESS: Seeding completed successfully.\n";
} elseif ($DEMO_APPLY && $DEMO_STATS['created'] === 0) {
    echo "IDEMPOTENT: 0 rows created. All records already present.\n";
} else {
    echo "DRY RUN COMPLETE: Validation finished.\n";
}
echo "\n";
