<?php
require_once __DIR__ . '/init.php';
require_login();
require_role(['Admin', 'Super Admin', 'HR', 'HR Admin']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) die("Employee ID required.");

$stmt = $pdo->prepare('SELECT *, CONCAT(first_name, " ",   last_name) AS full_name FROM employees WHERE id = ?');
$stmt->execute([$id]);
$employee = $stmt->fetch();
if (!$employee) die("Employee not found.");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $position = $_POST['position'] ?? $employee['position'];
    $category = $_POST['employment_category'] ?? $employee['employment_category'];
    $salary = (float)$_POST['hourly_rate'];
    
    if ($salary <= 0) die("Valid salary is required.");
    
    // Status always becomes active when rehired.
    $update = $pdo->prepare('UPDATE employees SET status = "Active", position = ?, employment_category = ?, hourly_rate = ?, termination_reason = NULL, termination_details = NULL, termination_date = NULL WHERE id = ?');
    $update->execute([$position, $category, $salary, $id]);
    
    log_activity($pdo, current_user()['id'], 'rehire_employee', "Reactivated employee " . $employee['full_name'] . " as " . $position);
    
    echo "<script>window.parent.closeEditModal();</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reactivate Employee</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #ffffff; }
    </style>
</head>
<body class="p-6">
<div class="max-w-md mx-auto">
    <div class="text-center mb-6">
        <div class="h-16 w-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-3">
            <i class="fa-solid fa-user-plus text-2xl"></i>
        </div>
        <h2 class="text-2xl font-bold text-slate-800">Rehire / Reactivate</h2>
        <p class="text-slate-500 mt-1">Bring <?= h($employee['full_name']) ?> back to Active status</p>
    </div>

    <form autocomplete="off" method="POST" class="space-y-4 bg-slate-50 border border-slate-200 p-5 rounded-xl">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">New Position</label>
            <input type="text" name="position" value="<?= h($employee['position']) ?>" required class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
        </div>
        
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Employment Category</label>
            <select name="employment_category" required class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="Full-Time" <?= ($employee['employment_category'] ?? '') === 'Full-Time' ? 'selected' : '' ?>>Full-Time</option>
                <option value="Part-Time" <?= ($employee['employment_category'] ?? '') === 'Part-Time' ? 'selected' : '' ?>>Part-Time</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">New Hourly Rate (₱)</label>
            <input type="text" id="rehireHourlyRate" name="hourly_rate" value="<?= h($employee['hourly_rate']) ?>" oninput="limitSalaryInput(this); updateRehireProjection()" required class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
            <p id="rehireProjectionText" class="text-xs text-slate-500 mt-2 ml-1 italic"></p>
        </div>
        
        <div class="pt-4">
            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-4 rounded-lg transition-colors flex items-center justify-center">
                <i class="fa-solid fa-check mr-2"></i> Confirm Reactivation
            </button>
        </div>
    </form>
</div>
</div>
<script>
function limitSalaryInput(input) {
    let val = input.value.replace(/[^0-9.]/g, '');
    let parts = val.split('.');
    if (parts.length > 2) {
        parts = [parts[0], parts.slice(1).join('')];
    }
    if (parts[0].length > 3) {
        parts[0] = parts[0].substring(0, 3);
    }
    if (parts.length > 1 && parts[1].length > 2) {
        parts[1] = parts[1].substring(0, 2);
    }
    input.value = parts.join('.');
}

function updateRehireProjection() {
    let input = document.getElementById('rehireHourlyRate');
    let val = parseFloat(input.value.replace(/,/g, ''));
    let textObj = document.getElementById('rehireProjectionText');
    if (!isNaN(val) && val > 0) {
        let monthly = val * 8 * 22;
        textObj.innerHTML = 'Estimated Monthly Income: <strong class="text-emerald-600">₱' + monthly.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</strong>';
    } else {
        textObj.innerText = '';
    }
}
updateRehireProjection();
</script>
</body>
</html>

