<?php
require __DIR__ . '/../app/bootstrap.php';

Auth::requireLogin();
Rbac::require_permission('settings.manage');

global $pdo;

$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$leaveStmt = $pdo->query("SELECT * FROM leave_types ORDER BY code ASC");
$leaveTypes = $leaveStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Global Settings - Fika HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 font-sans">
    <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">System Settings</h1>
            <p class="mt-2 text-sm text-gray-600">Manage global HR configurations, late grace periods, and leave rules.</p>
        </div>

        <div id="alertBox" class="hidden mb-4 p-4 rounded text-sm"></div>

        <form id="settingsForm">
            <input type="hidden" name="csrf_token" value="<?= e(Csrf::getToken()) ?>">

            <!-- Attendance Settings -->
            <div class="bg-white shadow rounded p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 border-b pb-2">Attendance & Timekeeping</h2>
                <div class="max-w-xs">
                    <label class="block text-sm font-medium text-gray-700">Late Grace Period (minutes)</label>
                    <p class="text-xs text-gray-500 mb-2">Within this grace period, the employee is NOT late. Beyond it, they are counted late from their scheduled start time.</p>
                    <input type="number" name="late_grace_minutes" value="<?= e($settings['late_grace_minutes'] ?? 15) ?>" min="0" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>

            <!-- Leave Types -->
            <div class="bg-white shadow rounded p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 border-b pb-2">Leave Types Configuration</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Code</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-500 uppercase">Is Paid?</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-500 uppercase">Annual Days</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-500 uppercase">Min Months Service</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-500 uppercase">Statutory (Manual)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($leaveTypes as $l): ?>
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-gray-900"><?= e($l['code']) ?></td>
                                    <td class="px-4 py-3">
                                        <input type="text" name="leave_types[<?= e($l['code']) ?>][name]" value="<?= e($l['name']) ?>" required class="block w-full rounded-md border-gray-300 shadow-sm p-1 border">
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <input type="checkbox" name="leave_types[<?= e($l['code']) ?>][is_paid]" value="1" <?= $l['is_paid'] ? 'checked' : '' ?> class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" name="leave_types[<?= e($l['code']) ?>][annual_days]" value="<?= e($l['annual_days']) ?>" min="0" required class="block w-24 mx-auto rounded-md border-gray-300 shadow-sm p-1 border text-center">
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" name="leave_types[<?= e($l['code']) ?>][min_service_months]" value="<?= e($l['min_service_months']) ?>" min="0" required class="block w-24 mx-auto rounded-md border-gray-300 shadow-sm p-1 border text-center">
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <input type="checkbox" name="leave_types[<?= e($l['code']) ?>][statutory]" value="1" <?= $l['statutory'] ? 'checked' : '' ?> class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-end gap-4 mt-8">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded shadow text-lg">Save Settings</button>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('settingsForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const box = document.getElementById('alertBox');
            box.className = 'hidden mb-4 p-4 rounded text-sm';
            
            // Build nested JSON payload
            const formData = new FormData(this);
            const payload = {
                late_grace_minutes: formData.get('late_grace_minutes'),
                leave_types: {}
            };
            
            for (let [key, val] of formData.entries()) {
                const match = key.match(/^leave_types\[(.*?)\]\[(.*?)\]$/);
                if (match) {
                    const code = match[1];
                    const field = match[2];
                    if (!payload.leave_types[code]) payload.leave_types[code] = {};
                    payload.leave_types[code][field] = val;
                }
            }
            
            try {
                const response = await fetch('api/fika_hrms_settings_save.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': formData.get('csrf_token') // Also sending as header if Csrf supports it, though payload doesn't contain csrf_token since we send json
                    },
                    // Send csrf_token inside payload for Csrf class
                    body: JSON.stringify({...payload, csrf_token: formData.get('csrf_token')})
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    box.className = 'mb-4 p-4 rounded text-sm bg-red-100 text-red-800';
                    box.innerText = data.error || 'Failed to save.';
                    box.style.display = 'block';
                } else {
                    box.className = 'mb-4 p-4 rounded text-sm bg-green-100 text-green-800';
                    box.innerText = data.message;
                    box.style.display = 'block';
                    window.scrollTo(0,0);
                }
            } catch (err) {
                box.className = 'mb-4 p-4 rounded text-sm bg-red-100 text-red-800';
                box.innerText = 'Network error occurred.';
                box.style.display = 'block';
            }
        });
    </script>
</body>
</html>
