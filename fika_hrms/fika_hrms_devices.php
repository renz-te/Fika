<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/core/Output.php';

Auth::requireLogin();
Rbac::require_permission('users.view');

global $pdo;

// Auto-seed standard terminal accounts if devices table is empty
$deviceCount = (int)$pdo->query("SELECT COUNT(*) FROM devices WHERE deleted_at IS NULL")->fetchColumn();
if ($deviceCount === 0) {
    // Get default branch id (fallback to 1)
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

// Fetch all devices with branch details
$sql = "
    SELECT d.id, d.device_name, d.device_token, d.mac_address, d.status, d.created_at, d.updated_at,
           b.name AS branch_name
    FROM devices d
    LEFT JOIN branches b ON d.branch_id = b.id
    WHERE d.deleted_at IS NULL
    ORDER BY d.id ASC
";
$devices = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Counts
$totalDevices = count($devices);
$activeDevices = count(array_filter($devices, fn($d) => strtolower($d['status'] ?? '') === 'active'));
$branchesLinked = count(array_unique(array_filter(array_column($devices, 'branch_name'))));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Accounts & Hardware Devices · Fika HRMS</title>
    <link rel="icon" type="image/svg+xml" href="../public/assets/logo.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-cyan-50 text-cyan-700 border border-cyan-200">
                        <span class="w-2 h-2 rounded-full bg-cyan-500 animate-pulse"></span>
                        Hardware Gateway Active
                    </span>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
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
                    <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-3 py-1 rounded-full">
                        <?= $totalDevices ?> Device<?= $totalDevices === 1 ? '' : 's' ?>
                    </span>
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
                                        No system devices registered.
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
</body>
</html>
