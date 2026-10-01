<?php
require_once __DIR__ . '/init.php';
require_login();

$user = current_user();



$bypassRoles = ['System Admin', 'Super Admin', 'Central HR', 'Executives', 'Global Accountant', 'Branch Accountant', 'Clock', 'Cashier Terminal', 'Table-Kiosk', 'Kiosk', 'Cashier', 'Tablet Kiosk', 'Admin'];
$isEmployee = !in_array($user['role'], $bypassRoles);

$employee = [];
$totalHours = 0;
$tenure = 'N/A';
$avgRating = 0;
$upcomingShifts = [];
$myPayslips = [];
$bestHB = $bestBarista = $bestTrainee = null;

if ($isEmployee) {
    // Ensure they have an employee_id linked
    $stmt = $pdo->prepare('SELECT employee_id FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $empId = $stmt->fetchColumn();

    if (!$empId) {
        die("Your account is not linked to an employee profile.");
    }

    // Fetch basic Employee info
    $emp = $pdo->prepare('SELECT *, CONCAT(first_name, " ",   last_name) AS full_name FROM employees WHERE id = ?');
    $emp->execute([$empId]);
    $employee = $emp->fetch();

    // Handle Leave Request
    $message = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_leave') {
        $type = trim($_POST['leave_type']);
        $start = trim($_POST['start_date']);
        $end = trim($_POST['end_date']);
        $reason = trim($_POST['reason']);
        
        $status = 'Pending';
        if ($type === 'Sick Leave') {
            $status = 'Approved';
        }
        
        // Maternal Leave Logic (105 Days, Female Only)
        if ($type === 'Maternal Leave') {
            if (($employee['sex'] ?? '') !== 'Female') {
                die("Maternal Leave is only applicable to female employees.");
            }
            $startDateObj = new DateTime($start);
            $startDateObj->modify('+105 days');
            $end = $startDateObj->format('Y-m-d');
            $status = 'Approved'; // Maternal is typically pre-approved statutorily
        }
        
        $medCertPath = null;
        if ($type === 'Sick Leave' && !empty($_FILES['med_cert']['tmp_name'])) {
            $uploadDir = __DIR__ . '/uploads/leaves/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext = pathinfo($_FILES['med_cert']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('cert_', true) . '.' . $ext;
            if (move_uploaded_file($_FILES['med_cert']['tmp_name'], $uploadDir . $filename)) {
                $medCertPath = 'uploads/leaves/' . $filename;
            }
        }
        
        $stmt = $pdo->prepare('INSERT INTO leaves (employee_id, leave_type, start_date, end_date, reason, status, med_cert, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$empId, $type, $start, $end, $reason, $status, $medCertPath]);
        $leaveId = $pdo->lastInsertId();
        
        // Count cleared shifts
        $countShifts = $pdo->prepare('SELECT COUNT(*) FROM schedules WHERE employee_id = ? AND shift_date BETWEEN ? AND ?');
        $countShifts->execute([$empId, $start, $end]);
        $clearedCount = $countShifts->fetchColumn();
        
        // Push shifts to Shift Board instead of deleting
        $updateShifts = $pdo->prepare("UPDATE schedules SET employee_id = NULL, status = 'Open' WHERE employee_id = ? AND shift_date BETWEEN ? AND ?");
        $updateShifts->execute([$empId, $start, $end]);
        
        $notifMsg = $employee['full_name'] . " requested " . $type . " (" . date('M d', strtotime($start)) . " - " . date('M d', strtotime($end)) . ").";
        if ($clearedCount > 0) {
            $notifMsg .= " $clearedCount scheduled shift(s) were posted to the Shift Board!";
        }
        
        // Notify HR
        notify_roles($pdo, ['Admin', 'Super Admin', 'HR', 'HR Admin'], 
            $notifMsg, 
            "leave.php", 
            "fa-plane-departure", "primary");
        
        $message = "Leave requested successfully. Your assigned shifts during this period have been cleared.";
    }

    // Fetch upcoming shifts (Starting from Monday of the current week)
    $shifts = $pdo->prepare('SELECT * FROM schedules WHERE employee_id = ? AND shift_date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) ORDER BY shift_date ASC LIMIT 7');
    $shifts->execute([$empId]);
    $upcomingShifts = $shifts->fetchAll();

    // Fetch released payslips
    $payslips = $pdo->prepare('SELECT * FROM payroll WHERE employee_id = ? AND status = "Released" ORDER BY created_at DESC LIMIT 5');
    $payslips->execute([$empId]);
    $myPayslips = $payslips->fetchAll();

    // Tenure calculation
    if (!empty($employee['date_hired'])) {
        $d1 = new DateTime($employee['date_hired']);
        $d2 = new DateTime();
        $diff = $d1->diff($d2);
        if ($diff->y > 0) $tenure = $diff->y . ' yrs, ' . $diff->m . ' mos';
        elseif ($diff->m > 0) $tenure = $diff->m . ' mos, ' . $diff->d . ' days';
        else $tenure = $diff->d . ' days';
    }

    // Total Hours
    $hoursStmt = $pdo->prepare("SELECT SUM(TIMESTAMPDIFF(MINUTE, time_in, time_out))/60 as total FROM attendance WHERE employee_id = ? AND time_out IS NOT NULL");
    $hoursStmt->execute([$empId]);
    $totalHours = round($hoursStmt->fetchColumn() ?? 0, 1);

    // Ratings (Average)
    $ratingStmt = $pdo->prepare("SELECT AVG(total_score) FROM performance_reviews WHERE employee_id = ?");
    $ratingStmt->execute([$empId]);
    $avgRating = round($ratingStmt->fetchColumn() ?? 0, 1);

    // Leaderboards
    $bestHB = $pdo->query("SELECT CONCAT(e.first_name, ' ',   e.last_name) AS full_name, AVG(p.total_score) as avg_score FROM performance_reviews p JOIN employees e ON e.id = p.employee_id WHERE e.position LIKE '%Head Barista%' GROUP BY e.id ORDER BY avg_score DESC LIMIT 1")->fetch();
    $bestBarista = $pdo->query("SELECT CONCAT(e.first_name, ' ',   e.last_name) AS full_name, AVG(p.total_score) as avg_score FROM performance_reviews p JOIN employees e ON e.id = p.employee_id WHERE e.position LIKE '%Barista%' AND e.position NOT LIKE '%Head%' GROUP BY e.id ORDER BY avg_score DESC LIMIT 1")->fetch();
    $bestTrainee = $pdo->query("SELECT CONCAT(e.first_name, ' ',   e.last_name) AS full_name, AVG(p.total_score) as avg_score FROM performance_reviews p JOIN employees e ON e.id = p.employee_id WHERE e.position LIKE '%Trainee%' GROUP BY e.id ORDER BY avg_score DESC LIMIT 1")->fetch();
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="px-6 py-4 border-b border-slate-200 bg-white flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">My Dashboard</h1>
        <p class="text-slate-500 text-sm"><?= h($employee['full_name'] ?? $user['name']) ?></p>
    </div>
</div>



<script>
function handleLeaveTypeChange() {
    const type = document.getElementById('leaveType').value;
    const start = document.getElementById('startDate').value;
    const endInput = document.getElementById('endDate');
    
    if (type === 'Maternal Leave') {
        endInput.readOnly = true;
        endInput.classList.add('bg-slate-100');
        endInput.classList.remove('bg-white');
        
        if (start) {
            const date = new Date(start);
            date.setDate(date.getDate() + 105);
            const yyyy = date.getFullYear();
            const mm = String(date.getMonth() + 1).padStart(2, '0');
            const dd = String(date.getDate()).padStart(2, '0');
            endInput.value = `${yyyy}-${mm}-${dd}`;
        }
    } else {
        endInput.readOnly = false;
        endInput.classList.remove('bg-slate-100');
        endInput.classList.add('bg-white');
    }
    
    if (type === 'Sick Leave') {
        document.getElementById('medCertContainer').classList.remove('hidden');
    } else {
        document.getElementById('medCertContainer').classList.add('hidden');
    }
}
</script>

<div class="p-6 max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-6">
<?php if ($isEmployee): ?>

    <?php if ($message): ?>
        <div class="md:col-span-3 bg-emerald-50 text-emerald-700 p-4 rounded-lg flex items-center shadow-sm border border-emerald-100">
            <i class="fa-solid fa-check-circle mr-3 text-lg"></i>
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <!-- Stats Row -->
    <div class="md:col-span-3 grid grid-cols-1 sm:grid-cols-3 gap-6 mb-2">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex items-center">
            <div class="h-12 w-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xl mr-4">
                <i class="fa-solid fa-clock"></i>
            </div>
            <div>
                <div class="text-sm font-medium text-slate-500">Total Hours</div>
                <div class="text-2xl font-bold text-slate-800"><?= $totalHours ?></div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex items-center">
            <div class="h-12 w-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-xl mr-4">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
            <div>
                <div class="text-sm font-medium text-slate-500">Tenure</div>
                <div class="text-2xl font-bold text-slate-800"><?= $tenure ?></div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex items-center">
            <div class="h-12 w-12 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center text-xl mr-4">
                <i class="fa-solid fa-star"></i>
            </div>
            <div>
                <div class="text-sm font-medium text-slate-500">Avg Rating</div>
                <div class="text-2xl font-bold text-slate-800"><?= $avgRating > 0 ? $avgRating : 'N/A' ?> <span class="text-sm font-normal text-slate-500">/ 5</span></div>
            </div>
        </div>
    </div>

    <!-- Left Column: Shifts -->
    <div class="md:col-span-2 space-y-6">
        
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
                <h2 class="font-bold text-slate-800"><i class="fa-regular fa-calendar-alt text-primary mr-2"></i> Upcoming Shifts</h2>
                <button onclick="document.getElementById('leaveModal').classList.remove('hidden')" class="text-sm text-indigo-600 font-medium hover:text-indigo-800">
                    <i class="fa-solid fa-plane-departure mr-1"></i> Request Leave
                </button>
            </div>
            <div class="p-0">
                <?php if (empty($upcomingShifts)): ?>
                    <div class="p-6 text-center text-slate-500">No upcoming shifts assigned.</div>
                <?php else: ?>
                    <ul class="divide-y divide-slate-100">
                        <?php foreach ($upcomingShifts as $s): ?>
                            <li class="p-4 hover:bg-slate-50 flex justify-between items-center">
                                <div>
                                    <div class="font-bold text-slate-800"><?= date('l, M d, Y', strtotime($s['shift_date'])) ?></div>
                                    <div class="text-sm text-slate-500"><?= h($s['shift_type']) ?> Shift</div>
                                </div>
                                <span class="bg-indigo-50 text-indigo-700 px-3 py-1 rounded-full text-xs font-medium border border-indigo-100">Assigned</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
    
    <!-- Right Column: Payslips -->
    <div class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 border-b border-slate-200 bg-slate-50">
                <h2 class="font-bold text-slate-800"><i class="fa-solid fa-money-bill-wave text-emerald-600 mr-2"></i> My Payslips</h2>
            </div>
            <div class="p-0">
                <?php if (empty($myPayslips)): ?>
                    <div class="p-6 text-center text-slate-500 text-sm">No released payslips yet.</div>
                <?php else: ?>
                    <ul class="divide-y divide-slate-100">
                        <?php foreach ($myPayslips as $p): ?>
                            <li class="p-4 hover:bg-slate-50">
                                <div class="flex justify-between items-center mb-1">
                                    <div class="font-bold text-slate-800">₱<?= number_format($p['net_pay'], 2) ?></div>
                                    <span class="text-xs text-emerald-600 font-medium bg-emerald-50 px-2 py-0.5 rounded"><?= h($p['payment_method']) ?></span>
                                </div>
                                <div class="text-xs text-slate-500">
                                    <?= date('M d', strtotime($p['period_start'])) ?> - <?= date('M d', strtotime($p['period_end'])) ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mt-6">
            <div class="p-4 border-b border-slate-200 bg-slate-50">
                <h2 class="font-bold text-slate-800"><i class="fa-solid fa-trophy text-amber-500 mr-2"></i> Leaderboard</h2>
            </div>
            <div class="p-4 space-y-4">
                <div>
                    <div class="text-xs font-semibold text-slate-500 uppercase mb-1">Top Head Barista</div>
                    <div class="flex justify-between items-center">
                        <span class="font-medium text-slate-800"><?= $bestHB ? h($bestHB['full_name']) : 'N/A' ?></span>
                        <span class="text-amber-600 font-bold text-sm"><i class="fa-solid fa-star text-xs mr-1"></i><?= $bestHB ? round($bestHB['avg_score'], 1) : '-' ?></span>
                    </div>
                </div>
                <div class="border-t border-slate-100 pt-3">
                    <div class="text-xs font-semibold text-slate-500 uppercase mb-1">Top Barista</div>
                    <div class="flex justify-between items-center">
                        <span class="font-medium text-slate-800"><?= $bestBarista ? h($bestBarista['full_name']) : 'N/A' ?></span>
                        <span class="text-amber-600 font-bold text-sm"><i class="fa-solid fa-star text-xs mr-1"></i><?= $bestBarista ? round($bestBarista['avg_score'], 1) : '-' ?></span>
                    </div>
                </div>
                <div class="border-t border-slate-100 pt-3">
                    <div class="text-xs font-semibold text-slate-500 uppercase mb-1">Top Trainee</div>
                    <div class="flex justify-between items-center">
                        <span class="font-medium text-slate-800"><?= $bestTrainee ? h($bestTrainee['full_name']) : 'N/A' ?></span>
                        <span class="text-amber-600 font-bold text-sm"><i class="fa-solid fa-star text-xs mr-1"></i><?= $bestTrainee ? round($bestTrainee['avg_score'], 1) : '-' ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
<?php endif; ?>

<!-- Leave Modal -->
<div id="leaveModal" class="fixed inset-0 z-50 bg-black/50 hidden flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="p-4 border-b flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800"><i class="fa-solid fa-plane-departure text-indigo-600 mr-2"></i> Request Leave</h3>
            <button onclick="document.getElementById('leaveModal').classList.add('hidden')" class="text-slate-400 hover:text-red-500"><i class="fa-solid fa-times"></i></button>
        </div>
        <form autocomplete="off" method="POST" action="ess" class="p-5" enctype="multipart/form-data">
            <input type="hidden" name="action" value="request_leave">
            
            <p class="text-xs text-slate-500 mb-4 bg-slate-50 p-2 rounded border border-slate-200">
                <i class="fa-solid fa-info-circle text-indigo-500 mr-1"></i> If you have assigned shifts during these dates, they will automatically be posted to the Shift Board for others to claim.
            </p>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Leave Type</label>
                <select name="leave_type" id="leaveType" required class="w-full rounded border-slate-300 p-2" onchange="handleLeaveTypeChange()">
                    <option value="Vacation">Vacation Leave</option>
                    <option value="Sick Leave">Sick Leave (Auto-Approved)</option>
                    <option value="Unpaid">Unpaid Leave</option>
                    <?php if (($employee['sex'] ?? '') === 'Female'): ?>
                        <option value="Maternal Leave">Maternal Leave (105 Days)</option>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Start Date</label>
                    <input type="date" name="start_date" id="startDate" required class="w-full rounded border-slate-300 p-2" onchange="handleLeaveTypeChange()">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">End Date</label>
                    <input type="date" name="end_date" id="endDate" required class="w-full rounded border-slate-300 p-2 bg-white">
                </div>
            </div>
            
            <div id="medCertContainer" class="mb-4 hidden bg-yellow-50 p-3 rounded border border-yellow-200">
                <label class="block text-sm font-medium text-slate-700 mb-1">Medical Certificate (Optional)</label>
                <p class="text-xs text-slate-500 mb-2">You can upload this now or later before clocking in.</p>
                <input type="file" name="med_cert" accept="image/*,.pdf" class="w-full text-sm">
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-700 mb-1">Reason</label>
                <textarea name="reason" rows="2" class="w-full rounded border-slate-300 p-2"></textarea>
            </div>
            
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('leaveModal').classList.add('hidden')" class="px-4 py-2 text-slate-600 hover:text-slate-800">Cancel</button>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded shadow transition font-medium">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

