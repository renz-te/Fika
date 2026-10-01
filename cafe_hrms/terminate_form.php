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
    $reason = $_POST['termination_reason'] ?? '';
    $details = $_POST['termination_details'] ?? '';
    
    if (empty($reason) || empty($details)) {
        die("Reason and details are required.");
    }
    
    $update = $pdo->prepare('UPDATE employees SET status = "Archived", termination_reason = ?, termination_details = ?, termination_date = CURDATE() WHERE id = ?');
    $update->execute([$reason, $details, $id]);
    
    log_activity($pdo, current_user()['id'], 'terminate_employee', "Archived employee " . $employee['full_name'] . " due to " . $reason);
    
    echo "<script>window.parent.closeEditModal();</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Terminate Employee</title>
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
        <div class="h-16 w-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-3">
            <i class="fa-solid fa-user-slash text-2xl"></i>
        </div>
        <h2 class="text-2xl font-bold text-slate-800">Archive / Terminate</h2>
        <p class="text-slate-500 mt-1">Move <?= h($employee['full_name']) ?> to Archive</p>
    </div>

    <form autocomplete="off" method="POST" class="space-y-4 bg-slate-50 border border-slate-200 p-5 rounded-xl">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Primary Reason *</label>
            <select name="termination_reason" required class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                <option value="">Select a reason...</option>
                <option value="Resignation">Resignation</option>
                <option value="Expiry of Contract">Expiry of Contract</option>
                <option value="Breach of Contract">Breach of Contract</option>
                <option value="Deployed to other store">Deployed to other store</option>
                <option value="Other">Other</option>
            </select>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Additional Details *</label>
            <textarea name="termination_details" rows="3" required class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500" placeholder="Provide specific reasoning for archiving this record..."></textarea>
        </div>
        
        <div class="pt-4">
            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-4 rounded-lg transition-colors flex items-center justify-center">
                <i class="fa-solid fa-box-archive mr-2"></i> Confirm Archive
            </button>
        </div>
    </form>
</div>
</body>
</html>

