<?php
require __DIR__ . '/../app/bootstrap.php';

Auth::requireLogin();
Rbac::require_permission('hr.attendance.certify');

global $pdo;
$user = Auth::user();

// Fetch past certifications
$stmt = $pdo->prepare("
    SELECT c.*, b.name as branch_name, e.first_name, e.last_name, u.username as certifier
    FROM attendance_certifications c
    LEFT JOIN branches b ON c.branch_id = b.id
    LEFT JOIN employees e ON c.employee_id = e.id
    LEFT JOIN users u ON c.certified_by = u.id
    ORDER BY c.created_at DESC LIMIT 50
");
$stmt->execute();
$certs = $stmt->fetchAll();

// Fetch branches and HQ employees for dropdowns
$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
$hqEmps = $pdo->query("
    SELECT e.id, e.first_name, e.last_name 
    FROM employees e 
    JOIN users u ON e.id = u.employee_id 
    WHERE u.branch_id IS NULL
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certify Attendance - Fika HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 font-sans">
    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Attendance Certification</h1>
                <p class="mt-2 text-sm text-gray-600">Lock and certify attendance periods for payroll generation.</p>
            </div>
            <div class="text-right">
                <a href="pos_dashboard.php" class="text-blue-600 hover:underline">Back to Dashboard</a>
            </div>
        </div>

        <div id="alertBox" class="hidden mb-6 p-4 rounded text-sm font-bold shadow-sm"></div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Certify Form -->
            <div class="bg-white p-6 rounded-lg shadow h-fit">
                <h2 class="text-xl font-bold mb-4">New Certification</h2>
                <form id="certifyForm" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= e(Csrf::getToken()) ?>">
                    <input type="hidden" name="action" value="certify">

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Scope</label>
                        <select name="scope" id="scopeSelect" onchange="toggleScopeInputs()" required class="mt-1 block w-full rounded border-gray-300 shadow-sm p-2 border focus:border-blue-500">
                            <option value="">-- Select --</option>
                            <option value="BRANCH">BRANCH (Crew)</option>
                            <option value="OFFICIALS">OFFICIALS (Branch Managers/HR)</option>
                            <option value="HQ">HQ (Central Office)</option>
                        </select>
                    </div>

                    <div id="branchDiv" class="hidden">
                        <label class="block text-sm font-medium text-gray-700">Branch</label>
                        <select name="branch_id" id="branchInput" class="mt-1 block w-full rounded border-gray-300 shadow-sm p-2 border focus:border-blue-500">
                            <option value="">-- Select --</option>
                            <?php foreach($branches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="hqDiv" class="hidden">
                        <label class="block text-sm font-medium text-gray-700">HQ Employee</label>
                        <select name="employee_id" id="hqInput" class="mt-1 block w-full rounded border-gray-300 shadow-sm p-2 border focus:border-blue-500">
                            <option value="">-- Select --</option>
                            <?php foreach($hqEmps as $emp): ?>
                                <option value="<?= $emp['id'] ?>"><?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Start Date</label>
                            <input type="date" name="period_start" required class="mt-1 block w-full rounded border-gray-300 shadow-sm p-2 border focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">End Date</label>
                            <input type="date" name="period_end" required class="mt-1 block w-full rounded border-gray-300 shadow-sm p-2 border focus:border-blue-500">
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded shadow">
                        Certify Period
                    </button>
                </form>
            </div>

            <!-- Certification History -->
            <div class="md:col-span-2 bg-white shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Target</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Period</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Certified By</th>
                            <th class="px-4 py-3 text-center font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-center font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($certs as $c): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    <span class="bg-gray-200 text-gray-800 text-xs px-2 py-1 rounded mr-2"><?= e($c['scope']) ?></span><br>
                                    <?php
                                        if ($c['scope'] === 'BRANCH') echo e($c['branch_name']);
                                        elseif ($c['scope'] === 'HQ') echo e($c['first_name'] . ' ' . $c['last_name']);
                                        else echo "All Branches";
                                    ?>
                                </td>
                                <td class="px-4 py-3 text-gray-700 font-mono text-xs">
                                    <?= e($c['period_start']) ?> to <?= e($c['period_end']) ?>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    <?= e($c['certifier']) ?> <br>
                                    <span class="text-xs text-gray-400"><?= e($c['certified_at']) ?></span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-1 text-xs rounded-full font-bold 
                                        <?= $c['status'] === 'CERTIFIED' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                        <?= e($c['status']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($c['status'] === 'CERTIFIED'): ?>
                                        <button onclick="reopenCert(<?= $c['id'] ?>, '<?= js($c['scope']) ?>', <?= $c['branch_id'] ?: 'null' ?>, <?= $c['employee_id'] ?: 'null' ?>, '<?= js($c['period_start']) ?>', '<?= js($c['period_end']) ?>')" class="text-orange-600 hover:text-orange-900 font-bold text-xs">Reopen</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(empty($certs)): ?>
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No certifications found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Reopen Modal -->
    <div id="reopenModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg shadow-xl w-[400px]">
            <h2 class="text-xl font-bold mb-4 text-gray-800">Reopen Certification</h2>
            <p class="text-sm text-gray-600 mb-4">This allows HR to make attendance adjustments for this period.</p>
            <form id="reopenForm">
                <input type="hidden" name="action" value="reopen">
                <input type="hidden" id="ro_scope" name="scope">
                <input type="hidden" id="ro_branch" name="branch_id">
                <input type="hidden" id="ro_emp" name="employee_id">
                <input type="hidden" id="ro_start" name="period_start">
                <input type="hidden" id="ro_end" name="period_end">

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700">Reason for Reopening</label>
                    <textarea name="reason" required rows="3" class="mt-1 block w-full rounded border-gray-300 shadow-sm p-2 border focus:border-blue-500"></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('reopenModal').classList.add('hidden')" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">Cancel</button>
                    <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white font-bold py-2 px-4 rounded shadow">Reopen</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function toggleScopeInputs() {
            const scope = document.getElementById('scopeSelect').value;
            const b = document.getElementById('branchDiv');
            const h = document.getElementById('hqDiv');
            const bi = document.getElementById('branchInput');
            const hi = document.getElementById('hqInput');

            b.classList.add('hidden');
            h.classList.add('hidden');
            bi.required = false;
            hi.required = false;

            if (scope === 'BRANCH') {
                b.classList.remove('hidden');
                bi.required = true;
            } else if (scope === 'HQ') {
                h.classList.remove('hidden');
                hi.required = true;
            }
        }

        async function submitApi(formId) {
            const form = document.getElementById(formId);
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerText = "Processing...";
            
            const formData = new FormData(form);
            // Append CSRF for reopen form which doesn't have it explicitly
            formData.append('csrf_token', '<?= e(Csrf::getToken()) ?>');
            
            const payload = Object.fromEntries(formData.entries());
            // Filter nulls
            if (!payload.branch_id) delete payload.branch_id;
            if (!payload.employee_id) delete payload.employee_id;

            try {
                const response = await fetch('api/fika_hrms_attendance_certify_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();
                
                if (response.ok) {
                    window.location.reload();
                } else {
                    const box = document.getElementById('alertBox');
                    box.innerText = data.error || 'Failed.';
                    box.className = 'mb-6 p-4 rounded text-sm font-bold shadow-sm bg-red-100 text-red-800';
                    box.classList.remove('hidden');
                    if(formId === 'reopenForm') document.getElementById('reopenModal').classList.add('hidden');
                    window.scrollTo(0,0);
                    btn.disabled = false;
                    btn.innerText = "Try Again";
                }
            } catch(e) {
                alert("Network error");
                btn.disabled = false;
                btn.innerText = "Try Again";
            }
        }

        document.getElementById('certifyForm').addEventListener('submit', function(e) {
            e.preventDefault();
            submitApi('certifyForm');
        });

        document.getElementById('reopenForm').addEventListener('submit', function(e) {
            e.preventDefault();
            submitApi('reopenForm');
        });

        function reopenCert(id, scope, branchId, empId, pStart, pEnd) {
            document.getElementById('ro_scope').value = scope;
            document.getElementById('ro_branch').value = branchId || '';
            document.getElementById('ro_emp').value = empId || '';
            document.getElementById('ro_start').value = pStart;
            document.getElementById('ro_end').value = pEnd;
            document.getElementById('reopenModal').classList.remove('hidden');
        }
    </script>
</body>
</html>
