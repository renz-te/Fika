<?php
require_once __DIR__ . '/init.php';
require_login();
$user = current_user();

if ($user['role'] !== 'Super Admin' && $user['role'] !== 'Central HR' && $user['role'] !== 'Branch Admin') {
    redirect('dashboard');
}

$error = '';
$success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'edit_account') {
        $id = $_POST['account_id'] ?? '';
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role_id = $_POST['role_id'] ?? '';
        $verified = isset($_POST['verified']) ? 1 : 0;
        
        if (empty($username) || empty($role_id) || empty($id)) {
            $error = "Username and Role are required.";
        } else {
            // check username
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $id]);
            if ($stmt->fetch()) {
                $error = "Username is already taken by another account.";
            } else {
                // Cannot deactivate yourself
                if ($id == $user['id'] && $verified == 0) {
                    $error = "You cannot deactivate your own account.";
                    $verified = 1;
                }
                
                if (!empty($password)) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, role_id = ?, verified = ?, password = ? WHERE id = ?");
                    $stmt->execute([$username, $role_id, $verified, $hashed, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, role_id = ?, verified = ? WHERE id = ?");
                    $stmt->execute([$username, $role_id, $verified, $id]);
                }
                if (!$error) {
                    $success = "Employee account updated successfully.";
                }
            }
        }
    }
}

// Fetch all roles to populate dropdown
$stmt = $pdo->query("SELECT * FROM roles ORDER BY name");
$allRoles = $stmt->fetchAll();

// Fetch Employee Accounts (employee_id IS NOT NULL)
$branchFilter = get_branch_filter('e');
$stmt = $pdo->query("SELECT u.id, u.name, u.username, u.role_id, u.verified, u.employee_id, r.name as role_name, e.status as emp_status 
                     FROM users u 
                     JOIN roles r ON u.role_id = r.id 
                     JOIN employees e ON u.employee_id = e.id
                     WHERE u.employee_id IS NOT NULL {$branchFilter}
                     ORDER BY u.name");
$accounts = $stmt->fetchAll();

$pageTitle = 'Account Management';
require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Account Management</h1>
        <p class="text-slate-500">Manage employee system access, passwords, and user roles.</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="mb-6 bg-red-50 text-red-700 border border-red-200 rounded-lg p-4 flex items-center">
        <i class="fa-solid fa-circle-exclamation mr-3 text-lg"></i>
        <?= h($error) ?>
    </div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="mb-6 bg-green-50 text-green-700 border border-green-200 rounded-lg p-4 flex items-center">
        <i class="fa-solid fa-circle-check mr-3 text-lg"></i>
        <?= h($success) ?>
    </div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <!-- Data Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-white border-b border-slate-200">
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider">Employee</th>
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider">Username</th>
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider">System Role</th>
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider">Access Status</th>
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($accounts)): ?>
                <tr>
                    <td colspan="5" class="py-10 text-center text-slate-500">
                        <div class="flex flex-col items-center justify-center">
                            <i class="fa-solid fa-users text-4xl mb-3 text-slate-300"></i>
                            <p>No employee accounts found.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach($accounts as $acc): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="py-4 px-6">
                            <div class="flex items-center">
                                <div class="h-10 w-10 rounded-full bg-slate-200 flex items-center justify-center text-slate-600 font-bold mr-3">
                                    <i class="fa-solid fa-user"></i>
                                </div>
                                <div>
                                    <div class="font-medium text-slate-800"><?= h($acc['name']) ?></div>
                                    <div class="text-xs text-slate-500">EMP ID: <?= $acc['employee_id'] ?> | <?= $acc['emp_status'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-6 text-sm text-slate-600 font-medium">
                            <?= h($acc['username']) ?>
                        </td>
                        <td class="py-4 px-6">
                            <?php 
                                $roleClass = 'bg-slate-100 text-slate-600';
                                if ($acc['role_name'] === 'Super Admin') $roleClass = 'bg-purple-100 text-purple-700';
                                elseif ($acc['role_name'] === 'HR Admin') $roleClass = 'bg-pink-100 text-pink-700';
                                elseif ($acc['role_name'] === 'Head Barista') $roleClass = 'bg-amber-100 text-amber-700';
                                elseif ($acc['role_name'] === 'Employee' || $acc['role_name'] === 'Barista') $roleClass = 'bg-blue-100 text-blue-700';
                            ?>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?= $roleClass ?>">
                                <?= h($acc['role_name']) ?>
                            </span>
                        </td>
                        <td class="py-4 px-6">
                            <?php if ($acc['verified']): ?>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                    <i class="fa-solid fa-circle-check mr-1.5"></i> Active
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                    <i class="fa-solid fa-ban mr-1.5"></i> Disabled
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="py-4 px-6 text-right">
                            <button onclick='editAccount(<?= json_encode($acc) ?>)' class="text-slate-400 hover:text-blue-600 transition-colors" title="Manage Account">
                                <i class="fa-solid fa-gear"></i> Manage
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Modal -->
<div id="editAccountModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 overflow-hidden flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 class="text-lg font-bold text-slate-800">Manage Employee Account</h3>
            <button type="button" onclick="closeModal('editAccountModal')" class="text-slate-400 hover:text-slate-600 transition-colors">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto">
            <form id="accountForm" method="POST" action="account_management">
                <input type="hidden" name="action" value="edit_account">
                <input type="hidden" name="account_id" id="accountId" value="">
                
                <div class="mb-4 p-3 bg-slate-50 border border-slate-200 rounded-lg">
                    <div class="text-sm text-slate-500 mb-1">Employee Name</div>
                    <div class="font-bold text-slate-800" id="empNameDisplay"></div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                        <input type="text" name="username" id="username" required class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-primary focus:border-primary text-sm" placeholder="Username">
                    </div>
                    
                    <div>
                        <label for="role_id" class="block text-sm font-medium text-slate-700 mb-1">System Role</label>
                        <select name="role_id" id="role_id" required class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-primary focus:border-primary text-sm">
                            <option value="">Select Role...</option>
                            <?php foreach ($allRoles as $r): ?>
                                <?php if (!in_array($r['name'], ['Kiosk'])): ?>
                                    <option value="<?= $r['id'] ?>"><?= h($r['name']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Reset Password</label>
                        <input type="password" name="password" id="password" class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-primary focus:border-primary text-sm" placeholder="Enter new password...">
                        <p class="text-xs text-slate-500 mt-1">Leave blank to keep the current password.</p>
                    </div>

                    <div class="flex items-center mt-2 border-t pt-4">
                        <input type="checkbox" name="verified" id="verified" value="1" class="w-4 h-4 text-primary border-slate-300 rounded focus:ring-primary">
                        <label for="verified" class="ml-2 block text-sm font-medium text-slate-700">
                            Account is Active (Can Login)
                        </label>
                    </div>
                </div>
                
                <div class="mt-8 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('editAccountModal')" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 font-medium transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg font-medium transition-colors">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

function editAccount(acc) {
    document.getElementById('accountId').value = acc.id;
    document.getElementById('empNameDisplay').innerText = acc.name;
    document.getElementById('username').value = acc.username;
    document.getElementById('role_id').value = acc.role_id;
    document.getElementById('password').value = '';
    document.getElementById('verified').checked = acc.verified == 1;
    
    document.getElementById('editAccountModal').classList.remove('hidden');
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay') || event.target.id === 'editAccountModal') {
        event.target.classList.add('hidden');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
