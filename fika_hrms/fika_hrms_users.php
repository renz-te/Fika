<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/core/Output.php';

Auth::requireLogin();
Rbac::require_permission('users.view');

global $pdo;

$error = null;
$success = null;

// Handle account updates if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_account') {
    if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $accountId = (int)($_POST['account_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $newPassword = $_POST['password'] ?? '';
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($accountId <= 0 || $username === '' || $roleId <= 0) {
            $error = 'All required fields must be filled.';
        } else {
            // Check if username already exists for another account
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ? AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$username, $accountId]);
            if ($stmt->fetch()) {
                $error = 'Username is already taken by another user account.';
            } else {
                $currentUser = Auth::user();
                if ($accountId === (int)$currentUser['id'] && $isActive === 0) {
                    $error = 'You cannot deactivate your own logged-in account.';
                    $isActive = 1;
                }

                if (!$error) {
                    if (!empty($newPassword)) {
                        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                        $uStmt = $pdo->prepare("UPDATE users SET username = ?, role_id = ?, password = ?, deleted_at = ? WHERE id = ?");
                        $uStmt->execute([$username, $roleId, $hash, $isActive ? null : date('Y-m-d H:i:s'), $accountId]);
                    } else {
                        $uStmt = $pdo->prepare("UPDATE users SET username = ?, role_id = ?, deleted_at = ? WHERE id = ?");
                        $uStmt->execute([$username, $roleId, $isActive ? null : date('Y-m-d H:i:s'), $accountId]);
                    }
                    $success = 'User account updated successfully.';
                }
            }
        }
    }
}

// Fetch all system roles
$rolesStmt = $pdo->query("SELECT id, name, description FROM roles ORDER BY id ASC");
$allRoles = $rolesStmt ? $rolesStmt->fetchAll() : [];

// Fetch user accounts joined with employee details
$accountsStmt = $pdo->query("
    SELECT u.id, u.username, u.role_id, u.employee_id, u.branch_id, u.deleted_at,
           r.name as role_name,
           e.employee_code, e.first_name, e.last_name, e.position as emp_position, e.status as emp_status,
           b.name as branch_name
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN employees e ON u.employee_id = e.id
    LEFT JOIN branches b ON u.branch_id = b.id
    ORDER BY u.id ASC
");
$accounts = $accountsStmt ? $accountsStmt->fetchAll() : [];
$csrfToken = Csrf::getToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Management &middot; Fika HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex">

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900">User Accounts Directory</h1>
                <p class="text-sm text-slate-500">Manage user login credentials, system access roles, and job positions.</p>
            </div>
        </div>

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

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500 uppercase tracking-wider">
                            <th class="py-4 px-6">Employee</th>
                            <th class="py-4 px-6">Username</th>
                            <th class="py-4 px-6">System Access Role</th>
                            <th class="py-4 px-6">Branch</th>
                            <th class="py-4 px-6">Access Status</th>
                            <th class="py-4 px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php if (empty($accounts)): ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-400">
                                    <i class="fa-solid fa-users text-4xl mb-3 block text-slate-300"></i>
                                    No accounts registered.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($accounts as $acc): 
                                $isAccActive = empty($acc['deleted_at']);
                                $fullName = trim(($acc['first_name'] ?? '') . ' ' . ($acc['last_name'] ?? ''));
                                if ($fullName === '') {
                                    $fullName = 'Unlinked System User';
                                }
                                $pos = $acc['emp_position'] ?? 'No Position Assigned';
                            ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-700 font-bold flex items-center justify-center text-xs flex-shrink-0 border border-amber-500/20">
                                            <?= strtoupper(substr($fullName, 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-900"><?= e($fullName) ?></div>
                                            <div class="text-xs text-slate-500 flex items-center gap-1.5">
                                                <span><?= e($pos) ?></span>
                                                <?php if (!empty($acc['employee_code'])): ?>
                                                    &bull; <span class="font-mono text-slate-400"><?= e($acc['employee_code']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6 font-mono font-medium text-slate-700">
                                    <?= e($acc['username']) ?>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                        <?= e($acc['role_name'] ?? 'Role #' . $acc['role_id']) ?>
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-slate-600 text-xs font-medium">
                                    <?= e($acc['branch_name'] ?? 'Main Branch') ?>
                                </td>
                                <td class="py-4 px-6">
                                    <?php if ($isAccActive): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <i class="fa-solid fa-circle text-[6px] mr-1.5 animate-pulse"></i> Active
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-500 border border-slate-200">
                                            Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-6 text-right">
                                    <button type="button" 
                                            onclick='openManageModal(<?= json_encode([
                                                "id" => $acc["id"],
                                                "name" => $fullName,
                                                "username" => $acc["username"],
                                                "role_id" => $acc["role_id"],
                                                "position" => $pos,
                                                "is_active" => $isAccActive
                                            ]) ?>)'
                                            class="bg-white border border-slate-200 text-slate-700 hover:text-amber-700 hover:border-amber-300 px-3 py-1.5 rounded-lg text-xs font-semibold shadow-sm transition">
                                        <i class="fa-solid fa-pen-to-square mr-1"></i> Manage
                                    </button>
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

    <!-- Manage Account Modal -->
    <div id="manageModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-lg w-full overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                <h3 class="font-bold text-slate-900 text-lg">Manage Account</h3>
                <button type="button" onclick="closeManageModal()" class="text-slate-400 hover:text-slate-600 transition">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <form method="POST" action="fika_hrms_users.php" class="p-6 space-y-4">
                <input type="hidden" name="action" value="edit_account">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="account_id" id="modalAccountId" value="">

                <!-- Read-only Employee Dossier Info -->
                <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Employee Name</div>
                        <div class="font-bold text-slate-900 text-base" id="modalEmpNameDisplay"></div>
                    </div>
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Employee Position</div>
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-50 text-amber-800 rounded-lg text-xs font-bold border border-amber-200 mt-0.5" id="modalEmpPosDisplay">
                            <!-- Populated via JS -->
                        </div>
                    </div>
                </div>

                <!-- Username -->
                <div>
                    <label for="modalUsername" class="block text-xs font-bold uppercase text-slate-600 mb-1">Username</label>
                    <input type="text" name="username" id="modalUsername" required class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>

                <!-- System Access Role -->
                <div>
                    <label for="modalRoleId" class="block text-xs font-bold uppercase text-slate-600 mb-1">System Access Role</label>
                    <select name="role_id" id="modalRoleId" required class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        <option value="">Select Access Role...</option>
                        <?php foreach ($allRoles as $r): ?>
                            <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?> &mdash; <?= e($r['description'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">Controls login authorization, portal routes, and action permissions.</p>
                </div>

                <!-- Reset Password -->
                <div>
                    <label for="modalPassword" class="block text-xs font-bold uppercase text-slate-600 mb-1">Reset Password</label>
                    <input type="password" name="password" id="modalPassword" placeholder="Leave blank to keep current password..." class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>

                <!-- Active Status -->
                <div class="flex items-center pt-2">
                    <input type="checkbox" name="is_active" id="modalIsActive" value="1" class="w-4 h-4 text-amber-600 border-slate-300 rounded focus:ring-amber-500">
                    <label for="modalIsActive" class="ml-2.5 text-sm font-semibold text-slate-700">Account is Active (Can Login to System)</label>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" onclick="closeManageModal()" class="px-4 py-2 border border-slate-300 rounded-xl text-slate-700 hover:bg-slate-50 text-sm font-medium transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-sm font-bold shadow-sm transition">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openManageModal(acc) {
        document.getElementById('modalAccountId').value = acc.id;
        document.getElementById('modalEmpNameDisplay').textContent = acc.name;
        document.getElementById('modalEmpPosDisplay').textContent = acc.position;
        document.getElementById('modalUsername').value = acc.username;
        document.getElementById('modalRoleId').value = acc.role_id;
        document.getElementById('modalPassword').value = '';
        document.getElementById('modalIsActive').checked = acc.is_active;

        document.getElementById('manageModal').classList.remove('hidden');
    }

    function closeManageModal() {
        document.getElementById('manageModal').classList.add('hidden');
    }
    </script>
</body>
</html>
