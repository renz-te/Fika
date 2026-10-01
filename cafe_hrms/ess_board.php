<?php
require_once __DIR__ . '/init.php';
require_login();

$user = current_user();

if ($user['role'] !== 'Employee') {
    if ($user['role'] === 'Kiosk') redirect('kiosk');
    redirect('dashboard');
}

$stmt = $pdo->prepare('SELECT employee_id FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$empId = $stmt->fetchColumn();

if (!$empId) {
    die("Your account is not linked to an employee profile.");
}

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'claim_shift') {
    $shiftId = (int)$_POST['shift_id'];
    
    // Validate shift is still open
    $check = $pdo->prepare('SELECT * FROM open_shifts WHERE id = ? AND status = "Open"');
    $check->execute([$shiftId]);
    $openShift = $check->fetch();
    
    if ($openShift) {
        if ($openShift['original_employee_id'] == $empId) {
            $message = "You cannot claim your own shift.";
        } else {
            // Check if claiming employee already has a shift on this date
            $conflict = $pdo->prepare('SELECT id FROM schedules WHERE employee_id = ? AND shift_date = ?');
            $conflict->execute([$empId, $openShift['shift_date']]);
            if ($conflict->fetchColumn()) {
                $message = "You already have a shift assigned on this date. You cannot claim another.";
            } else {
                // Claim it!
                // 1. Mark as Claimed
                $pdo->prepare('UPDATE open_shifts SET status = "Claimed", claimed_by = ? WHERE id = ?')
                    ->execute([$empId, $shiftId]);
                    
                // 2. Automatically Swap in the Schedules table (replace original employee with claiming employee)
                // We use UPDATE because the shift already exists for the original employee
                $pdo->prepare('UPDATE schedules SET employee_id = ? WHERE employee_id = ? AND shift_date = ? AND shift_type = ? LIMIT 1')
                    ->execute([$empId, $openShift['original_employee_id'], $openShift['shift_date'], $openShift['shift_type']]);
                
                $message = "You have successfully claimed the shift on " . date('M d, Y', strtotime($openShift['shift_date'])) . "! Your schedule has been updated.";
            }
        }
    } else {
        $message = "Sorry, this shift has already been claimed or is no longer available.";
    }
}

// Fetch Open Shifts
$openShiftsStmt = $pdo->query('
    SELECT o.*, CONCAT(e.first_name, " ",   e.last_name) AS original_name 
    FROM open_shifts o 
    JOIN employees e ON e.id = o.original_employee_id 
    WHERE o.status = "Open" 
    AND o.shift_date >= CURDATE()
    ORDER BY o.shift_date ASC
');
$openShifts = $openShiftsStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="px-6 py-4 border-b border-slate-200 bg-white flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Shift Board</h1>
        <p class="text-slate-500 text-sm">Help cover shifts for your peers and pick up extra hours!</p>
    </div>
    <a href="ess" class="text-slate-500 hover:text-indigo-600 font-medium transition flex items-center">
        <i class="fa-solid fa-arrow-left mr-2"></i> Back to Dashboard
    </a>
</div>

<div class="p-6 max-w-6xl mx-auto">
    <?php if ($message): ?>
        <div class="mb-6 <?= strpos($message, 'successfully') !== false ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-red-50 text-red-700 border-red-100' ?> p-4 rounded-lg flex items-center shadow-sm border">
            <i class="fa-solid <?= strpos($message, 'successfully') !== false ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-3 text-lg"></i>
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (empty($openShifts)): ?>
            <div class="col-span-full py-12 text-center text-slate-500 bg-white rounded-xl shadow-sm border border-slate-200">
                <i class="fa-regular fa-calendar-check text-4xl mb-3 text-slate-300"></i>
                <p class="font-medium text-lg text-slate-700">No shifts need coverage right now.</p>
                <p class="text-sm">Check back later! When peers go on leave, their shifts will appear here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($openShifts as $o): ?>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
                    <div class="p-4 border-b border-slate-100 bg-indigo-50/50 flex justify-between items-start">
                        <div>
                            <div class="font-bold text-slate-800 text-lg"><?= date('l, M d', strtotime($o['shift_date'])) ?></div>
                            <div class="text-indigo-600 font-medium text-sm"><?= h($o['shift_type']) ?> Shift</div>
                        </div>
                        <div class="bg-white px-2 py-1 rounded shadow-sm text-xs font-bold text-slate-500 border border-slate-200">OPEN</div>
                    </div>
                    <div class="p-4 flex-1">
                        <p class="text-sm text-slate-600 mb-2"><strong>Dropped by:</strong> <?= h($o['original_name']) ?></p>
                        <?php if ($o['reason']): ?>
                            <p class="text-xs text-slate-500 italic bg-slate-50 p-2 rounded">"<?= h($o['reason']) ?>"</p>
                        <?php endif; ?>
                    </div>
                    <div class="p-4 border-t border-slate-100 bg-slate-50">
                        <?php if ($o['original_employee_id'] == $empId): ?>
                            <button disabled class="w-full bg-slate-200 text-slate-500 py-2 rounded font-medium cursor-not-allowed">Your Shift</button>
                        <?php else: ?>
                            <form autocomplete="off" method="POST" action="ess_board">
                                <input type="hidden" name="action" value="claim_shift">
                                <input type="hidden" name="shift_id" value="<?= $o['id'] ?>">
                                <button type="submit" onclick="return confirmFormButton(event, 'Are you sure you want to claim this shift? This will automatically update your schedule.')" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded font-medium transition shadow-sm">
                                    Claim Shift
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

