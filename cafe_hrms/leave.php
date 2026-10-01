<?php
require_once __DIR__ . '/init.php';
require_login();
$user = current_user();

// Determine Employee ID for the logged in user
$employeeId = null;
$stmt = $pdo->prepare('SELECT id, sick_leave_balance FROM employees WHERE email = ? LIMIT 1');
$stmt->execute([$user['email']]);
$empRow = $stmt->fetch();
$employeeId = $empRow['id'] ?? null;
$sickLeaveBalance = $empRow['sick_leave_balance'] ?? 0;

$message = null;

// Determine active tab
$tab = $_GET['tab'] ?? 'roster'; // 'roster' or 'leaves'

// Handle POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($token)) {
        $action = $_POST['action'] ?? '';
        
        // Request Leave
        if ($action === 'request_leave') {
            $reqEmployeeId = $_POST['employee_id'] ?? null;
            $type = $_POST['leave_type'] ?? 'Vacation';
            $startDate = $_POST['start_date'] ?? null;
            $endDate = $_POST['end_date'] ?? null;
            $reason = trim($_POST['reason'] ?? '');
            
            $daysReq = 0;
            if ($startDate && $endDate) {
                $sDate = new DateTime($startDate);
                $eDate = new DateTime($endDate);
                $daysReq = $sDate->diff($eDate)->days + 1;
            }

            $hasError = false;

            if ($type === 'Sick' && $reqEmployeeId) {
                $balStmt = $pdo->prepare('SELECT sick_leave_balance FROM employees WHERE id = ?');
                $balStmt->execute([$reqEmployeeId]);
                $bal = $balStmt->fetchColumn();

                if ($daysReq > $bal) {
                    $message = "Error: Not enough Sick Leave balance. Requested $daysReq day(s), but only $bal day(s) remaining. Please apply for Unpaid Absence instead.";
                    $hasError = true;
                } elseif ($daysReq >= 2 && empty($_FILES['medical_certificate']['name'])) {
                    $message = "Error: A Medical Certificate is mandatory for Sick Leaves of 2 or more consecutive days.";
                    $hasError = true;
                }
            }
            
            // Handle Medical Certificate
            $medicalCertPath = null;
            if (!$hasError && $type === 'Sick' && isset($_FILES['medical_certificate']) && $_FILES['medical_certificate']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads/certificates/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $fileName = time() . '_' . basename($_FILES['medical_certificate']['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['medical_certificate']['tmp_name'], $targetPath)) {
                    $medicalCertPath = 'uploads/certificates/' . $fileName;
                }
            }
            
            $status = ($type === 'Sick') ? 'Approved' : 'Pending';
            
            if (!$hasError && $reqEmployeeId && $startDate && $endDate) {
                $stmt = $pdo->prepare('INSERT INTO leaves (employee_id, leave_type, start_date, end_date, reason, medical_certificate, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
                $stmt->execute([$reqEmployeeId, $type, $startDate, $endDate, $reason, $medicalCertPath, $status]);
                
                // Deduct balance
                if ($type === 'Sick' && $status === 'Approved') {
                    $deduct = $pdo->prepare('UPDATE employees SET sick_leave_balance = sick_leave_balance - ? WHERE id = ?');
                    $deduct->execute([$daysReq, $reqEmployeeId]);
                }

                $clearedCount = 0;
                if ($status === 'Approved') {
                    // Count cleared shifts
                    $countShifts = $pdo->prepare('SELECT COUNT(*) FROM schedules WHERE employee_id = ? AND shift_date BETWEEN ? AND ?');
                    $countShifts->execute([$reqEmployeeId, $startDate, $endDate]);
                    $clearedCount = $countShifts->fetchColumn();
                    
                    // Free up the schedule
                    $deleteShifts = $pdo->prepare('DELETE FROM schedules WHERE employee_id = ? AND shift_date BETWEEN ? AND ?');
                    $deleteShifts->execute([$reqEmployeeId, $startDate, $endDate]);
                }
                
                // Fetch employee name
                $empNameStmt = $pdo->prepare('SELECT CONCAT(first_name, " ",   last_name) AS full_name FROM employees WHERE id = ?');
                $empNameStmt->execute([$reqEmployeeId]);
                $empName = $empNameStmt->fetchColumn();
                
                $notifMsg = "HR requested $type for $empName (" . date('M d', strtotime($startDate)) . " - " . date('M d', strtotime($endDate)) . ").";
                if ($clearedCount > 0) {
                    $notifMsg .= " $clearedCount scheduled shift(s) were cleared and need reassignment!";
                }
                notify_roles($pdo, ['Admin', 'Super Admin', 'HR', 'HR Admin'], $notifMsg, "leave.php", "fa-plane-departure", "primary");
                
                $message = 'Leave request submitted successfully. ' . ($status === 'Approved' ? '(Sick Leave Auto-Approved)' : '');
                if ($clearedCount > 0) $message .= " $clearedCount conflicting shifts were cleared.";
                
                log_activity($pdo, $_SESSION['user']['id'], 'request_leave', "Leave requested for employee {$reqEmployeeId}");
            }
        }
        
        // Schedule Shift
        if (($action === 'assign_shift' || $action === 'delete_shift') && in_array($user['role'], ['Admin', 'Super Admin', 'HR Admin', 'HR'])) {
            $empId = (int)$_POST['employee_id'];
            $shiftDate = $_POST['shift_date'];
            
            // Lockout check: Cannot modify shifts before Next Monday
            $nextMonday = new DateTime('next monday');
            $nextMondayStr = $nextMonday->format('Y-m-d');
            
            if ($shiftDate < $nextMondayStr) {
                $message = "Error: You can only modify schedules for upcoming weeks (starting $nextMondayStr).";
            } else {
                if ($action === 'delete_shift') {
                    $stmt = $pdo->prepare('DELETE FROM schedules WHERE employee_id = ? AND shift_date = ?');
                    $stmt->execute([$empId, $shiftDate]);
                    $message = "Shift deleted successfully.";
                } else {
                    $shiftType = $_POST['shift_type']; // Morning, Mid, Night
                    $startTime = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
                    $endTime = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
                    $notes = trim($_POST['notes'] ?? '');
                    
                    // Conflict Check 1: Is employee on approved leave?
                    $leaveCheck = $pdo->prepare('
                        SELECT id FROM leaves 
                        WHERE employee_id = ? AND status = "Approved" 
                        AND ? BETWEEN start_date AND end_date
                    ');
                    $leaveCheck->execute([$empId, $shiftDate]);
                    if ($leaveCheck->fetchColumn()) {
                        $message = "Error: Employee is on an Approved Leave on $shiftDate.";
                    } else {
                        // Upsert Schedule
                        $stmt = $pdo->prepare('
                            INSERT INTO schedules (employee_id, shift_date, shift_type, start_time, end_time, assigned_by) 
                            VALUES (?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE shift_type=VALUES(shift_type), start_time=VALUES(start_time), end_time=VALUES(end_time), assigned_by=VALUES(assigned_by)
                        ');
                        $stmt->execute([$empId, $shiftDate, $shiftType, $startTime, $endTime, $_SESSION['user']['id']]);
                        
                        // Check warnings for the week
                        $wD = new DateTime($shiftDate);
                        $wD->modify('monday this week');
                        $startWeek = $wD->format('Y-m-d');
                        $wD->modify('sunday this week');
                        $endWeek = $wD->format('Y-m-d');
                        
                        $catStmt = $pdo->prepare('SELECT employment_category, full_name FROM employees WHERE id = ?');
                        $catStmt->execute([$empId]);
                        $eData = $catStmt->fetch();
                        $category = $eData['employment_category'] ?? 'Full-Time';
                        $empName = $eData['full_name'];
                        
                        $stmt2 = $pdo->prepare("SELECT COUNT(*) as days, SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time))/60 as hours FROM schedules WHERE employee_id = ? AND shift_date BETWEEN ? AND ?");
                        $stmt2->execute([$empId, $startWeek, $endWeek]);
                        $stats = $stmt2->fetch();
                        
                        $days = $stats['days'];
                        $hours = round($stats['hours'] ?? 0, 1);
                        
                        $warningMsg = '';
                        if ($category === 'Full-Time' && $days > 5) {
                            $warningMsg = " (Warning: $empName is scheduled for $days days this week, exceeding the 5-day recommendation)";
                        } elseif ($category === 'Part-Time' && $hours > 35) {
                            $warningMsg = " (Warning: $empName is scheduled for $hours hours this week, exceeding the 35-hour recommendation)";
                        }
                        
                        $message = "Shift assigned successfully." . $warningMsg;
                    }
                }
            }
        }
    }
}

// Handle GET Actions (Leave Approvals)
$actionGet = $_GET['action'] ?? null;
$leaveId = $_GET['id'] ?? null;
if ($actionGet && $leaveId && in_array($user['role'], ['Admin', 'Super Admin', 'HR Admin', 'HR'])) {
    
    // Check requester role
    $reqStmt = $pdo->prepare('
        SELECT l.id, l.employee_id, l.leave_type, l.start_date, l.end_date, r.name as requester_role 
        FROM leaves l 
        JOIN employees e ON l.employee_id = e.id
        LEFT JOIN users u ON u.email = e.email
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE l.id = ?
    ');
    $reqStmt->execute([$leaveId]);
    $leaveData = $reqStmt->fetch();
    
    if ($leaveData) {
        $reqRole = $leaveData['requester_role'] ?? 'Employee';
        
        if ($actionGet === 'reject') {
            $stmt = $pdo->prepare('UPDATE leaves SET status = "Rejected" WHERE id = ?');
            $stmt->execute([$leaveId]);
            notify_user($pdo, $leaveData['requester_user_id'], "Your leave request from " . date('M d', strtotime($leaveData['start_date'])) . " to " . date('M d', strtotime($leaveData['end_date'])) . " has been denied.", "ess.php", "fa-circle-xmark", "red");
            $message = "Leave request rejected.";
        } else if ($actionGet === 'revoke') {
            $stmt = $pdo->prepare('UPDATE leaves SET status = "Rejected" WHERE id = ?');
            $stmt->execute([$leaveId]);
            notify_user($pdo, $leaveData['requester_user_id'], "Your approved leave request from " . date('M d', strtotime($leaveData['start_date'])) . " to " . date('M d', strtotime($leaveData['end_date'])) . " has been revoked.", "ess.php", "fa-circle-xmark", "red");
            $message = "Leave automatically revoked.";
        } else if ($actionGet === 'approve') {
            if (in_array($reqRole, ['Admin', 'Super Admin', 'HR Admin', 'HR'])) {
                // Peer Approval Logic
                try {
                    $insertVote = $pdo->prepare('INSERT IGNORE INTO leave_approvals (leave_id, approver_id) VALUES (?, ?)');
                    $insertVote->execute([$leaveId, $_SESSION['user']['id']]);
                    
                    // Count votes
                    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM leave_approvals WHERE leave_id = ?');
                    $countStmt->execute([$leaveId]);
                    $votes = (int)$countStmt->fetchColumn();
                    
                    if ($votes >= 2) {
                        $pdo->prepare('UPDATE leaves SET status = "Approved" WHERE id = ?')->execute([$leaveId]);
                        $message = "Leave fully approved! (2/2 peer votes reached).";
                    } else {
                        $message = "Your approval vote was recorded ($votes/2 votes).";
                    }
                } catch(Exception $e) { }
            } else {
                // Standard Approval
                $stmt = $pdo->prepare('UPDATE leaves SET status = "Approved" WHERE id = ?');
                $stmt->execute([$leaveId]);
                
                // Free up schedule since it's now approved
                $deleteShifts = $pdo->prepare('DELETE FROM schedules WHERE employee_id = ? AND shift_date BETWEEN ? AND ?');
                $deleteShifts->execute([$leaveData['employee_id'], $leaveData['start_date'], $leaveData['end_date']]);

                $message = "Leave request approved. Any conflicting shifts have been vacated.";
            }
        } else if ($actionGet === 'void') {
            $stmt = $pdo->prepare('UPDATE leaves SET status = "Voided (AWOL)" WHERE id = ?');
            $stmt->execute([$leaveId]);
            
            // Refund sick_leave_balance
            if ($leaveData['leave_type'] === 'Sick') {
                $sDate = new DateTime($leaveData['start_date']);
                $eDate = new DateTime($leaveData['end_date']);
                $days = $sDate->diff($eDate)->days + 1;
                
                $refund = $pdo->prepare('UPDATE employees SET sick_leave_balance = sick_leave_balance + ? WHERE id = ?');
                $refund->execute([$days, $leaveData['employee_id']]);
            }
            
            // Insert Auto-Closed attendance penalties
            $loopDate = new DateTime($leaveData['start_date']);
            $endDate = new DateTime($leaveData['end_date']);
            while ($loopDate <= $endDate) {
                $inject = $pdo->prepare('INSERT INTO attendance (employee_id, time_in, time_out, status) VALUES (?, ?, ?, "Auto-Closed")');
                $tIn = $loopDate->format('Y-m-d') . ' 09:00:00';
                $tOut = $loopDate->format('Y-m-d') . ' 17:00:00';
                $inject->execute([$leaveData['employee_id'], $tIn, $tOut]);
                $loopDate->modify('+1 day');
            }
            $message = "Leave voided. Balance refunded and AWOL penalties applied.";
        }
    }
}

// Role Filter
$roleFilter = $_GET['role_filter'] ?? '';

// Fetch Employees (Active and Trainee)
$branchFilter = get_branch_filter();
$empQuery = 'SELECT id, CONCAT(first_name, " ",   last_name) AS full_name, preferred_schedule, employment_category, position FROM employees WHERE status IN ("Active", "Trainee") AND position NOT IN ("Branch Admin", "Central HR", "Global Accountant", "Executive")' . $branchFilter;
$empParams = [];
if ($roleFilter) {
    $empQuery .= ' AND position = ?';
    $empParams[] = $roleFilter;
}
$empQuery .= ' ORDER BY first_name';
$stmtEmp = $pdo->prepare($empQuery);
$stmtEmp->execute($empParams);
$employees = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);

// For coverage, we need roles of all employees, not just the filtered ones
$allEmployees = $pdo->query('SELECT id, position FROM employees')->fetchAll(PDO::FETCH_ASSOC);
$employeeRoles = [];
foreach ($allEmployees as $ae) {
    $employeeRoles[$ae['id']] = $ae['position'];
}

// Calculate Current Week for Roster
$weekOffset = (int)($_GET['week_offset'] ?? 0);
// Find Monday of the current week
$monday = new DateTime('monday this week');
if ($weekOffset !== 0) {
    $monday->modify($weekOffset > 0 ? "+$weekOffset week" : "$weekOffset week");
}
$sunday = clone $monday;
$sunday->modify('+6 days');

$startStr = $monday->format('Y-m-d');
$endStr = $sunday->format('Y-m-d');

// Fetch Schedules for Week
$branchFilterS = get_branch_filter('s');
$schedStmt = $pdo->prepare('
    SELECT s.*, u.name AS assigned_by_name 
    FROM schedules s
    LEFT JOIN users u ON s.assigned_by = u.id
    WHERE shift_date BETWEEN ? AND ? ' . $branchFilterS . '
');
$schedStmt->execute([$startStr, $endStr]);
$rawSchedules = $schedStmt->fetchAll(PDO::FETCH_ASSOC);

// Lockout Date
$nextMonday = new DateTime('next monday');
$nextMondayStr = $nextMonday->format('Y-m-d');

$schedules = [];
$coverage = []; // date => [Morning => [...], Mid => [...], Night => [...]]

foreach ($rawSchedules as $s) {
    $schedules[$s['employee_id']][$s['shift_date']] = $s;
    
    // Coverage calculation
    $date = $s['shift_date'];
    $shift = $s['shift_type'];
    $empRole = $employeeRoles[$s['employee_id']] ?? 'Barista';
    
    if (!isset($coverage[$date])) {
        $coverage[$date] = [
            'Morning' => ['heads' => 0, 'regulars' => 0],
            'Mid' =>     ['heads' => 0, 'regulars' => 0],
            'Night' =>   ['heads' => 0, 'regulars' => 0]
        ];
    }
    
    if (in_array($empRole, ['Head Barista', 'Assistant Head'])) {
        $coverage[$date][$shift]['heads']++;
    } else {
        $coverage[$date][$shift]['regulars']++;
    }
}

// Coverage Color Helper
function getCoverageColor($heads, $regulars) {
    if ($heads < 1) return 'bg-red-500'; 
    if ($regulars < 3) return 'bg-amber-400'; 
    return 'bg-emerald-500';
}


// Fetch Approved Leaves for Week
$leaveWeekStmt = $pdo->prepare('
    SELECT employee_id, start_date, end_date FROM leaves 
    WHERE status = "Approved" 
    AND (start_date <= ? AND end_date >= ?)
');
$leaveWeekStmt->execute([$endStr, $startStr]);
$rawWeekLeaves = $leaveWeekStmt->fetchAll(PDO::FETCH_ASSOC);

// Helper to check if employee is on leave on a specific date
function is_on_leave($empId, $dateStr, $rawWeekLeaves) {
    $ts = strtotime($dateStr);
    foreach ($rawWeekLeaves as $l) {
        if ($l['employee_id'] == $empId) {
            $start = strtotime($l['start_date']);
            $end = strtotime($l['end_date']);
            if ($ts >= $start && $ts <= $end) return true;
        }
    }
    return false;
}

// Fetch All Leaves for Leaves Tab
$branchFilterL = get_branch_filter('e');
$leaveQuery = 'SELECT l.*, CONCAT(e.first_name, " ",   e.last_name) AS full_name, e.employee_id as emp_code, r.name as requester_role 
               FROM leaves l 
               JOIN employees e ON e.id = l.employee_id
               LEFT JOIN users u ON u.email = e.email
               LEFT JOIN roles r ON u.role_id = r.id';
$params = [];
$whereClauses = [];
if (in_array($user['role'], ['Employee', 'Barista', 'Head Barista'])) {
    $whereClauses[] = 'e.email = ?';
    $params[] = $user['email'];
}
$whereSql = '';
if (!empty($whereClauses)) {
    $whereSql = ' WHERE ' . implode(' AND ', $whereClauses) . $branchFilterL;
} else {
    $whereSql = ' WHERE 1=1 ' . $branchFilterL;
}
$leaveQuery .= $whereSql . ' ORDER BY l.created_at DESC';
$stmt = $pdo->prepare($leaveQuery);
$stmt->execute($params);
$leavesList = $stmt->fetchAll();

$pageTitle = 'Leave & Shifts';
require_once __DIR__ . '/includes/header.php';
?>

<div class="px-6 py-4 border-b border-slate-200 flex space-x-6">
    <a href="?tab=roster" class="font-medium px-1 py-2 border-b-2 transition <?= $tab === 'roster' ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">Weekly Roster</a>
    <a href="?tab=leaves" class="font-medium px-1 py-2 border-b-2 transition <?= $tab === 'leaves' ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">Leave Requests</a>
</div>

<div class="p-6">
    <?php if ($message): ?>
        <div class="mb-4 bg-primary/10 text-primary p-4 rounded-lg flex items-center shadow-sm">
            <i class="fa-solid fa-circle-info mr-3 text-lg"></i>
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'roster'): ?>
        
        <!-- Roster Controls -->
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center gap-3">
                <a href="?tab=roster&week_offset=<?= $weekOffset - 1 ?><?= $roleFilter ? '&role_filter='.urlencode($roleFilter) : '' ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded shadow-sm hover:bg-slate-50 text-slate-600">
                    <i class="fa-solid fa-chevron-left"></i> Prev
                </a>
                <span class="font-bold text-slate-800">
                    <?= $monday->format('M d') ?> - <?= $sunday->format('M d, Y') ?>
                </span>
                <a href="?tab=roster&week_offset=<?= $weekOffset + 1 ?><?= $roleFilter ? '&role_filter='.urlencode($roleFilter) : '' ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded shadow-sm hover:bg-slate-50 text-slate-600">
                    Next <i class="fa-solid fa-chevron-right"></i>
                </a>
            </div>
            
            <div class="flex items-center gap-4">
                <h2 class="text-xl font-bold text-slate-800">Weekly Roster</h2>
                
                <form autocomplete="off" method="GET" action="leave" class="flex items-center gap-2">
                    <input type="hidden" name="tab" value="roster">
                    <input type="hidden" name="week_offset" value="<?= $weekOffset ?>">
                    <select name="role_filter" onchange="this.form.submit()" class="text-sm rounded-lg border-slate-300 py-1.5 pl-3 pr-8 focus:ring-primary focus:border-primary bg-white shadow-sm">
                        <option value="">All Roles</option>
                        <option value="Head Barista" <?= $roleFilter === 'Head Barista' ? 'selected' : '' ?>>Head Barista</option>
                        <option value="Barista" <?= $roleFilter === 'Barista' ? 'selected' : '' ?>>Barista</option>
                    </select>
                </form>
                
                <?php if ($weekOffset !== 0): ?>
                    <a href="?tab=roster<?= $roleFilter ? '&role_filter='.urlencode($roleFilter) : '' ?>" class="text-primary text-sm hover:underline">Today</a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Roster Grid -->
        <div class="bg-slate-50 rounded-xl shadow-sm border border-slate-300 overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead>
                    <tr class="bg-slate-200 border-b border-slate-300 text-slate-700">
                        <th class="p-4 font-semibold sticky left-0 bg-slate-200 z-10 shadow-[1px_0_0_0_#cbd5e1]">Employee</th>
                        <?php 
                        $curr = clone $monday;
                        for($i=0; $i<7; $i++): 
                            $isToday = $curr->format('Y-m-d') == date('Y-m-d');
                            $dStr = $curr->format('Y-m-d');
                            $cov = $coverage[$dStr] ?? ['Morning' => ['heads'=>0,'regulars'=>0], 'Mid' => ['heads'=>0,'regulars'=>0], 'Night' => ['heads'=>0,'regulars'=>0]];
                        ?>
                            <th class="p-3 font-semibold text-center <?= $isToday ? 'bg-indigo-100 text-indigo-800' : '' ?>">
                                <div class="text-xs font-normal"><?= $curr->format('l') ?></div>
                                <?= $curr->format('M d') ?>
                                <div class="flex justify-center gap-1 mt-1.5">
                                    <div class="w-4 h-4 rounded-full text-[9px] flex items-center justify-center text-white <?= getCoverageColor($cov['Morning']['heads'], $cov['Morning']['regulars']) ?>" title="Morning Coverage: <?= $cov['Morning']['heads'] ?> Head(s), <?= $cov['Morning']['regulars'] ?> Regular(s)">M</div>
                                    <div class="w-4 h-4 rounded-full text-[9px] flex items-center justify-center text-white <?= getCoverageColor($cov['Mid']['heads'], $cov['Mid']['regulars']) ?>" title="Mid Coverage: <?= $cov['Mid']['heads'] ?> Head(s), <?= $cov['Mid']['regulars'] ?> Regular(s)">M</div>
                                    <div class="w-4 h-4 rounded-full text-[9px] flex items-center justify-center text-white <?= getCoverageColor($cov['Night']['heads'], $cov['Night']['regulars']) ?>" title="Night Coverage: <?= $cov['Night']['heads'] ?> Head(s), <?= $cov['Night']['regulars'] ?> Regular(s)">N</div>
                                </div>
                            </th>
                        <?php 
                            $curr->modify('+1 day');
                        endfor; 
                        ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <?php foreach ($employees as $emp): ?>
                        <tr class="hover:bg-slate-200/50">
                            <td class="p-4 sticky left-0 bg-slate-50 shadow-[1px_0_0_0_#cbd5e1]">
                                <div class="font-bold text-slate-800 text-sm"><?= h($emp['full_name']) ?></div>
                                <div class="text-[10px] mt-0.5 space-x-1">
                                    <span class="inline-block px-1.5 py-0.5 rounded font-bold uppercase tracking-wider <?= $emp['position'] === 'Head Barista' ? 'bg-purple-100 text-purple-700' : 'bg-slate-100 text-slate-600' ?>"><?= h($emp['position']) ?></span>
                                    <span class="inline-block px-1.5 py-0.5 rounded font-bold uppercase tracking-wider <?= $emp['employment_category'] === 'Full-Time' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700' ?>"><?= h($emp['employment_category']) ?></span>
                                </div>
                                <div class="text-[10px] text-slate-400 mt-1">Prefers: <?= h($emp['preferred_schedule'] ?? 'Any') ?></div>
                            </td>
                            <?php 
                            $curr = clone $monday;
                            for($i=0; $i<7; $i++): 
                                $dateStr = $curr->format('Y-m-d');
                                $isLeave = is_on_leave($emp['id'], $dateStr, $rawWeekLeaves);
                                $shift = $schedules[$emp['id']][$dateStr] ?? null;
                                $isLocked = ($dateStr < $nextMondayStr);
                                $cellClasses = "p-2 min-w-[120px] text-center border-l border-slate-100 relative group";
                                if ($isLocked) $cellClasses .= " bg-slate-50 opacity-80";
                            ?>
                                <td class="<?= $cellClasses ?>">
                                    <?php if ($isLeave): ?>
                                        <div class="bg-red-50 text-red-600 text-xs py-1.5 px-2 rounded font-medium border border-red-100 flex items-center justify-center">
                                            <i class="fa-solid fa-plane mr-1"></i> On Leave
                                        </div>
                                    <?php elseif ($shift): ?>
                                        <?php 
                                            // Determine badge color
                                            $bg = 'bg-blue-50 text-blue-700 border-blue-200';
                                            if($shift['shift_type'] == 'Mid') $bg = 'bg-amber-50 text-amber-700 border-amber-200';
                                            if($shift['shift_type'] == 'Night') $bg = 'bg-purple-50 text-purple-700 border-purple-200';
                                            
                                            // Check warning
                                            $pref = $emp['preferred_schedule'] ?? 'Any';
                                            $warning = '';
                                            if ($pref !== 'Any' && $shift['shift_type'] !== $pref) {
                                                $warning = '<i class="fa-solid fa-triangle-exclamation text-red-500 ml-1" title="Shift does not match preferred schedule"></i>';
                                            }
                                            
                                            $onclick = $isLocked ? "" : "onclick=\"openAssignModal({$emp['id']}, '" . h(addslashes($emp['full_name'])) . "', '{$dateStr}', '{$shift['shift_type']}', '{$shift['start_time']}', '{$shift['end_time']}', '" . h(addslashes($emp['employment_category'] ?? 'Full-Time')) . "')\"";
                                            $cursor = $isLocked ? "cursor-not-allowed" : "cursor-pointer hover:shadow-md transition";
                                        ?>
                                        <div class="border rounded px-2 py-1.5 text-xs text-left <?= $cursor ?> <?= $bg ?>" <?= $onclick ?>>
                                            <div class="font-bold flex items-center justify-between">
                                                <span><?= h($shift['shift_type']) ?></span>
                                                <?= $warning ?>
                                            </div>
                                            <?php if($shift['start_time']): ?>
                                                <div class="text-[10px] mt-0.5 opacity-80"><?= date('g:ia', strtotime($shift['start_time'])) ?> - <?= date('g:ia', strtotime($shift['end_time'])) ?></div>
                                            <?php endif; ?>
                                            <?php if($shift['assigned_by_name']): ?>
                                                <div class="text-[9px] mt-1 opacity-60 italic" title="Assigned by">By: <?= h(explode(' ', $shift['assigned_by_name'])[0]) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <?php if (!$isLocked): ?>
                                        <button onclick="openAssignModal(<?= $emp['id'] ?>, '<?= h(addslashes($emp['full_name'])) ?>', '<?= $dateStr ?>', '', '', '', '<?= h(addslashes($emp['employment_category'] ?? 'Full-Time')) ?>')" class="w-8 h-8 rounded-full bg-slate-100 text-slate-400 hover:bg-primary hover:text-white transition flex items-center justify-center mx-auto opacity-0 group-hover:opacity-100">
                                            <i class="fa-solid fa-plus"></i>
                                        </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            <?php 
                                $curr->modify('+1 day');
                            endfor; 
                            ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php else: // leaves tab ?>

        <div class="flex justify-between items-center mb-6">
            <h3 class="font-bold text-slate-800 text-lg">Leave Requests</h3>
            <?php if (!in_array($user['role'], ['Admin', 'Super Admin', 'HR Admin', 'HR'])): ?>
                <button onclick="document.getElementById('leaveModal').classList.remove('hidden')" class="bg-primary hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium shadow-sm transition">
                    <i class="fa-solid fa-plus mr-2"></i> Request Leave
                </button>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-600">
                        <th class="p-4 font-semibold">Employee</th>
                        <th class="p-4 font-semibold">Type</th>
                        <th class="p-4 font-semibold">Dates</th>
                        <th class="p-4 font-semibold">Status</th>
                        <th class="p-4 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($leavesList as $l): 
                        $statusColor = 'bg-yellow-100 text-yellow-800';
                        if ($l['status'] === 'Approved') $statusColor = 'bg-green-100 text-green-800';
                        if ($l['status'] === 'Rejected') $statusColor = 'bg-red-100 text-red-800';
                        
                        // Check if it's an HR leave
                        $isHRLeave = in_array($l['requester_role'], ['Admin', 'Super Admin', 'HR Admin']);
                        
                        // Fetch approvals if HR
                        $approvals = 0;
                        if ($isHRLeave && $l['status'] === 'Pending') {
                            $cnt = $pdo->prepare('SELECT COUNT(*) FROM leave_approvals WHERE leave_id = ?');
                            $cnt->execute([$l['id']]);
                            $approvals = (int)$cnt->fetchColumn();
                        }
                    ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="p-4">
                                <div class="font-medium text-slate-800"><?= h($l['full_name']) ?></div>
                                <?php if($isHRLeave): ?>
                                    <span class="inline-block mt-1 text-[10px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded font-bold">HR/Admin</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <div class="font-medium"><?= h($l['leave_type']) ?></div>
                                <?php if($l['leave_type'] === 'Sick' && $l['medical_certificate']): ?>
                                    <a href="<?= h($l['medical_certificate']) ?>" target="_blank" class="text-xs text-blue-600 hover:underline mt-1 inline-flex items-center">
                                        <i class="fa-solid fa-paperclip mr-1"></i> Med Cert
                                    </a>
                                <?php elseif($l['leave_type'] === 'Sick'): ?>
                                    <span class="text-[10px] text-red-500 block mt-1"><i class="fa-solid fa-triangle-exclamation"></i> No Cert</span>
                                <?php endif; ?>
                                <?php if(!empty($l['reason'])): ?>
                                    <div class="text-xs text-slate-500 italic mt-1 max-w-[200px] truncate" title="<?= h($l['reason']) ?>">
                                        "<?= h($l['reason']) ?>"
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-slate-600">
                                <?= date('M d, Y', strtotime($l['start_date'])) ?> to <?= date('M d, Y', strtotime($l['end_date'])) ?>
                            </td>
                            <td class="p-4">
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $statusColor ?>">
                                    <?= h($l['status']) ?>
                                    <?php if($l['status'] === 'Pending' && $isHRLeave): ?>
                                        (<?= $approvals ?>/2 Votes)
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <?php if ($l['status'] === 'Pending' && in_array($user['role'], ['Admin', 'Super Admin', 'HR Admin', 'HR'])): ?>
                                    <div class="flex items-center justify-end space-x-2">
                                        <a href="leave?tab=leaves&action=approve&id=<?= $l['id'] ?>" class="text-green-600 hover:bg-green-50 p-2 rounded transition" title="Approve">
                                            <i class="fa-solid fa-check text-lg"></i>
                                        </a>
                                        <a href="leave?tab=leaves&action=reject&id=<?= $l['id'] ?>" class="text-red-600 hover:bg-red-50 p-2 rounded transition" title="Reject">
                                            <i class="fa-solid fa-times text-lg"></i>
                                        </a>
                                    </div>
                                <?php elseif ($l['status'] === 'Approved' && $l['leave_type'] === 'Sick' && in_array($user['role'], ['Admin', 'Super Admin', 'HR Admin', 'HR'])): ?>
                                    <a href="leave?tab=leaves&action=void&id=<?= $l['id'] ?>" onclick="return confirmLink(event, 'Are you sure you want to VOID this leave? This will mark the employee as AWOL and deduct penalties from their payroll.', this.href)" class="text-xs bg-red-50 text-red-600 hover:bg-red-600 hover:text-white px-3 py-1.5 rounded transition" title="Mark as Void / AWOL (Refunds Balance & Penalizes)">
                                        Void (AWOL)
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($leavesList)): ?>
                        <tr><td colspan="5" class="p-8 text-center text-slate-500">No leave requests found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (!in_array($user['role'], ['Admin', 'Super Admin', 'HR Admin', 'HR'])): ?>
        <!-- Leave Modal -->
        <div id="leaveModal" class="fixed inset-0 z-50 bg-black/50 hidden flex items-center justify-center">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
                <div class="p-4 border-b flex justify-between items-center bg-slate-50">
                    <h3 class="font-bold text-slate-800">Request Leave</h3>
                    <button onclick="document.getElementById('leaveModal').classList.add('hidden')" type="button" class="text-slate-400 hover:text-red-500 transition"><i class="fa-solid fa-times text-xl"></i></button>
                </div>
                <form autocomplete="off" method="POST" action="leave?tab=leaves" enctype="multipart/form-data" class="p-5 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="request_leave">
                    
                    <?php if ($employeeId): ?>
                        <input type="hidden" name="employee_id" value="<?= $employeeId ?>">
                    <?php else: ?>
                        <!-- Fallback if current user has no employee record -->
                        <div class="bg-yellow-50 text-yellow-800 p-3 rounded text-sm">
                            Your user account is not linked to an Employee profile. You cannot request leave.
                        </div>
                    <?php endif; ?>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Leave Type</label>
                        <select name="leave_type" id="modal_leave_type" class="w-full rounded border-slate-300 p-2 focus:ring-primary focus:border-primary">
                            <option value="Vacation">Vacation</option>
                            <option value="Sick">Sick</option>
                            <option value="Unpaid">Unpaid</option>
                        </select>
                    </div>
                    
                    <div id="med_cert_container" class="hidden bg-slate-50 p-3 rounded border border-slate-200">
                        <div class="flex justify-between items-center mb-1">
                            <label class="block text-sm font-medium text-slate-700">Sick Leave Balance</label>
                            <span class="text-xs font-bold bg-indigo-100 text-indigo-800 px-2 py-0.5 rounded-full"><?= $sickLeaveBalance ?> Day(s)</span>
                        </div>
                        <p class="text-xs text-slate-500 mb-2">Note: Medical Certificate is optional for 1 day, but MANDATORY for 2+ consecutive days.</p>
                        <input type="file" name="medical_certificate" id="med_cert_input" accept="image/*,.pdf" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Start Date</label>
                            <input type="date" name="start_date" required class="w-full rounded border-slate-300 p-2 focus:ring-primary focus:border-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">End Date</label>
                            <input type="date" name="end_date" required class="w-full rounded border-slate-300 p-2 focus:ring-primary focus:border-primary">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Reason</label>
                        <textarea name="reason" rows="3" class="w-full rounded border-slate-300 p-2 focus:ring-primary focus:border-primary"></textarea>
                    </div>
                    
                    <div class="pt-2 text-right">
                        <button type="submit" class="bg-primary hover:bg-indigo-700 text-white px-5 py-2 rounded-lg font-medium shadow-sm transition">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<!-- Assign Shift Modal -->
<div id="assignModal" class="fixed inset-0 z-50 bg-black/50 hidden flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm overflow-hidden">
        <div class="p-4 border-b flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800">Assign Shift</h3>
            <button onclick="document.getElementById('assignModal').classList.add('hidden')" type="button" class="text-slate-400 hover:text-red-500 transition"><i class="fa-solid fa-times text-xl"></i></button>
        </div>
        <form autocomplete="off" method="POST" action="leave?tab=roster&week_offset=<?= $weekOffset ?>" class="p-5 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="assign_shift">
            <input type="hidden" name="employee_id" id="shift_emp_id">
            <input type="hidden" name="shift_date" id="shift_date_input">
            
            <p class="text-sm text-slate-600 bg-slate-100 p-2 rounded"><strong id="shift_emp_name"></strong><br><span id="shift_date_display"></span></p>
            
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Shift Type *</label>
                <select name="shift_type" id="shift_type_select" required class="w-full rounded border-slate-300 p-2 focus:ring-primary focus:border-primary">
                    <option value="Morning">Morning</option>
                    <option value="Mid">Mid</option>
                    <option value="Night">Night</option>
                </select>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Start (Opt)</label>
                    <input type="time" name="start_time" id="shift_start" class="w-full rounded border-slate-300 p-2 text-sm focus:ring-primary focus:border-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">End (Opt)</label>
                    <input type="time" name="end_time" id="shift_end" class="w-full rounded border-slate-300 p-2 text-sm focus:ring-primary focus:border-primary">
                </div>
            </div>
            
            <div id="shift_action_buttons" class="pt-2 flex justify-between items-center space-x-3">
                <button type="button" onclick="deleteShift()" id="btn_delete_shift" class="hidden text-red-600 hover:bg-red-50 px-4 py-2 rounded-lg font-medium transition">Delete</button>
                <button type="submit" class="bg-primary hover:bg-indigo-700 text-white px-5 py-2 w-full rounded-lg font-medium shadow-sm transition flex-1">Save Shift</button>
            </div>
            
            <div id="shift_confirm_delete" class="hidden pt-2 bg-red-50 p-4 rounded-lg border border-red-200 mt-2">
                <p class="text-sm text-red-600 font-medium mb-3 text-center">Are you sure you want to remove this shift?</p>
                <div class="flex justify-between items-center space-x-3">
                    <button type="button" onclick="cancelDeleteShift()" class="text-slate-600 hover:bg-slate-200 bg-white px-4 py-2 rounded-lg font-medium transition flex-1 border border-slate-300">Cancel</button>
                    <button type="button" onclick="confirmDeleteShift()" class="bg-red-600 hover:bg-red-700 text-white px-5 py-2 w-full rounded-lg font-medium shadow-sm transition flex-1">Yes, Delete</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
let currentEmpCategory = 'Full-Time';

function openAssignModal(empId, empName, dateStr, currentType, currentStart, currentEnd, category) {
    document.getElementById('shift_emp_id').value = empId;
    document.getElementById('shift_emp_name').innerText = empName;
    document.getElementById('shift_date_input').value = dateStr;
    currentEmpCategory = category || 'Full-Time';
    
    // Format display date
    const d = new Date(dateStr + "T12:00:00");
    const displayStr = d.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });
    document.getElementById('shift_date_display').innerText = displayStr + " (" + currentEmpCategory + ")";
    
    if (currentType) {
        document.getElementById('shift_type_select').value = currentType;
        document.getElementById('btn_delete_shift').classList.remove('hidden');
    } else {
        document.getElementById('shift_type_select').value = '';
        document.getElementById('btn_delete_shift').classList.add('hidden');
    }
    
    document.getElementById('shift_start').value = currentStart || '';
    document.getElementById('shift_end').value = currentEnd || '';
    
    document.getElementById('shift_action_buttons').classList.remove('hidden');
    document.getElementById('shift_confirm_delete').classList.add('hidden');
    
    document.getElementById('assignModal').classList.remove('hidden');
}

function deleteShift() {
    document.getElementById('shift_action_buttons').classList.add('hidden');
    document.getElementById('shift_confirm_delete').classList.remove('hidden');
}

function cancelDeleteShift() {
    document.getElementById('shift_confirm_delete').classList.add('hidden');
    document.getElementById('shift_action_buttons').classList.remove('hidden');
}

function confirmDeleteShift() {
    const form = document.querySelector('#assignModal form');
    form.querySelector('input[name="action"]').value = 'delete_shift';
    form.submit();
}

document.getElementById('shift_type_select').addEventListener('change', function() {
    const shift = this.value;
    if (!shift) return;
    
    let startHour = 0;
    if (shift === 'Morning') startHour = 7;
    else if (shift === 'Mid') startHour = 11;
    else if (shift === 'Night') startHour = 16;
    
    // Default durations
    let duration = 8;
    if (currentEmpCategory === 'Part-Time') {
        duration = 6; // Configurable Part-Time default
    }
    
    let endHour = startHour + duration;
    
    // Format to HH:MM string
    const startStr = startHour.toString().padStart(2, '0') + ":00";
    
    // Handle midnight wrap-around for End Time
    if (endHour >= 24) endHour -= 24;
    const endStr = endHour.toString().padStart(2, '0') + ":00";
    
    document.getElementById('shift_start').value = startStr;
    document.getElementById('shift_end').value = endStr;
});

document.getElementById('modal_leave_type')?.addEventListener('change', function() {
    const type = this.value;
    const certContainer = document.getElementById('med_cert_container');
    const certInput = document.getElementById('med_cert_input');
    
    if (type === 'Sick') {
        certContainer.classList.remove('hidden');
        certInput.required = true;
    } else {
        certContainer.classList.add('hidden');
        certInput.required = false;
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

