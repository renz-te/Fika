<?php
/**
 * tools/demo_seed/common.php
 *
 * Core engine for demo data seeding framework:
 * - CLI and local-host guards
 * - Argument parsing (--apply, --reset-demo, --phase=N)
 * - Prepared statement demo_insert with natural key deduplication
 * - Foreign-key safe demo_reset engine with FOREIGN_KEY_CHECKS toggling
 * - Execution metrics tracking
 */

// 1. CLI Guard
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    fwrite(STDERR, "ERROR: Demo seed tools can only be run from the command line (CLI).\n");
    exit(1);
}

// Global runtime state
$CURRENT_DEMO_PHASE = null;
$DEMO_APPLY = false;
$DEMO_STATS = [
    'created'      => 0,
    'would_create' => 0,
    'skipped'      => 0,
    'deleted'      => 0,
    'by_table'     => []
];

/**
 * Check if the target database host is localhost or 127.0.0.1.
 */
function check_local_host(?string $host = null, ?PDO $pdo = null): bool {
    if ($host === null) {
        $host = function_exists('config') ? config('db.host', '127.0.0.1') : '127.0.0.1';
    }
    $normalized = strtolower(trim($host));
    $allowed = ['127.0.0.1', 'localhost', '::1'];
    return in_array($normalized, $allowed, true);
}

/**
 * Assert that the database host is local. Halts execution immediately if not.
 */
function assert_local_host(?string $host = null, ?PDO $pdo = null): void {
    if (!check_local_host($host, $pdo)) {
        $displayHost = $host ?? 'unknown';
        fwrite(STDERR, "FATAL ERROR: Non-local database host detected ('{$displayHost}').\n");
        fwrite(STDERR, "Demo seed scripts are strictly prohibited from running against non-localhost databases.\n");
        exit(1);
    }
}

/**
 * Parse CLI arguments for seeding and resetting.
 * Supported options:
 *   --apply          : Actually execute write/delete operations (defaults to dry-run)
 *   --reset-demo     : Trigger demo deletion mode
 *   --phase=N        : Target phase identifier (e.g. 0, 1, 2)
 *   --phase N        : Target phase identifier (separate arg)
 */
function parse_demo_args(?array $argv = null): array {
    if ($argv === null) {
        global $argv;
    }
    $args = [
        'apply'      => false,
        'reset_demo' => false,
        'phase'      => null,
    ];

    if (!is_array($argv)) {
        return $args;
    }

    $count = count($argv);
    for ($i = 1; $i < $count; $i++) {
        $arg = trim($argv[$i]);
        if ($arg === '--apply') {
            $args['apply'] = true;
        } elseif ($arg === '--reset-demo') {
            $args['reset_demo'] = true;
        } elseif (strpos($arg, '--phase=') === 0) {
            $args['phase'] = trim(substr($arg, 8));
        } elseif ($arg === '--phase' && isset($argv[$i + 1])) {
            $args['phase'] = trim($argv[++$i]);
        }
    }

    return $args;
}

/**
 * Normalizes a phase argument into a standard string key (e.g. '0' -> 'phase_0', 'phase_0' -> 'phase_0').
 */
function normalize_phase_name(?string $phase): ?string {
    if ($phase === null || $phase === '' || strtolower($phase) === 'all') {
        return null;
    }
    if (is_numeric($phase)) {
        return 'phase_' . $phase;
    }
    return $phase;
}

/**
 * Reset execution stats counters.
 */
function demo_stats_reset(): void {
    global $DEMO_STATS;
    $DEMO_STATS = [
        'created'      => 0,
        'would_create' => 0,
        'skipped'      => 0,
        'deleted'      => 0,
        'by_table'     => []
    ];
}

/**
 * Record action in stats tracker.
 */
function demo_stats_record(string $table, string $status, int $count = 1): void {
    global $DEMO_STATS;
    if (isset($DEMO_STATS[$status])) {
        $DEMO_STATS[$status] += $count;
    }
    if (!isset($DEMO_STATS['by_table'][$table])) {
        $DEMO_STATS['by_table'][$table] = ['created' => 0, 'would_create' => 0, 'skipped' => 0, 'deleted' => 0];
    }
    if (isset($DEMO_STATS['by_table'][$table][$status])) {
        $DEMO_STATS['by_table'][$table][$status] += $count;
    }
}

/**
 * Inserts a tracked demo record into $table if not already present by $naturalKey.
 *
 * @param PDO $pdo
 * @param string $table
 * @param array $row Associative array of column => value to insert
 * @param array|string $naturalKey Associative array of col => val, or list of column names
 * @param string|null $phase Phase identifier (defaults to global $CURRENT_DEMO_PHASE)
 * @param bool|null $apply Apply flag (defaults to global $DEMO_APPLY)
 * @return array ['status' => 'created'|'skipped'|'would_create', 'id' => int|null, 'row' => array]
 */
function demo_insert(PDO $pdo, string $table, array $row, $naturalKey, ?string $phase = null, ?bool $apply = null): array {
    global $CURRENT_DEMO_PHASE, $DEMO_APPLY;

    $activePhase = $phase ?? ($CURRENT_DEMO_PHASE ?? 'phase_0');
    $isApply = $apply ?? ($DEMO_APPLY ?? false);

    // 1. Build Natural Key Condition
    $keyPairs = [];
    if (is_string($naturalKey)) {
        $keyPairs[$naturalKey] = $row[$naturalKey] ?? null;
    } elseif (is_array($naturalKey)) {
        $isAssoc = array_keys($naturalKey) !== range(0, count($naturalKey) - 1);
        if ($isAssoc) {
            $keyPairs = $naturalKey;
        } else {
            foreach ($naturalKey as $col) {
                $keyPairs[$col] = $row[$col] ?? null;
            }
        }
    } else {
        throw new InvalidArgumentException("Invalid natural key specification for table {$table}.");
    }

    if (empty($keyPairs)) {
        throw new InvalidArgumentException("Empty natural key given for table {$table}.");
    }

    $where = [];
    $params = [];
    foreach ($keyPairs as $col => $val) {
        if ($val === null) {
            $where[] = "`{$col}` IS NULL";
        } else {
            $where[] = "`{$col}` = ?";
            $params[] = $val;
        }
    }

    // 2. Check if row already exists
    $checkSql = "SELECT * FROM `{$table}` WHERE " . implode(' AND ', $where) . " LIMIT 1";
    $stmt = $pdo->prepare($checkSql);
    $stmt->execute($params);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        demo_stats_record($table, 'skipped');
        $existingId = $existing['id'] ?? null;
        echo "  [SKIPPED] {$table}: record already exists (ID: " . ($existingId !== null ? "#{$existingId}" : "N/A") . ")\n";
        return ['status' => 'skipped', 'id' => $existingId ? (int)$existingId : null, 'row' => $existing];
    }

    // 3. Not found: Dry-run check
    if (!$isApply) {
        demo_stats_record($table, 'would_create');
        echo "  [WOULD CREATE] {$table}: 1 row would be inserted (DRY-RUN)\n";
        return ['status' => 'would_create', 'id' => null, 'row' => $row];
    }

    // 4. Apply mode: Insert row
    $columns = array_keys($row);
    $escapedCols = array_map(fn($c) => "`{$c}`", $columns);
    $placeholders = array_fill(0, count($columns), '?');

    $insertSql = "INSERT INTO `{$table}` (" . implode(', ', $escapedCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $insertStmt = $pdo->prepare($insertSql);
    $insertStmt->execute(array_values($row));

    $newId = (int)$pdo->lastInsertId();
    if ($newId === 0 && isset($row['id'])) {
        $newId = (int)$row['id'];
    }

    // 5. Track in demo_seed table
    $trackStmt = $pdo->prepare("
        INSERT INTO `demo_seed` (`phase`, `table_name`, `row_id`, `created_at`) 
        VALUES (?, ?, ?, NOW()) 
        ON DUPLICATE KEY UPDATE `phase` = VALUES(`phase`)
    ");
    $trackStmt->execute([$activePhase, $table, $newId]);

    demo_stats_record($table, 'created');
    echo "  [CREATED] {$table}: inserted row ID #{$newId} (phase: {$activePhase})\n";
    return ['status' => 'created', 'id' => $newId, 'row' => $row];
}

/**
 * Resets demo data recorded in demo_seed for the specified phase (or all phases).
 * Strictly wraps deletions in SET FOREIGN_KEY_CHECKS=0 / SET FOREIGN_KEY_CHECKS=1
 * and processes rows in ID DESC order (reverse of insertion).
 *
 * @param PDO $pdo
 * @param string|null $phase
 * @param bool $apply
 * @return array
 */
function demo_reset(PDO $pdo, ?string $phase = null, bool $apply = false): array {
    $normalizedPhase = normalize_phase_name($phase);

    // 1. Fetch tracked rows
    if ($normalizedPhase !== null) {
        $stmt = $pdo->prepare("SELECT id, phase, table_name, row_id FROM `demo_seed` WHERE phase = ? ORDER BY id DESC");
        $stmt->execute([$normalizedPhase]);
    } else {
        $stmt = $pdo->query("SELECT id, phase, table_name, row_id FROM `demo_seed` ORDER BY id DESC");
    }
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalTracked = count($records);
    $phaseLabel = $normalizedPhase ?? 'ALL PHASES';

    echo "====================================================================\n";
    echo "Demo Reset: {$phaseLabel} (" . ($apply ? "APPLY" : "DRY-RUN") . ")\n";
    echo "====================================================================\n";
    echo "Found {$totalTracked} tracked demo record(s) in demo_seed.\n\n";

    if ($totalTracked === 0) {
        echo "No demo records found to reset.\n";
        return ['db_deleted' => 0, 'seed_deleted' => 0, 'by_table' => []];
    }

    // Count per table for summary
    $byTable = [];
    foreach ($records as $r) {
        $t = $r['table_name'];
        $byTable[$t] = ($byTable[$t] ?? 0) + 1;
    }

    if (!$apply) {
        echo "[DRY-RUN] The following records WOULD be deleted:\n";
        foreach ($byTable as $tbl => $cnt) {
            echo "  - {$tbl}: {$cnt} row(s)\n";
        }
        echo "\nNo changes were made to the database. Re-run with --apply to execute deletion.\n";
        return ['db_deleted' => 0, 'seed_deleted' => 0, 'by_table' => $byTable];
    }

    // Apply deletion with FOREIGN_KEY_CHECKS toggling
    $dbDeleted = 0;
    $seedDeleted = 0;

    $pdo->beginTransaction();
    try {
        echo "Disabling foreign key checks (SET FOREIGN_KEY_CHECKS=0)...\n";
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0;");

        $delSeedStmt = $pdo->prepare("DELETE FROM `demo_seed` WHERE id = ?");

        foreach ($records as $rec) {
            $seedId = (int)$rec['id'];
            $table = $rec['table_name'];
            $rowId = (int)$rec['row_id'];

            // Delete target table row
            $delTableStmt = $pdo->prepare("DELETE FROM `{$table}` WHERE id = ?");
            $delTableStmt->execute([$rowId]);
            $affected = $delTableStmt->rowCount();
            $dbDeleted += $affected;

            // Delete demo_seed tracking row
            $delSeedStmt->execute([$seedId]);
            $seedDeleted += $delSeedStmt->rowCount();

            echo "  [DELETED] {$table} #{$rowId} (demo_seed #{$seedId})\n";
        }

        $pdo->commit();
        echo "Deletion committed successfully.\n";
    } catch (\Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, "ERROR: Reset failed: " . $e->getMessage() . "\n");
        throw $e;
    } finally {
        echo "Re-enabling foreign key checks (SET FOREIGN_KEY_CHECKS=1)...\n";
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
    }

    echo "\nReset Summary:\n";
    echo "  - DB Rows Deleted: {$dbDeleted}\n";
    echo "  - Tracking Records Deleted: {$seedDeleted}\n";
    echo "  - Status: Completed successfully\n\n";

    return ['db_deleted' => $dbDeleted, 'seed_deleted' => $seedDeleted, 'by_table' => $byTable];
}
