<?php
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/lib/attendance_calc.php';

Auth::requireLogin();
Rbac::require_permission('hr.attendance.view');

global $pdo;

$canAdjust = Rbac::can('hr.attendance.adjust');

// Load settings
$graceMins = 15;
$setStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'late_grace_minutes'");
if ($val = $setStmt->fetchColumn()) {
    $graceMins = (int)$val;
}

$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

[$scopeSql, $scopeParams] = Rbac::branch_scope('e');

$sql = "
    SELECT a.*, e.first_name, e.last_name, e.employee_code 
    FROM attendance_logs a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.work_date BETWEEN ? AND ? {$scopeSql}
    ORDER BY a.work_date DESC, a.clock_in DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$dateFrom, $dateTo], $scopeParams));
$logs = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Records - Fika HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 font-sans">
    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Attendance Records</h1>
                <p class="mt-2 text-sm text-gray-600">View logs and computed times (Grace period: <?= $graceMins ?> mins)</p>
            </div>
            <div class="text-right">
                <a href="pos_dashboard.php" class="text-blue-600 hover:underline">Back to Dashboard</a>
            </div>
        </div>

        <form method="GET" class="bg-white p-4 rounded shadow mb-6 flex gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700">Date From</label>
                <input type="date" name="date_from" value="<?= e($dateFrom) ?>" class="mt-1 block rounded-md border-gray-300 shadow-sm p-2 border">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Date To</label>
                <input type="date" name="date_to" value="<?= e($dateTo) ?>" class="mt-1 block rounded-md border-gray-300 shadow-sm p-2 border">
            </div>
            <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-900 shadow">Filter</button>
        </form>

        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Employee</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">In / Out (UTC)</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase">Worked (h:m)</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase">Late (m)</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase">OT (m)</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase">ND (m)</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-500 uppercase">Status</th>
                        <?php if($canAdjust): ?>
                            <th class="px-4 py-3 text-center font-medium text-gray-500 uppercase">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($logs as $log): 
                        // Compute dynamically
                        $calc = AttendanceCalc::calculate($log['clock_in'], $log['clock_out'], $log['clock_in'], $graceMins);
                        
                        $hours = floor($calc['worked_minutes'] / 60);
                        $mins = $calc['worked_minutes'] % 60;
                        $workedStr = "{$hours}h {$mins}m";
                    ?>
                        <tr class="hover:bg-gray-50 <?= $log['status'] === 'ADJUSTED' ? 'bg-yellow-50' : '' ?>">
                            <td class="px-4 py-3 font-medium text-gray-900">
                                <?= e($log['first_name'] . ' ' . $log['last_name']) ?> <br>
                                <span class="text-xs text-gray-500"><?= e($log['employee_code']) ?></span>
                            </td>
                            <td class="px-4 py-3 text-gray-500"><?= e($log['work_date']) ?></td>
                            <td class="px-4 py-3 text-gray-700">
                                <span class="text-green-700 font-bold"><?= e($log['clock_in']) ?></span> <br>
                                <span class="text-red-700 font-bold"><?= $log['clock_out'] ? e($log['clock_out']) : '--:--' ?></span>
                            </td>
                            <td class="px-4 py-3 text-right font-bold"><?= $log['clock_out'] ? $workedStr : '-' ?></td>
                            <td class="px-4 py-3 text-right text-orange-600 font-bold"><?= $calc['late_minutes'] > 0 ? $calc['late_minutes'] : '-' ?></td>
                            <td class="px-4 py-3 text-right text-blue-600 font-bold"><?= $calc['overtime_minutes'] > 0 ? $calc['overtime_minutes'] : '-' ?></td>
                            <td class="px-4 py-3 text-right text-purple-600 font-bold"><?= $calc['night_diff_minutes'] > 0 ? $calc['night_diff_minutes'] : '-' ?></td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-1 text-xs rounded-full font-bold 
                                    <?= $log['status'] === 'OPEN' ? 'bg-green-100 text-green-800' : ($log['status'] === 'ADJUSTED' ? 'bg-yellow-200 text-yellow-800' : 'bg-gray-200 text-gray-800') ?>">
                                    <?= e($log['status']) ?>
                                </span>
                            </td>
                            <?php if($canAdjust): ?>
                                <td class="px-4 py-3 text-center">
                                    <button onclick="openAdjust(<?= $log['id'] ?>, '<?= js($log['clock_in']) ?>', '<?= js($log['clock_out'] ?? '') ?>')" class="text-blue-600 hover:text-blue-900 font-medium">Adjust</button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($logs)): ?>
                        <tr><td colspan="9" class="px-4 py-8 text-center text-gray-500">No records found for this period.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if($canAdjust): ?>
    <!-- Adjustment Modal -->
    <div id="adjustModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg shadow-xl w-[500px]">
            <h2 class="text-2xl font-bold mb-4 text-gray-800">Adjust Attendance</h2>
            <div id="adjustAlert" class="hidden mb-4 p-3 rounded text-sm text-red-800 bg-red-100"></div>
            
            <form id="adjustForm">
                <input type="hidden" name="csrf_token" value="<?= e(Csrf::getToken()) ?>">
                <input type="hidden" id="adj_log_id" name="log_id">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Clock In (UTC, YYYY-MM-DD HH:MM:SS)</label>
                    <input type="text" id="adj_clock_in" name="clock_in" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500 font-mono">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Clock Out (UTC, YYYY-MM-DD HH:MM:SS)</label>
                    <input type="text" id="adj_clock_out" name="clock_out" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500 font-mono">
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700">Adjustment Reason (Required)</label>
                    <textarea name="reason" id="adj_reason" required rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500"></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeAdjust()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">Cancel</button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded shadow">Save Adjustment</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openAdjust(logId, inTime, outTime) {
            document.getElementById('adj_log_id').value = logId;
            document.getElementById('adj_clock_in').value = inTime;
            document.getElementById('adj_clock_out').value = outTime || inTime;
            document.getElementById('adj_reason').value = '';
            document.getElementById('adjustAlert').classList.add('hidden');
            document.getElementById('adjustModal').classList.remove('hidden');
        }

        function closeAdjust() {
            document.getElementById('adjustModal').classList.add('hidden');
        }

        document.getElementById('adjustForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerText = "Saving...";
            
            const formData = new FormData(this);
            const payload = Object.fromEntries(formData.entries());
            
            try {
                const response = await fetch('api/fika_hrms_attendance_adjust.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    const alert = document.getElementById('adjustAlert');
                    alert.innerText = data.error || 'Adjustment failed.';
                    alert.classList.remove('hidden');
                    btn.disabled = false;
                    btn.innerText = "Save Adjustment";
                } else {
                    window.location.reload();
                }
            } catch (err) {
                const alert = document.getElementById('adjustAlert');
                alert.innerText = 'Network error.';
                alert.classList.remove('hidden');
                btn.disabled = false;
                btn.innerText = "Save Adjustment";
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
