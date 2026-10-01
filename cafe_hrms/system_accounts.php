<?php
require_once __DIR__ . '/init.php';
require_login();
$user = current_user();

if ($user['role'] !== 'Super Admin' && $user['role'] !== 'HR Admin') {
    redirect('dashboard');
}

$error = '';
$success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_account') {
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role_id = $_POST['role_id'] ?? '';
        $branch_id = !empty($_POST['branch_id']) ? $_POST['branch_id'] : null;
        
        if (empty($name) || empty($username) || empty($password) || empty($role_id) || empty($branch_id)) {
            $error = "All fields (including Assigned Branch) are required.";
        } else {
            // check username
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = "Username is already taken.";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, username, password, role_id, email, verified, branch_id) VALUES (?, ?, ?, ?, ?, 1, ?)");
                // generate a fake email since email must be unique and is required
                $fakeEmail = strtolower($username) . "_" . time() . "@system.local";
                $stmt->execute([$name, $username, $hashed, $role_id, $fakeEmail, $branch_id]);
                $success = "System account created successfully.";
            }
        }
    } elseif ($action === 'edit_account') {
        $id = $_POST['account_id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role_id = $_POST['role_id'] ?? '';
        $branch_id = !empty($_POST['branch_id']) ? $_POST['branch_id'] : null;
        
        if (empty($name) || empty($username) || empty($role_id) || empty($id) || empty($branch_id)) {
            $error = "Name, Username, Role and Branch are required.";
        } else {
            // check username
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $id]);
            if ($stmt->fetch()) {
                $error = "Username is already taken by another account.";
            } else {
                if (!empty($password)) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, role_id = ?, password = ?, branch_id = ? WHERE id = ?");
                    $stmt->execute([$name, $username, $role_id, $hashed, $branch_id, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, role_id = ?, branch_id = ? WHERE id = ?");
                    $stmt->execute([$name, $username, $role_id, $branch_id, $id]);
                }
                $success = "System account updated successfully.";
            }
        }
    } elseif ($action === 'delete_account') {
        $id = $_POST['account_id'] ?? '';
        if ($id == $user['id']) {
            $error = "You cannot delete your own account.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND employee_id IS NULL");
            $stmt->execute([$id]);
            $success = "System account deleted.";
        }
    } elseif ($action === 'add_branch') {
        $branch_name = trim($_POST['branch_name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '');
        if (empty($branch_name)) {
            $error = "Branch Name is required.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO branches (name, address, contact_number, status) VALUES (?, ?, ?, 'Active')");
            $stmt->execute([$branch_name, $address, $contact_number]);
            $success = "Branch added successfully.";
        }
    } elseif ($action === 'edit_branch') {
        $branch_id = $_POST['branch_id'] ?? '';
        $branch_name = trim($_POST['branch_name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '');
        if (empty($branch_name) || empty($branch_id)) {
            $error = "Branch Name is required.";
        } else {
            $stmt = $pdo->prepare("UPDATE branches SET name = ?, address = ?, contact_number = ? WHERE id = ?");
            $stmt->execute([$branch_name, $address, $contact_number, $branch_id]);
            $success = "Branch updated successfully.";
        }
    } elseif ($action === 'toggle_status') {
        $branch_id = $_POST['branch_id'] ?? '';
        if ($branch_id) {
            $stmt = $pdo->prepare("UPDATE branches SET status = IF(status='Active', 'Inactive', 'Active') WHERE id = ?");
            $stmt->execute([$branch_id]);
            $success = "Branch status updated.";
        }
    }
}

$tab = $_GET['tab'] ?? 'accounts';

// Fetch Roles that are non-employee specific if possible, but let's just fetch all roles and filter in UI or let Admin choose
$stmt = $pdo->query("SELECT * FROM roles ORDER BY name");
$allRoles = $stmt->fetchAll();

// Fetch System Accounts (employee_id IS NULL)
$stmt = $pdo->query("SELECT u.id, u.name, u.username, u.role_id, u.branch_id, r.name as role_name, b.name as branch_name 
                     FROM users u 
                     JOIN roles r ON u.role_id = r.id 
                     LEFT JOIN branches b ON u.branch_id = b.id
                     WHERE u.employee_id IS NULL 
                     ORDER BY u.name");
$accounts = $stmt->fetchAll();

// Fetch branches for the data grid
$branches_query = $pdo->query("SELECT * FROM branches ORDER BY name ASC");
$branches_list = $branches_query->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'System Settings';
require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-6 border-b border-slate-200">
    <div class="flex space-x-6">
        <a href="?tab=accounts" class="py-3 px-1 border-b-2 font-medium text-sm transition-colors <?= $tab === 'accounts' ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' ?>">
            <i class="fa-solid fa-users-gear mr-2"></i> System Accounts
        </a>
        <a href="?tab=branches" class="py-3 px-1 border-b-2 font-medium text-sm transition-colors <?= $tab === 'branches' ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' ?>">
            <i class="fa-solid fa-store mr-2"></i> Branches
        </a>
    </div>
</div>

<?php if ($tab === 'accounts'): ?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h2 class="text-xl font-bold text-slate-800">System Accounts</h2>
        <p class="text-slate-500">Manage terminal and kiosk accounts for POS and HRMS operations.</p>
    </div>
    <button onclick="openModal('addAccountModal')" class="bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg font-medium transition-colors shadow-sm flex items-center">
        <i class="fa-solid fa-plus mr-2"></i> Add Account
    </button>
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
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider">Name / Identifier</th>
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider">Username</th>
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider">Role</th>
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider">Assigned Branch</th>
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($accounts)): ?>
                <tr>
                    <td colspan="4" class="py-10 text-center text-slate-500">
                        <div class="flex flex-col items-center justify-center">
                            <i class="fa-solid fa-server text-4xl mb-3 text-slate-300"></i>
                            <p>No system accounts found.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach($accounts as $acc): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="py-4 px-6">
                            <div class="flex items-center">
                                <div class="h-10 w-10 rounded-full bg-slate-200 flex items-center justify-center text-slate-600 font-bold mr-3">
                                    <i class="fa-solid <?= $acc['role_name'] === 'Super Admin' ? 'fa-shield-halved' : ($acc['role_name'] === 'Cashier' ? 'fa-cash-register' : 'fa-tablet-screen-button') ?>"></i>
                                </div>
                                <div>
                                    <div class="font-medium text-slate-800"><?= h($acc['name']) ?></div>
                                    <div class="text-xs text-slate-500">ID: <?= $acc['id'] ?></div>
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
                                elseif ($acc['role_name'] === 'Cashier') $roleClass = 'bg-blue-100 text-blue-700';
                                elseif ($acc['role_name'] === 'Kiosk') $roleClass = 'bg-orange-100 text-orange-700';
                            ?>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?= $roleClass ?>">
                                <?= h($acc['role_name']) ?>
                            </span>
                        </td>
                        <td class="py-4 px-6 text-sm text-slate-600">
                            <?= h($acc['branch_name'] ?? 'Global/Unassigned') ?>
                        </td>
                        <td class="py-4 px-6 text-right">
                            <button onclick='editAccount(<?= json_encode($acc) ?>)' class="text-slate-400 hover:text-blue-600 transition-colors mr-3" title="Edit">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <?php if ($acc['id'] != $user['id']): ?>
                            <button onclick="deleteAccount(<?= $acc['id'] ?>)" class="text-slate-400 hover:text-red-600 transition-colors" title="Delete">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif ($tab === 'branches'): ?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h2 class="text-xl font-bold text-slate-800">Branch Directory</h2>
        <p class="text-slate-500">Manage physical cafe locations and statuses.</p>
    </div>
    <button onclick="openModal('addBranchModal')" class="bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg font-medium transition-colors shadow-sm flex items-center">
        <i class="fa-solid fa-plus mr-2"></i> Add Branch
    </button>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-white border-b border-slate-200">
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider">Branch Name</th>
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider">Address</th>
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider">Contact</th>
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if(empty($branches_list)): ?>
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">No branches found.</td></tr>
                <?php else: ?>
                    <?php foreach ($branches_list as $branch): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="py-4 px-6 text-sm text-slate-800 font-bold"><?= h($branch['name']) ?></td>
                        <td class="py-4 px-6 text-sm text-slate-600 truncate max-w-xs"><?= h($branch['address'] ?? 'N/A') ?></td>
                        <td class="py-4 px-6 text-sm text-slate-600"><?= h($branch['contact_number'] ?? 'N/A') ?></td>
                        <td class="py-4 px-6">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?= $branch['status'] === 'Active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                <?= h($branch['status']) ?>
                            </span>
                        </td>
                        <td class="py-4 px-6 text-right">
                            <button onclick='editBranch(<?= json_encode($branch) ?>)' class="text-slate-400 hover:text-blue-600 transition-colors mr-3" title="Edit">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <form method="POST" class="inline" onsubmit="return confirm('Toggle status for this branch?');">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="branch_id" value="<?= $branch['id'] ?>">
                                <button type="submit" class="text-slate-400 hover:text-slate-600 transition-colors" title="Toggle Status">
                                    <i class="fa-solid fa-power-off"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Add/Edit Modal -->
<div id="addAccountModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 overflow-hidden flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 class="text-lg font-bold text-slate-800" id="modalTitle">Add System Account</h3>
            <button type="button" onclick="closeModal('addAccountModal')" class="text-slate-400 hover:text-slate-600 transition-colors">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto">
            <form id="accountForm" method="POST" action="system_accounts">
                <input type="hidden" name="action" id="formAction" value="add_account">
                <input type="hidden" name="account_id" id="accountId" value="">
                
                <div class="space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Account Name / Identifier</label>
                        <input type="text" name="name" id="name" required class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-primary focus:border-primary text-sm" placeholder="e.g. Cashier Terminal 2">
                    </div>
                    
                    <div>
                        <label for="username" class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                        <input type="text" name="username" id="username" required class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-primary focus:border-primary text-sm" placeholder="e.g. cashier_term2">
                    </div>
                    
                    <div>
                        <label for="role_id" class="block text-sm font-medium text-slate-700 mb-1">System Role</label>
                        <select name="role_id" id="role_id" required class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-primary focus:border-primary text-sm">
                            <option value="">Select Role...</option>
                            <?php foreach ($allRoles as $r): ?>
                                <!-- Highlight Kiosk and Cashier -->
                                <?php if (in_array($r['name'], ['Kiosk', 'Cashier', 'Super Admin'])): ?>
                                    <option value="<?= $r['id'] ?>"><?= h($r['name']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-slate-500 mt-1">If Kiosk, username must contain 'tablet' to open POS tablet kiosk, otherwise it opens HRMS clock-in kiosk.</p>
                    </div>
                    
                    <div>
                        <label for="branch_id" class="block text-sm font-medium text-slate-700 mb-1">Assigned Branch (Required)</label>
                        <select name="branch_id" id="branch_id" required class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-primary focus:border-primary text-sm">
                            <option value="">Select Branch...</option>
                            <?php foreach ($branches_list as $b): ?>
                                <?php if ($b['status'] === 'Active'): ?>
                                    <option value="<?= $b['id'] ?>"><?= h($b['name']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-slate-500 mt-1">Locks this terminal to specific location data.</p>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                        <input type="password" name="password" id="password" class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-primary focus:border-primary text-sm" placeholder="Enter password...">
                        <p class="text-xs text-slate-500 mt-1" id="pwdHelp">Leave blank when editing to keep current password.</p>
                    </div>
                </div>
                
                <div class="mt-8 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('addAccountModal')" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 font-medium transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg font-medium transition-colors">Save Account</button>
                </div>
            </form>
            </form>
        </div>
    </div>
</div>

<!-- Add/Edit Branch Modal -->
<div id="addBranchModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 overflow-hidden flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 class="text-lg font-bold text-slate-800" id="branchModalTitle">Add Branch</h3>
            <button type="button" onclick="closeModal('addBranchModal')" class="text-slate-400 hover:text-slate-600 transition-colors">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto">
            <form id="branchForm" method="POST" action="system_accounts">
                <input type="hidden" name="action" id="branchActionField" value="add_branch">
                <input type="hidden" name="branch_id" id="branchIdField" value="">
                
                <div class="space-y-4">
                    <div>
                        <label for="branchNameField" class="block text-sm font-medium text-slate-700 mb-1">Branch Name *</label>
                        <input type="text" name="branch_name" id="branchNameField" required class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-primary focus:border-primary text-sm" placeholder="e.g. Uptown Cafe">
                    </div>
                    
                    <div>
                        <label for="branchAddressField" class="block text-sm font-medium text-slate-700 mb-1">Address</label>
                        <textarea name="address" id="branchAddressField" rows="2" class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-primary focus:border-primary text-sm" placeholder="Full branch address..."></textarea>
                    </div>
                    
                    <div>
                        <label for="branchContactField" class="block text-sm font-medium text-slate-700 mb-1">Contact Number</label>
                        <input type="text" name="contact_number" id="branchContactField" class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-primary focus:border-primary text-sm" placeholder="Phone or mobile number">
                    </div>
                </div>
                
                <div class="mt-8 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('addBranchModal')" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 font-medium transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg font-medium transition-colors">Save Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" action="system_accounts" style="display: none;">
    <input type="hidden" name="action" value="delete_account">
    <input type="hidden" name="account_id" id="deleteAccountId" value="">
</form>

<script>


function editAccount(acc) {
    document.getElementById('addAccountModal').classList.remove('hidden');
    document.getElementById('formAction').value = 'edit_account';
    document.getElementById('accountId').value = acc.id;
    
    document.getElementById('name').value = acc.name;
    document.getElementById('username').value = acc.username;
    document.getElementById('role_id').value = acc.role_id;
    document.getElementById('branch_id').value = acc.branch_id || '';
    document.getElementById('password').value = '';
    
    document.getElementById('modalTitle').textContent = 'Edit System Account';
    document.getElementById('password').required = false;
    document.getElementById('pwdHelp').style.display = 'block';
}

function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
    // reset forms
    if(id === 'addAccountModal') {
        document.getElementById('modalTitle').innerText = 'Add System Account';
        document.getElementById('formAction').value = 'add_account';
        document.getElementById('accountId').value = '';
        document.getElementById('accountForm').reset();
        document.getElementById('password').required = true;
        document.getElementById('pwdHelp').style.display = 'none';
    }
    if(id === 'addBranchModal') {
        document.getElementById('branchModalTitle').innerText = 'Add Branch';
        document.getElementById('branchActionField').value = 'add_branch';
        document.getElementById('branchIdField').value = '';
        document.getElementById('branchForm').reset();
    }
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

function editBranch(branch) {
    document.getElementById('branchModalTitle').innerText = 'Edit Branch';
    document.getElementById('branchActionField').value = 'edit_branch';
    document.getElementById('branchIdField').value = branch.id;
    document.getElementById('branchNameField').value = branch.name;
    document.getElementById('branchAddressField').value = branch.address || '';
    document.getElementById('branchContactField').value = branch.contact_number || '';
    openModal('addBranchModal');
}

function deleteAccount(id) {
    if (confirm('Are you sure you want to delete this system account?')) {
        document.getElementById('deleteAccountId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

// Close modals when clicking outside the box
window.addEventListener('click', (e) => {
    const accountModal = document.getElementById('addAccountModal');
    const branchModal = document.getElementById('addBranchModal');
    
    if (e.target === accountModal) {
        closeModal('addAccountModal');
    }
    if (e.target === branchModal) {
        closeModal('addBranchModal');
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
