<?php
/**
 * tools/demo_seed/phase_5.php
 *
 * Phase D5: Roster and Leaves Seeding
 *
 * Populates:
 *   - leave_requests: 4 Approved leaves and 2 Pending leaves over the 6-week timeframe
 *   - shifts: Roster for 3 active branches (General Trias, Trece Martires, Imus):
 *       - Past 4 weeks (2026-09-28 to 2026-10-25): 25 shifts/wk/branch (status: PUBLISHED)
 *       - Current week (2026-10-26 to 2026-11-01): 25 shifts/wk/branch (status: PUBLISHED)
 *       - Next week (2026-11-02 to 2026-11-08): 10 open shifts/branch (status: PENDING_BM)
 *
 * Rules:
 *   - Exactly 5 working days and 2 rest days per employee per full week.
 *   - Overlaps = 0.
 *   - Shifts on Approved Leave = 0.
 *   - Assigned by Branch HR (Role 5) user ID per branch.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

global $pdo, $DEMO_APPLY;

echo "====================================================================\n";
echo "Phase D5: Seeding Roster and Leaves\n";
echo "====================================================================\n";

// 1. Fetch Target Active Branches (1, 2, 3)
$branches = [
    1 => 'General Trias Branch',
    2 => 'Trece Martires Branch',
    3 => 'Imus Branch'
];

// 2. Fetch Crew Employees & Key Officials Per Branch
$branchCrew = [];
$branchBHR  = [];
$branchBM   = [];
$branchBA   = [];

foreach (array_keys($branches) as $bId) {
    // 5 Crew Employees
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, employee_code, position
        FROM employees
        WHERE branch_id = ? AND status = 'Active' AND staff_class = 'CREW' AND deleted_at IS NULL
        ORDER BY id ASC
    ");
    $stmt->execute([$bId]);
    $branchCrew[$bId] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($branchCrew[$bId]) !== 5) {
        throw new RuntimeException("Expected 5 crew employees for branch {$bId}, found " . count($branchCrew[$bId]));
    }

    // Branch HR (Role 5)
    $bhrStmt = $pdo->prepare("SELECT id, username FROM users WHERE branch_id = ? AND role_id = 5 LIMIT 1");
    $bhrStmt->execute([$bId]);
    $bhrUser = $bhrStmt->fetch(PDO::FETCH_ASSOC);
    if (!$bhrUser) throw new RuntimeException("Missing Branch HR user for branch {$bId}");
    $branchBHR[$bId] = (int)$bhrUser['id'];

    // Branch Manager (Role 6)
    $bmStmt = $pdo->prepare("SELECT id, username FROM users WHERE branch_id = ? AND role_id = 6 LIMIT 1");
    $bmStmt->execute([$bId]);
    $bmUser = $bmStmt->fetch(PDO::FETCH_ASSOC);
    if (!$bmUser) throw new RuntimeException("Missing Branch Manager user for branch {$bId}");
    $branchBM[$bId] = (int)$bmUser['id'];

    // Branch Accountant (Role 4)
    $baStmt = $pdo->prepare("SELECT id, username FROM users WHERE branch_id = ? AND role_id = 4 LIMIT 1");
    $baStmt->execute([$bId]);
    $baUser = $baStmt->fetch(PDO::FETCH_ASSOC);
    if (!$baUser) throw new RuntimeException("Missing Branch Accountant user for branch {$bId}");
    $branchBA[$bId] = (int)$baUser['id'];
}

// 3. Define Weeks Timeline
$weeks = [
    1 => ['start' => '2026-09-28', 'end' => '2026-10-04', 'type' => 'past'],
    2 => ['start' => '2026-10-05', 'end' => '2026-10-11', 'type' => 'past'],
    3 => ['start' => '2026-10-12', 'end' => '2026-10-18', 'type' => 'past'],
    4 => ['start' => '2026-10-19', 'end' => '2026-10-25', 'type' => 'past'],
    5 => ['start' => '2026-10-26', 'end' => '2026-11-01', 'type' => 'current'],
    6 => ['start' => '2026-11-02', 'end' => '2026-11-08', 'type' => 'next'],
];

// ====================================================================
// TASK 1: Leave Requests Seeding (4 Approved, 2 Pending)
// ====================================================================
echo "[1/3] Processing Leave Requests (4 Approved, 2 Pending)...\n";

$leavesToSeed = [
    // Approved Leaves (4):
    [
        'branch_id' => 1, 'crew_idx' => 1, // Sofia Garcia (EMP #8)
        'date_from' => '2026-10-07', 'date_to' => '2026-10-07', 'days' => 1.00,
        'type' => 'VACATION', 'reason' => 'Personal family matter',
        'status' => 'APPROVED', 'decided_at' => '2026-10-05 09:00:00'
    ],
    [
        'branch_id' => 1, 'crew_idx' => 3, // Bea Torres (EMP #10)
        'date_from' => '2026-10-21', 'date_to' => '2026-10-21', 'days' => 1.00,
        'type' => 'SIL', 'reason' => 'Medical appointment',
        'status' => 'APPROVED', 'decided_at' => '2026-10-19 14:00:00'
    ],
    [
        'branch_id' => 2, 'crew_idx' => 1, // Apolinario Mabini (EMP #16)
        'date_from' => '2026-10-14', 'date_to' => '2026-10-14', 'days' => 1.00,
        'type' => 'EMERGENCY', 'reason' => 'Home emergency repair',
        'status' => 'APPROVED', 'decided_at' => '2026-10-13 11:30:00'
    ],
    [
        'branch_id' => 3, 'crew_idx' => 2, // Diosdado Macapagal (EMP #25)
        'date_from' => '2026-10-28', 'date_to' => '2026-10-28', 'days' => 1.00,
        'type' => 'VACATION', 'reason' => 'Personal anniversary',
        'status' => 'APPROVED', 'decided_at' => '2026-10-24 16:00:00'
    ],

    // Pending Leaves (2):
    [
        'branch_id' => 1, 'crew_idx' => 2, // Carlo Mendoza (EMP #9)
        'date_from' => '2026-11-05', 'date_to' => '2026-11-05', 'days' => 1.00,
        'type' => 'VACATION', 'reason' => 'Upcoming family reunion',
        'status' => 'PENDING', 'decided_at' => null
    ],
    [
        'branch_id' => 2, 'crew_idx' => 3, // Gregorio Del Pilar (EMP #18)
        'date_from' => '2026-11-06', 'date_to' => '2026-11-06', 'days' => 1.00,
        'type' => 'SIL', 'reason' => 'Dental surgery',
        'status' => 'PENDING', 'decided_at' => null
    ],
];

foreach ($leavesToSeed as $l) {
    $empId = (int)$branchCrew[$l['branch_id']][$l['crew_idx']]['id'];
    $deciderId = ($l['status'] === 'APPROVED') ? $branchBHR[$l['branch_id']] : null;

    $leaveRow = [
        'employee_id' => $empId,
        'type'        => $l['type'],
        'date_from'   => $l['date_from'],
        'date_to'     => $l['date_to'],
        'days'        => $l['days'],
        'reason'      => $l['reason'],
        'status'      => $l['status'],
        'decided_by'  => $deciderId,
        'decided_at'  => $l['decided_at']
    ];

    demo_insert($pdo, 'leave_requests', $leaveRow, [
        'employee_id' => $empId,
        'date_from'   => $l['date_from'],
        'date_to'     => $l['date_to']
    ], 'phase_5');
}

// ====================================================================
// TASK 2: Shifts Seeding (Weeks 1 to 6 across 3 Branches)
// ====================================================================
echo "[2/3] Processing Roster Shifts (Weeks 1 to 6)...\n";

$allGeneratedShifts = [];

foreach (array_keys($branches) as $bId) {
    $crew = $branchCrew[$bId];
    $bhrId = $branchBHR[$bId];
    $bmId  = $branchBM[$bId];
    $baId  = $branchBA[$bId];

    // --- Weeks 1 to 5 (Full Weeks: 25 shifts per week) ---
    for ($w = 1; $w <= 5; $w++) {
        $wStart = new DateTime($weeks[$w]['start']);

        for ($day = 0; $day < 7; $day++) {
            $curDate = (clone $wStart)->modify("+{$day} days")->format('Y-m-d');
            $dayAssignments = [];

            // Standard Shift Distribution (Covering Opening, Mid, Closing; 2 on Opening on 4-crew days)
            if ($day === 0) { // Monday (3 crew)
                $dayAssignments[0] = ['type' => 'Opening', 'start' => '06:00:00', 'end' => '14:00:00'];
                $dayAssignments[2] = ['type' => 'Mid',     'start' => '10:00:00', 'end' => '18:00:00'];
                $dayAssignments[3] = ['type' => 'Closing', 'start' => '14:00:00', 'end' => '22:00:00'];
            } elseif ($day === 1) { // Tuesday (4 crew)
                $dayAssignments[0] = ['type' => 'Opening', 'start' => '06:00:00', 'end' => '14:00:00'];
                $dayAssignments[4] = ['type' => 'Opening', 'start' => '06:00:00', 'end' => '14:00:00'];
                $dayAssignments[2] = ['type' => 'Mid',     'start' => '10:00:00', 'end' => '18:00:00'];
                $dayAssignments[3] = ['type' => 'Closing', 'start' => '14:00:00', 'end' => '22:00:00'];
            } elseif ($day === 2) { // Wednesday (4 crew)
                $dayAssignments[0] = ['type' => 'Opening', 'start' => '06:00:00', 'end' => '14:00:00'];
                $dayAssignments[1] = ['type' => 'Opening', 'start' => '06:00:00', 'end' => '14:00:00'];
                $dayAssignments[4] = ['type' => 'Mid',     'start' => '10:00:00', 'end' => '18:00:00'];
                $dayAssignments[3] = ['type' => 'Closing', 'start' => '14:00:00', 'end' => '22:00:00'];
            } elseif ($day === 3) { // Thursday (3 crew)
                $dayAssignments[0] = ['type' => 'Opening', 'start' => '06:00:00', 'end' => '14:00:00'];
                $dayAssignments[4] = ['type' => 'Mid',     'start' => '10:00:00', 'end' => '18:00:00'];
                $dayAssignments[1] = ['type' => 'Closing', 'start' => '14:00:00', 'end' => '22:00:00'];
            } elseif ($day === 4) { // Friday (4 crew)
                $dayAssignments[0] = ['type' => 'Opening', 'start' => '06:00:00', 'end' => '14:00:00'];
                $dayAssignments[1] = ['type' => 'Opening', 'start' => '06:00:00', 'end' => '14:00:00'];
                $dayAssignments[2] = ['type' => 'Mid',     'start' => '10:00:00', 'end' => '18:00:00'];
                $dayAssignments[4] = ['type' => 'Closing', 'start' => '14:00:00', 'end' => '22:00:00'];
            } elseif ($day === 5) { // Saturday (4 crew)
                $dayAssignments[1] = ['type' => 'Opening', 'start' => '06:00:00', 'end' => '14:00:00'];
                $dayAssignments[2] = ['type' => 'Opening', 'start' => '06:00:00', 'end' => '14:00:00'];
                $dayAssignments[4] = ['type' => 'Mid',     'start' => '10:00:00', 'end' => '18:00:00'];
                $dayAssignments[3] = ['type' => 'Closing', 'start' => '14:00:00', 'end' => '22:00:00'];
            } elseif ($day === 6) { // Sunday (3 crew)
                $dayAssignments[1] = ['type' => 'Opening', 'start' => '06:00:00', 'end' => '14:00:00'];
                $dayAssignments[2] = ['type' => 'Mid',     'start' => '10:00:00', 'end' => '18:00:00'];
                $dayAssignments[3] = ['type' => 'Closing', 'start' => '14:00:00', 'end' => '22:00:00'];
            }

            // Adjustments to guarantee ZERO shifts on Approved Leave while keeping 5 working days:
            // 1. Branch 1, Week 2 (2026-10-07): Sofia Garcia (crew 1) takes leave on Wed
            if ($bId === 1 && $w === 2) {
                if ($day === 1) { // Tue: Sofia Garcia works Opening
                    $dayAssignments[1] = ['type' => 'Opening', 'start' => '06:00:00', 'end' => '14:00:00'];
                } elseif ($day === 2) { // Wed: Sofia Garcia on leave (no shift)
                    unset($dayAssignments[1]);
                }
            }

            // 2. Branch 1, Week 4 (2026-10-21): Bea Torres (crew 3) takes leave on Wed
            if ($bId === 1 && $w === 4) {
                if ($day === 2) { // Wed: Bea Torres on leave (no shift)
                    unset($dayAssignments[3]);
                } elseif ($day === 3) { // Thu: Bea Torres works Mid
                    $dayAssignments[3] = ['type' => 'Mid', 'start' => '10:00:00', 'end' => '18:00:00'];
                }
            }

            // 3. Branch 2, Week 3 (2026-10-14): Apolinario Mabini (crew 1) takes leave on Wed
            if ($bId === 2 && $w === 3) {
                if ($day === 1) { // Tue: Apolinario Mabini works Opening
                    $dayAssignments[1] = ['type' => 'Opening', 'start' => '06:00:00', 'end' => '14:00:00'];
                } elseif ($day === 2) { // Wed: Apolinario on leave (no shift)
                    unset($dayAssignments[1]);
                }
            }

            // Seed shifts for current day
            foreach ($dayAssignments as $cIdx => $sh) {
                $empId = (int)$crew[$cIdx]['id'];
                $shiftRow = [
                    'branch_id'      => $bId,
                    'employee_id'    => $empId,
                    'shift_date'     => $curDate,
                    'start_time'     => $sh['start'],
                    'end_time'       => $sh['end'],
                    'shift_type'     => $sh['type'],
                    'status'         => 'PUBLISHED',
                    'created_by'     => $bhrId,
                    'bm_screened_by' => $bmId,
                    'ba_approved_by' => $baId,
                ];

                $allGeneratedShifts[] = $shiftRow;

                demo_insert($pdo, 'shifts', $shiftRow, [
                    'branch_id'   => $bId,
                    'employee_id' => $empId,
                    'shift_date'  => $curDate,
                    'start_time'  => $sh['start'],
                ], 'phase_5');
            }
        }
    }

    // --- Week 6: Next week (10 open shifts, status PENDING_BM) ---
    $w6Start = new DateTime($weeks[6]['start']);
    $w6Assignments = [
        0 => [ // Mon
            ['c_idx' => 0, 'type' => 'Opening', 'start' => '06:00:00', 'end' => '14:00:00'],
            ['c_idx' => 2, 'type' => 'Mid',     'start' => '10:00:00', 'end' => '18:00:00'],
        ],
        1 => [ // Tue
            ['c_idx' => 1, 'type' => 'Opening', 'start' => '06:00:00', 'end' => '14:00:00'],
            ['c_idx' => 3, 'type' => 'Closing', 'start' => '14:00:00', 'end' => '22:00:00'],
        ],
        2 => [ // Wed
            ['c_idx' => 0, 'type' => 'Opening', 'start' => '06:00:00', 'end' => '14:00:00'],
            ['c_idx' => 4, 'type' => 'Mid',     'start' => '10:00:00', 'end' => '18:00:00'],
        ],
        3 => [ // Thu
            ['c_idx' => 1, 'type' => 'Opening', 'start' => '06:00:00', 'end' => '14:00:00'],
            ['c_idx' => 4, 'type' => 'Closing', 'start' => '14:00:00', 'end' => '22:00:00'],
        ],
        4 => [ // Fri
            ['c_idx' => 2, 'type' => 'Opening', 'start' => '06:00:00', 'end' => '14:00:00'],
            ['c_idx' => 3, 'type' => 'Closing', 'start' => '14:00:00', 'end' => '22:00:00'],
        ],
    ];

    foreach ($w6Assignments as $d => $shifts) {
        $curDate = (clone $w6Start)->modify("+{$d} days")->format('Y-m-d');
        foreach ($shifts as $s) {
            $empId = (int)$crew[$s['c_idx']]['id'];
            $shiftRow = [
                'branch_id'      => $bId,
                'employee_id'    => $empId,
                'shift_date'     => $curDate,
                'start_time'     => $s['start'],
                'end_time'       => $s['end'],
                'shift_type'     => $s['type'],
                'status'         => 'PENDING_BM',
                'created_by'     => $bhrId,
                'bm_screened_by' => null,
                'ba_approved_by' => null,
            ];

            $allGeneratedShifts[] = $shiftRow;

            demo_insert($pdo, 'shifts', $shiftRow, [
                'branch_id'   => $bId,
                'employee_id' => $empId,
                'shift_date'  => $curDate,
                'start_time'  => $s['start'],
            ], 'phase_5');
        }
    }
}

// ====================================================================
// VERIFICATION AUDITS
// ====================================================================
echo "[3/3] Generating Verification Audits...\n";
echo "====================================================================\n";
echo "VERIFICATION 1: Shifts Per Branch Per Week Audit\n";
echo "====================================================================\n";

printf("%-6s | %-24s | %-12s | %-12s | %-10s | %-10s\n",
    "Week", "Dates", "Branch 1", "Branch 2", "Branch 3", "Total Shifts"
);
echo str_repeat("-", 85) . "\n";

foreach ($weeks as $wNum => $wInfo) {
    $counts = [];
    $totalWeek = 0;
    foreach ([1, 2, 3] as $bId) {
        if ($DEMO_APPLY) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM shifts
                WHERE branch_id = ? AND shift_date BETWEEN ? AND ?
            ");
            $stmt->execute([$bId, $wInfo['start'], $wInfo['end']]);
            $c = (int)$stmt->fetchColumn();
        } else {
            // Count in-memory generated shifts
            $c = 0;
            foreach ($allGeneratedShifts as $gs) {
                if ($gs['branch_id'] === $bId && $gs['shift_date'] >= $wInfo['start'] && $gs['shift_date'] <= $wInfo['end']) {
                    $c++;
                }
            }
        }
        $counts[$bId] = $c;
        $totalWeek += $c;
    }
    printf("W%-5d | %-24s | %12d | %12d | %12d | %12d\n",
        $wNum,
        "{$wInfo['start']}..{$wInfo['end']}",
        $counts[1], $counts[2], $counts[3], $totalWeek
    );
}

echo str_repeat("-", 85) . "\n";

echo "\n====================================================================\n";
echo "VERIFICATION 2: Overlapping Shifts Audit (Target: 0)\n";
echo "====================================================================\n";

if ($DEMO_APPLY) {
    $overlapStmt = $pdo->query("
        SELECT s1.id AS shift1_id, s2.id AS shift2_id, s1.employee_id, s1.shift_date,
               s1.start_time AS s1_start, s1.end_time AS s1_end,
               s2.start_time AS s2_start, s2.end_time AS s2_end
        FROM shifts s1
        JOIN shifts s2 ON s1.employee_id = s2.employee_id
                      AND s1.shift_date = s2.shift_date
                      AND s1.id < s2.id
                      AND s1.start_time < s2.end_time
                      AND s1.end_time > s2.start_time
    ");
    $overlaps = $overlapStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Check in-memory generated shifts
    $overlaps = [];
    $seen = [];
    foreach ($allGeneratedShifts as $gs) {
        $k = "{$gs['employee_id']}-{$gs['shift_date']}";
        if (isset($seen[$k])) {
            $overlaps[] = $gs;
        }
        $seen[$k] = $gs;
    }
}

if (count($overlaps) === 0) {
    echo "  [PASS] Overlaps Audit: Overlaps = 0 (No overlapping shifts found).\n";
} else {
    echo "  [FAIL] Overlaps Audit: Found " . count($overlaps) . " overlapping shifts!\n";
}

echo "\n====================================================================\n";
echo "VERIFICATION 3: Shifts on Approved Leave Audit (Target: 0)\n";
echo "====================================================================\n";

if ($DEMO_APPLY) {
    $leaveCollisionStmt = $pdo->query("
        SELECT s.id AS shift_id, s.employee_id, s.shift_date, s.start_time, s.end_time,
               lr.id AS leave_id, lr.type AS leave_type, lr.status AS leave_status
        FROM shifts s
        JOIN leave_requests lr ON s.employee_id = lr.employee_id
                              AND s.shift_date BETWEEN lr.date_from AND lr.date_to
        WHERE lr.status = 'APPROVED'
    ");
    $leaveCollisions = $leaveCollisionStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Check in-memory
    $leaveCollisions = [];
    foreach ($leavesToSeed as $l) {
        if ($l['status'] !== 'APPROVED') continue;
        $empId = (int)$branchCrew[$l['branch_id']][$l['crew_idx']]['id'];
        $from = $l['date_from'];
        $to = $l['date_to'];
        foreach ($allGeneratedShifts as $gs) {
            if ($gs['employee_id'] === $empId && $gs['shift_date'] >= $from && $gs['shift_date'] <= $to) {
                $leaveCollisions[] = $gs;
            }
        }
    }
}

if (count($leaveCollisions) === 0) {
    echo "  [PASS] Leave Collision Audit: Shifts on approved leave = 0.\n";
} else {
    echo "  [FAIL] Leave Collision Audit: Found " . count($leaveCollisions) . " shifts scheduled during approved leave!\n";
}

echo "\n====================================================================\n";
echo "VERIFICATION 4: 5 Working Days Per Employee Per Full Week Audit\n";
echo "====================================================================\n";

$allEmployees5Days = true;
$checkedEmpWeeks = 0;

for ($wNum = 1; $wNum <= 5; $wNum++) {
    $wInfo = $weeks[$wNum];
    if ($DEMO_APPLY) {
        $weekStmt = $pdo->prepare("
            SELECT s.employee_id, e.first_name, e.last_name, e.branch_id, COUNT(*) AS days_worked
            FROM shifts s
            JOIN employees e ON s.employee_id = e.id
            WHERE s.shift_date BETWEEN ? AND ?
            GROUP BY s.employee_id, e.first_name, e.last_name, e.branch_id
            ORDER BY e.branch_id ASC, s.employee_id ASC
        ");
        $weekStmt->execute([$wInfo['start'], $wInfo['end']]);
        $weekResults = $weekStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Compute in-memory
        $empCounts = [];
        foreach ($allGeneratedShifts as $gs) {
            if ($gs['shift_date'] >= $wInfo['start'] && $gs['shift_date'] <= $wInfo['end']) {
                $empCounts[$gs['employee_id']] = ($empCounts[$gs['employee_id']] ?? 0) + 1;
            }
        }
        $weekResults = [];
        foreach ($branchCrew as $bId => $cList) {
            foreach ($cList as $c) {
                $weekResults[] = [
                    'employee_id' => (int)$c['id'],
                    'first_name'  => $c['first_name'],
                    'last_name'   => $c['last_name'],
                    'branch_id'   => $bId,
                    'days_worked' => $empCounts[(int)$c['id']] ?? 0
                ];
            }
        }
    }

    foreach ($weekResults as $wr) {
        $checkedEmpWeeks++;
        if ((int)$wr['days_worked'] !== 5) {
            echo "  [FAIL] W{$wNum}: {$wr['first_name']} {$wr['last_name']} (Emp #{$wr['employee_id']}) worked {$wr['days_worked']} days (Expected: 5)\n";
            $allEmployees5Days = false;
        }
    }
}

if ($allEmployees5Days && $checkedEmpWeeks === 75) {
    echo "  [PASS] Workload Audit: All 15 crew employees have EXACTLY 5 working days per full week (75/75 employee-weeks verified).\n";
} else {
    echo "  [FAIL] Workload Audit: Found non-compliant employee-weeks (Total checked: {$checkedEmpWeeks}).\n";
}

echo "\n====================================================================\n";
echo "VERIFICATION 5: Live Shift Board Query Output (This Week: 2026-10-26 to 2026-11-01)\n";
echo "====================================================================\n";

if ($DEMO_APPLY) {
    // Live query extracted from fika_hrms/fika_hrms_shift_board.php lines 87-95
    $boardStmt = $pdo->prepare("
        SELECT s.id, s.shift_date, s.start_time, s.end_time, s.shift_type, s.status,
               e.first_name, e.last_name, e.employee_code,
               u.username as creator_username
        FROM shifts s
        JOIN employees e ON s.employee_id = e.id
        LEFT JOIN users u ON s.created_by = u.id
        WHERE s.branch_id = 1 AND s.shift_date BETWEEN '2026-10-26' AND '2026-11-01'
        ORDER BY s.shift_date ASC, s.start_time ASC
    ");
    $boardStmt->execute();
    $boardRows = $boardStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // In-memory projected board rows
    $boardRows = [];
    $empLookup = [];
    foreach ($branchCrew[1] as $c) {
        $empLookup[(int)$c['id']] = $c;
    }
    foreach ($allGeneratedShifts as $gs) {
        if ($gs['branch_id'] === 1 && $gs['shift_date'] >= '2026-10-26' && $gs['shift_date'] <= '2026-11-01') {
            $c = $empLookup[$gs['employee_id']];
            $boardRows[] = [
                'id'               => 0,
                'shift_date'       => $gs['shift_date'],
                'start_time'       => $gs['start_time'],
                'end_time'         => $gs['end_time'],
                'shift_type'       => $gs['shift_type'],
                'status'           => $gs['status'],
                'first_name'       => $c['first_name'],
                'last_name'        => $c['last_name'],
                'employee_code'    => $c['employee_code'],
                'creator_username' => 'ana.reyes'
            ];
        }
    }
}

printf("%-6s | %-10s | %-17s | %-10s | %-15s | %-10s | %-12s\n",
    "ID", "Date", "Employee", "Code", "Times", "Shift Type", "Status"
);
echo str_repeat("-", 90) . "\n";

foreach ($boardRows as $r) {
    $timeRange = substr($r['start_time'], 0, 5) . " - " . substr($r['end_time'], 0, 5);
    $empName = $r['first_name'] . ' ' . $r['last_name'];
    $idDisplay = $r['id'] > 0 ? (string)$r['id'] : "DRY";
    printf("%-6s | %-10s | %-17s | %-10s | %-15s | %-10s | %-12s\n",
        $idDisplay, $r['shift_date'], $empName, $r['employee_code'], $timeRange, $r['shift_type'], $r['status']
    );
}

echo str_repeat("-", 90) . "\n";
echo "Live Shift Board query for Branch 1 returned " . count($boardRows) . " shifts for this week.\n\n";
