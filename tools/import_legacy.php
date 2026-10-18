<?php
/**
 * tools/import_legacy.php
 *
 * Production-grade CLI ETL tool for migrating legacy Fika HRMS & POS data
 * into the modern rebuild/core architecture according to docs/DATA_MAPPING.md.
 *
 * Usage:
 *   php tools/import_legacy.php          (Dry run mode - no changes made)
 *   php tools/import_legacy.php --apply  (Apply migration to database)
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    exit;
}

ini_set('memory_limit', '512M');

require_once __DIR__ . '/../app/bootstrap.php';

$isDryRun = !in_array('--apply', $argv, true);

$legacyHost   = getenv('LEGACY_DB_HOST') ?: '127.0.0.1';
$legacyUser   = getenv('LEGACY_DB_USER') ?: 'root';
$legacyPass   = getenv('LEGACY_DB_PASS') !== false ? getenv('LEGACY_DB_PASS') : '';
$legacyHrmsDb = getenv('LEGACY_HRMS_DB') ?: (getenv('LEGACY_DB_NAME') ?: 'hrms');
$legacyPosDb  = getenv('LEGACY_POS_DB')  ?: (getenv('LEGACY_DB_NAME') ?: 'cafe_pos');

echo "====================================================================\n";
echo "Fika Café — Legacy Data Import Pipeline (Phase 17C)\n";
echo "====================================================================\n";
echo "Mode:         " . ($isDryRun ? "DRY RUN (read-only, no database modifications)" : "APPLY (writing to database)") . "\n";
echo "Host:         {$legacyHost}\n";
echo "HRMS DB:      {$legacyHrmsDb}\n";
echo "POS DB:       {$legacyPosDb}\n";
echo "Target DB:    " . config('db.name', 'fika_scratch') . " on " . config('db.host', '127.0.0.1') . "\n";
echo "====================================================================\n\n";

try {
    $dsnHrms = "mysql:host={$legacyHost};dbname={$legacyHrmsDb};charset=utf8mb4";
    $hrmsPdo = new PDO($dsnHrms, $legacyUser, $legacyPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $dsnPos = "mysql:host={$legacyHost};dbname={$legacyPosDb};charset=utf8mb4";
    $posPdo = new PDO($dsnPos, $legacyUser, $legacyPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (\PDOException $e) {
    echo "[FATAL] Cannot connect to legacy database: " . $e->getMessage() . "\n";
    exit(1);
}

// Target database connection ($pdo is from app/bootstrap.php)
$targetPdo = $pdo;

// Resolve default system admin user ID for mandatory administrative FKs
$adminUserId = (int)($targetPdo->query("SELECT id FROM `users` ORDER BY `id` ASC LIMIT 1")->fetchColumn() ?: 1);

/**
 * Cache and resolution helper for foreign keys
 */
class LegacyResolver {
    private static array $cache = [];
    private static array $planned = [];

    public static function registerPlanned(string $table, int $legacyId): void {
        self::$planned[$table][$legacyId] = 900000 + $legacyId;
    }

    public static function resolve(PDO $targetPdo, string $table, ?int $legacyId, bool $isDryRun = false): ?int {
        if ($legacyId === null || $legacyId <= 0) {
            return null;
        }
        if (isset(self::$cache[$table][$legacyId])) {
            return self::$cache[$table][$legacyId];
        }
        $stmt = $targetPdo->prepare("SELECT id FROM `{$table}` WHERE legacy_id = ? LIMIT 1");
        $stmt->execute([$legacyId]);
        $val = $stmt->fetchColumn();
        if ($val !== false && $val !== null) {
            self::$cache[$table][$legacyId] = (int)$val;
            return (int)$val;
        }
        if ($isDryRun && isset(self::$planned[$table][$legacyId])) {
            return self::$planned[$table][$legacyId];
        }
        return null;
    }

    public static function mapRole(string|int $legacyRole): int {
        if (is_numeric($legacyRole)) {
            $num = (int)$legacyRole;
            if ($num === 1) return 1; // ADMIN
            if ($num === 2) return 2; // CHR
            if ($num === 3) return 3; // GA
            if ($num === 4) return 4; // BA
            if ($num === 5) return 5; // BHR
            if ($num === 6) return 6; // BM
            if ($num >= 7) return 7;  // STAFF
        }
        $name = strtolower(trim((string)$legacyRole));
        if (str_contains($name, 'admin')) return 1;
        if (str_contains($name, 'central hr') || $name === 'hr') return 2;
        if (str_contains($name, 'global accountant')) return 3;
        if (str_contains($name, 'branch accountant')) return 4;
        if (str_contains($name, 'branch hr')) return 5;
        if (str_contains($name, 'manager')) return 6;
        return 7; // STAFF default
    }
}

/**
 * Helper to check if legacy table exists
 */
function legacyTableExists(PDO $legacyPdo, string $tableName): bool {
    try {
        $stmt = $legacyPdo->query("SHOW TABLES LIKE '{$tableName}'");
        return (bool)$stmt->fetchColumn();
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Helper to format an SQL string with parameters for dry-run inspection
 */
function formatSqlWithParams(string $sql, array $params): string {
    uksort($params, fn($a, $b) => strlen((string)$b) <=> strlen((string)$a));
    foreach ($params as $key => $val) {
        if ($val === null) {
            $rep = 'NULL';
        } elseif (is_int($val) || is_float($val)) {
            $rep = (string)$val;
        } else {
            $rep = "'" . addslashes((string)$val) . "'";
        }
        $pattern = '/:' . preg_quote((string)$key, '/') . '\b/';
        $sql = preg_replace($pattern, $rep, $sql);
    }
    return preg_replace('/\s+/', ' ', trim($sql));
}

// Tracking metrics
$summary = [];

/**
 * Generic Batch Importer with 500-row chunking and transaction granularity
 */
function runTableImport(
    PDO $legacyPdo,
    PDO $targetPdo,
    string $targetTable,
    string $legacyTable,
    string $primaryKey,
    callable $rowTransformer,
    bool $isDryRun,
    array &$summary
): void {
    if (!legacyTableExists($legacyPdo, $legacyTable)) {
        echo "Table `{$legacyTable}` not found in legacy database. Skipped.\n\n";
        $summary[$targetTable] = ['read' => 0, 'written' => 0, 'skipped' => 0];
        return;
    }

    $countStmt = $legacyPdo->query("SELECT COUNT(*) FROM `{$legacyTable}`");
    $totalRows = (int)$countStmt->fetchColumn();

    $batchSize = 500;
    $offset = 0;
    $batchNum = 1;
    $totalRead = 0;
    $totalWritten = 0;
    $totalSkipped = 0;
    $dryRunPrintCount = 0;

    echo "--- Migrating Table: {$targetTable} (Source: {$legacyTable}, Total: {$totalRows} rows) ---\n";

    if ($totalRows === 0) {
        echo "Processing {$targetTable}: 0 rows found in legacy source.\n";
        echo "{$targetTable}: 0 read, 0 written, 0 skipped\n\n";
        $summary[$targetTable] = ['read' => 0, 'written' => 0, 'skipped' => 0];
        return;
    }

    $checkStmt = $targetPdo->prepare("SELECT id FROM `{$targetTable}` WHERE legacy_id = ? LIMIT 1");

    while ($offset < $totalRows) {
        $rangeEnd = min($offset + $batchSize, $totalRows);
        echo "Processing {$targetTable} batch {$batchNum} ({$offset}-{$rangeEnd})...\n";

        $fetchStmt = $legacyPdo->prepare("SELECT * FROM `{$legacyTable}` ORDER BY `{$primaryKey}` ASC LIMIT :limit OFFSET :offset");
        $fetchStmt->bindValue(':limit', $batchSize, PDO::PARAM_INT);
        $fetchStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $fetchStmt->execute();

        if (!$isDryRun) {
            $targetPdo->beginTransaction();
        }

        try {
            while ($row = $fetchStmt->fetch(PDO::FETCH_ASSOC)) {
                $totalRead++;
                $legacyId = (int)$row[$primaryKey];

                // Check idempotency: does record with legacy_id already exist?
                $checkStmt->execute([$legacyId]);
                if ($checkStmt->fetchColumn()) {
                    $totalSkipped++;
                    continue;
                }

                // Transform row
                $transformed = $rowTransformer($row, $legacyPdo, $targetPdo, $isDryRun);
                if ($transformed === null) {
                    $totalSkipped++;
                    continue;
                }

                $columns = array_keys($transformed);
                $placeholders = array_map(fn($col) => ":{$col}", $columns);
                $sql = "INSERT INTO `{$targetTable}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";

                if ($isDryRun) {
                    LegacyResolver::registerPlanned($targetTable, $legacyId);
                    if ($dryRunPrintCount < 3) {
                        $formatted = formatSqlWithParams($sql, $transformed);
                        echo "  [DRY RUN SQL #{$dryRunPrintCount}] {$formatted}\n";
                        $dryRunPrintCount++;
                    }
                    $totalWritten++; // Planned in dry run
                } else {
                    $insertStmt = $targetPdo->prepare($sql);
                    $insertStmt->execute($transformed);
                    $totalWritten++;
                }
            }

            if (!$isDryRun) {
                $targetPdo->commit();
            }
        } catch (\Throwable $e) {
            if (!$isDryRun && $targetPdo->inTransaction()) {
                $targetPdo->rollBack();
            }
            echo "  [ERROR] Batch {$batchNum} on {$targetTable} failed: " . $e->getMessage() . "\n";
        }

        $offset += $batchSize;
        $batchNum++;
    }

    $actualWritten = $isDryRun ? 0 : $totalWritten;
    echo "{$targetTable}: {$totalRead} read, {$actualWritten} written, {$totalSkipped} skipped" . ($isDryRun ? " (Planned: {$totalWritten})" : "") . "\n\n";
    $summary[$targetTable] = ['read' => $totalRead, 'written' => $actualWritten, 'skipped' => $totalSkipped];
}

// Pre-load map of employee_id -> branch_id for tables missing branch_id
$empBranchMap = [];
try {
    $empRows = $hrmsPdo->query("SELECT id, branch_id FROM `employees`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($empRows as $er) {
        $empBranchMap[(int)$er['id']] = !empty($er['branch_id']) ? (int)$er['branch_id'] : 1;
    }
} catch (\Throwable $e) {}

// ====================================================================
// 1. Branches (HRMS)
// ====================================================================
runTableImport($hrmsPdo, $targetPdo, 'branches', 'branches', 'id', function($row) {
    return [
        'name'           => trim($row['name'] ?? 'Main Branch'),
        'address'        => $row['address'] ?? null,
        'contact_number' => $row['contact_number'] ?? null,
        'status'         => (strcasecmp($row['status'] ?? '', 'Inactive') === 0) ? 'Inactive' : 'Active',
        'created_at'     => $row['created_at'] ?? date('Y-m-d H:i:s'),
        'legacy_id'      => (int)$row['id'],
    ];
}, $isDryRun, $summary);

// ====================================================================
// 2. Roles (HRMS)
// ====================================================================
if (legacyTableExists($hrmsPdo, 'roles')) {
    echo "--- Checking Roles mapping ---\n";
    $rolesStmt = $hrmsPdo->query("SELECT * FROM `roles` ORDER BY `id` ASC");
    $rolesRead = 0;
    $rolesWritten = 0;
    while ($r = $rolesStmt->fetch(PDO::FETCH_ASSOC)) {
        $rolesRead++;
        $pamId = LegacyResolver::mapRole($r['name'] ?? $r['id']);
        if (!$isDryRun) {
            $upd = $targetPdo->prepare("UPDATE `roles` SET legacy_id = ? WHERE id = ? AND legacy_id IS NULL");
            $upd->execute([$r['id'], $pamId]);
            if ($upd->rowCount() > 0) $rolesWritten++;
        }
    }
    echo "roles: {$rolesRead} read, {$rolesWritten} mapped\n\n";
    $summary['roles'] = ['read' => $rolesRead, 'written' => $rolesWritten, 'skipped' => ($rolesRead - $rolesWritten)];
}

// ====================================================================
// 3. Employees (HRMS)
// ====================================================================
runTableImport($hrmsPdo, $targetPdo, 'employees', 'employees', 'id', function($row, $legacyPdo, $targetPdo, $isDryRun) {
    $legacyBranchId = !empty($row['branch_id']) ? (int)$row['branch_id'] : null;
    $newBranchId = LegacyResolver::resolve($targetPdo, 'branches', $legacyBranchId, $isDryRun);

    if ($legacyBranchId !== null && $newBranchId === null) {
        echo "  [WARNING] Orphan resolved to NULL: employees.branch_id for legacy_id {$row['id']} (legacy branch_id {$legacyBranchId} not found)\n";
    }

    // Encrypt PII
    $tin = !empty($row['tin']) ? Crypto::encrypt(trim($row['tin'])) : null;
    $sss = !empty($row['sss']) ? Crypto::encrypt(trim($row['sss'])) : null;
    $philhealth = !empty($row['philhealth']) ? Crypto::encrypt(trim($row['philhealth'])) : null;
    $pagibig = !empty($row['pagibig']) ? Crypto::encrypt(trim($row['pagibig'])) : null;
    $bankNo = !empty($row['bank_account']) ? Crypto::encrypt(trim($row['bank_account'])) : null;

    $empType = 'REGULAR';
    $rawType = strtoupper(trim($row['employment_type'] ?? ''));
    if (str_contains($rawType, 'PART')) $empType = 'PART_TIME';
    elseif (str_contains($rawType, 'PROB') || str_contains($rawType, 'TRAIN')) $empType = 'PROBATIONARY';

    $status = 'ACTIVE';
    $rawStatus = strtoupper(trim($row['status'] ?? ''));
    if (str_contains($rawStatus, 'INACT') || str_contains($rawStatus, 'ARCHIV')) $status = 'INACTIVE';
    elseif (str_contains($rawStatus, 'TERM') || str_contains($rawStatus, 'SEP')) $status = 'SEPARATED';

    $empCode = $row['employee_id'] ?? ('EMP-LEGACY-' . $row['id']);
    $email = !empty($row['email']) ? trim($row['email']) : (strtolower($empCode) . '@fika.test');

    $hourlyRate = (float)($row['hourly_rate'] ?? 0.00);
    $basicSalary = (float)($row['basic_salary'] ?? ($hourlyRate * 8 * 22));

    return [
        'employee_code'     => $empCode,
        'branch_id'         => $newBranchId,
        'first_name'        => trim($row['first_name'] ?? 'Staff'),
        'last_name'         => trim($row['last_name'] ?? 'Member'),
        'email'             => $email,
        'phone'             => !empty($row['phone']) ? trim($row['phone']) : null,
        'address'           => $row['address'] ?? null,
        'emergency_contact' => $row['emergency_contact'] ?? null,
        'position'          => $row['position'] ?? 'Staff',
        'department'        => $row['department'] ?? 'Operations',
        'employment_type'   => $empType,
        'status'            => $status,
        'basic_salary'      => $basicSalary,
        'hourly_rate'       => $hourlyRate,
        'date_hired'        => !empty($row['date_hired']) ? $row['date_hired'] : null,
        'bank_no_enc'       => $bankNo,
        'tin_enc'           => $tin,
        'sss_no_enc'        => $sss,
        'philhealth_no_enc' => $philhealth,
        'pagibig_no_enc'    => $pagibig,
        'created_at'        => $row['created_at'] ?? date('Y-m-d H:i:s'),
        'legacy_id'         => (int)$row['id'],
    ];
}, $isDryRun, $summary);

// ====================================================================
// 4. Users (HRMS)
// ====================================================================
runTableImport($hrmsPdo, $targetPdo, 'users', 'users', 'id', function($row, $legacyPdo, $targetPdo, $isDryRun) {
    $legacyEmpId = !empty($row['employee_id']) ? (int)$row['employee_id'] : null;
    $newEmpId = LegacyResolver::resolve($targetPdo, 'employees', $legacyEmpId, $isDryRun);
    if ($legacyEmpId !== null && $newEmpId === null) {
        echo "  [WARNING] Orphan resolved to NULL: users.employee_id for legacy_id {$row['id']} (legacy employee_id {$legacyEmpId} not found)\n";
    }

    $legacyBranchId = !empty($row['branch_id']) ? (int)$row['branch_id'] : null;
    $newBranchId = LegacyResolver::resolve($targetPdo, 'branches', $legacyBranchId, $isDryRun);
    if ($legacyBranchId !== null && $newBranchId === null) {
        echo "  [WARNING] Orphan resolved to NULL: users.branch_id for legacy_id {$row['id']} (legacy branch_id {$legacyBranchId} not found)\n";
    }

    $roleId = LegacyResolver::mapRole($row['role_id'] ?? 7);

    return [
        'username'    => trim(strtolower($row['username'] ?? ('user_' . $row['id']))),
        'password'    => $row['password'], // Bcrypt hash copied verbatim
        'email'       => trim($row['email'] ?? ('user_' . $row['id'] . '@fika.test')),
        'role_id'     => $roleId,
        'employee_id' => $newEmpId,
        'branch_id'   => $newBranchId,
        'created_at'  => $row['created_at'] ?? date('Y-m-d H:i:s'),
        'legacy_id'   => (int)$row['id'],
    ];
}, $isDryRun, $summary);

// ====================================================================
// 5. Applicants (HRMS, with Interview & Scorecard consolidation)
// ====================================================================
$hasLegacyInterviews = legacyTableExists($hrmsPdo, 'interviews');
runTableImport($hrmsPdo, $targetPdo, 'applicants', 'applicants', 'id', function($row, $legacyPdo, $targetPdo) use ($hasLegacyInterviews, $hrmsPdo) {
    $notes = trim($row['notes'] ?? '');

    if ($hasLegacyInterviews) {
        try {
            $iStmt = $hrmsPdo->prepare("
                SELECT i.*, sc.technical_score, sc.communication_score, sc.culture_score, sc.recommendation, sc.strengths, sc.concerns
                FROM interviews i
                LEFT JOIN interview_scorecards sc ON sc.interview_id = i.id
                WHERE i.applicant_id = ?
                ORDER BY i.id ASC
            ");
            $iStmt->execute([$row['id']]);
            $interviews = $iStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($interviews)) {
                $interviewSnippets = [];
                foreach ($interviews as $iv) {
                    $scores = [];
                    if (!empty($iv['technical_score'])) $scores[] = "Tech: {$iv['technical_score']}/5";
                    if (!empty($iv['communication_score'])) $scores[] = "Comm: {$iv['communication_score']}/5";
                    if (!empty($iv['culture_score'])) $scores[] = "Culture: {$iv['culture_score']}/5";
                    $scoreStr = !empty($scores) ? implode(', ', $scores) : "No rating";
                    $rec = !empty($iv['recommendation']) ? " Rec: {$iv['recommendation']}." : "";
                    $scNotes = !empty($iv['strengths']) ? " Strengths: {$iv['strengths']}." : "";
                    if (!empty($iv['concerns'])) $scNotes .= " Concerns: {$iv['concerns']}.";
                    $interviewSnippets[] = "[Legacy Interview #{$iv['id']}] {$scoreStr}.{$rec}{$scNotes}";
                }
                $consolidated = implode("\n", $interviewSnippets);
                $notes = $notes !== '' ? ($notes . "\n\n" . $consolidated) : $consolidated;
            }
        } catch (\Throwable $e) {}
    }

    $rawStatus = strtoupper(trim($row['stage'] ?? ($row['status'] ?? 'NEW')));
    $status = match($rawStatus) {
        'SHORTLISTED' => 'SHORTLISTED',
        'REVIEWING', 'INTERVIEW', 'OFFER' => 'REVIEWING',
        'REJECTED'    => 'REJECTED',
        'HIRED'       => 'HIRED',
        default       => 'NEW'
    };

    return [
        'job_opening_id'   => null,
        'first_name'       => trim($row['first_name'] ?? 'Applicant'),
        'last_name'        => trim($row['last_name'] ?? 'Candidate'),
        'email'            => trim($row['email'] ?? ('applicant_' . $row['id'] . '@candidate.test')),
        'phone'            => !empty($row['phone']) ? trim($row['phone']) : '09000000000',
        'position'         => $row['position_applied'] ?? ($row['position'] ?? 'General Staff'),
        'position_applied' => $row['position_applied'] ?? ($row['position'] ?? 'General Staff'),
        'resume'           => $row['resume'] ?? null,
        'resume_path'      => $row['resume_path'] ?? null,
        'status'           => $status,
        'notes'            => $notes !== '' ? $notes : null,
        'created_at'       => $row['created_at'] ?? date('Y-m-d H:i:s'),
        'legacy_id'        => (int)$row['id'],
    ];
}, $isDryRun, $summary);

// ====================================================================
// 6. Attendance Logs (HRMS)
// ====================================================================
runTableImport($hrmsPdo, $targetPdo, 'attendance_logs', 'attendance', 'id', function($row, $legacyPdo, $targetPdo, $isDryRun) use ($empBranchMap) {
    $empId = LegacyResolver::resolve($targetPdo, 'employees', (int)$row['employee_id'], $isDryRun);
    
    // Resolve branch: from row branch_id, or mapped employee branch, or default to branch 1
    $legacyBranchId = !empty($row['branch_id']) ? (int)$row['branch_id'] : ($empBranchMap[(int)$row['employee_id']] ?? 1);
    $branchId = LegacyResolver::resolve($targetPdo, 'branches', $legacyBranchId, $isDryRun);

    if ($empId === null || $branchId === null) {
        echo "  [WARNING] Orphan skipped: attendance_logs legacy_id {$row['id']} (required employee_id {$row['employee_id']} not found)\n";
        return null;
    }

    $clockIn = $row['time_in'] ?? ($row['clock_in'] ?? null);
    $clockOut = $row['time_out'] ?? ($row['clock_out'] ?? null);
    if (!$clockIn) {
        $clockIn = date('Y-m-d 08:00:00');
    }

    $status = match(strtoupper(trim($row['status'] ?? ''))) {
        'OPEN'     => 'OPEN',
        'ADJUSTED' => 'ADJUSTED',
        'ABSENT'   => 'ABSENT',
        default    => 'CLOSED'
    };

    return [
        'employee_id' => $empId,
        'branch_id'   => $branchId,
        'clock_in'    => $clockIn,
        'clock_out'   => $clockOut,
        'work_date'   => !empty($row['work_date']) ? $row['work_date'] : date('Y-m-d', strtotime($clockIn)),
        'source'      => (strtoupper($row['source'] ?? '') === 'MANUAL') ? 'MANUAL' : 'DEVICE',
        'status'      => $status,
        'created_at'  => $row['created_at'] ?? date('Y-m-d H:i:s'),
        'legacy_id'   => (int)$row['id'],
    ];
}, $isDryRun, $summary);

// ====================================================================
// 7. Leave Requests (HRMS)
// ====================================================================
runTableImport($hrmsPdo, $targetPdo, 'leave_requests', 'leaves', 'id', function($row, $legacyPdo, $targetPdo, $isDryRun) {
    $empId = LegacyResolver::resolve($targetPdo, 'employees', (int)$row['employee_id'], $isDryRun);
    if ($empId === null) {
        echo "  [WARNING] Orphan skipped: leave_requests legacy_id {$row['id']} (required employee_id {$row['employee_id']} not found)\n";
        return null;
    }

    $rawStatus = strtoupper(trim($row['status'] ?? 'PENDING'));
    $status = match($rawStatus) {
        'APPROVED' => 'APPROVED',
        'REJECTED' => 'REJECTED',
        default    => 'PENDING'
    };

    $rawType = strtoupper(trim($row['leave_type'] ?? ($row['type'] ?? 'VACATION')));
    $type = match($rawType) {
        'SICK', 'SIL' => 'SIL',
        'MATERNITY'   => 'MATERNITY',
        'PATERNITY'   => 'PATERNITY',
        'EMERGENCY'   => 'EMERGENCY',
        'SOLO_PARENT' => 'SOLO_PARENT',
        default       => 'VACATION'
    };

    $dateFrom = $row['start_date'] ?? ($row['date_from'] ?? date('Y-m-d'));
    $dateTo   = $row['end_date'] ?? ($row['date_to'] ?? $dateFrom);
    $days     = isset($row['days']) ? (float)$row['days'] : (max(1, (strtotime($dateTo) - strtotime($dateFrom)) / 86400 + 1));

    return [
        'employee_id' => $empId,
        'type'        => $type,
        'date_from'   => $dateFrom,
        'date_to'     => $dateTo,
        'days'        => $days,
        'reason'      => !empty($row['reason']) ? $row['reason'] : 'Legacy leave request',
        'status'      => $status,
        'created_at'  => $row['created_at'] ?? date('Y-m-d H:i:s'),
        'legacy_id'   => (int)$row['id'],
    ];
}, $isDryRun, $summary);

// ====================================================================
// 8. Shifts (HRMS schedules)
// ====================================================================
runTableImport($hrmsPdo, $targetPdo, 'shifts', 'schedules', 'id', function($row, $legacyPdo, $targetPdo, $isDryRun) use ($adminUserId, $empBranchMap) {
    $legacyBranchId = !empty($row['branch_id']) ? (int)$row['branch_id'] : ($empBranchMap[(int)$row['employee_id']] ?? 1);
    $branchId = LegacyResolver::resolve($targetPdo, 'branches', $legacyBranchId, $isDryRun);
    $empId = LegacyResolver::resolve($targetPdo, 'employees', (int)$row['employee_id'], $isDryRun);

    if ($branchId === null || $empId === null) {
        echo "  [WARNING] Orphan skipped: shifts legacy_id {$row['id']} (required branch_id or employee_id {$row['employee_id']} not found)\n";
        return null;
    }

    $status = (strtoupper(trim($row['status'] ?? '')) === 'DRAFT') ? 'DRAFT' : 'PUBLISHED';

    return [
        'branch_id'   => $branchId,
        'employee_id' => $empId,
        'shift_date'  => $row['shift_date'] ?? date('Y-m-d'),
        'start_time'  => $row['start_time'] ?? '08:00:00',
        'end_time'    => $row['end_time'] ?? '17:00:00',
        'shift_type'  => $row['shift_type'] ?? 'Standard',
        'status'      => $status,
        'created_by'  => $adminUserId,
        'created_at'  => $row['created_at'] ?? date('Y-m-d H:i:s'),
        'legacy_id'   => (int)$row['id'],
    ];
}, $isDryRun, $summary);

// ====================================================================
// 9. Payroll Runs & Items (HRMS payroll)
// ====================================================================
if (legacyTableExists($hrmsPdo, 'payroll')) {
    echo "--- Migrating Table: payroll_items (Source: payroll) ---\n";
    $pCount = (int)$hrmsPdo->query("SELECT COUNT(*) FROM `payroll`")->fetchColumn();
    if ($pCount > 0) {
        $runStmt = $targetPdo->query("SELECT id FROM `payroll_runs` WHERE legacy_id = 999999 LIMIT 1");
        $legacyRunId = $runStmt->fetchColumn();
        if (!$legacyRunId && !$isDryRun) {
            $createRun = $targetPdo->prepare("
                INSERT INTO `payroll_runs` (`scope`, `period_start`, `period_end`, `status`, `processed_by`, `legacy_id`, `created_at`)
                VALUES ('HQ', '2026-01-01', '2026-12-31', 'RELEASED', ?, 999999, NOW())
            ");
            $createRun->execute([$adminUserId]);
            $legacyRunId = (int)$targetPdo->lastInsertId();
        } elseif (!$legacyRunId && $isDryRun) {
            $legacyRunId = 1;
        }

        runTableImport($hrmsPdo, $targetPdo, 'payroll_items', 'payroll', 'id', function($row, $legacyPdo, $targetPdo, $isDryRun) use ($legacyRunId) {
            $empId = LegacyResolver::resolve($targetPdo, 'employees', (int)$row['employee_id'], $isDryRun);
            if ($empId === null) {
                echo "  [WARNING] Orphan skipped: payroll_items legacy_id {$row['id']} (required employee_id {$row['employee_id']} not found)\n";
                return null;
            }

            $grossPay = (float)($row['gross_pay'] ?? 0.00);
            $overtimePay = (float)($row['overtime_pay'] ?? 0.00);
            $basicPay = isset($row['basic_pay']) ? (float)$row['basic_pay'] : max(0.00, $grossPay - $overtimePay);

            return [
                'payroll_run_id'       => $legacyRunId,
                'employee_id'          => $empId,
                'basic_pay'            => $basicPay,
                'overtime_pay'         => $overtimePay,
                'bonus_pay'            => (float)($row['bonus_amount'] ?? 0.00),
                'gross_pay'            => $grossPay,
                'sss_deduction'        => (float)($row['sss'] ?? ($row['sss_deduction'] ?? 0.00)),
                'philhealth_deduction' => (float)($row['philhealth'] ?? ($row['philhealth_deduction'] ?? 0.00)),
                'pagibig_deduction'    => (float)($row['pagibig'] ?? ($row['pagibig_deduction'] ?? 0.00)),
                'tax_deduction'        => (float)($row['tax'] ?? ($row['tax_deduction'] ?? 0.00)),
                'net_pay'              => (float)($row['net_pay'] ?? 0.00),
                'created_at'           => $row['created_at'] ?? date('Y-m-d H:i:s'),
                'legacy_id'            => (int)$row['id'],
            ];
        }, $isDryRun, $summary);
    }
}

// ====================================================================
// 10. Inventory Items (POS)
// ====================================================================
runTableImport($posPdo, $targetPdo, 'inventory_items', 'inventory', 'id', function($row) {
    return [
        'item_name'  => trim($row['item_name'] ?? 'Unnamed Ingredient'),
        'unit'       => !empty($row['unit']) ? $row['unit'] : 'pcs',
        'unit_cost'  => (float)($row['unit_cost'] ?? 0.00),
        'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
        'legacy_id'  => (int)$row['id'],
    ];
}, $isDryRun, $summary);

// ====================================================================
// 11. Inventory Stock & Synthetic Opening Balance (POS)
// ====================================================================
if (legacyTableExists($posPdo, 'inventory_stock')) {
    echo "--- Migrating Table: inventory_stock (Source: inventory_stock) ---\n";
    $stockRows = $posPdo->query("SELECT * FROM `inventory_stock` ORDER BY `inventory_id` ASC")->fetchAll(PDO::FETCH_ASSOC);
    $sRead = 0;
    $sWritten = 0;
    $sSkipped = 0;

    $checkStockStmt = $targetPdo->prepare("SELECT id FROM `inventory_stock` WHERE legacy_id = ? LIMIT 1");
    $insertStockStmt = $targetPdo->prepare("
        INSERT INTO `inventory_stock` (`branch_id`, `inventory_id`, `current_stock`, `low_stock_threshold`, `created_at`, `legacy_id`)
        VALUES (?, ?, ?, ?, NOW(), ?)
    ");
    $insertLedgerStmt = $targetPdo->prepare("
        INSERT INTO `inventory_ledger` (`branch_id`, `inventory_id`, `type`, `quantity`, `unit_cost_snapshot`, `user_id`, `remarks`, `created_at`, `legacy_id`)
        VALUES (?, ?, 'ADJUSTMENT', ?, ?, ?, 'Opening balance from legacy migration', NOW(), NULL)
    ");
    $checkLedgerStmt = $targetPdo->prepare("
        SELECT id FROM `inventory_ledger` WHERE branch_id = ? AND inventory_id = ? AND remarks = 'Opening balance from legacy migration' LIMIT 1
    ");

    // Fetch existing historical transactions to compute opening balance offset
    $txnSumStmt = $posPdo->prepare("
        SELECT COALESCE(SUM(CASE WHEN LOWER(type) = 'spoilage' THEN -ABS(quantity) ELSE quantity END), 0)
        FROM `inventory_transactions`
        WHERE inventory_id = ? AND branch_id = ?
    ");

    foreach ($stockRows as $sr) {
        $sRead++;
        $legacyId = (int)$sr['inventory_id'];

        $checkStockStmt->execute([$legacyId]);
        if ($checkStockStmt->fetchColumn()) {
            $sSkipped++;
            continue;
        }

        $branchId = LegacyResolver::resolve($targetPdo, 'branches', (int)$sr['branch_id'], $isDryRun) ?: 1;
        $invId = LegacyResolver::resolve($targetPdo, 'inventory_items', (int)$sr['inventory_id'], $isDryRun);

        if ($invId === null) {
            echo "  [WARNING] Orphan skipped: inventory_stock legacy_id {$legacyId} (inventory item not found)\n";
            $sSkipped++;
            continue;
        }

        $currentStock = (float)($sr['stock'] ?? ($sr['current_stock'] ?? 0.00));
        $lowThreshold = (float)($sr['low_stock_level'] ?? ($sr['low_stock_threshold'] ?? 5.00));

        // Get legacy unit cost
        $costStmt = $targetPdo->prepare("SELECT unit_cost FROM inventory_items WHERE id = ?");
        $costStmt->execute([$invId]);
        $unitCost = (float)$costStmt->fetchColumn();

        // Calculate opening balance quantity needed so SUM(ledger.quantity) === current_stock
        $txnSumStmt->execute([(int)$sr['inventory_id'], (int)$sr['branch_id']]);
        $historicalTxnSum = (float)$txnSumStmt->fetchColumn();
        $openingQty = $currentStock - $historicalTxnSum;

        if (!$isDryRun) {
            $insertStockStmt->execute([$branchId, $invId, $currentStock, $lowThreshold, $legacyId]);

            // Insert synthetic opening balance if not already present
            $checkLedgerStmt->execute([$branchId, $invId]);
            if (!$checkLedgerStmt->fetchColumn()) {
                $insertLedgerStmt->execute([$branchId, $invId, $openingQty, $unitCost, $adminUserId]);
            }
            $sWritten++;
        } else {
            LegacyResolver::registerPlanned('inventory_stock', $legacyId);
            echo "  [DRY RUN SQL] INSERT INTO `inventory_stock` VALUES (branch: {$branchId}, inv: {$invId}, stock: {$currentStock}, low: {$lowThreshold})\n";
            echo "  [DRY RUN SQL] INSERT INTO `inventory_ledger` (Opening balance synthetic): qty: {$openingQty}\n";
            $sWritten++;
        }
    }

    $actualWritten = $isDryRun ? 0 : $sWritten;
    echo "inventory_stock: {$sRead} read, {$actualWritten} written, {$sSkipped} skipped\n\n";
    $summary['inventory_stock'] = ['read' => $sRead, 'written' => $actualWritten, 'skipped' => $sSkipped];
}

// ====================================================================
// 12. Inventory Ledger (POS inventory_transactions)
// ====================================================================
runTableImport($posPdo, $targetPdo, 'inventory_ledger', 'inventory_transactions', 'id', function($row, $legacyPdo, $targetPdo, $isDryRun) use ($adminUserId) {
    $branchId = LegacyResolver::resolve($targetPdo, 'branches', (int)$row['branch_id'], $isDryRun) ?: 1;
    $invId = LegacyResolver::resolve($targetPdo, 'inventory_items', (int)$row['inventory_id'], $isDryRun);

    if ($invId === null) {
        echo "  [WARNING] Orphan skipped: inventory_ledger legacy_id {$row['id']} (inventory_id not found)\n";
        return null;
    }

    $rawType = ucfirst(strtolower(trim($row['type'] ?? 'Adjustment')));
    $type = match($rawType) {
        'Restock'    => 'Restock',
        'Spoilage'   => 'Spoilage',
        'Sold'       => 'Sold',
        'Transfer'   => 'Transfer',
        default      => 'Adjustment'
    };

    $rawQty = (float)($row['quantity'] ?? 0.00);
    // Reductions/Spoilage in double-entry ledger have negative sign
    $qty = ($type === 'Spoilage') ? -abs($rawQty) : $rawQty;

    $cost = (float)($row['financial_impact'] ?? ($row['unit_cost'] ?? 0.00));

    return [
        'branch_id'          => $branchId,
        'inventory_id'       => $invId,
        'type'               => $type,
        'quantity'           => $qty,
        'unit_cost_snapshot' => $cost,
        'user_id'            => $adminUserId,
        'remarks'            => $row['remarks'] ?? 'Legacy transaction',
        'created_at'         => $row['transaction_date'] ?? ($row['created_at'] ?? date('Y-m-d H:i:s')),
        'legacy_id'          => (int)$row['id'],
    ];
}, $isDryRun, $summary);

// ====================================================================
// 13. Products (POS)
// ====================================================================
runTableImport($posPdo, $targetPdo, 'products', 'products', 'id', function($row) {
    $price = (float)($row['price'] ?? 0.00);
    $priceCents = (int)round($price * 100);

    return [
        'name'        => trim($row['name'] ?? 'Unnamed Product'),
        'category'    => trim($row['category'] ?? 'General'),
        'price'       => $price,
        'price_cents' => $priceCents,
        'image_path'  => $row['image_path'] ?? null,
        'image_alt'   => $row['image_alt'] ?? null,
        'is_active'   => !empty($row['is_active']) ? 1 : 0,
        'created_at'  => $row['created_at'] ?? date('Y-m-d H:i:s'),
        'legacy_id'   => (int)$row['id'],
    ];
}, $isDryRun, $summary);

// ====================================================================
// 14. Recipes (POS)
// ====================================================================
runTableImport($posPdo, $targetPdo, 'recipes', 'recipes', 'id', function($row, $legacyPdo, $targetPdo, $isDryRun) {
    $prodId = LegacyResolver::resolve($targetPdo, 'products', (int)$row['product_id'], $isDryRun);
    $invId = LegacyResolver::resolve($targetPdo, 'inventory_items', (int)$row['inventory_id'], $isDryRun);

    if ($prodId === null || $invId === null) {
        echo "  [WARNING] Orphan skipped: recipes legacy_id {$row['id']} (product_id or inventory_id not found)\n";
        return null;
    }

    $qty = (float)($row['quantity'] ?? ($row['quantity_required'] ?? 1.00));

    return [
        'product_id'        => $prodId,
        'modifier_id'       => null,
        'inventory_id'      => $invId,
        'quantity_required' => $qty,
        'created_at'        => $row['created_at'] ?? date('Y-m-d H:i:s'),
        'legacy_id'         => (int)$row['id'],
    ];
}, $isDryRun, $summary);

// ====================================================================
// 15. Cash Sessions (POS)
// ====================================================================
runTableImport($posPdo, $targetPdo, 'cash_sessions', 'cash_sessions', 'id', function($row, $legacyPdo, $targetPdo, $isDryRun) use ($adminUserId) {
    $branchId = LegacyResolver::resolve($targetPdo, 'branches', (int)$row['branch_id'], $isDryRun) ?: 1;
    $cashierLegacyId = !empty($row['cashier_id']) ? (int)$row['cashier_id'] : null;
    $cashierId = LegacyResolver::resolve($targetPdo, 'users', $cashierLegacyId, $isDryRun);

    $startingCash = (float)($row['opening_float'] ?? ($row['starting_cash'] ?? 0.00));
    $expectedCash = (float)($row['expected_cash'] ?? $startingCash);
    $endingCash   = (float)($row['closing_cash'] ?? ($row['ending_cash'] ?? $expectedCash));

    $status = (strtoupper(trim($row['status'] ?? '')) === 'OPEN') ? 'OPEN' : 'CLOSED';

    return [
        'branch_id'     => $branchId,
        'cashier_id'    => $cashierId ?: $adminUserId,
        'starting_cash' => $startingCash,
        'expected_cash' => $expectedCash,
        'ending_cash'   => $endingCash,
        'status'        => $status,
        'opened_at'     => $row['opened_at'] ?? null,
        'closed_at'     => $row['closed_at'] ?? null,
        'legacy_id'     => (int)$row['id'],
    ];
}, $isDryRun, $summary);

// ====================================================================
// 16. Orders (POS)
// ====================================================================
runTableImport($posPdo, $targetPdo, 'orders', 'orders', 'id', function($row, $legacyPdo, $targetPdo, $isDryRun) {
    $branchId = LegacyResolver::resolve($targetPdo, 'branches', (int)$row['branch_id'], $isDryRun) ?: 1;

    $cashierLegacyId = !empty($row['cashier_id']) ? (int)$row['cashier_id'] : null;
    $cashierId = LegacyResolver::resolve($targetPdo, 'users', $cashierLegacyId, $isDryRun);
    if ($cashierLegacyId !== null && $cashierId === null) {
        echo "  [WARNING] Orphan resolved to NULL: orders.cashier_id for legacy_id {$row['id']} (legacy cashier_id {$cashierLegacyId} not found)\n";
    }

    $sessionLegacyId = !empty($row['pos_session_id']) ? (int)$row['pos_session_id'] : null;
    $sessionId = LegacyResolver::resolve($targetPdo, 'cash_sessions', $sessionLegacyId, $isDryRun);

    $totalPrice = (float)($row['total_price'] ?? ($row['total_amount'] ?? 0.00));
    $discount = (float)($row['discount_amount'] ?? 0.00);
    $vat = (float)($row['vat_amount'] ?? 0.00);
    $finalAmount = max(0.00, $totalPrice - $discount);

    $rawStatus = strtoupper(trim($row['order_status'] ?? 'COMPLETED'));
    $orderStatus = match($rawStatus) {
        'PENDING'   => 'PENDING',
        'PAID'      => 'PAID',
        'COMPLETED' => 'COMPLETED',
        'CANCELLED' => 'CANCELLED',
        'VOID'      => 'VOID',
        'REFUNDED'  => 'REFUNDED',
        default     => 'COMPLETED'
    };

    return [
        'order_number'    => $row['order_number'] ?? ('ORD-' . $row['id']),
        'branch_id'       => $branchId,
        'pos_session_id'  => $sessionId,
        'cashier_id'      => $cashierId,
        'total_amount'    => $totalPrice,
        'discount_amount' => $discount,
        'vat_amount'      => $vat,
        'final_amount'    => $finalAmount,
        'order_status'    => $orderStatus,
        'payment_status'  => strtoupper(trim($row['payment_status'] ?? 'PAID')),
        'payment_method'  => strtoupper(trim($row['payment_method'] ?? 'CASH')),
        'created_at'      => $row['created_at'] ?? date('Y-m-d H:i:s'),
        'legacy_id'       => (int)$row['id'],
    ];
}, $isDryRun, $summary);

// ====================================================================
// 17. Order Items (POS)
// ====================================================================
runTableImport($posPdo, $targetPdo, 'order_items', 'order_items', 'id', function($row, $legacyPdo, $targetPdo, $isDryRun) {
    $orderId = LegacyResolver::resolve($targetPdo, 'orders', (int)$row['order_id'], $isDryRun);
    $prodId = LegacyResolver::resolve($targetPdo, 'products', (int)$row['product_id'], $isDryRun);

    if ($orderId === null || $prodId === null) {
        echo "  [WARNING] Orphan skipped: order_items legacy_id {$row['id']} (order_id or product_id not found)\n";
        return null;
    }

    $qty = max(1, (int)($row['quantity'] ?? 1));
    $subtotal = (float)($row['subtotal'] ?? 0.00);
    $price = isset($row['price']) ? (float)$row['price'] : ($subtotal / $qty);
    $priceCents = (int)round($price * 100);

    return [
        'order_id'            => $orderId,
        'product_id'          => $prodId,
        'quantity'            => $qty,
        'price_snapshot'      => $price,
        'price_snapshot_cents'=> $priceCents,
        'subtotal'            => $subtotal,
        'created_at'          => $row['created_at'] ?? date('Y-m-d H:i:s'),
        'legacy_id'           => (int)$row['id'],
    ];
}, $isDryRun, $summary);

// ====================================================================
// 18. Payments (POS)
// ====================================================================
runTableImport($posPdo, $targetPdo, 'payments', 'payments', 'id', function($row, $legacyPdo, $targetPdo, $isDryRun) {
    $orderId = LegacyResolver::resolve($targetPdo, 'orders', (int)$row['order_id'], $isDryRun);
    if ($orderId === null) {
        echo "  [WARNING] Orphan skipped: payments legacy_id {$row['id']} (order_id {$row['order_id']} not found)\n";
        return null;
    }

    $rawMethod = strtoupper(trim($row['method'] ?? ($row['payment_method'] ?? 'CASH')));
    $method = match($rawMethod) {
        'GCASH' => 'GCASH',
        'MAYA'  => 'MAYA',
        'CARD'  => 'CARD',
        default => 'CASH'
    };

    $amountPaid = (float)($row['gross_amount'] ?? ($row['amount_paid'] ?? 0.00));

    return [
        'order_id'       => $orderId,
        'payment_method' => $method,
        'amount_paid'    => $amountPaid,
        'change_amount'  => (float)($row['change_amount'] ?? 0.00),
        'gross_amount'   => $amountPaid,
        'status'         => 'paid',
        'created_at'     => $row['created_at'] ?? date('Y-m-d H:i:s'),
        'legacy_id'      => (int)$row['id'],
    ];
}, $isDryRun, $summary);

// ====================================================================
// 19. Branch Budgets (HRMS)
// ====================================================================
runTableImport($hrmsPdo, $targetPdo, 'branch_budgets', 'branch_budgets', 'id', function($row, $legacyPdo, $targetPdo, $isDryRun) {
    $branchId = LegacyResolver::resolve($targetPdo, 'branches', (int)$row['branch_id'], $isDryRun) ?: 1;

    $amount = (float)($row['allocated_labor_budget'] ?? ($row['allocated_amount'] ?? 0.00));
    $amountCents = (int)round($amount * 100);

    return [
        'branch_id'              => $branchId,
        'budget_month'           => trim($row['budget_month'] ?? date('Y-m')),
        'allocated_labor_budget' => $amount,
        'allocated_amount_cents' => $amountCents,
        'created_at'             => $row['created_at'] ?? date('Y-m-d H:i:s'),
        'legacy_id'              => (int)$row['id'],
    ];
}, $isDryRun, $summary);

// ====================================================================
// 20. Audit Logs (HRMS)
// ====================================================================
runTableImport($hrmsPdo, $targetPdo, 'audit_logs', 'activity_logs', 'id', function($row, $legacyPdo, $targetPdo, $isDryRun) {
    $legacyUserId = !empty($row['user_id']) ? (int)$row['user_id'] : null;
    $userId = LegacyResolver::resolve($targetPdo, 'users', $legacyUserId, $isDryRun);

    if ($legacyUserId !== null && $userId === null) {
        echo "  [WARNING] Orphan resolved to NULL: audit_logs.user_id for legacy_id {$row['id']} (legacy user_id {$legacyUserId} not found)\n";
    }

    return [
        'user_id'    => $userId,
        'action'     => strtoupper(trim($row['action'] ?? 'ACTIVITY')),
        'details'    => $row['details'] ?? null,
        'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
        'legacy_id'  => (int)$row['id'],
    ];
}, $isDryRun, $summary);

// ====================================================================
// Final Execution Summary
// ====================================================================
echo "====================================================================\n";
echo "MIGRATION SUMMARY " . ($isDryRun ? "(DRY RUN)" : "(APPLIED)") . "\n";
echo "====================================================================\n";
foreach ($summary as $table => $metrics) {
    echo sprintf("%-20s: %5d read, %5d written, %5d skipped\n", $table, $metrics['read'], $metrics['written'], $metrics['skipped']);
}
echo "====================================================================\n";
echo "Completed at " . date('Y-m-d H:i:s') . "\n";
