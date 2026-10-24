<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/core/Output.php';

Auth::requireLogin();
Rbac::require_permission('settings.manage');

global $pdo;

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    if ($stmt) {
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (Throwable $e) {
    // Non-fatal fallback
}

$leaveTypes = [];
try {
    $leaveStmt = $pdo->query("SELECT * FROM leave_types ORDER BY code ASC");
    $leaveTypes = $leaveStmt ? $leaveStmt->fetchAll() : [];
} catch (Throwable $e) {
    $leaveTypes = [];
}

$csrfToken = Csrf::getToken();
$pageTitle = 'Global Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global System Settings &middot; Fika HRMS</title>
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
                    <div class="w-10 h-10 rounded-xl bg-slate-800 text-white flex items-center justify-center font-black text-lg shadow-xs">
                        <i class="fa-solid fa-gear text-base text-amber-500"></i>
                    </div>
                    <div>
                        <h1 class="font-bold text-lg text-slate-900 leading-tight">System &amp; HR Settings</h1>
                        <p class="text-xs text-slate-500">Manage timekeeping rules, statutory policies, leave allowances, and receipt branding</p>
                    </div>
                </div>

                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-600">
                    <i class="fa-solid fa-shield-halved text-amber-600"></i>
                    <span>Admin Configuration</span>
                </div>
            </div>
        </header>

        <!-- Main Form Container -->
        <main class="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
            
            <div id="alertBox" class="hidden p-4 rounded-xl text-sm font-bold border transition"></div>

            <form id="settingsForm" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <!-- Attendance & Timekeeping Settings -->
                <div class="bg-white shadow-xs rounded-2xl border border-slate-200 p-6">
                    <div class="flex items-center gap-2 mb-4 pb-3 border-b border-slate-100">
                        <i class="fa-solid fa-clock text-indigo-600 text-base"></i>
                        <h2 class="text-base font-bold text-slate-900">Attendance &amp; Timekeeping Rules</h2>
                    </div>
                    <div class="max-w-md">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                            Late Grace Period (minutes) *
                        </label>
                        <p class="text-xs text-slate-500 mb-2.5">
                            Within this grace period, employee check-ins are not marked as late. Beyond it, attendance is flagged as late against scheduled shift start.
                        </p>
                        <div class="flex items-center gap-3">
                            <input type="number" name="late_grace_minutes" value="<?= e($settings['late_grace_minutes'] ?? 15) ?>" min="0" max="180" required 
                                   class="block w-36 rounded-xl border border-slate-300 shadow-xs p-2.5 text-sm font-bold text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                            <span class="text-xs font-semibold text-slate-500">minutes allowed</span>
                        </div>
                    </div>
                </div>

                <!-- Contributions by Employment Type -->
                <div class="bg-white shadow-xs rounded-2xl border border-slate-200 p-6">
                    <div class="flex items-center gap-2 mb-4 pb-3 border-b border-slate-100">
                        <i class="fa-solid fa-shield-cat text-emerald-600 text-base"></i>
                        <h2 class="text-base font-bold text-slate-900">Statutory Contributions by Employment Type</h2>
                    </div>
                    <p class="text-xs text-slate-500 mb-4">
                        Enable or disable government mandatory contributions (SSS, PhilHealth, Pag-IBIG) deduction eligibility by contract type.
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <label class="flex items-center gap-3 p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 transition cursor-pointer">
                            <input type="checkbox" name="contrib_FULL_TIME" value="1" <?= !empty($settings['contrib_FULL_TIME']) ? 'checked' : '' ?> class="h-4 w-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500">
                            <div>
                                <span class="block text-xs font-bold text-slate-800">Full-Time</span>
                                <span class="text-[11px] text-slate-500">Deduct SSS/PH/Pag-IBIG</span>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 transition cursor-pointer">
                            <input type="checkbox" name="contrib_PART_TIME" value="1" <?= !empty($settings['contrib_PART_TIME']) ? 'checked' : '' ?> class="h-4 w-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500">
                            <div>
                                <span class="block text-xs font-bold text-slate-800">Part-Time</span>
                                <span class="text-[11px] text-slate-500">Deduct SSS/PH/Pag-IBIG</span>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 transition cursor-pointer">
                            <input type="checkbox" name="contrib_CONTRACTUAL" value="1" <?= !empty($settings['contrib_CONTRACTUAL']) ? 'checked' : '' ?> class="h-4 w-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500">
                            <div>
                                <span class="block text-xs font-bold text-slate-800">Contractual</span>
                                <span class="text-[11px] text-slate-500">Deduct SSS/PH/Pag-IBIG</span>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 transition cursor-pointer">
                            <input type="checkbox" name="contrib_INTERN" value="1" <?= !empty($settings['contrib_INTERN']) ? 'checked' : '' ?> class="h-4 w-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500">
                            <div>
                                <span class="block text-xs font-bold text-slate-800">Intern</span>
                                <span class="text-[11px] text-slate-500">Deduct SSS/PH/Pag-IBIG</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Leave Types Configuration -->
                <div class="bg-white shadow-xs rounded-2xl border border-slate-200 p-6">
                    <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-plane-departure text-blue-600 text-base"></i>
                            <h2 class="text-base font-bold text-slate-900">Leave Policies &amp; Annual Allocations</h2>
                        </div>
                        <span class="text-xs text-slate-500 font-semibold"><?= count($leaveTypes) ?> leave type(s) configured</span>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full text-left divide-y divide-slate-200 text-xs">
                            <thead class="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
                                <tr>
                                    <th class="px-4 py-3">Code</th>
                                    <th class="px-4 py-3">Policy Name</th>
                                    <th class="px-4 py-3 text-center">Paid Leave?</th>
                                    <th class="px-4 py-3 text-center">Annual Allowance (Days)</th>
                                    <th class="px-4 py-3 text-center">Min Months Service</th>
                                    <th class="px-4 py-3 text-center">Statutory Benefit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white text-slate-700">
                                <?php if (empty($leaveTypes)): ?>
                                    <tr>
                                        <td colspan="6" class="px-4 py-6 text-center text-slate-400">No leave policies found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($leaveTypes as $l): ?>
                                        <tr class="hover:bg-slate-50/60 transition">
                                            <td class="px-4 py-3 font-mono font-bold text-slate-900"><?= e($l['code']) ?></td>
                                            <td class="px-4 py-3">
                                                <input type="text" name="leave_types[<?= e($l['code']) ?>][name]" value="<?= e($l['name']) ?>" required 
                                                       class="block w-full rounded-lg border border-slate-300 shadow-2xs p-1.5 text-xs font-semibold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <input type="checkbox" name="leave_types[<?= e($l['code']) ?>][is_paid]" value="1" <?= $l['is_paid'] ? 'checked' : '' ?> 
                                                       class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <input type="number" step="0.5" name="leave_types[<?= e($l['code']) ?>][annual_days]" value="<?= e($l['annual_days']) ?>" min="0" required 
                                                       class="block w-24 mx-auto rounded-lg border border-slate-300 shadow-2xs p-1.5 text-xs text-center font-bold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <input type="number" name="leave_types[<?= e($l['code']) ?>][min_service_months]" value="<?= e($l['min_service_months']) ?>" min="0" required 
                                                       class="block w-24 mx-auto rounded-lg border border-slate-300 shadow-2xs p-1.5 text-xs text-center font-bold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <input type="checkbox" name="leave_types[<?= e($l['code']) ?>][statutory]" value="1" <?= $l['statutory'] ? 'checked' : '' ?> 
                                                       class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Thermal Receipt & BIR Settings -->
                <div class="bg-white shadow-xs rounded-2xl border border-slate-200 p-6">
                    <div class="flex items-center gap-2 mb-4 pb-3 border-b border-slate-100">
                        <i class="fa-solid fa-receipt text-amber-600 text-base"></i>
                        <h2 class="text-base font-bold text-slate-900">Thermal Receipt Header &amp; BIR Compliance</h2>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 text-xs">
                        <div>
                            <label class="block font-bold uppercase tracking-wider text-slate-700 mb-1.5">Business Name (Header) *</label>
                            <input type="text" name="receipt_business_name" value="<?= e($settings['receipt_business_name'] ?? 'Fika Coffee & Pastries') ?>" required 
                                   class="block w-full rounded-xl border border-slate-300 shadow-xs p-2.5 font-medium text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label class="block font-bold uppercase tracking-wider text-slate-700 mb-1.5">TIN (Taxpayer Identification Number) *</label>
                            <input type="text" name="receipt_tin" value="<?= e($settings['receipt_tin'] ?? 'TIN: 123-456-789-00000 VAT') ?>" required 
                                   class="block w-full rounded-xl border border-slate-300 shadow-xs p-2.5 font-mono font-medium text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block font-bold uppercase tracking-wider text-slate-700 mb-1.5">Receipt Branch Address *</label>
                            <input type="text" name="receipt_address" value="<?= e($settings['receipt_address'] ?? '123 Coffee St., BGC, Taguig City') ?>" required 
                                   class="block w-full rounded-xl border border-slate-300 shadow-xs p-2.5 font-medium text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block font-bold uppercase tracking-wider text-slate-700 mb-1.5">Receipt Footer Message *</label>
                            <input type="text" name="receipt_footer" value="<?= e($settings['receipt_footer'] ?? 'Thank you for your visit!') ?>" required 
                                   class="block w-full rounded-xl border border-slate-300 shadow-xs p-2.5 font-medium text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div class="md:col-span-2 pt-2">
                            <label class="flex items-start gap-3 p-4 rounded-xl border border-slate-200 bg-amber-50/30 hover:bg-amber-50/50 transition cursor-pointer">
                                <input type="checkbox" id="receipt_bir_registered" name="receipt_bir_registered" value="1" 
                                       <?= (!empty($settings['receipt_bir_registered']) || !empty($settings['bir_registered'])) ? 'checked' : '' ?> 
                                       class="h-4 w-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500 mt-0.5">
                                <div>
                                    <span class="block text-xs font-bold text-slate-900">
                                        BIR-Registered POS System Mode
                                    </span>
                                    <span class="text-[11px] text-slate-600">
                                        Prints "THIS SERVES AS YOUR OFFICIAL RECEIPT", VAT Breakdown (VATable, VAT-Exempt, 12% VAT), and Senior/PWD discount itemizations.
                                    </span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Submit Button Bar -->
                <div class="flex justify-end gap-3 pt-2">
                    <button type="submit" id="btnSaveSettings" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Save System Settings</span>
                    </button>
                </div>
            </form>
        </main>
    </div>

    <script>
        document.getElementById('settingsForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveSettings');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

            const box = document.getElementById('alertBox');
            box.className = 'hidden p-4 rounded-xl text-sm font-bold border transition';
            
            const formData = new FormData(this);
            const payload = {
                late_grace_minutes: formData.get('late_grace_minutes'),
                contrib_FULL_TIME: formData.get('contrib_FULL_TIME') ? '1' : '0',
                contrib_PART_TIME: formData.get('contrib_PART_TIME') ? '1' : '0',
                contrib_CONTRACTUAL: formData.get('contrib_CONTRACTUAL') ? '1' : '0',
                contrib_INTERN: formData.get('contrib_INTERN') ? '1' : '0',
                receipt_business_name: formData.get('receipt_business_name'),
                receipt_address: formData.get('receipt_address'),
                receipt_tin: formData.get('receipt_tin'),
                receipt_footer: formData.get('receipt_footer'),
                receipt_bir_registered: formData.get('receipt_bir_registered') ? '1' : '0',
                bir_registered: formData.get('receipt_bir_registered') ? '1' : '0',
                csrf_token: formData.get('csrf_token'),
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
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const response = await fetch('api/fika_hrms_settings_save.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(payload)
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    box.className = 'p-4 rounded-xl text-sm font-bold bg-rose-50 text-rose-800 border border-rose-200 block';
                    box.innerText = data.error || 'Failed to save settings.';
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    box.className = 'p-4 rounded-xl text-sm font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 block';
                    box.innerText = data.message || 'Settings saved successfully.';
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            } catch (err) {
                box.className = 'p-4 rounded-xl text-sm font-bold bg-rose-50 text-rose-800 border border-rose-200 block';
                box.innerText = 'Network or server communication error occurred.';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save System Settings';
            }
        });
    </script>
</body>
</html>
