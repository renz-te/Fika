<?php
require_once __DIR__ . '/init.php';
require_login();
$user = current_user();

// Centralized role identification
$userRole = $_SESSION['user']['role'] ?? ($user['role'] ?? '');
$userBranchId = (int)($_SESSION['user']['branch_id'] ?? ($user['branch_id'] ?? 1));

$isBHR = in_array($userRole, ['Branch HR', 'HR', ROLE_CENTRAL_HR, 'Central HR', 'HR Manager']);
$isBA = in_array($userRole, ['Branch Accountant', ROLE_BRANCH_ACCOUNTANT, ROLE_GLOBAL_ACCOUNTANT]);
$isBM = in_array($userRole, ['Branch Manager', ROLE_BRANCH_MANAGER]);
$isAdmin = in_array($userRole, [ROLE_SUPER_ADMIN, 'Admin', ROLE_EXECUTIVE]);

$allowed_roles = [
    ROLE_SUPER_ADMIN, ROLE_EXECUTIVE, 'Admin',
    ROLE_CENTRAL_HR, 'Central HR', 'Branch HR', 'HR', 'HR Manager',
    ROLE_GLOBAL_ACCOUNTANT, ROLE_BRANCH_ACCOUNTANT, 'Branch Accountant',
    ROLE_BRANCH_MANAGER, 'Branch Manager'
];
if (!in_array($userRole, $allowed_roles)) {
    http_response_code(403);
    die("Access denied.");
}

// Branch scoping rules:
// Branch HR, Branch Accountant, and Branch Manager are locked to their own branch.
// Central HR, Global Accountant, and Admin can view all branches or filter.
$isBranchScoped = in_array($userRole, ['Branch HR', 'Branch Accountant', 'Branch Manager', ROLE_BRANCH_MANAGER, ROLE_BRANCH_ACCOUNTANT]);
$filterBranchId = null;

if ($isBranchScoped) {
    $filterBranchId = $userBranchId;
} elseif (!empty($_GET['branch_id'])) {
    $filterBranchId = (int)$_GET['branch_id'];
}

$branchFilterP = $filterBranchId ? " AND p.branch_id = {$filterBranchId}" : "";
$branchFilterE = $filterBranchId ? " AND e.branch_id = {$filterBranchId}" : "";

$message = null;
$error = null;

// =========================================================================
// POST ACTION HANDLERS WITH STRICT SERVER-SIDE ENFORCEMENT
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF token validation failed.');
    }
    $action = $_POST['action'] ?? '';

    // --- 1. GENERATE DRAFTS (BHR and Admin only) ---
    if ($action === 'generate_drafts') {
        if (!$isBHR && !$isAdmin) {
            http_response_code(403);
            die("Access denied: Only HR roles can generate payroll drafts.");
        }

        $periodParts = explode('|', $_POST['payroll_period']);
        $periodStart = $periodParts[0];
        $periodEnd = $periodParts[1];
        $targetBranchId = $filterBranchId ?: ($userBranchId ?: 1);

        $employeesQuery = $pdo->prepare('SELECT *, CONCAT(first_name, " ", last_name) AS full_name FROM employees WHERE (status IN ("Active", "Trainee") OR (status = "Terminated" AND termination_date >= ?))' . $branchFilterE);
        $employeesQuery->execute([$periodStart]);
        $employees = $employeesQuery->fetchAll();
        $generated = 0;

        $pdo->beginTransaction();
        try {
            // Create or fetch the parent payroll_run record
            $runStmt = $pdo->prepare("INSERT INTO payroll_runs (period_start, period_end, branch_id, status, created_by)
                                      VALUES (?, ?, ?, 'Draft', ?)
                                      ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
            $runStmt->execute([$periodStart, $periodEnd, $targetBranchId, $user['id']]);
            $runId = $pdo->lastInsertId();

            foreach ($employees as $emp) {
                // Check if record already exists for this employee and period
                $check = $pdo->prepare('SELECT id FROM payroll WHERE employee_id = ? AND period_start = ? AND period_end = ?');
                $check->execute([$emp['id'], $periodStart, $periodEnd]);
                if ($check->fetchColumn()) continue;

                // ── 1. Calculate Time & Attendance ──
                $attStmt = $pdo->prepare('SELECT DATE(time_in) as d, time_in, time_out, break_in, break_out, overtime_hours, night_differential_hours, late_minutes FROM attendance WHERE employee_id = ? AND DATE(time_in) BETWEEN ? AND ? AND time_out IS NOT NULL');
                $attStmt->execute([$emp['id'], $periodStart, $periodEnd]);

                $totalWorkMins = 0;
                $totalOtHrs = 0;
                $totalNdHrs = 0;
                $totalLateMins = 0;
                $attDays = [];

                while ($row = $attStmt->fetch()) {
                    $attDays[] = $row['d'];
                    $timeIn = strtotime($row['time_in']);
                    $timeOut = strtotime($row['time_out']);
                    $mins = ($timeOut - $timeIn) / 60;

                    // Subtract break time
                    if (!empty($row['break_in']) && !empty($row['break_out'])) {
                        $breakIn = strtotime($row['break_in']);
                        $breakOut = strtotime($row['break_out']);
                        if ($breakOut > $breakIn) {
                            $mins -= ($breakOut - $breakIn) / 60;
                        }
                    }

                    $totalWorkMins += max(0, $mins);
                    $totalOtHrs += (float)$row['overtime_hours'];
                    $totalNdHrs += (float)$row['night_differential_hours'];
                    $totalLateMins += (int)$row['late_minutes'];
                }
                $workedHours = round($totalWorkMins / 60, 2);

                // ── 2. Count Approved Leave Days ──
                $leaveDays = 0;
                $leaveStmt = $pdo->prepare('SELECT start_date, end_date, leave_type FROM leaves WHERE employee_id = ? AND status = "Approved" AND leave_type != "Unpaid Leave" AND (start_date <= ? AND end_date >= ?)');
                $leaveStmt->execute([$emp['id'], $periodEnd, $periodStart]);
                $leaves = $leaveStmt->fetchAll();
                foreach ($leaves as $l) {
                    $current = strtotime(max($periodStart, $l['start_date']));
                    $endLeave = strtotime(min($periodEnd, $l['end_date']));
                    while ($current <= $endLeave) {
                        $dateStr = date('Y-m-d', $current);
                        $dayOfWeek = date('N', $current);
                        if ($dayOfWeek < 6 && !in_array($dateStr, $attDays)) {
                            $leaveDays++;
                        }
                        $current = strtotime('+1 day', $current);
                    }
                }

                // ── 3. Calculate Gross Pay & Premium Pays ──
                $hourlyRate = (float)$emp['hourly_rate'];
                $paidLeaveHours = $leaveDays * 8;
                $regularHours = max(0, $workedHours - $totalOtHrs) + $paidLeaveHours;

                $otPay = $totalOtHrs * $hourlyRate * 1.25;
                $ndPay = $totalNdHrs * $hourlyRate * 0.10;
                $lateDeduction = ($totalLateMins / 60) * $hourlyRate;

                $grossPay = ($regularHours * $hourlyRate) + $otPay + $ndPay;
                $taxableIncome = $grossPay - $lateDeduction;

                // Estimate monthly equivalent for statutory calculations
                $monthlyGrossEstimate = $taxableIncome * 2;

                // ── 4. Philippine Statutory Contributions ──
                $sssCalc = calculate_sss($pdo, $monthlyGrossEstimate);
                $sss_ee = round($sssCalc['employee'] / 2, 2);
                $sss_er = round($sssCalc['employer'] / 2, 2);

                $philCalc = calculate_philhealth($monthlyGrossEstimate);
                $phil_ee = round($philCalc['employee'] / 2, 2);
                $phil_er = round($philCalc['employer'] / 2, 2);

                $pagCalc = calculate_pagibig($monthlyGrossEstimate);
                $pag_ee = round($pagCalc['employee'] / 2, 2);
                $pag_er = round($pagCalc['employer'] / 2, 2);

                // ── 5. BIR Withholding Tax ──
                $taxableIncomeForBIR = $taxableIncome - $sss_ee - $phil_ee - $pag_ee;
                $tax = calculate_withholding_tax_semimonthly($taxableIncomeForBIR);

                $totalDeductions = $sss_ee + $phil_ee + $pag_ee + $tax + $lateDeduction;

                // ── 6. Pending Bonuses ──
                $bonusStmt = $pdo->prepare('SELECT id, amount FROM pending_bonuses WHERE employee_id = ? AND status = "Pending"');
                $bonusStmt->execute([$emp['id']]);
                $pendingBonuses = $bonusStmt->fetchAll();
                $bonusAmount = 0;
                $bonusIds = [];
                foreach ($pendingBonuses as $pb) {
                    $bonusAmount += (float)$pb['amount'];
                    $bonusIds[] = $pb['id'];
                }

                $netPay = max(0, $grossPay + $bonusAmount - $totalDeductions);

                // ── 7. Insert Payroll Record ──
                $stmt = $pdo->prepare('INSERT INTO payroll
                    (employee_id, branch_id, run_id, hourly_rate, regular_hours,
                     overtime_hours, overtime_pay, night_differential, late_deduction,
                     absent_deduction, tax, sss, philhealth, pagibig,
                     employer_sss, employer_philhealth, employer_pagibig,
                     gross_pay, bonus_amount, deductions, net_pay, status,
                     period_start, period_end, created_at)
                    VALUES (?, ?, ?, ?, ?,
                            ?, ?, ?, ?,
                            0, ?, ?, ?, ?,
                            ?, ?, ?,
                            ?, ?, ?, ?, "Draft",
                            ?, ?, NOW())');
                $stmt->execute([
                    $emp['id'], $emp['branch_id'] ?? $targetBranchId, $runId, $hourlyRate, $regularHours,
                    $totalOtHrs, $otPay, $ndPay, $lateDeduction,
                    $tax, $sss_ee, $phil_ee, $pag_ee,
                    $sss_er, $phil_er, $pag_er,
                    $grossPay, $bonusAmount, $totalDeductions, $netPay,
                    $periodStart, $periodEnd
                ]);

                $payrollId = $pdo->lastInsertId();

                if (!empty($bonusIds)) {
                    $inQuery = implode(',', array_fill(0, count($bonusIds), '?'));
                    $updateBonus = $pdo->prepare("UPDATE pending_bonuses SET status = 'Processed', payroll_id = ? WHERE id IN ($inQuery)");
                    $updateBonus->execute(array_merge([$payrollId], $bonusIds));
                }

                $generated++;
            }

            $pdo->commit();
            $message = "Generated $generated payroll drafts for period $periodStart to $periodEnd.";
            log_activity($pdo, $user['id'], 'generate_drafts', "Generated $generated drafts for period $periodStart to $periodEnd (Run ID: $runId)");
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error generating payroll: " . $e->getMessage();
        }

    // --- 2. CLEAR DRAFTS (BHR and Admin only) ---
    } elseif ($action === 'clear_drafts') {
        if (!$isBHR && !$isAdmin) {
            http_response_code(403);
            die("Access denied: Only HR roles can clear payroll drafts.");
        }
        $periodParts = explode('|', $_POST['payroll_period']);
        $periodStart = $periodParts[0];
        $periodEnd = $periodParts[1];

        $pdo->beginTransaction();
        try {
            if ($filterBranchId) {
                $draftIds = $pdo->prepare('SELECT p.id FROM payroll p JOIN employees e ON p.employee_id = e.id WHERE p.period_start = ? AND p.period_end = ? AND p.status IN ("Draft", "Rejected") AND p.branch_id = ?');
                $draftIds->execute([$periodStart, $periodEnd, $filterBranchId]);
            } else {
                $draftIds = $pdo->prepare('SELECT id FROM payroll WHERE period_start = ? AND period_end = ? AND status IN ("Draft", "Rejected")');
                $draftIds->execute([$periodStart, $periodEnd]);
            }
            $ids = $draftIds->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($ids)) {
                $inQuery = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("UPDATE pending_bonuses SET status = 'Pending', payroll_id = NULL WHERE payroll_id IN ($inQuery)")->execute($ids);
                $pdo->prepare("DELETE FROM payroll WHERE id IN ($inQuery)")->execute($ids);
            }
            $deleted = count($ids);
            $pdo->exec("DELETE FROM payroll_runs WHERE status = 'Draft' AND id NOT IN (SELECT DISTINCT COALESCE(run_id,0) FROM payroll)");

            $pdo->commit();
            $message = "Cleared $deleted draft(s) for period $periodStart to $periodEnd.";
            log_activity($pdo, $user['id'], 'clear_drafts', "Cleared $deleted drafts for period $periodStart to $periodEnd");
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error clearing drafts: " . $e->getMessage();
        }

    // --- 3. SUBMIT FOR APPROVAL (BHR -> BA): Draft / Rejected -> Pending Approval ---
    } elseif ($action === 'submit_approval') {
        if (!$isBHR && !$isAdmin) {
            http_response_code(403);
            die("Access denied: Only HR roles can submit payroll for approval.");
        }

        $payrollId = (int)($_POST['payroll_id'] ?? 0);
        $chk = $pdo->prepare("SELECT status, run_id FROM payroll WHERE id = ?");
        $chk->execute([$payrollId]);
        $row = $chk->fetch();

        if (!$row || !in_array($row['status'], ['Draft', 'Rejected'])) {
            http_response_code(400);
            die("Bad Request: Only records in Draft or Rejected state can be submitted for approval.");
        }

        $stmt = $pdo->prepare("UPDATE payroll SET status = 'Pending Approval', submitted_by = ?, rejection_reason = NULL WHERE id = ?");
        $stmt->execute([$user['id'], $payrollId]);

        if (!empty($row['run_id'])) {
            $pdo->prepare("UPDATE payroll_runs SET status = 'Pending Approval', submitted_by = ? WHERE id = ?")
                ->execute([$user['id'], $row['run_id']]);
        }

        $message = "Payroll record submitted for Branch Accountant review.";
        log_activity($pdo, $user['id'], 'submit_approval', "Submitted payroll ID $payrollId for BA approval");

    // --- 4. APPROVE & SEND TO BM (BA -> BM): Pending Approval -> Approved ---
    } elseif ($action === 'approve') {
        if (!$isBA && !$isAdmin) {
            http_response_code(403);
            die("Access denied: Only Finance/Accountant roles can approve payroll.");
        }

        $payrollId = (int)($_POST['payroll_id'] ?? 0);
        $chk = $pdo->prepare("SELECT status, run_id FROM payroll WHERE id = ?");
        $chk->execute([$payrollId]);
        $row = $chk->fetch();

        if (!$row || $row['status'] !== 'Pending Approval') {
            http_response_code(400);
            die("Bad Request: Only payroll records in Pending Approval state can be approved.");
        }

        $stmt = $pdo->prepare("UPDATE payroll SET status = 'Approved', approved_by = ?, rejection_reason = NULL WHERE id = ?");
        $stmt->execute([$user['id'], $payrollId]);

        if (!empty($row['run_id'])) {
            $rem = $pdo->prepare("SELECT COUNT(*) FROM payroll WHERE run_id = ? AND status != 'Approved'");
            $rem->execute([$row['run_id']]);
            if ($rem->fetchColumn() == 0) {
                $pdo->prepare("UPDATE payroll_runs SET status = 'Approved', approved_by = ? WHERE id = ?")
                    ->execute([$user['id'], $row['run_id']]);
            }
        }

        $message = "Payroll approved by Branch Accountant and forwarded to Branch Manager for release.";
        log_activity($pdo, $user['id'], 'approve_payroll', "Approved payroll ID $payrollId (Sent to BM)");

    // --- 5. REJECT PAYROLL (BA -> BHR): Pending Approval -> Rejected with mandatory reason ---
    } elseif ($action === 'reject') {
        if (!$isBA && !$isAdmin) {
            http_response_code(403);
            die("Access denied: Only Finance/Accountant roles can reject payroll.");
        }

        $payrollId = (int)($_POST['payroll_id'] ?? 0);
        $reason = trim($_POST['rejection_reason'] ?? '');

        if (empty($reason)) {
            http_response_code(400);
            die("Bad Request: A rejection reason is required.");
        }

        $chk = $pdo->prepare("SELECT status, run_id FROM payroll WHERE id = ?");
        $chk->execute([$payrollId]);
        $row = $chk->fetch();

        if (!$row || $row['status'] !== 'Pending Approval') {
            http_response_code(400);
            die("Bad Request: Only payroll records in Pending Approval state can be rejected.");
        }

        $stmt = $pdo->prepare("UPDATE payroll SET status = 'Rejected', rejection_reason = ? WHERE id = ?");
        $stmt->execute([$reason, $payrollId]);

        if (!empty($row['run_id'])) {
            $pdo->prepare("UPDATE payroll_runs SET status = 'Rejected', rejection_reason = ? WHERE id = ?")
                ->execute([$reason, $row['run_id']]);
        }

        $message = "Payroll returned to HR as Rejected with feedback.";
        log_activity($pdo, $user['id'], 'reject_payroll', "Rejected payroll ID $payrollId: $reason");

    // --- 6. RELEASE PAYROLL (BM): Approved -> Released ---
    } elseif ($action === 'release') {
        // Server-side guard: Only BM and Super Admin can release payroll!
        if ($isBHR && !$isAdmin) {
            http_response_code(403);
            die("Access denied: Branch HR cannot release payroll.");
        }
        if ($isBA && !$isAdmin) {
            http_response_code(403);
            die("Access denied: Branch Accountants cannot release payroll.");
        }
        if (!$isBM && !$isAdmin) {
            http_response_code(403);
            die("Access denied: Only Branch Managers have permission to release payroll.");
        }

        $payrollId = (int)($_POST['payroll_id'] ?? 0);
        $chk = $pdo->prepare("SELECT status, run_id FROM payroll WHERE id = ?");
        $chk->execute([$payrollId]);
        $row = $chk->fetch();

        if (!$row) {
            http_response_code(404);
            die("Error: Payroll record not found.");
        }

        // Must be in Approved state before releasing
        if ($row['status'] !== 'Approved') {
            http_response_code(400);
            die("Bad Request: Payroll record must be in 'Approved' state before it can be Released.");
        }

        $paymentMethod = trim($_POST['payment_method'] ?? 'Cash');
        $allowed_methods = ['Cash', 'Bank Transfer', 'eWallet'];
        if (!in_array($paymentMethod, $allowed_methods)) {
            $paymentMethod = 'Cash';
        }

        $stmt = $pdo->prepare('UPDATE payroll SET status = "Released", payment_method = ?, released_by = ? WHERE id = ? AND status = "Approved"');
        $stmt->execute([$paymentMethod, $user['id'], $payrollId]);

        if (!empty($row['run_id'])) {
            $rem = $pdo->prepare('SELECT COUNT(*) FROM payroll WHERE run_id = ? AND status != "Released"');
            $rem->execute([$row['run_id']]);
            if ($rem->fetchColumn() == 0) {
                $pdo->prepare('UPDATE payroll_runs SET status = "Released", released_by = ? WHERE id = ?')
                    ->execute([$user['id'], $row['run_id']]);
            }
        }

        $pStmt = $pdo->prepare('SELECT p.*, e.email FROM payroll p JOIN employees e ON e.id = p.employee_id WHERE p.id = ?');
        $pStmt->execute([$payrollId]);
        $p = $pStmt->fetch();

        $message = "Payroll Released via {$paymentMethod}. Notification logged for {$p['email']}.";
        log_activity($pdo, $user['id'], 'release_payroll', "Released payroll ID $payrollId via $paymentMethod");
    }
}

// =========================================================================
// FETCH DATASETS WITH BRANCH SCOPING
// =========================================================================
$branches_list = $pdo->query("SELECT id, name FROM branches WHERE status = 'Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Drafts tab includes Draft and Rejected records
$drafts = $pdo->query('
    SELECT p.*, CONCAT(e.first_name, " ", e.last_name) AS full_name, e.employment_category, b.name as branch_name,
           u_sub.name as submitter_name, u_app.name as approver_name
    FROM payroll p 
    JOIN employees e ON e.id = p.employee_id 
    LEFT JOIN branches b ON b.id = p.branch_id
    LEFT JOIN users u_sub ON u_sub.id = p.submitted_by
    LEFT JOIN users u_app ON u_app.id = p.approved_by
    WHERE p.status IN ("Draft", "Rejected") ' . $branchFilterP . ' 
    ORDER BY (p.status = "Rejected") DESC, p.created_at DESC
')->fetchAll();

// Pending BA approval
$pending = $pdo->query('
    SELECT p.*, CONCAT(e.first_name, " ", e.last_name) AS full_name, e.employment_category, b.name as branch_name,
           u_sub.name as submitter_name
    FROM payroll p 
    JOIN employees e ON e.id = p.employee_id 
    LEFT JOIN branches b ON b.id = p.branch_id
    LEFT JOIN users u_sub ON u_sub.id = p.submitted_by
    WHERE p.status = "Pending Approval" ' . $branchFilterP . ' 
    ORDER BY p.created_at DESC
')->fetchAll();

// Approved (Ready for BM release)
$approved = $pdo->query('
    SELECT p.*, CONCAT(e.first_name, " ", e.last_name) AS full_name, e.employment_category, b.name as branch_name,
           u_sub.name as submitter_name, u_app.name as approver_name
    FROM payroll p 
    JOIN employees e ON e.id = p.employee_id 
    LEFT JOIN branches b ON b.id = p.branch_id
    LEFT JOIN users u_sub ON u_sub.id = p.submitted_by
    LEFT JOIN users u_app ON u_app.id = p.approved_by
    WHERE p.status = "Approved" ' . $branchFilterP . ' 
    ORDER BY p.created_at DESC
')->fetchAll();

// Released history
$released = $pdo->query('
    SELECT p.*, CONCAT(e.first_name, " ", e.last_name) AS full_name, e.employment_category, b.name as branch_name,
           u_rel.name as releaser_name
    FROM payroll p 
    JOIN employees e ON e.id = p.employee_id 
    LEFT JOIN branches b ON b.id = p.branch_id
    LEFT JOIN users u_rel ON u_rel.id = p.released_by
    WHERE p.status = "Released" ' . $branchFilterP . ' 
    ORDER BY p.created_at DESC LIMIT 50
')->fetchAll();

// Tab routing logic based on user role
if (isset($_GET['tab'])) {
    $currentTab = $_GET['tab'];
} else {
    if ($isBM && !$isAdmin) {
        $currentTab = 'approved'; // Branch Manager defaults to Ready for Release
    } elseif ($isBA && !$isAdmin) {
        $currentTab = 'pending';  // Branch Accountant defaults to Pending Approval
    } else {
        $currentTab = 'drafts';   // BHR and Admin default to Drafts
    }
}

$pageTitle = 'Payroll Management';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Sub-Navbar / Tabs -->
<div class="px-6 py-4 border-b border-slate-200 bg-white flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div class="flex flex-wrap gap-4 items-center">
        <!-- Drafts Tab -->
        <?php if (!$isBA || $isAdmin): ?>
        <a href="?tab=drafts<?= $filterBranchId ? '&branch_id=' . $filterBranchId : '' ?>" class="font-medium px-2 py-2 border-b-2 transition flex items-center gap-1.5 <?= $currentTab === 'drafts' ? 'border-amber-500 text-espresso-950 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-pen-ruler text-xs text-amber-500"></i>
            <span>Drafts</span>
            <span class="ml-1 bg-slate-100 text-slate-700 px-2 py-0.5 rounded-full text-xs font-semibold"><?= count($drafts) ?></span>
        </a>
        <?php endif; ?>

        <!-- Pending Approval Tab (BA Review) -->
        <a href="?tab=pending<?= $filterBranchId ? '&branch_id=' . $filterBranchId : '' ?>" class="font-medium px-2 py-2 border-b-2 transition flex items-center gap-1.5 <?= $currentTab === 'pending' ? 'border-amber-500 text-espresso-950 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-clipboard-check text-xs text-amber-500"></i>
            <span>Pending Approval</span>
            <span class="ml-1 bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full text-xs font-bold"><?= count($pending) ?></span>
        </a>

        <!-- Ready for Release Tab (BM Release) -->
        <a href="?tab=approved<?= $filterBranchId ? '&branch_id=' . $filterBranchId : '' ?>" class="font-medium px-2 py-2 border-b-2 transition flex items-center gap-1.5 <?= $currentTab === 'approved' ? 'border-amber-500 text-espresso-950 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-money-bill-transfer text-xs text-emerald-600"></i>
            <span><?= $isBM ? 'Ready for Release' : 'Approved' ?></span>
            <span class="ml-1 bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full text-xs font-bold"><?= count($approved) ?></span>
        </a>

        <!-- Released History Tab -->
        <a href="?tab=released<?= $filterBranchId ? '&branch_id=' . $filterBranchId : '' ?>" class="font-medium px-2 py-2 border-b-2 transition flex items-center gap-1.5 <?= $currentTab === 'released' ? 'border-amber-500 text-espresso-950 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-history text-xs text-slate-400"></i>
            <span>Released History</span>
            <span class="ml-1 bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full text-xs"><?= count($released) ?></span>
        </a>
    </div>

    <!-- Multi-Branch Selector for Central Roles -->
    <?php if (!$isBranchScoped): ?>
    <form method="GET" action="payroll" class="flex items-center gap-2">
        <input type="hidden" name="tab" value="<?= h($currentTab) ?>">
        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Branch:</label>
        <select name="branch_id" onchange="this.form.submit()" class="text-xs rounded-lg border-slate-300 border p-1.5 bg-slate-50 focus:ring-amber-500 font-medium">
            <option value="">All Branches</option>
            <?php foreach ($branches_list as $br): ?>
                <option value="<?= $br['id'] ?>" <?= ($filterBranchId == $br['id']) ? 'selected' : '' ?>><?= h($br['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php endif; ?>
</div>

<div class="p-6 max-w-7xl mx-auto space-y-6">

    <!-- Alerts -->
    <?php if ($message): ?>
        <div class="bg-emerald-50 text-emerald-800 p-4 rounded-xl flex items-center shadow-sm border border-emerald-200">
            <i class="fa-solid fa-circle-check mr-3 text-lg text-emerald-600"></i>
            <span class="font-medium text-sm"><?= h($message) ?></span>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-rose-50 text-rose-800 p-4 rounded-xl flex items-center shadow-sm border border-rose-200">
            <i class="fa-solid fa-circle-exclamation mr-3 text-lg text-rose-600"></i>
            <span class="font-medium text-sm"><?= h($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Generation Panel: Enabled for BHR & Admin in Drafts tab -->
    <?php if ($currentTab === 'drafts'): ?>
        <?php if ($isBHR || $isAdmin): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center">
                <i class="fa-solid fa-calculator text-amber-500 mr-2"></i> Generate Semi-Monthly Payroll Draft
            </h2>
            <form autocomplete="off" method="POST" action="payroll?tab=drafts<?= $filterBranchId ? '&branch_id=' . $filterBranchId : '' ?>" class="flex flex-col sm:flex-row items-end gap-4">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="generate_drafts">
                
                <div class="flex-1 w-full">
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Select Payroll Period</label>
                    <select name="payroll_period" required class="w-full rounded-xl border-slate-300 p-2.5 text-sm focus:ring-amber-500 focus:border-amber-500 bg-white">
                        <?php
                        $currentY = (int)date('Y');
                        $currentM = (int)date('m');
                        for ($i = 0; $i < 3; $i++) {
                            $m = $currentM - $i;
                            $y = $currentY;
                            if ($m <= 0) { $m += 12; $y--; }
                            $mStr = str_pad($m, 2, '0', STR_PAD_LEFT);
                            
                            $start1 = "$y-$mStr-01";
                            $end1 = "$y-$mStr-15";
                            $label1 = date('M 01, Y', strtotime($start1)) . " to " . date('M 15, Y', strtotime($end1));
                            
                            $start2 = "$y-$mStr-16";
                            $end2 = date('Y-m-t', strtotime($start1));
                            $label2 = date('M 16, Y', strtotime($start2)) . " to " . date('M t, Y', strtotime($end2));
                            
                            echo "<option value=\"$start2|$end2\">$label2</option>";
                            echo "<option value=\"$start1|$end1\">$label1</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="flex gap-2 w-full sm:w-auto">
                    <?php if (count($drafts) > 0): ?>
                        <button type="button" disabled title="Clear existing drafts first before calculating new ones." class="w-full sm:w-auto bg-slate-200 text-slate-400 cursor-not-allowed px-5 py-2.5 rounded-xl font-bold text-sm shadow-sm">
                            Calculate Drafts
                        </button>
                    <?php else: ?>
                        <button type="submit" name="action" value="generate_drafts" class="w-full sm:w-auto bg-amber-500 hover:bg-amber-400 text-espresso-950 px-5 py-2.5 rounded-xl font-bold text-sm shadow-sm transition">
                            Calculate Drafts
                        </button>
                    <?php endif; ?>
                    <button type="submit" name="action" value="clear_drafts" onclick="return confirm('Clear all drafts for this period? Associated bonuses will be reverted to pending.')" class="w-full sm:w-auto bg-rose-50 text-rose-600 hover:bg-rose-100 border border-rose-200 px-4 py-2.5 rounded-xl font-semibold text-sm transition">
                        Clear Drafts
                    </button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="p-4 bg-amber-50 border border-amber-200 text-amber-900 rounded-xl text-sm flex items-center shadow-sm">
            <i class="fa-solid fa-info-circle mr-3 text-lg text-amber-600"></i>
            <div>
                <span class="font-bold">Draft View (Finance Read-Only):</span> Payroll drafts are prepared and calculated by Branch HR. Once submitted, they appear in <a href="?tab=pending" class="underline font-bold hover:text-amber-950">Pending Approval</a> for your review.
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Display List Selection -->
    <?php
    $displayList = [];
    if ($currentTab === 'drafts') $displayList = $drafts;
    elseif ($currentTab === 'pending') $displayList = $pending;
    elseif ($currentTab === 'approved') $displayList = $approved;
    elseif ($currentTab === 'released') $displayList = $released;
    ?>

    <!-- Payroll Table Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500 uppercase tracking-wider">
                        <th class="p-4">Employee</th>
                        <th class="p-4">Period</th>
                        <th class="p-4 text-center">Workflow Progress</th>
                        <th class="p-4">Gross Pay</th>
                        <th class="p-4">Deductions</th>
                        <th class="p-4 text-right">Net Pay</th>
                        <th class="p-4 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($displayList)): ?>
                        <tr>
                            <td colspan="7" class="p-12 text-center text-slate-400">
                                <i class="fa-solid fa-file-invoice-dollar text-4xl mb-3 text-slate-300 block"></i>
                                No payroll records found in this stage.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($displayList as $row): 
                            $st = $row['status'];
                        ?>
                            <tr class="hover:bg-slate-50/80 transition-colors <?= $st === 'Rejected' ? 'bg-rose-50/30' : '' ?>">
                                <td class="p-4">
                                    <div class="font-bold text-slate-800"><?= h($row['full_name']) ?></div>
                                    <div class="text-xs text-slate-500">
                                        <?= h($row['employment_category']) ?> · <span class="text-slate-600 font-medium"><?= h($row['branch_name'] ?? 'Main') ?></span>
                                    </div>
                                    <?php if ($st === 'Rejected' && !empty($row['rejection_reason'])): ?>
                                        <!-- Rejection Reason Banner for BHR -->
                                        <div class="mt-2 p-2 bg-rose-100/70 border border-rose-200 text-rose-900 rounded-lg text-xs max-w-sm whitespace-normal">
                                            <div class="font-bold flex items-center text-rose-700">
                                                <i class="fa-solid fa-circle-exclamation mr-1.5 text-xs"></i>
                                                BA Feedback:
                                            </div>
                                            <div class="mt-0.5 text-rose-800 italic"><?= h($row['rejection_reason']) ?></div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-xs text-slate-600 font-mono">
                                    <?= date('M d', strtotime($row['period_start'])) ?> - <?= date('M d, Y', strtotime($row['period_end'])) ?>
                                </td>

                                <!-- Constraint 3: Visual Stepper (Draft -> BA Review -> BM Release) -->
                                <td class="p-4">
                                    <div class="flex items-center justify-center space-x-1 text-xs">
                                        <!-- Step 1: Draft -->
                                        <div class="flex items-center px-2 py-0.5 rounded border text-[11px] <?= in_array($st, ['Draft', 'Pending Approval', 'Approved', 'Released']) ? ($st === 'Draft' ? 'bg-amber-100 text-amber-800 font-bold border-amber-300' : 'bg-emerald-100 text-emerald-800 font-semibold border-emerald-300') : 'bg-rose-100 text-rose-800 font-bold border-rose-300' ?>" title="Step 1: HR Draft Preparation">
                                            <i class="fa-solid <?= $st === 'Rejected' ? 'fa-arrow-rotate-left' : ($st === 'Draft' ? 'fa-pen-to-square' : 'fa-check') ?> mr-1 text-[10px]"></i>
                                            <span>Draft</span>
                                        </div>
                                        <i class="fa-solid fa-chevron-right text-[9px] text-slate-300"></i>

                                        <!-- Step 2: BA Review -->
                                        <div class="flex items-center px-2 py-0.5 rounded border text-[11px] <?= in_array($st, ['Approved', 'Released']) ? 'bg-emerald-100 text-emerald-800 font-semibold border-emerald-300' : ($st === 'Pending Approval' ? 'bg-amber-100 text-amber-900 font-bold border-amber-400 animate-pulse' : ($st === 'Rejected' ? 'bg-rose-200 text-rose-900 font-bold border-rose-400' : 'bg-slate-100 text-slate-400 border-slate-200')) ?>" title="Step 2: Branch Accountant Review">
                                            <i class="fa-solid <?= in_array($st, ['Approved', 'Released']) ? 'fa-check' : ($st === 'Pending Approval' ? 'fa-clock' : ($st === 'Rejected' ? 'fa-xmark' : 'fa-hourglass')) ?> mr-1 text-[10px]"></i>
                                            <span>BA Review</span>
                                        </div>
                                        <i class="fa-solid fa-chevron-right text-[9px] text-slate-300"></i>

                                        <!-- Step 3: BM Release -->
                                        <div class="flex items-center px-2 py-0.5 rounded border text-[11px] <?= $st === 'Released' ? 'bg-emerald-100 text-emerald-800 font-semibold border-emerald-300' : ($st === 'Approved' ? 'bg-amber-100 text-amber-800 font-bold border-amber-300' : 'bg-slate-100 text-slate-400 border-slate-200') ?>" title="Step 3: Branch Manager Disbursement">
                                            <i class="fa-solid <?= $st === 'Released' ? 'fa-check-double' : ($st === 'Approved' ? 'fa-hand-holding-dollar' : 'fa-hourglass') ?> mr-1 text-[10px]"></i>
                                            <span>BM Release</span>
                                        </div>
                                    </div>
                                </td>

                                <td class="p-4 text-slate-800 font-medium">
                                    ₱<?= number_format($row['gross_pay'], 2) ?>
                                    <?php if (isset($row['bonus_amount']) && $row['bonus_amount'] > 0): ?>
                                        <div class="text-[11px] text-amber-600 font-bold">+ ₱<?= number_format($row['bonus_amount'], 2) ?> Bonus</div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-rose-600 cursor-help" title="SSS: ₱<?= number_format($row['sss'], 2) ?> | PhilHealth: ₱<?= number_format($row['philhealth'], 2) ?> | Pag-IBIG: ₱<?= number_format($row['pagibig'], 2) ?> | Tax: ₱<?= number_format($row['tax'], 2) ?>">
                                    -₱<?= number_format($row['deductions'], 2) ?>
                                    <i class="fa-solid fa-circle-info text-slate-300 ml-1 text-xs"></i>
                                </td>
                                <td class="p-4 font-extrabold text-emerald-600 text-right text-base font-mono">
                                    ₱<?= number_format($row['net_pay'], 2) ?>
                                </td>

                                <!-- Action Buttons Per Tab & Role -->
                                <td class="p-4 text-center">
                                    <?php if ($currentTab === 'drafts'): ?>
                                        <!-- BHR Actions -->
                                        <?php if ($isBHR || $isAdmin): ?>
                                            <form autocomplete="off" method="POST" action="payroll?tab=drafts<?= $filterBranchId ? '&branch_id=' . $filterBranchId : '' ?>" class="inline-block">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="action" value="submit_approval">
                                                <input type="hidden" name="payroll_id" value="<?= $row['id'] ?>">
                                                <?php if ($st === 'Rejected'): ?>
                                                    <button type="submit" class="bg-amber-500 hover:bg-amber-400 text-espresso-950 font-bold px-3 py-1.5 rounded-xl text-xs transition shadow-sm flex items-center gap-1">
                                                        <i class="fa-solid fa-arrows-rotate text-[11px]"></i> Resubmit for Approval
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" class="bg-espresso-950 hover:bg-espresso-900 text-amber-400 border border-amber-500/30 font-bold px-3 py-1.5 rounded-xl text-xs transition shadow-sm flex items-center gap-1">
                                                        <i class="fa-solid fa-paper-plane text-[11px]"></i> Submit for Approval
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-xs bg-slate-100 text-slate-600 px-2.5 py-1 rounded-full font-medium">In Draft</span>
                                        <?php endif; ?>

                                    <?php elseif ($currentTab === 'pending'): ?>
                                        <!-- BA Actions: Approve & Send to BM OR Reject -->
                                        <?php if ($isBA || $isAdmin): ?>
                                            <div class="flex items-center justify-center gap-2">
                                                <form autocomplete="off" method="POST" action="payroll?tab=pending<?= $filterBranchId ? '&branch_id=' . $filterBranchId : '' ?>" class="inline-block">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="payroll_id" value="<?= $row['id'] ?>">
                                                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3 py-1.5 rounded-xl text-xs transition shadow-sm flex items-center gap-1">
                                                        <i class="fa-solid fa-circle-check text-[11px]"></i> Approve & Send to BM
                                                    </button>
                                                </form>
                                                <button type="button" onclick="openRejectModal(<?= $row['id'] ?>, <?= htmlspecialchars(json_encode($row['full_name']), ENT_QUOTES, 'UTF-8') ?>)" class="bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-300 font-bold px-3 py-1.5 rounded-xl text-xs transition shadow-sm flex items-center gap-1">
                                                    <i class="fa-solid fa-ban text-[11px]"></i> Reject
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="inline-flex items-center text-xs bg-amber-100 text-amber-900 font-bold px-2.5 py-1 rounded-full">
                                                <i class="fa-solid fa-clock text-[10px] mr-1 text-amber-600"></i> Under BA Review
                                            </span>
                                        <?php endif; ?>

                                    <?php elseif ($currentTab === 'approved'): ?>
                                        <!-- BM Action: Release Payroll -->
                                        <?php if ($isBM || $isAdmin): ?>
                                            <button type="button" onclick="openReleaseModal(<?= $row['id'] ?>, <?= htmlspecialchars(json_encode($row['full_name']), ENT_QUOTES, 'UTF-8') ?>)" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-1.5 rounded-xl text-xs transition shadow-sm flex items-center gap-1.5">
                                                <i class="fa-solid fa-money-bill-wave text-xs"></i> Release Payroll
                                            </button>
                                        <?php else: ?>
                                            <span class="inline-flex items-center text-xs bg-emerald-50 text-emerald-800 font-bold px-2.5 py-1 rounded-full border border-emerald-200">
                                                <i class="fa-solid fa-clock text-[10px] mr-1 text-emerald-600"></i> Ready for BM Release
                                            </span>
                                        <?php endif; ?>

                                    <?php elseif ($currentTab === 'released'): ?>
                                        <span class="inline-flex items-center text-xs bg-slate-100 text-slate-700 px-3 py-1 rounded-full font-medium">
                                            <i class="fa-solid fa-circle-check text-emerald-600 mr-1.5"></i> <?= h($row['payment_method'] ?: 'Cash') ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal 1: Release Modal for BM -->
<div id="releaseModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden flex flex-col">
        <div class="p-4 bg-espresso-950 text-cream-100 flex justify-between items-center border-b border-amber-500/20">
            <h3 class="font-bold flex items-center font-serif">
                <i class="fa-solid fa-paper-plane text-amber-400 mr-2"></i> Release Payroll Disbursement
            </h3>
            <button onclick="document.getElementById('releaseModal').classList.add('hidden')" class="text-cream-100/60 hover:text-amber-400">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <form autocomplete="off" method="POST" action="payroll?tab=approved<?= $filterBranchId ? '&branch_id=' . $filterBranchId : '' ?>" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="release">
            <input type="hidden" name="payroll_id" id="release_payroll_id">
            
            <p class="text-sm text-slate-600">
                You are officially releasing the approved net payslip for <strong id="release_emp_name" class="text-slate-900 font-bold"></strong>. This step finalizes the transaction.
            </p>
            
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Disbursement Method</label>
                <select name="payment_method" required class="w-full rounded-xl border-slate-300 p-2.5 text-sm focus:ring-amber-500 focus:border-amber-500">
                    <option value="Cash" selected>Cash (Over-the-Counter Handover)</option>
                    <option value="Bank Transfer">Bank Transfer (Direct Deposit)</option>
                    <option value="eWallet">eWallet (GCash / Maya)</option>
                </select>
            </div>
            
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('releaseModal').classList.add('hidden')" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-xl text-sm font-medium transition">Cancel</button>
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-5 py-2 rounded-xl text-sm transition shadow-sm">Confirm Disbursement</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Reject Modal for BA -->
<div id="rejectModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden flex flex-col">
        <div class="p-4 bg-rose-950 text-rose-100 flex justify-between items-center border-b border-rose-800">
            <h3 class="font-bold flex items-center font-serif text-rose-200">
                <i class="fa-solid fa-ban text-rose-400 mr-2"></i> Reject Payroll Record
            </h3>
            <button onclick="document.getElementById('rejectModal').classList.add('hidden')" class="text-rose-300 hover:text-white">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <form autocomplete="off" method="POST" action="payroll?tab=pending<?= $filterBranchId ? '&branch_id=' . $filterBranchId : '' ?>" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="payroll_id" id="reject_payroll_id">
            
            <p class="text-sm text-slate-600">
                Provide a reason for rejecting the payroll draft for <strong id="reject_emp_name" class="text-slate-900 font-bold"></strong>. Branch HR will see this feedback in their Drafts table to make revisions.
            </p>
            
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Rejection Reason *</label>
                <textarea name="rejection_reason" required rows="3" placeholder="e.g. Discrepancy in recorded overtime hours for Oct 12; please verify attendance logs." class="w-full rounded-xl border-slate-300 p-2.5 text-sm focus:ring-rose-500 focus:border-rose-500"></textarea>
            </div>
            
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('rejectModal').classList.add('hidden')" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-xl text-sm font-medium transition">Cancel</button>
                <button type="submit" class="bg-rose-600 hover:bg-rose-700 text-white font-bold px-5 py-2 rounded-xl text-sm transition shadow-sm">Confirm Rejection</button>
            </div>
        </form>
    </div>
</div>

<script>
function openReleaseModal(id, name) {
    document.getElementById('release_payroll_id').value = id;
    document.getElementById('release_emp_name').innerText = name;
    document.getElementById('releaseModal').classList.remove('hidden');
}

function openRejectModal(id, name) {
    document.getElementById('reject_payroll_id').value = id;
    document.getElementById('reject_emp_name').innerText = name;
    document.getElementById('rejectModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
