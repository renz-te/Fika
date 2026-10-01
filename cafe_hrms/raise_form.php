<?php
require_once __DIR__ . '/init.php';
require_login();
require_role(['Admin', 'Super Admin', 'HR Admin', 'HR']);

$employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
if (!$employee_id) die("Employee ID required.");

$stmt = $pdo->prepare('SELECT *, CONCAT(first_name, " ",   last_name) AS full_name FROM employees WHERE id = ?');
$stmt->execute([$employee_id]);
$employee = $stmt->fetch();
if (!$employee) die("Employee not found.");

$isPartTime = ($employee['employment_category'] === 'Part-Time');

// 1. Bonus Logic (4-Week Cycle)
$bonusStmt = $pdo->prepare('SELECT AVG(total_score) as avg_score, COUNT(id) as review_count FROM performance_reviews WHERE employee_id = ? AND evaluation_type = "Official" AND (? IS NULL OR review_date > ?)');
$bonusStmt->execute([$employee_id, $employee['last_bonus_date'], $employee['last_bonus_date']]);
$bonusStats = $bonusStmt->fetch();
$bonusAvg = round((float)$bonusStats['avg_score'], 1);
$bonusReviews = (int)$bonusStats['review_count'];

$bonusAmount = 0;
$bonusPremium = 0;
$totalHours = 0;

if ($bonusAvg >= 4.5 && $bonusReviews >= 4) {
    if ($isPartTime) {
        $hoursStmt = $pdo->prepare('SELECT TIMESTAMPDIFF(MINUTE, time_in, time_out) as mins FROM attendance WHERE employee_id = ? AND time_out IS NOT NULL AND (? IS NULL OR DATE(time_in) > ?)');
        $hoursStmt->execute([$employee_id, $employee['last_bonus_date'], $employee['last_bonus_date']]);
        $totalMins = 0;
        while ($row = $hoursStmt->fetch()) {
            $totalMins += (int)$row['mins'];
        }
        $totalHours = $totalMins / 60;

        if ($bonusAvg == 5.0) {
            $bonusPremium = 10.00;
        } else {
            $bonusPremium = (($bonusAvg - 4.5) / 0.4) * 2.50 + 5.00;
        }
        $bonusAmount = $totalHours * $bonusPremium;
    } else {
        if ($bonusAvg == 5.0) $bonusAmount = 1500;
        elseif ($bonusAvg == 4.9) $bonusAmount = 900;
        elseif ($bonusAvg == 4.8) $bonusAmount = 800;
        elseif ($bonusAvg == 4.7) $bonusAmount = 700;
        elseif ($bonusAvg == 4.6) $bonusAmount = 600;
        elseif ($bonusAvg == 4.5) $bonusAmount = 500;
    }
}

// 2. Raise Logic (6-Month Cycle)
$raiseStmt = $pdo->prepare('SELECT AVG(total_score) as avg_score, COUNT(id) as review_count FROM performance_reviews WHERE employee_id = ? AND evaluation_type = "Official" AND (? IS NULL OR review_date > ?)');
$raiseStmt->execute([$employee_id, $employee['last_raise_date'], $employee['last_raise_date']]);
$raiseStats = $raiseStmt->fetch();
$raiseAvg = round((float)$raiseStats['avg_score'], 1);
$raiseReviews = (int)$raiseStats['review_count'];

$raiseAmount = 0;
$newSalary = (float)$employee['hourly_rate'];
$raisePct = 0;

if ($raiseAvg >= 4.5 && $raiseReviews >= 24) {
    if ($isPartTime) {
        if ($raiseAvg == 5.0) {
            $raiseAmount = 20.00;
        } else {
            $raiseAmount = (($raiseAvg - 4.5) / 0.4) * 5.00 + 5.00;
        }
        $newSalary += $raiseAmount;
    } else {
        if ($raiseAvg == 5.0) $raisePct = 0.10;
        elseif ($raiseAvg == 4.9) $raisePct = 0.07;
        elseif ($raiseAvg == 4.8) $raisePct = 0.06;
        elseif ($raiseAvg == 4.7) $raisePct = 0.05;
        elseif ($raiseAvg == 4.6) $raisePct = 0.04;
        elseif ($raiseAvg == 4.5) $raisePct = 0.03;
        
        $raiseAmount = $employee['hourly_rate'] * $raisePct;
        $newSalary += $raiseAmount;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user = current_user();
    
    if ($action === 'give_bonus' && $bonusAmount > 0) {
        // Log into pending_bonuses and update last_bonus_date
        $pdo->prepare('INSERT INTO pending_bonuses (employee_id, amount) VALUES (?, ?)')->execute([$employee_id, $bonusAmount]);
        $pdo->prepare('UPDATE employees SET last_bonus_date = CURDATE() WHERE id = ?')->execute([$employee_id]);
        log_activity($pdo, $user['id'], 'give_bonus', "Queued ₱" . number_format($bonusAmount, 2) . " bonus for " . $employee['full_name']);
        
        notify_roles($pdo, ['Admin', 'Super Admin', 'HR', 'HR Admin'], 
            $employee['full_name'] . " just received a performance bonus of ₱" . number_format($bonusAmount, 2) . "!", 
            "employee_view.php?id=" . $employee_id, 
            "fa-gift", "emerald");
        
        echo "<script>window.parent.closeEvaluateModal();</script>";
        exit;
    }
    
    if ($action === 'give_raise' && $raiseAmount > 0) {
        // Update salary and last_raise_date
        $pdo->prepare('UPDATE employees SET hourly_rate = ?, last_raise_date = CURDATE() WHERE id = ?')->execute([$newSalary, $employee_id]);
        log_activity($pdo, $user['id'], 'give_raise', "Granted raise to " . $employee['full_name'] . " (New Rate: ₱" . number_format($newSalary, 2) . ")");
        
        echo "<script>window.parent.closeEvaluateModal();</script>";
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Compensation Options</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #ffffff; }
    </style>
</head>
<body class="p-6 bg-slate-50">

<div class="max-w-md mx-auto">
    <div class="text-center mb-6">
        <div class="h-16 w-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-3 shadow-inner border-2 border-white">
            <i class="fa-solid fa-hand-holding-dollar text-2xl"></i>
        </div>
        <h2 class="text-2xl font-bold text-slate-800">Compensation Review</h2>
        <p class="text-slate-500 mt-1">For <?= h($employee['full_name']) ?> (<?= h($employee['employment_category']) ?>)</p>
    </div>

    <!-- Short Term Bonus Section -->
    <div class="bg-white border border-slate-200 rounded-xl p-5 mb-5 shadow-sm relative overflow-hidden">
        <div class="absolute top-0 left-0 w-1 h-full bg-indigo-500"></div>
        <div class="flex justify-between items-center mb-3">
            <h3 class="font-bold text-slate-800">4-Week Bonus</h3>
            <span class="text-xs font-semibold px-2 py-1 bg-indigo-50 text-indigo-700 rounded-full"><i class="fa-solid fa-star text-[10px] mr-1"></i><?= number_format($bonusAvg, 1) ?> Avg</span>
        </div>
        
        <div class="text-sm text-slate-600 mb-4">
            <div class="flex justify-between mb-1">
                <span>Reviews Since Last Bonus:</span>
                <span class="font-medium <?= $bonusReviews >= 4 ? 'text-green-600' : 'text-slate-700' ?>"><?= $bonusReviews ?> / 4</span>
            </div>
            
            <?php if ($isPartTime): ?>
                <div class="flex justify-between mb-1">
                    <span>Total Hours Worked:</span>
                    <span class="font-medium text-slate-700"><?= number_format($totalHours, 1) ?> Hrs</span>
                </div>
                <div class="flex justify-between mb-1">
                    <span>Performance Premium:</span>
                    <span class="font-medium text-indigo-600">+₱<?= number_format($bonusPremium, 2) ?>/hr</span>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($bonusAmount > 0): ?>
            <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg border border-green-100 mb-4">
                <span class="text-green-800 font-bold">Total Bonus:</span>
                <span class="text-xl font-black text-green-700">₱<?= number_format($bonusAmount, 2) ?></span>
            </div>
            <form autocomplete="off" method="POST">
                <input type="hidden" name="action" value="give_bonus">
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-4 rounded-lg transition-colors shadow-sm flex items-center justify-center text-sm">
                    <i class="fa-solid fa-gift mr-2"></i> Queue ₱<?= number_format($bonusAmount, 2) ?> Bonus for Payroll
                </button>
            </form>
        <?php else: ?>
            <div class="bg-slate-100 text-slate-500 p-3 rounded-lg border border-slate-200 text-xs text-center font-medium">
                <i class="fa-solid fa-lock mr-1"></i> Not eligible. Requires 4 reviews and 4.5+ average.
            </div>
        <?php endif; ?>
    </div>

    <!-- Long Term Raise Section -->
    <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
        <div class="absolute top-0 left-0 w-1 h-full bg-amber-500"></div>
        <div class="flex justify-between items-center mb-3">
            <h3 class="font-bold text-slate-800">6-Month Permanent Raise</h3>
            <span class="text-xs font-semibold px-2 py-1 bg-amber-50 text-amber-700 rounded-full"><i class="fa-solid fa-star text-[10px] mr-1"></i><?= number_format($raiseAvg, 1) ?> Avg</span>
        </div>

        <div class="text-sm text-slate-600 mb-4">
            <div class="flex justify-between mb-1">
                <span>Reviews Since Last Raise:</span>
                <span class="font-medium <?= $raiseReviews >= 24 ? 'text-green-600' : 'text-slate-700' ?>"><?= $raiseReviews ?> / 24</span>
            </div>
            <div class="flex justify-between mb-1">
                <span>Current Hourly Rate:</span>
                <span class="font-medium text-slate-700">₱<?= number_format($employee['hourly_rate'], 2) ?> <span class="text-xs text-slate-500 font-normal ml-1">(Est. ₱<?= number_format($employee['hourly_rate'] * 8 * 22, 2) ?>/mo)</span></span>
            </div>
            <?php if ($raiseAmount > 0): ?>
                <div class="flex justify-between mb-1">
                    <span>Raise Earned:</span>
                    <span class="font-medium text-amber-600">
                        +₱<?= number_format($raiseAmount, 2) ?> <?= !$isPartTime ? '('.($raisePct*100).'%)' : '' ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($raiseAmount > 0): ?>
            <div class="flex items-center justify-between p-3 bg-amber-50 rounded-lg border border-amber-100 mb-4">
                <span class="text-amber-800 font-bold">New Rate:</span>
                <div class="text-right">
                    <div class="text-xl font-black text-amber-700">₱<?= number_format($newSalary, 2) ?>/hr</div>
                    <div class="text-xs font-medium text-amber-600/80 mt-0.5">Est. ₱<?= number_format($newSalary * 8 * 22, 2) ?>/mo</div>
                </div>
            </div>
            <form autocomplete="off" method="POST">
                <input type="hidden" name="action" value="give_raise">
                <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-2.5 px-4 rounded-lg transition-colors shadow-sm flex items-center justify-center text-sm">
                    <i class="fa-solid fa-arrow-trend-up mr-2"></i> Confirm Raise to ₱<?= number_format($newSalary, 2) ?>
                </button>
            </form>
        <?php else: ?>
            <div class="bg-slate-100 text-slate-500 p-3 rounded-lg border border-slate-200 text-xs text-center font-medium">
                <i class="fa-solid fa-lock mr-1"></i> Not eligible. Requires 24 reviews and 4.5+ average.
            </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>

