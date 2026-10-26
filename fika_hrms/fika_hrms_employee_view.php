<?php
require_once __DIR__ . '/../app/bootstrap.php';

Auth::requireLogin();
Rbac::require_permission('hr.employee.view');

global $pdo;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$code = trim($_GET['code'] ?? '');

$employee = null;
if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT e.*, b.name as branch_name, r.name as role_name, u.username
        FROM employees e
        LEFT JOIN branches b ON e.branch_id = b.id
        LEFT JOIN users u ON u.employee_id = e.id
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE e.id = ? AND e.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $employee = $stmt->fetch();
} elseif ($code !== '') {
    $stmt = $pdo->prepare("
        SELECT e.*, b.name as branch_name, r.name as role_name, u.username
        FROM employees e
        LEFT JOIN branches b ON e.branch_id = b.id
        LEFT JOIN users u ON u.employee_id = e.id
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE e.employee_code = ? AND e.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$code]);
    $employee = $stmt->fetch();
}

if (!$employee) {
    // If no specific employee ID, grab first employee for demo or 404
    $stmt = $pdo->query("
        SELECT e.*, b.name as branch_name, r.name as role_name, u.username
        FROM employees e
        LEFT JOIN branches b ON e.branch_id = b.id
        LEFT JOIN users u ON u.employee_id = e.id
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE e.deleted_at IS NULL
        ORDER BY e.id ASC
        LIMIT 1
    ");
    $employee = $stmt->fetch();
}

if (!$employee) {
    die("No employee profile found.");
}

$isArchived = in_array(strtoupper((string)$employee['status']), ['ARCHIVED', 'SEPARATED', 'INACTIVE'], true)
              && (!empty($employee['termination_date']) || !empty($employee['termination_reason']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($employee['first_name'] . ' ' . $employee['last_name']) ?> &middot; Employee Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">

    <!-- Top Navigation -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="fika_hrms_employees.php" class="text-sm font-semibold text-slate-600 hover:text-amber-600 transition flex items-center gap-2">
                    <i class="fa-solid fa-arrow-left text-xs"></i> Back to Directory
                </a>
            </div>
            <div class="flex items-center gap-2">
                <a href="fika_hrms_dashboard.php" class="text-xs font-medium text-slate-500 hover:text-slate-800 transition">HRMS Dashboard</a>
            </div>
        </div>
    </header>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Archived Banner -->
        <?php if ($isArchived): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl p-4 flex items-start space-x-3 mb-6 shadow-sm">
                <i class="fa-solid fa-triangle-exclamation text-xl text-red-500 mt-0.5"></i>
                <div>
                    <h3 class="font-bold text-red-900">Archived Record</h3>
                    <p class="text-sm mt-1">This employee was archived on <strong><?= $employee['termination_date'] ? date('F j, Y', strtotime($employee['termination_date'])) : 'Unknown' ?></strong>. Reason: <strong><?= e($employee['termination_reason'] ?? 'Unspecified') ?></strong></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Profile Header Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 mb-6 relative overflow-hidden">
            <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
                <div class="w-24 h-24 rounded-2xl bg-amber-600 text-white flex items-center justify-center text-3xl font-black shadow-md flex-shrink-0">
                    <?= strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)) ?>
                </div>
                <div class="flex-1 text-center md:text-left">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-2">
                        <div>
                            <h1 class="text-2xl font-black text-slate-900"><?= e($employee['first_name'] . ' ' . $employee['last_name']) ?></h1>
                            <p class="text-sm text-slate-500 font-medium"><?= e($employee['position'] ?? 'Staff') ?> &bull; <?= e($employee['department'] ?? 'Operations') ?></p>
                        </div>
                        <div>
                            <?php if (strtoupper((string)$employee['status']) === 'ACTIVE'): ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <i class="fa-solid fa-circle text-[6px] mr-1.5 animate-pulse"></i> Active
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                    <i class="fa-solid fa-circle text-[6px] mr-1.5"></i> <?= e($employee['status']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap gap-4 text-xs font-medium text-slate-600">
                        <div class="flex items-center gap-1.5">
                            <i class="fa-solid fa-id-badge text-amber-600"></i>
                            <span class="font-mono font-bold text-slate-800"><?= e($employee['employee_code']) ?></span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <i class="fa-solid fa-location-dot text-amber-600"></i>
                            <span><?= e($employee['branch_name'] ?? 'Unassigned') ?></span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <i class="fa-solid fa-briefcase text-amber-600"></i>
                            <span><?= e($employee['employment_type'] ?? 'Regular') ?></span>
                        </div>
                        <?php if (!empty($employee['username'])): ?>
                        <div class="flex items-center gap-1.5">
                            <i class="fa-solid fa-user-shield text-amber-600"></i>
                            <span>User: <strong class="text-slate-800"><?= e($employee['username']) ?></strong> (<?= e($employee['role_name'] ?? 'Staff') ?>)</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Details Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Contact Info -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-base font-bold text-slate-900 mb-4 pb-3 border-b border-slate-100 flex items-center gap-2">
                    <i class="fa-solid fa-address-book text-amber-600"></i> Contact Information
                </h2>
                <div class="space-y-3 text-sm">
                    <div>
                        <span class="text-xs text-slate-400 uppercase font-bold block">Email Address</span>
                        <span class="font-medium text-slate-800"><?= e($employee['email'] ?: 'None provided') ?></span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 uppercase font-bold block">Phone Number</span>
                        <span class="font-medium text-slate-800"><?= e($employee['phone'] ?: 'None provided') ?></span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 uppercase font-bold block">Residential Address</span>
                        <span class="font-medium text-slate-800"><?= e($employee['address'] ?: 'None provided') ?></span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 uppercase font-bold block">Emergency Contact</span>
                        <span class="font-medium text-slate-800"><?= e($employee['emergency_contact'] ?: 'None provided') ?></span>
                    </div>
                </div>
            </div>

            <!-- Employment Details -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-base font-bold text-slate-900 mb-4 pb-3 border-b border-slate-100 flex items-center gap-2">
                    <i class="fa-solid fa-briefcase text-amber-600"></i> Employment Details
                </h2>
                <div class="space-y-3 text-sm">
                    <div>
                        <span class="text-xs text-slate-400 uppercase font-bold block">Date Hired</span>
                        <span class="font-medium text-slate-800"><?= !empty($employee['date_hired']) ? date('F j, Y', strtotime($employee['date_hired'])) : 'Not recorded' ?></span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 uppercase font-bold block">Attendance Mode</span>
                        <span class="font-medium text-slate-800"><?= e($employee['attendance_mode'] ?? 'PIN & Photo') ?></span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 uppercase font-bold block">Staff Classification</span>
                        <span class="font-medium text-slate-800"><?= e($employee['staff_class'] ?? 'Operations') ?></span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 uppercase font-bold block">Sick Leave Balance</span>
                        <span class="font-medium text-slate-800"><?= (int)($employee['sick_leave_balance'] ?? 0) ?> days</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

</body>
</html>
