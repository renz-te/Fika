<?php
/**
 * tools/demo_seed/phase_9.php
 *
 * Phase D9: Operating Expenses and Branch Budgets
 *
 * Requirements:
 * 1. Target Branches: 1 (General Trias), 2 (Trece Martires), 3 (Imus).
 * 2. Timeframe: Last 3 calendar months (August, September, October 2026).
 * 3. Expenses:
 *    - Uses existing expense_categories (Rent, Utilities, Supplies, Maintenance, Marketing, Miscellaneous).
 *    - Realistic amounts, payees, notes.
 *    - Statuses: Mostly APPROVED, a few DRAFT (pending), exactly ONE VOIDED with void_reason.
 *    - Real created_by (Branch Manager) and approved_by (Branch Accountant).
 *    - amount_cents strictly synchronized.
 * 4. Budgets:
 *    - Insert into branch_budgets for allocated labor budget per branch per month.
 *    - September: 5-10% above D7 released payroll.
 *    - August & October: Realistic baseline of ₱150,000.00 (since no released payroll).
 *    - allocated_amount_cents == round(allocated_labor_budget * 100).
 * 5. Auto-seed check: Verifies fika_finance/fika_finance_budgets.php does NOT auto-seed on GET.
 * 6. Idempotent: Second apply writes 0 rows.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

global $pdo, $DEMO_APPLY;

if (!function_exists('demo_track_record')) {
    function demo_track_record(PDO $pdo, string $table, int $rowId, string $phase = 'phase_9'): void {
        $trackStmt = $pdo->prepare("
            INSERT INTO `demo_seed` (`phase`, `table_name`, `row_id`, `created_at`) 
            VALUES (?, ?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE `phase` = VALUES(`phase`)
        ");
        $trackStmt->execute([$phase, $table, $rowId]);
    }
}

echo "====================================================================\n";
echo "Phase D9: Operating Expenses and Branch Budgets\n";
echo "====================================================================\n";

$branches = [
    1 => 'General Trias Branch',
    2 => 'Trece Martires Branch',
    3 => 'Imus Branch'
];

$branchManagers = [
    1 => 10007, // Juan Dela Cruz (BM Branch 1)
    2 => 10015, // Andres Bonifacio (BM Branch 2)
    3 => 10023  // Jose Laurel (BM Branch 3)
];

$branchAccountants = [
    1 => 10009, // Pedro Santos (BA Branch 1)
    2 => 10017, // Emilio Aguinaldo (BA Branch 2)
    3 => 10025  // Sergio Osmeña (BA Branch 3)
];

$globalAccountantId = 2; // Jose Rizal (Global Accountant)

// --------------------------------------------------------------------
// AUTO-SEED CHECK (Task Item 3)
// --------------------------------------------------------------------
echo "[1/4] Running Auto-Seed Safety Check on fika_finance_budgets.php...\n";
$budgetsFile = APP_ROOT . '/fika_finance/fika_finance_budgets.php';
$budgetsCode = file_get_contents($budgetsFile);

// Check if file contains dangerous INSERT / UPDATE queries outside POST handlers
if (preg_match('/INSERT\s+INTO\s+branch_budgets/i', $budgetsCode) || preg_match('/INSERT\s+INTO\s+expenses/i', $budgetsCode)) {
    fwrite(STDERR, "CRITICAL STOP: fika_finance_budgets.php contains auto-seeding write logic! STOPPING per instructions.\n");
    exit(1);
}
echo "  [PASS] Auto-seed check passed: fika_finance_budgets.php contains NO auto-seed write queries.\n";

// --------------------------------------------------------------------
// IDEMPOTENCY CHECK
// --------------------------------------------------------------------
$existingExpenses = (int)$pdo->query("SELECT COUNT(*) FROM demo_seed WHERE phase = 'phase_9' AND table_name = 'expenses'")->fetchColumn();
$existingBudgets  = (int)$pdo->query("SELECT COUNT(*) FROM demo_seed WHERE phase = 'phase_9' AND table_name = 'branch_budgets'")->fetchColumn();

if ($existingExpenses > 0 && $existingBudgets > 0) {
    echo "\n  [SKIPPED] Phase D9 demo expenses and budgets already seeded ({$existingExpenses} expenses, {$existingBudgets} budgets).\n";
    demo_stats_record('expenses', 'skipped', $existingExpenses);
    demo_stats_record('branch_budgets', 'skipped', $existingBudgets);

    // Proceed directly to verifications
    require __DIR__ . '/phase_9_verifications.php';
    return;
}

// --------------------------------------------------------------------
// TASK 1: Seed Branch Monthly Labor Budgets
// --------------------------------------------------------------------
echo "\n[2/4] Planning & Seeding Monthly Branch Labor Budgets...\n";

// Fetch D7 Released Payroll labor cost per branch for September 2026
$septPayrollStmt = $pdo->prepare("
    SELECT COALESCE(SUM(ROUND((
               CASE 
                   WHEN pi.gross_pay > 0 THEN pi.gross_pay 
                   ELSE (pi.basic_pay + pi.overtime_pay + pi.bonus_pay) 
               END 
               + pi.employer_sss + pi.employer_philhealth + pi.employer_pagibig
           ) * 100)), 0) / 100 AS released_cost
    FROM payroll_items pi
    JOIN payroll_runs pr ON pi.payroll_run_id = pr.id
    WHERE pr.status = 'RELEASED'
      AND pr.branch_id = ?
      AND pr.period_end BETWEEN '2026-09-01' AND '2026-09-30'
");

$budgetPlans = [];
$targetMonths = ['2026-08', '2026-09', '2026-10'];

foreach (array_keys($branches) as $bId) {
    $septPayrollStmt->execute([$bId]);
    $septReleased = (float)$septPayrollStmt->fetchColumn();

    foreach ($targetMonths as $m) {
        if ($m === '2026-09' && $septReleased > 0) {
            // Set budget to ~7.5% above released payroll, rounded to nearest ₱100
            $laborBudget = round(($septReleased * 1.075) / 100) * 100;
            $note = sprintf("Based on Sep released payroll ₱%s + 7.5%% buffer", number_format($septReleased, 2));
        } else {
            // Realistic baseline of ₱150,000 for August & October
            $laborBudget = 150000.00;
            $note = "Baseline allocation (no released payroll runs for this month)";
        }

        $budgetCents = (int)round($laborBudget * 100);

        $budgetPlans[] = [
            'branch_id'              => $bId,
            'budget_month'           => $m,
            'allocated_amount_cents' => $budgetCents,
            'allocated_labor_budget' => $laborBudget,
            'created_by'             => $globalAccountantId,
            'note'                   => $note,
            'created_at'             => "{$m}-01 08:00:00"
        ];
    }
}

$budgetInsertStmt = $pdo->prepare("
    INSERT INTO branch_budgets (
        branch_id, budget_month, allocated_amount_cents, created_by,
        allocated_labor_budget, created_at, updated_at
    ) VALUES (
        ?, ?, ?, ?,
        ?, ?, ?
    )
");

if ($DEMO_APPLY) {
    $pdo->beginTransaction();
}

try {
    $budgetsCreated = 0;
    foreach ($budgetPlans as $bp) {
    if ($DEMO_APPLY) {
        $budgetInsertStmt->execute([
            $bp['branch_id'],
            $bp['budget_month'],
            $bp['allocated_amount_cents'],
            $bp['created_by'],
            $bp['allocated_labor_budget'],
            $bp['created_at'],
            $bp['created_at']
        ]);
        $bId = (int)$pdo->lastInsertId();
        demo_track_record($pdo, 'branch_budgets', $bId, 'phase_9');
        demo_stats_record('branch_budgets', 'created', 1);
        $budgetsCreated++;
    } else {
        demo_stats_record('branch_budgets', 'would_create', 1);
        $budgetsCreated++;
    }
}

if ($DEMO_APPLY) {
    echo "  [CREATED]  Seeded {$budgetsCreated} monthly branch budget allocations.\n";
} else {
    echo "  [DRY-RUN] Would create {$budgetsCreated} monthly branch budget allocations.\n";
}

// --------------------------------------------------------------------
// TASK 2: Seed Operating Expenses
// --------------------------------------------------------------------
echo "\n[3/4] Planning & Seeding Operational Expenses Across 3 Branches & 3 Months...\n";

// Vendor / Payee directory by category and branch
$payeeDirectory = [
    1 => [ // Rent
        1 => 'Trias Commercial Leasing Inc.',
        2 => 'Trece Martires Retail Properties Corp.',
        3 => 'Imus Central Realty Holdings Inc.'
    ],
    2 => [ // Utilities
        1 => 'Meralco / Maynilad Cavite',
        2 => 'Meralco / Primewater Trece Martires',
        3 => 'Meralco / Maynilad Imus Business Center'
    ],
    3 => [ // Supplies
        1 => 'Cavite Dairy & Specialty Wholesalers',
        2 => 'South Luzon Barista Supplies & Packaging',
        3 => 'Mega Manila Beverage Essentials Corp.'
    ],
    4 => [ // Maintenance
        1 => 'Barista Tech Espresso Machine Maintenance',
        2 => 'CoolBreeze Commercial HVAC Services',
        3 => 'Cavite Kitchen & Appliance Service Center'
    ],
    5 => [ // Marketing
        1 => 'Meta Platforms PH / Cavite Local Ads',
        2 => 'Cavite Print & Signage Solutions',
        3 => 'Barangay Community Event Sponsorship'
    ],
    6 => [ // Miscellaneous
        1 => 'Puregold Price Club General Trias',
        2 => 'SM City Trece Martires Supermarket',
        3 => 'Office Warehouse Imus Branch'
    ]
];

$expenseTemplates = [
    // Cat ID => [dayOfMonth, baseAmountPesos, notes]
    1 => ['day' => '05', 'amount' => 35000.00, 'notes' => 'Monthly commercial retail space lease payment.'],
    2 => ['day' => '18', 'amount' => 9500.00,  'notes' => 'Electricity and commercial water utility billing for coffee shop operations.'],
    3 => ['day' => '10', 'amount' => 14200.00, 'notes' => 'Paper cups, dome lids, straws, napkins, and commercial cleaning supplies.'],
    4 => ['day' => '15', 'amount' => 5800.00,  'notes' => 'Quarterly preventative espresso machine tune-up and water filter replacement.'],
    5 => ['day' => '22', 'amount' => 6500.00,  'notes' => 'Local digital geo-targeted promotions and in-store loyalty program flyers.'],
    6 => ['day' => '25', 'amount' => 2800.00,  'notes' => 'Staff pantry coffee/snacks, thermal register receipt rolls, first aid supplies.']
];

$expensePlans = [];
$voidAssigned = false;

foreach ($targetMonths as $m) {
    foreach (array_keys($branches) as $bId) {
        $bmId = $branchManagers[$bId];
        $baId = $branchAccountants[$bId];

        foreach ($expenseTemplates as $catId => $tmpl) {
            $day = $tmpl['day'];
            $expDate = "{$m}-{$day}";
            $baseAmt = $tmpl['amount'];

            // Introduce realistic branch/month variance (+/- 5%)
            $variance = 1.0 + (((($bId * 17) + ($catId * 31) + (int)substr($m, 5, 2) * 11) % 11) - 5) / 100.0;
            $finalAmount = round(($baseAmt * $variance) / 10) * 10;
            $cents = (int)round($finalAmount * 100);

            $payee = $payeeDirectory[$catId][$bId];
            $notes = $tmpl['notes'];
            $voidReason = null;

            // Status assignment:
            // August & September: 100% APPROVED
            // October:
            //   - Exactly ONE VOIDED on Branch 2 Maintenance
            //   - 3 DRAFT on late October (Oct 22-25)
            //   - Remainder APPROVED
            if ($m === '2026-10') {
                if ($bId === 2 && $catId === 4 && !$voidAssigned) {
                    $status = 'VOIDED';
                    $approvedBy = $baId;
                    $voidReason = 'Duplicate invoice submitted by vendor; corrected on invoice #CB-2026-904.';
                    $voidAssigned = true;
                } elseif ($catId === 5 && $bId === 1) { // Branch 1 Marketing (Oct 22)
                    $status = 'DRAFT';
                    $approvedBy = null;
                } elseif ($catId === 6 && $bId === 2) { // Branch 2 Misc (Oct 25)
                    $status = 'DRAFT';
                    $approvedBy = null;
                } elseif ($catId === 6 && $bId === 3) { // Branch 3 Misc (Oct 25)
                    $status = 'DRAFT';
                    $approvedBy = null;
                } else {
                    $status = 'APPROVED';
                    $approvedBy = $baId;
                }
            } else {
                $status = 'APPROVED';
                $approvedBy = $baId;
            }

            $expensePlans[] = [
                'branch_id'    => $bId,
                'category_id'  => $catId,
                'amount_cents' => $cents,
                'amount_pesos' => $finalAmount,
                'expense_date' => $expDate,
                'payee'        => $payee,
                'notes'        => $notes,
                'status'       => $status,
                'created_by'   => $bmId,
                'approved_by'  => $approvedBy,
                'void_reason'  => $voidReason,
                'created_at'   => "{$expDate} 09:15:00"
            ];
        }
    }
}

$expInsertStmt = $pdo->prepare("
    INSERT INTO expenses (
        branch_id, category_id, amount_cents, expense_date, payee, notes,
        receipt_path, status, created_by, approved_by, void_reason, created_at, updated_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?,
        NULL, ?, ?, ?, ?, ?, ?
    )
");

$expensesCreated = 0;
foreach ($expensePlans as $ep) {
    if ($DEMO_APPLY) {
        $expInsertStmt->execute([
            $ep['branch_id'],
            $ep['category_id'],
            $ep['amount_cents'],
            $ep['expense_date'],
            $ep['payee'],
            $ep['notes'],
            $ep['status'],
            $ep['created_by'],
            $ep['approved_by'],
            $ep['void_reason'],
            $ep['created_at'],
            $ep['created_at']
        ]);
        $eId = (int)$pdo->lastInsertId();
        demo_track_record($pdo, 'expenses', $eId, 'phase_9');
        demo_stats_record('expenses', 'created', 1);
        $expensesCreated++;
    } else {
        demo_stats_record('expenses', 'would_create', 1);
        $expensesCreated++;
    }
}

    if ($DEMO_APPLY) {
        $pdo->commit();
        echo "  [CREATED]  Seeded {$expensesCreated} operational expenses across 3 branches.\n";
    } else {
        echo "  [DRY-RUN] Would create {$expensesCreated} operational expenses across 3 branches.\n";
    }
} catch (\Throwable $e) {
    if ($DEMO_APPLY && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "ERROR: Phase 9 seeding failed: " . $e->getMessage() . "\n");
    throw $e;
}

// --------------------------------------------------------------------
// RUN VERIFICATION & REPORTING SUITE
// --------------------------------------------------------------------
require __DIR__ . '/phase_9_verifications.php';
