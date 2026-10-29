<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/core/Output.php';

Auth::requireLogin();

global $pdo;

$currentUser = Auth::user();
$currentRoleId = (int)($currentUser['role_id'] ?? 0);

// Only Branch Manager (Role 6) and Admin (Role 1) can manage branch settings
if (!in_array($currentRoleId, [1, 6], true)) {
    http_response_code(403);
    echo "Access denied: Branch Manager or Administrator permissions required.";
    exit;
}

// Fetch the BM's branch_id from $_SESSION (default fallback to 1)
$branchId = (int)($_SESSION['user']['branch_id'] ?? $currentUser['branch_id'] ?? 1);
if ($branchId <= 0) {
    $branchId = 1;
}

// Fetch branch details
$bStmt = $pdo->prepare("SELECT id, name, address, contact_number FROM branches WHERE id = ?");
$bStmt->execute([$branchId]);
$branch = $bStmt->fetch(PDO::FETCH_ASSOC);
$branchName = $branch['name'] ?? "Branch #{$branchId}";

$successMessage = '';
$errorMessage = '';

// Handle POST save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isJson = (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false);
    $inputData = [];
    
    if ($isJson) {
        $raw = file_get_contents('php://input');
        $inputData = json_decode($raw, true) ?: [];
    } else {
        $inputData = $_POST;
    }
    
    $token = $inputData['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    
    if (!Csrf::validate($token)) {
        $errorMessage = 'Invalid or expired CSRF security token. Please refresh and try again.';
        if ($isJson) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $errorMessage]);
            exit;
        }
    } else {
        // Extract & sanitize inputs
        $receiptBusinessName = trim((string)($inputData['receipt_business_name'] ?? ''));
        $receiptTin = trim((string)($inputData['receipt_tin'] ?? ''));
        $receiptAddress = trim((string)($inputData['receipt_address'] ?? ''));
        $receiptFooter = trim((string)($inputData['receipt_footer'] ?? ''));
        $receiptBirRegistered = !empty($inputData['receipt_bir_registered']) ? '1' : '0';
        
        $lateGraceMinutes = (int)($inputData['late_grace_minutes'] ?? 15);
        if ($lateGraceMinutes < 0) $lateGraceMinutes = 0;
        if ($lateGraceMinutes > 180) $lateGraceMinutes = 180;
        
        $overtimeGraceMinutes = (int)($inputData['overtime_grace_minutes'] ?? 15);
        if ($overtimeGraceMinutes < 0) $overtimeGraceMinutes = 0;
        if ($overtimeGraceMinutes > 180) $overtimeGraceMinutes = 180;
        
        $kioskOrderAgeLimit = (int)($inputData['kiosk_order_age_limit_minutes'] ?? 60);
        if ($kioskOrderAgeLimit < 1) $kioskOrderAgeLimit = 60;
        if ($kioskOrderAgeLimit > 1440) $kioskOrderAgeLimit = 1440;
        
        $branchOpeningTime = trim((string)($inputData['branch_opening_time'] ?? '07:00'));
        if (!preg_match('/^\d{1,2}:\d{2}$/', $branchOpeningTime)) {
            $branchOpeningTime = '07:00';
        }
        
        $branchClosingTime = trim((string)($inputData['branch_closing_time'] ?? '22:00'));
        if (!preg_match('/^\d{1,2}:\d{2}$/', $branchClosingTime)) {
            $branchClosingTime = '22:00';
        }
        
        $inventoryAllowNegative = !empty($inputData['inventory_allow_negative']) ? '1' : '0';
        
        if ($receiptBusinessName === '' || $receiptTin === '' || $receiptAddress === '' || $receiptFooter === '') {
            $errorMessage = 'Receipt Business Name, TIN, Branch Address, and Footer Message cannot be empty.';
            if ($isJson) {
                http_response_code(422);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $errorMessage]);
                exit;
            }
        } else {
            $settingsToSave = [
                'receipt_business_name' => $receiptBusinessName,
                'receipt_tin' => $receiptTin,
                'receipt_address' => $receiptAddress,
                'receipt_footer' => $receiptFooter,
                'receipt_bir_registered' => $receiptBirRegistered,
                'bir_registered' => $receiptBirRegistered,
                'late_grace_minutes' => (string)$lateGraceMinutes,
                'overtime_grace_minutes' => (string)$overtimeGraceMinutes,
                'kiosk_order_age_limit_minutes' => (string)$kioskOrderAgeLimit,
                'branch_opening_time' => $branchOpeningTime,
                'branch_closing_time' => $branchClosingTime,
                'inventory_allow_negative' => $inventoryAllowNegative,
            ];
            
            $userId = (int)($currentUser['id'] ?? 0);
            if ($userId > 0) {
                $uChk = $pdo->prepare("SELECT id FROM users WHERE id = ?");
                $uChk->execute([$userId]);
                if (!$uChk->fetchColumn()) {
                    $userId = null;
                }
            } else {
                $userId = null;
            }
            
            $pdo->beginTransaction();
            try {
                $upsertStmt = $pdo->prepare("
                    INSERT INTO settings (setting_key, branch_id, setting_value, updated_by, updated_at)
                    VALUES (?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                        setting_value = VALUES(setting_value),
                        updated_by = VALUES(updated_by),
                        updated_at = NOW()
                ");
                
                foreach ($settingsToSave as $k => $v) {
                    $upsertStmt->execute([$k, $branchId, $v, $userId]);
                }
                
                $pdo->commit();
                $successMessage = "Branch settings for {$branchName} saved successfully!";
                
                if ($isJson) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => $successMessage]);
                    exit;
                }
            } catch (\Throwable $e) {
                $pdo->rollBack();
                $errorMessage = "Failed to save branch settings: " . $e->getMessage();
                if ($isJson) {
                    http_response_code(500);
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $errorMessage]);
                    exit;
                }
            }
        }
    }
}

// 4. Database Logic: Fetch current values with global fallback
// Baseline defaults
$settings = [
    'receipt_business_name' => 'Fika Coffee & Pastries',
    'receipt_tin' => 'TIN: 123-456-789-00000 VAT',
    'receipt_address' => $branch['address'] ?? '123 Coffee St., BGC, Taguig City',
    'receipt_footer' => 'Thank you for your visit!',
    'receipt_bir_registered' => '1',
    'bir_registered' => '1',
    'late_grace_minutes' => '15',
    'overtime_grace_minutes' => '15',
    'kiosk_order_age_limit_minutes' => '60',
    'branch_opening_time' => '07:00',
    'branch_closing_time' => '22:00',
    'inventory_allow_negative' => '0',
];

// 1. Fetch Global Settings as baseline (branch_id IS NULL)
try {
    $stmtGlobal = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE branch_id IS NULL");
    if ($stmtGlobal) {
        while ($row = $stmtGlobal->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (\Throwable $e) {}

// 2. Override with branch-specific settings if present (branch_id = ?)
$branchOverrides = [];
try {
    $stmtBranch = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE branch_id = ?");
    $stmtBranch->execute([$branchId]);
    while ($row = $stmtBranch->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
        $branchOverrides[$row['setting_key']] = true;
    }
} catch (\Throwable $e) {}

$csrfToken = Csrf::getToken();
$pageTitle = "Branch Settings ({$branchName})";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> &middot; Fika System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans antialiased min-h-screen flex">

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">
        <!-- Top Nav Header -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-600 text-white flex items-center justify-center font-black text-lg shadow-xs">
                        <i class="fa-solid fa-sliders text-base"></i>
                    </div>
                    <div>
                        <h1 class="font-bold text-lg text-slate-900 leading-tight">Branch Settings</h1>
                        <p class="text-xs text-slate-500">Configure parameters scoped strictly to <span class="font-bold text-amber-700"><?= e($branchName) ?></span></p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200/60">
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                        Branch #<?= (int)$branchId ?>
                    </span>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 flex-1 w-full space-y-6">

            <!-- Branch Info Banner -->
            <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 rounded-3xl p-6 text-white shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 rounded-2xl bg-amber-500/20 border border-amber-400/30 flex items-center justify-center text-amber-300 font-black text-2xl shadow-inner">
                        <i class="fa-solid fa-store"></i>
                    </div>
                    <div>
                        <div class="flex items-center space-x-2">
                            <h2 class="text-xl md:text-2xl font-black tracking-tight text-white"><?= e($branchName) ?></h2>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-amber-500/30 text-amber-200 border border-amber-400/30 tracking-wider">Branch Scope</span>
                        </div>
                        <p class="text-xs text-slate-300 mt-1">Changes saved here will apply to this branch and override Global Admin defaults.</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="px-4 py-2 rounded-2xl bg-white/10 backdrop-blur-md border border-white/10 text-right">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-200 block">Active Status</span>
                        <span class="text-sm font-extrabold text-white flex items-center justify-end space-x-1.5">
                            <i class="fa-solid fa-circle-check text-emerald-400 text-xs"></i>
                            <span>Isolated Branch #<?= (int)$branchId ?></span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Feedback Alerts -->
            <?php if (!empty($successMessage)): ?>
            <div id="alertBox" class="p-4 rounded-2xl text-sm font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i>
                    <span><?= e($successMessage) ?></span>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-emerald-700 hover:text-emerald-900">&times;</button>
            </div>
            <?php elseif (!empty($errorMessage)): ?>
            <div id="alertBox" class="p-4 rounded-2xl text-sm font-bold bg-rose-50 text-rose-800 border border-rose-200 flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <i class="fa-solid fa-circle-exclamation text-rose-600 text-lg"></i>
                    <span><?= e($errorMessage) ?></span>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-rose-700 hover:text-rose-900">&times;</button>
            </div>
            <?php else: ?>
            <div id="alertBox" class="hidden p-4 rounded-2xl text-sm font-bold border transition"></div>
            <?php endif; ?>

            <form method="POST" id="branchSettingsForm" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <!-- SECTION 1: Thermal Receipt Header & BIR Compliance -->
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-6">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-lg border border-amber-100">
                                <i class="fa-solid fa-receipt"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-900">Thermal Receipt Header &amp; BIR Compliance</h3>
                                <p class="text-xs text-slate-500">Configure customer 58mm thermal receipts issued by this branch's POS and Kiosks.</p>
                            </div>
                        </div>
                        <?php if (!empty($branchOverrides['receipt_business_name']) || !empty($branchOverrides['receipt_tin'])): ?>
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Customized</span>
                        <?php else: ?>
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 text-slate-500">Inherited</span>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                Business Name <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="receipt_business_name" value="<?= e($settings['receipt_business_name'] ?? 'Fika Coffee & Pastries') ?>" required
                                   class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <p class="text-[11px] text-slate-400">Printed as the primary store title on thermal receipts.</p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                Tax Identification Number (TIN) <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="receipt_tin" value="<?= e($settings['receipt_tin'] ?? 'TIN: 123-456-789-00000 VAT') ?>" required
                                   class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <p class="text-[11px] text-slate-400">Must match branch BIR registration certificate.</p>
                        </div>

                        <div class="space-y-1.5 md:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                Branch Address <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="receipt_address" value="<?= e($settings['receipt_address'] ?? '123 Coffee St., BGC, Taguig City') ?>" required
                                   class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <p class="text-[11px] text-slate-400">Physical address printed on receipt header.</p>
                        </div>

                        <div class="space-y-1.5 md:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                Receipt Footer Message <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="receipt_footer" value="<?= e($settings['receipt_footer'] ?? 'Thank you for your visit!') ?>" required
                                   class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <p class="text-[11px] text-slate-400">Closing greeting printed at the bottom of customer receipts.</p>
                        </div>
                    </div>

                    <!-- BIR Checkbox -->
                    <div class="pt-4 border-t border-slate-100 flex items-start space-x-3">
                        <div class="flex items-center h-5 mt-0.5">
                            <input type="checkbox" id="receipt_bir_registered" name="receipt_bir_registered" value="1"
                                   <?= (!empty($settings['receipt_bir_registered']) || !empty($settings['bir_registered'])) ? 'checked' : '' ?>
                                   class="w-5 h-5 text-amber-600 bg-slate-50 border-slate-300 rounded focus:ring-amber-500 cursor-pointer">
                        </div>
                        <label for="receipt_bir_registered" class="cursor-pointer">
                            <span class="block text-sm font-bold text-slate-900">BIR-Registered POS System Mode</span>
                            <span class="block text-xs text-slate-500 mt-0.5">
                                Enable strict Bureau of Internal Revenue (BIR) layout formatting with VAT breakdown, Serial Number (SN), and Machine Identification Number (MIN).
                            </span>
                        </label>
                    </div>
                </div>

                <!-- SECTION 2: Attendance & Timekeeping -->
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-6">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-lg border border-indigo-100">
                                <i class="fa-solid fa-business-time"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-900">Attendance &amp; Timekeeping</h3>
                                <p class="text-xs text-slate-500">Configure time clock grace limits for branch staff and baristas.</p>
                            </div>
                        </div>
                        <?php if (!empty($branchOverrides['late_grace_minutes']) || !empty($branchOverrides['overtime_grace_minutes'])): ?>
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">Customized</span>
                        <?php else: ?>
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 text-slate-500">Inherited</span>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                Late Grace Period (Minutes) <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="number" name="late_grace_minutes" min="0" max="180" value="<?= (int)($settings['late_grace_minutes'] ?? 15) ?>" required
                                       class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                <span class="absolute right-4 top-2.5 text-xs text-slate-400 font-bold">mins</span>
                            </div>
                            <p class="text-[11px] text-slate-400">Minutes past shift start before an employee clock-in is flagged as tardy.</p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                Overtime Grace Period (Minutes) <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="number" name="overtime_grace_minutes" min="0" max="180" value="<?= (int)($settings['overtime_grace_minutes'] ?? 15) ?>" required
                                       class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                <span class="absolute right-4 top-2.5 text-xs text-slate-400 font-bold">mins</span>
                            </div>
                            <p class="text-[11px] text-slate-400">Buffer time past shift end before overtime calculation initiates.</p>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: Branch Operations -->
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-6">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-lg border border-emerald-100">
                                <i class="fa-solid fa-store"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-900">Branch Operations &amp; Inventory</h3>
                                <p class="text-xs text-slate-500">Operating hours, kiosk order aging thresholds, and stock policies.</p>
                            </div>
                        </div>
                        <?php if (!empty($branchOverrides['kiosk_order_age_limit_minutes']) || !empty($branchOverrides['branch_opening_time']) || !empty($branchOverrides['inventory_allow_negative'])): ?>
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Customized</span>
                        <?php else: ?>
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 text-slate-500">Inherited</span>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                Kiosk Order Age Limit <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="number" name="kiosk_order_age_limit_minutes" min="5" max="1440" value="<?= (int)($settings['kiosk_order_age_limit_minutes'] ?? 60) ?>" required
                                       class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                <span class="absolute right-4 top-2.5 text-xs text-slate-400 font-bold">mins</span>
                            </div>
                            <p class="text-[11px] text-slate-400">Unpaid kiosk orders exceeding this age are filtered to aged queue.</p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                Branch Opening Time <span class="text-rose-500">*</span>
                            </label>
                            <input type="time" name="branch_opening_time" value="<?= e($settings['branch_opening_time'] ?? '07:00') ?>" required
                                   class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <p class="text-[11px] text-slate-400">Standard store opening time for shift starts.</p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                Branch Closing Time <span class="text-rose-500">*</span>
                            </label>
                            <input type="time" name="branch_closing_time" value="<?= e($settings['branch_closing_time'] ?? '22:00') ?>" required
                                   class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <p class="text-[11px] text-slate-400">Standard store closing and reconciliation time.</p>
                        </div>
                    </div>

                    <!-- Allow Negative Inventory Toggle -->
                    <div class="pt-4 border-t border-slate-100 flex items-start space-x-3">
                        <div class="flex items-center h-5 mt-0.5">
                            <input type="checkbox" id="inventory_allow_negative" name="inventory_allow_negative" value="1"
                                   <?= (!empty($settings['inventory_allow_negative']) && (string)$settings['inventory_allow_negative'] === '1') ? 'checked' : '' ?>
                                   class="w-5 h-5 text-emerald-600 bg-slate-50 border-slate-300 rounded focus:ring-emerald-500 cursor-pointer">
                        </div>
                        <label for="inventory_allow_negative" class="cursor-pointer">
                            <span class="block text-sm font-bold text-slate-900">Allow Negative Inventory Stock</span>
                            <span class="block text-xs text-slate-500 mt-0.5">
                                When enabled, POS register sales will continue to process orders even if ingredient stock levels fall below 0.00.
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Submit Bar -->
                <div class="flex items-center justify-between pt-2">
                    <a href="fika_pos/fika_pos_dashboard.php" class="inline-flex items-center space-x-2 text-xs font-bold text-slate-500 hover:text-slate-800 transition">
                        <i class="fa-solid fa-arrow-left"></i>
                        <span>Back to Dashboard</span>
                    </a>
                    <button type="submit" id="btnSubmitBranchSettings"
                            class="px-8 py-3.5 bg-amber-600 hover:bg-amber-700 text-white font-bold text-sm rounded-2xl shadow-lg shadow-amber-600/30 transition active:scale-95 flex items-center space-x-2 cursor-pointer">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Save Branch Settings</span>
                    </button>
                </div>
            </form>
        </main>
    </div>

    <script>
        document.getElementById('branchSettingsForm')?.addEventListener('submit', function() {
            const btn = document.getElementById('btnSubmitBranchSettings');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving Settings...';
            }
        });
    </script>
</body>
</html>
