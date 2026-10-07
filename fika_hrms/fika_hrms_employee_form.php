<?php
require __DIR__ . '/../app/bootstrap.php';

Auth::requireLogin();
$id = (int) ($_GET['id'] ?? 0);

if ($id > 0) {
    Rbac::require_permission('hr.employee.edit');
} else {
    Rbac::require_permission('hr.employee.create');
}

$canViewSensitive = Rbac::can('hr.employee.view_sensitive');

global $pdo;

$employee = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        die("Employee not found.");
    }
    Rbac::assert_branch_access($employee['branch_id']);
}

// Fetch branches for dropdown
$bSql = "SELECT id, name FROM branches WHERE deleted_at IS NULL";
$bParams = [];
list($bScopeSql, $bScopeParams) = Rbac::branch_scope();
$bSql .= str_replace('branch_id', 'id', $bScopeSql) . " ORDER BY name ASC";
$bStmt = $pdo->prepare($bSql);
$bStmt->execute($bScopeParams);
$branches = $bStmt->fetchAll();

// Helper to mask or decrypt
function display_sensitive(?string $encryptedValue, bool $canViewSensitive): string {
    if (empty($encryptedValue)) return '';
    
    try {
        $decrypted = Crypto::decrypt($encryptedValue);
    } catch (Exception $e) {
        return '****[ERR]';
    }
    
    if ($canViewSensitive) {
        return $decrypted;
    }
    
    if (strlen($decrypted) <= 4) {
        return '****';
    }
    
    return '****' . substr($decrypted, -4);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $id > 0 ? 'Edit' : 'Create' ?> Employee - Fika HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 font-sans">
    <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900"><?= $id > 0 ? 'Edit Employee' : 'Create New Employee' ?></h1>
            <p class="mt-2 text-sm text-gray-600">Please fill out the details carefully. Sensitive fields will be encrypted.</p>
        </div>

        <div class="bg-white p-6 rounded shadow">
            <div id="alertBox" class="hidden mb-4 p-4 rounded text-sm"></div>
            
            <form id="employeeForm">
                <input type="hidden" name="csrf_token" value="<?= e(Csrf::getToken()) ?>">
                <input type="hidden" name="id" value="<?= e($id) ?>">
                
                <h3 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2">Primary Details</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Employee Code *</label>
                        <input type="text" name="employee_code" value="<?= e($employee['employee_code'] ?? '') ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Branch *</label>
                        <select name="branch_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select Branch</option>
                            <?php foreach($branches as $b): ?>
                                <option value="<?= e($b['id']) ?>" <?= ($employee['branch_id'] ?? '') == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Staff Class *</label>
                        <select name="staff_class" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                            <option value="CREW" <?= ($employee['staff_class'] ?? '') === 'CREW' ? 'selected' : '' ?>>CREW</option>
                            <option value="OFFICIAL" <?= ($employee['staff_class'] ?? '') === 'OFFICIAL' ? 'selected' : '' ?>>OFFICIAL</option>
                            <option value="HQ" <?= ($employee['staff_class'] ?? '') === 'HQ' ? 'selected' : '' ?>>HQ</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">First Name *</label>
                        <input type="text" name="first_name" value="<?= e($employee['first_name'] ?? '') ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Last Name *</label>
                        <input type="text" name="last_name" value="<?= e($employee['last_name'] ?? '') ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                <h3 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2">Employment Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Position</label>
                        <input type="text" name="position" value="<?= e($employee['position'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Department</label>
                        <input type="text" name="department" value="<?= e($employee['department'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Date Hired</label>
                        <input type="date" name="date_hired" value="<?= e($employee['date_hired'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Basic Salary (₱) *</label>
                        <input type="number" step="0.01" name="basic_salary" value="<?= e($employee['basic_salary'] ?? '') ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Employment Type *</label>
                        <select name="employment_type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                            <?php $et = $employee['employment_type'] ?? 'REGULAR'; ?>
                            <option value="REGULAR" <?= $et === 'REGULAR' ? 'selected' : '' ?>>Regular</option>
                            <option value="PROBATIONARY" <?= $et === 'PROBATIONARY' ? 'selected' : '' ?>>Probationary</option>
                            <option value="PART_TIME" <?= $et === 'PART_TIME' ? 'selected' : '' ?>>Part-Time</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status *</label>
                        <select name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                            <?php $st = $employee['status'] ?? 'ACTIVE'; ?>
                            <option value="ACTIVE" <?= $st === 'ACTIVE' ? 'selected' : '' ?>>Active</option>
                            <option value="INACTIVE" <?= $st === 'INACTIVE' ? 'selected' : '' ?>>Inactive</option>
                            <option value="SEPARATED" <?= $st === 'SEPARATED' ? 'selected' : '' ?>>Separated</option>
                        </select>
                    </div>
                </div>

                <h3 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2">Sensitive Information (Encrypted)</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Bank Account</label>
                        <input type="text" name="bank_no" value="<?= e(display_sensitive($employee['bank_no_enc'] ?? null, $canViewSensitive)) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">TIN</label>
                        <input type="text" name="tin" value="<?= e(display_sensitive($employee['tin_enc'] ?? null, $canViewSensitive)) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">SSS</label>
                        <input type="text" name="sss" value="<?= e(display_sensitive($employee['sss_no_enc'] ?? null, $canViewSensitive)) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">PhilHealth</label>
                        <input type="text" name="philhealth" value="<?= e(display_sensitive($employee['philhealth_no_enc'] ?? null, $canViewSensitive)) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Pag-IBIG</label>
                        <input type="text" name="pagibig" value="<?= e(display_sensitive($employee['pagibig_no_enc'] ?? null, $canViewSensitive)) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                <h3 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2">Security</h3>
                <div class="mb-8">
                    <label class="block text-sm font-medium text-gray-700">Clock PIN (4-6 digits) <?= $id > 0 ? '<span class="text-xs text-gray-400">(Leave blank to keep unchanged)</span>' : '*' ?></label>
                    <input type="password" name="pin" <?= $id === 0 ? 'required' : '' ?> class="mt-1 block w-full md:w-1/2 rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500 focus:ring-blue-500" placeholder="••••">
                </div>

                <div class="flex justify-end gap-4 border-t pt-4">
                    <a href="fika_hrms_employees.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded shadow">Cancel</a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded shadow">Save Employee</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('employeeForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const box = document.getElementById('alertBox');
            box.className = 'hidden mb-4 p-4 rounded text-sm';
            
            try {
                const response = await fetch('api/fika_hrms_employee_save.php', {
                    method: 'POST',
                    body: new FormData(this)
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
                    
                    if (data.id && document.querySelector('input[name="id"]').value === '0') {
                        setTimeout(() => {
                            window.location.href = 'fika_hrms_employees.php';
                        }, 1000);
                    }
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
