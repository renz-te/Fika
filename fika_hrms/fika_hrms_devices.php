<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/core/Output.php';
require_once __DIR__ . '/../app/core/Audit.php';

Auth::requireLogin();
Rbac::require_permission('devices.view');

global $pdo;

$currentUser = Auth::user();
$currentRoleId = (int)($currentUser['role_id'] ?? 0);
$bmBranchId = (int)($currentUser['branch_id'] ?? 0);

$userRoleName = '';
if ($currentRoleId > 0 && $pdo) {
    $rStmt = $pdo->prepare("SELECT name FROM roles WHERE id = ? LIMIT 1");
    $rStmt->execute([$currentRoleId]);
    $userRoleName = strtoupper(trim((string)$rStmt->fetchColumn()));
}
$isBM = ($currentRoleId === 6) || ($userRoleName === 'BM');

// Auto-seed standard terminal accounts if devices table is empty globally
$deviceCount = (int)$pdo->query("SELECT COUNT(*) FROM devices WHERE deleted_at IS NULL")->fetchColumn();
if ($deviceCount === 0) {
    $bId = (int)($pdo->query("SELECT id FROM branches ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1);
    
    $seedDevices = [
        [
            'device_name' => 'Main Cashier Terminal (POS-01)',
            'mac_address' => '00:1A:2B:3C:4D:5E',
            'device_token' => 'DEV-POS-MAIN-001',
            'status' => 'Active'
        ],
        [
            'device_name' => 'Attendance Clock Station (Clock-01)',
            'mac_address' => '00:1A:2B:3C:4D:5F',
            'device_token' => 'DEV-CLOCK-MAIN-001',
            'status' => 'Active'
        ],
        [
            'device_name' => 'Self-Service Customer Kiosk (Kiosk-01)',
            'mac_address' => '00:1A:2B:3C:4D:60',
            'device_token' => 'DEV-KIOSK-MAIN-001',
            'status' => 'Active'
        ]
    ];
    
    $ins = $pdo->prepare("INSERT INTO devices (branch_id, device_name, mac_address, device_token, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
    foreach ($seedDevices as $sd) {
        $ins->execute([$bId, $sd['device_name'], $sd['mac_address'], $sd['device_token'], $sd['status']]);
    }
}

$error = null;
$success = null;

$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true) ?? [];
$inputData = array_merge($_POST, $jsonInput);
$action = $inputData['action'] ?? $_GET['action'] ?? '';
$isJson = (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
    || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'));

// Handle device enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['enroll_device', 'add_device', 'create_device'], true)) {
    Rbac::require_permission('devices.manage');

    $token = $inputData['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Csrf::verify($token)) {
        http_response_code(403);
        die(json_encode(["error" => "Invalid security token. Please refresh and try again."]));
    }

    $deviceName = trim($inputData['device_name'] ?? '');
    $macAddress = trim($inputData['mac_address'] ?? '');
    $deviceToken = trim($inputData['device_token'] ?? '');
    $status = $inputData['status'] ?? 'Active';
    if (!in_array($status, ['Active', 'Inactive', 'Maintenance'], true)) {
        $status = 'Active';
    }

    $submittedBranchId = isset($inputData['branch_id']) && $inputData['branch_id'] !== '' ? (int)$inputData['branch_id'] : null;

    if ($isBM) {
        // Critical Constraint: If a BM tries to submit a branch_id that doesn't match their own, return a 403.
        if ($submittedBranchId !== null && $submittedBranchId !== $bmBranchId) {
            Audit::log('SECURITY_CROSS_BRANCH_DEVICE_BLOCKED', "BM user {$currentUser['id']} attempted to enroll device for branch {$submittedBranchId}");
            http_response_code(403);
            die(json_encode([
                "error" => "Forbidden. Branch scope mismatch. Cannot enroll device for another branch."
            ]));
        }
        $effectiveBranchId = $bmBranchId;
    } else {
        $effectiveBranchId = $submittedBranchId ?: 1;
    }

    if ($deviceName === '') {
        $error = 'Device Name is required.';
        if ($isJson) {
            http_response_code(400);
            die(json_encode(["error" => $error]));
        }
    } else {
        if (empty($deviceToken)) {
            $slug = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $deviceName), 0, 6));
            $deviceToken = 'DEV-' . ($slug ?: 'TERM') . '-' . bin2hex(random_bytes(4));
        }

        $checkStmt = $pdo->prepare("SELECT id FROM devices WHERE device_token = ? AND deleted_at IS NULL LIMIT 1");
        $checkStmt->execute([$deviceToken]);
        if ($checkStmt->fetch()) {
            $error = 'Device token is already in use by another terminal.';
            if ($isJson) {
                http_response_code(409);
                die(json_encode(["error" => $error]));
            }
        } else {
            $ins = $pdo->prepare("INSERT INTO devices (branch_id, device_name, mac_address, device_token, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
            $ins->execute([$effectiveBranchId, $deviceName, $macAddress ?: null, $deviceToken, $status]);
            $newDeviceId = (int)$pdo->lastInsertId();
            $success = 'Hardware device enrolled successfully.';
            if ($isJson) {
                header('Content-Type: application/json');
                die(json_encode(["success" => true, "device_id" => $newDeviceId, "message" => $success]));
            }
        }
    }
}

// Fetch devices with branch details scoped by Rbac::branch_scope
[$branchClause, $branchParams] = Rbac::branch_scope('d');
$sql = "
    SELECT d.id, d.branch_id, d.device_name, d.device_token, d.mac_address, d.status, d.created_at, d.updated_at,
           b.name AS branch_name
    FROM devices d
    LEFT JOIN branches b ON d.branch_id = b.id
    WHERE d.deleted_at IS NULL {$branchClause}
    ORDER BY d.id ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($branchParams);
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counts
$totalDevices = count($devices);
$activeDevices = count(array_filter($devices, fn($d) => strtolower($d['status'] ?? '') === 'active'));
$branchesLinked = count(array_unique(array_filter(array_column($devices, 'branch_name'))));

// Fetch branches for admin enrollment modal
$branchesStmt = $pdo->query("SELECT id, name FROM branches ORDER BY id ASC");
$allBranches = $branchesStmt ? $branchesStmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Branch name for BM
$bmBranchName = 'My Branch';
foreach ($allBranches as $b) {
    if ((int)$b['id'] === $bmBranchId) {
        $bmBranchName = $b['name'];
        break;
    }
}

$csrfToken = Csrf::getToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Accounts & Hardware Devices &middot; Fika HRMS</title>
    <link rel="icon" type="image/svg+xml" href="../public/assets/logo.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans antialiased min-h-screen flex">

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">
        <!-- Top Navigation -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-cyan-600 flex items-center justify-center text-white font-black text-xl shadow-sm">
                        <i class="fa-solid fa-tablet-screen-button text-base"></i>
                    </div>
                    <div>
                        <h1 class="font-bold text-lg text-slate-900 leading-tight">System Accounts</h1>
                        <p class="text-xs text-slate-500">Hardware terminals: Cashier Terminals, Biometric Clocks, and Self-Service Kiosks</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <button type="button" onclick="openEnrollModal()" class="inline-flex items-center gap-2 px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-xl text-xs font-bold shadow-sm transition">
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Enroll Device</span>
                    </button>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-cyan-50 text-cyan-700 border border-cyan-200">
                        <span class="w-2 h-2 rounded-full bg-cyan-500 animate-pulse"></span>
                        Hardware Gateway Active
                    </span>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <?php if ($error): ?>
                <div class="mb-6 bg-rose-50 text-rose-800 border border-rose-200 rounded-xl p-4 flex items-center gap-3 text-sm font-medium shadow-sm">
                    <i class="fa-solid fa-circle-exclamation text-rose-600 text-base"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-6 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-xl p-4 flex items-center gap-3 text-sm font-medium shadow-sm">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                    <span><?= e($success) ?></span>
                </div>
            <?php endif; ?>

            <!-- 3 Stat Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
                <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Registered Devices</span>
                        <p class="text-3xl font-black text-slate-900 mt-2"><?= $totalDevices ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-server"></i>
                    </div>
                </div>
                <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Active Terminals</span>
                        <p class="text-3xl font-black text-emerald-600 mt-2"><?= $activeDevices ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                </div>
                <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Branches Deployed</span>
                        <p class="text-3xl font-black text-slate-900 mt-2"><?= $branchesLinked ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-store"></i>
                    </div>
                </div>
            </div>

            <!-- Devices Table Card -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Registered System Devices</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Authorised terminal tokens and physical MAC bindings</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-3 py-1 rounded-full">
                            <?= $totalDevices ?> Device<?= $totalDevices === 1 ? '' : 's' ?>
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                <th class="px-6 py-3.5">Device Name</th>
                                <th class="px-6 py-3.5">Device Token</th>
                                <th class="px-6 py-3.5">Assigned Branch</th>
                                <th class="px-6 py-3.5">MAC Address</th>
                                <th class="px-6 py-3.5">Status</th>
                                <th class="px-6 py-3.5">Registered Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php if (empty($devices)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-slate-400">
                                        No system devices registered for this branch.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($devices as $dev): ?>
                                    <tr class="hover:bg-slate-50/80 transition-colors">
                                        <td class="px-6 py-4 font-bold text-slate-900 flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-cyan-50 text-cyan-600 flex items-center justify-center text-sm">
                                                <?php if (stripos($dev['device_name'], 'kiosk') !== false): ?>
                                                    <i class="fa-solid fa-desktop"></i>
                                                <?php elseif (stripos($dev['device_name'], 'clock') !== false): ?>
                                                    <i class="fa-solid fa-clock"></i>
                                                <?php else: ?>
                                                    <i class="fa-solid fa-cash-register"></i>
                                                <?php endif; ?>
                                            </div>
                                            <span><?= e($dev['device_name']) ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <code class="px-2.5 py-1 rounded-md bg-slate-100 text-slate-700 text-xs font-mono font-semibold border border-slate-200">
                                                <?= e($dev['device_token'] ?: '—') ?>
                                            </code>
                                        </td>
                                        <td class="px-6 py-4 text-slate-600 font-medium">
                                            <span class="inline-flex items-center gap-1.5">
                                                <i class="fa-solid fa-location-dot text-xs text-slate-400"></i>
                                                <?= e($dev['branch_name'] ?: 'Branch #' . $dev['branch_id']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-slate-500 font-mono text-xs">
                                            <?= e($dev['mac_address'] ?: '—') ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if (strtolower($dev['status'] ?? '') === 'active'): ?>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> <?= e($dev['status']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-xs text-slate-500">
                                            <?= date('M d, Y', strtotime($dev['created_at'])) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Enroll Device Modal -->
    <div id="enrollModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-lg w-full overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                <h3 class="font-bold text-slate-900 text-lg">Enroll New System Device</h3>
                <button type="button" onclick="closeEnrollModal()" class="text-slate-400 hover:text-slate-600 transition">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <form method="POST" action="fika_hrms_devices.php" class="p-6 space-y-4">
                <input type="hidden" name="action" value="enroll_device">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <!-- Device Name -->
                <div>
                    <label for="enrollDeviceName" class="block text-xs font-bold uppercase text-slate-600 mb-1">Device Name</label>
                    <input type="text" name="device_name" id="enrollDeviceName" required class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-cyan-500 focus:outline-none" placeholder="e.g. Counter Terminal 2 (POS-02)">
                </div>

                <!-- MAC Address -->
                <div>
                    <label for="enrollMacAddress" class="block text-xs font-bold uppercase text-slate-600 mb-1">MAC Address (Optional)</label>
                    <input type="text" name="mac_address" id="enrollMacAddress" class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-cyan-500 focus:outline-none font-mono" placeholder="00:1A:2B:3C:4D:5E">
                </div>

                <!-- Device Token -->
                <div>
                    <label for="enrollDeviceToken" class="block text-xs font-bold uppercase text-slate-600 mb-1">Device Token (Optional)</label>
                    <input type="text" name="device_token" id="enrollDeviceToken" class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-cyan-500 focus:outline-none font-mono" placeholder="Leave blank to auto-generate...">
                    <p class="text-[11px] text-slate-400 mt-1">Unique secret token installed on physical hardware terminal.</p>
                </div>

                <!-- Assigned Branch -->
                <div>
                    <label for="enrollBranchId" class="block text-xs font-bold uppercase text-slate-600 mb-1">Assigned Branch</label>
                    <?php if ($isBM): ?>
                        <input type="hidden" name="branch_id" value="<?= $bmBranchId ?>">
                        <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 flex items-center gap-2">
                            <i class="fa-solid fa-store text-cyan-600 text-xs"></i>
                            <span><?= e($bmBranchName) ?> (Branch #<?= $bmBranchId ?>)</span>
                            <span class="ml-auto text-[10px] font-bold uppercase tracking-wider text-cyan-700 bg-cyan-50 border border-cyan-200 px-2 py-0.5 rounded">Enforced</span>
                        </div>
                    <?php else: ?>
                        <select name="branch_id" id="enrollBranchId" required class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-cyan-500 focus:outline-none">
                            <?php foreach ($allBranches as $b): ?>
                                <option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <!-- Status -->
                <div>
                    <label for="enrollStatus" class="block text-xs font-bold uppercase text-slate-600 mb-1">Initial Status</label>
                    <select name="status" id="enrollStatus" class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-cyan-500 focus:outline-none">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                        <option value="Maintenance">Maintenance</option>
                    </select>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" onclick="closeEnrollModal()" class="px-4 py-2 border border-slate-300 rounded-xl text-slate-700 hover:bg-slate-50 text-sm font-medium transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-xl text-sm font-bold shadow-sm transition">
                        Enroll Device
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openEnrollModal() {
        document.getElementById('enrollModal').classList.remove('hidden');
    }

    function closeEnrollModal() {
        document.getElementById('enrollModal').classList.add('hidden');
    }
    </script>
</body>
</html>
