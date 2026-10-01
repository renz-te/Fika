<?php
require_once __DIR__ . '/init.php';
require_login();

$user = current_user();

if (!in_array($user['role'], ['Employee', 'Barista', 'Head Barista', 'Admin', 'Super Admin', 'HR Admin', 'HR'])) {
    redirect('dashboard');
}

$isAdminHR = in_array($user['role'], ['Admin', 'Super Admin', 'HR Admin', 'HR']);

$stmt = $pdo->prepare('SELECT id, position, employment_category FROM employees WHERE id = (SELECT employee_id FROM users WHERE id = ?)');
$stmt->execute([$user['id']]);
$emp = $stmt->fetch();

$isBarista = false;
if (!$isAdminHR && $emp) {
    $isBarista = (stripos($emp['position'], 'Barista') !== false) && ($emp['employment_category'] === 'Full-Time' || $emp['employment_category'] === 'Part-Time');
} elseif (!$isAdminHR && !$emp) {
    die("Your account is not linked to an employee profile.");
}

$eligibleEmployees = [];
if ($isAdminHR) {
    $branchFilter = get_branch_filter();
    $stmt = $pdo->query("SELECT id, CONCAT(first_name, ' ',   last_name) AS full_name, employment_category FROM employees WHERE status = 'Active' AND position LIKE '%Barista%' AND employment_category IN ('Full-Time', 'Part-Time') {$branchFilter} ORDER BY first_name ASC");
    $eligibleEmployees = $stmt->fetchAll();
}

// Handle claiming
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_shift_id'])) {
    if (!$isBarista) {
        flash('error', 'Only Full-Time and Part-Time Baristas can claim shifts.');
        redirect('shift_board');
    }
    
    $shiftId = $_POST['claim_shift_id'];
    
    // Check if shift is still open
    $check = $pdo->prepare("SELECT id, shift_date FROM schedules WHERE id = ? AND status = 'Open'");
    $check->execute([$shiftId]);
    $openShift = $check->fetch();
    
    if ($openShift) {
        // Check if employee is on approved leave
        $checkLeave = $pdo->prepare("SELECT id FROM leaves WHERE employee_id = ? AND status = 'Approved' AND ? BETWEEN start_date AND end_date");
        $checkLeave->execute([$emp['id'], $openShift['shift_date']]);
        
        if ($checkLeave->fetch()) {
            flash('error', 'You are on an Approved Leave on this day. You cannot claim this shift.');
        } else {
            // Check if employee already has a shift on this date
            $checkDouble = $pdo->prepare("SELECT id FROM schedules WHERE employee_id = ? AND shift_date = ?");
            $checkDouble->execute([$emp['id'], $openShift['shift_date']]);
            if ($checkDouble->fetch()) {
                flash('error', 'You already have a shift scheduled on this day. You cannot claim multiple shifts on the same day.');
            } else {
                $update = $pdo->prepare("UPDATE schedules SET employee_id = ?, status = 'Assigned' WHERE id = ?");
                $update->execute([$emp['id'], $shiftId]);
                flash('success', 'You have successfully claimed the shift!');
            }
        }
    } else {
        flash('error', 'Sorry, this shift is no longer available.');
    }
    redirect('shift_board');
}

// Handle deployment (HR/Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deploy_shift_id']) && $isAdminHR) {
    $shiftId = $_POST['deploy_shift_id'];
    $empId = $_POST['employee_id'];
    
    // Check if shift is still open
    $check = $pdo->prepare("SELECT id, shift_date FROM schedules WHERE id = ? AND status = 'Open'");
    $check->execute([$shiftId]);
    $openShift = $check->fetch();
    
    if ($openShift) {
        // Check if employee is on approved leave
        $checkLeave = $pdo->prepare("SELECT id FROM leaves WHERE employee_id = ? AND status = 'Approved' AND ? BETWEEN start_date AND end_date");
        $checkLeave->execute([$empId, $openShift['shift_date']]);
        
        if ($checkLeave->fetch()) {
            flash('error', 'This employee is on an Approved Leave on this day. Please select someone else.');
        } else {
            // Check double booking
            $checkDouble = $pdo->prepare("SELECT id FROM schedules WHERE employee_id = ? AND shift_date = ?");
            $checkDouble->execute([$empId, $openShift['shift_date']]);
            if ($checkDouble->fetch()) {
                flash('error', 'This employee already has a shift on this day. Please select someone else.');
            } else {
                // Assign shift
                $update = $pdo->prepare("UPDATE schedules SET employee_id = ?, status = 'Assigned', assigned_by = ? WHERE id = ?");
                $update->execute([$empId, $user['id'], $shiftId]);
                
                // Check warnings for the week
                $shiftDate = $openShift['shift_date'];
                $wD = new DateTime($shiftDate);
                // Get monday of that week
                $wD->modify('monday this week');
                $startWeek = $wD->format('Y-m-d');
                $wD->modify('sunday this week');
                $endWeek = $wD->format('Y-m-d');
                
                // Get employee category
                $catStmt = $pdo->prepare('SELECT employment_category, full_name FROM employees WHERE id = ?');
                $catStmt->execute([$empId]);
                $eData = $catStmt->fetch();
                $category = $eData['employment_category'] ?? 'Full-Time';
                $empName = $eData['full_name'];
                
                $stmt = $pdo->prepare("SELECT COUNT(*) as days, SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time))/60 as hours FROM schedules WHERE employee_id = ? AND shift_date BETWEEN ? AND ?");
                $stmt->execute([$empId, $startWeek, $endWeek]);
                $stats = $stmt->fetch();
                
                $days = $stats['days'];
                $hours = round($stats['hours'] ?? 0, 1);
                
                $warningMsg = '';
                if ($category === 'Full-Time' && $days > 5) {
                    $warningMsg = " (Warning: $empName is now scheduled for $days days this week, exceeding the 5-day recommendation)";
                } elseif ($category === 'Part-Time' && $hours > 35) {
                    $warningMsg = " (Warning: $empName is now scheduled for $hours hours this week, exceeding the 35-hour recommendation)";
                }
                
                flash('success', 'Successfully deployed shift to employee!' . $warningMsg);
            }
        }
    } else {
        flash('error', 'Sorry, this shift is no longer available.');
    }
    redirect('shift_board');
}


// Fetch open shifts in the future
$branchFilter = get_branch_filter();
$stmt = $pdo->prepare("
    SELECT * FROM schedules 
    WHERE status = 'Open' AND shift_date >= CURDATE() {$branchFilter}
    ORDER BY shift_date ASC, start_time ASC
");
$stmt->execute();
$openShifts = $stmt->fetchAll();

$pageTitle = 'Shift Board';
require_once __DIR__ . '/includes/header.php';
?>

<div class="px-6 py-4 border-b border-slate-200 bg-white flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Shift Board</h1>
        <p class="text-slate-500 text-sm">View and claim open shifts</p>
    </div>
</div>

<div class="p-6 max-w-6xl mx-auto">
    <?php if (!$isBarista && !$isAdminHR): ?>
        <div class="bg-amber-50 text-amber-700 p-4 rounded-lg flex items-center shadow-sm border border-amber-100 mb-6">
            <i class="fa-solid fa-triangle-exclamation mr-3 text-lg"></i>
            <div>
                <strong>Notice:</strong> Only Full-Time and Part-Time Baristas are eligible to claim shifts. Trainees and other roles can view this board but cannot claim.
            </div>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-200 bg-slate-50">
            <h2 class="font-bold text-slate-800"><i class="fa-solid fa-clipboard-list text-primary mr-2"></i> Available Shifts</h2>
        </div>
        <div class="p-0">
            <?php if (empty($openShifts)): ?>
                <div class="p-12 text-center text-slate-500 flex flex-col items-center">
                    <i class="fa-solid fa-mug-hot text-4xl text-slate-300 mb-3"></i>
                    <p class="text-lg font-medium text-slate-600">No open shifts right now!</p>
                    <p class="text-sm mt-1">Check back later for newly available shifts.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6">
                    <?php foreach ($openShifts as $s): ?>
                        <div class="border border-slate-200 rounded-lg p-5 hover:border-primary transition-colors flex flex-col h-full bg-slate-50 shadow-sm">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <div class="text-sm text-slate-500 uppercase tracking-wider font-semibold"><?= date('l', strtotime($s['shift_date'])) ?></div>
                                    <div class="font-bold text-xl text-slate-800"><?= date('M d, Y', strtotime($s['shift_date'])) ?></div>
                                </div>
                                <span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-bold border border-green-200">OPEN</span>
                            </div>
                            
                            <div class="space-y-2 flex-grow mb-6">
                                <div class="flex items-center text-sm text-slate-600">
                                    <i class="fa-solid fa-clock w-5 text-slate-400"></i>
                                    <span><?= date('h:i A', strtotime($s['start_time'])) ?> - <?= date('h:i A', strtotime($s['end_time'])) ?></span>
                                </div>
                                <div class="flex items-center text-sm text-slate-600">
                                    <i class="fa-solid fa-tag w-5 text-slate-400"></i>
                                    <span><?= h($s['shift_type']) ?> Shift</span>
                                </div>
                            </div>
                            
                            <?php if ($isAdminHR): ?>
                                <form autocomplete="off" method="POST" action="shift_board" class="mt-auto">
                                    <input type="hidden" name="deploy_shift_id" value="<?= $s['id'] ?>">
                                    <div class="flex gap-2">
                                        <select name="employee_id" required class="w-full text-sm rounded border-slate-300 focus:ring-primary focus:border-primary">
                                            <option value="">Select Barista...</option>
                                            <?php foreach ($eligibleEmployees as $ee): ?>
                                                <option value="<?= $ee['id'] ?>"><?= h($ee['full_name']) ?> (<?= h($ee['employment_category'] === 'Full-Time' ? 'FT' : 'PT') ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" onclick="return confirmFormButton(event, 'Are you sure you want to deploy this shift to the selected employee?')" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-1 px-3 rounded transition-colors shadow-sm text-sm">
                                            Deploy
                                        </button>
                                    </div>
                                </form>
                            <?php elseif ($isBarista): ?>
                                <form autocomplete="off" method="POST" action="shift_board" onsubmit="return confirmSubmit(event, 'Are you sure you want to claim this shift?');" class="mt-auto">
                                    <input type="hidden" name="claim_shift_id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="w-full bg-primary hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded transition-colors shadow-sm">
                                        Claim Shift
                                    </button>
                                </form>
                            <?php else: ?>
                                <button disabled class="w-full mt-auto bg-slate-200 text-slate-400 font-medium py-2 px-4 rounded cursor-not-allowed">
                                    Cannot Claim
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

