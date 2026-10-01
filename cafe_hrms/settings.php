<?php
require_once __DIR__ . '/init.php';
require_login();
$user = current_user();
$message = null;

// Only Super Admin can change global settings
$isAdmin = in_array($user['role'], ['Admin', 'Super Admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($token)) {
        if ($_POST['action'] === 'company' && $isAdmin) {
            set_setting($pdo, 'company_name', trim($_POST['company_name'] ?? 'Café HRMS'));
            set_setting($pdo, 'company_address', trim($_POST['company_address'] ?? ''));
            set_setting($pdo, 'app_url', trim($_POST['app_url'] ?? ''));
            $message = 'Company Settings updated successfully.';
        }
        if ($_POST['action'] === 'smtp' && $isAdmin) {
            set_setting($pdo, 'smtp_host', trim($_POST['smtp_host'] ?? ''));
            set_setting($pdo, 'smtp_port', trim($_POST['smtp_port'] ?? '587'));
            set_setting($pdo, 'smtp_user', trim($_POST['smtp_user'] ?? ''));
            set_setting($pdo, 'smtp_pass', trim($_POST['smtp_pass'] ?? ''));
            set_setting($pdo, 'smtp_from_email', trim($_POST['smtp_from_email'] ?? 'noreply@cafehrms.local'));
            set_setting($pdo, 'smtp_from_name', trim($_POST['smtp_from_name'] ?? 'Cafe HRMS'));
            $message = 'Email Settings updated successfully.';
        }
        if ($_POST['action'] === 'kiosk' && $isAdmin) {
            $newPin = trim($_POST['kiosk_pin'] ?? '1234');
            
            if (strlen($newPin) === 4 && is_numeric($newPin)) {
                set_setting($pdo, 'kiosk_pin', $newPin);
                $message = 'Kiosk PIN updated successfully.';
            } else {
                $message = 'Error: Kiosk PIN must be a 4-digit number.';
            }
        }
        if ($_POST['action'] === 'password') {
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$_SESSION['user']['id']]);
            $userRow = $stmt->fetch();
            if ($userRow && password_verify($current, $userRow['password']) && $new === $confirm) {
                $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user']['id']]);
                $message = 'Password changed successfully.';
            } else {
                $message = 'Password change failed. Check the values and try again.';
            }
        }
        if ($_POST['action'] === 'personal_info') {
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $newEmail = trim($_POST['email'] ?? '');
            
            // Validate email unique in users
            $check = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $check->execute([$newEmail, $_SESSION['user']['id']]);
            if ($check->fetchColumn()) {
                $message = "Error: That email is already taken by another user.";
            } else {
                // Update Users
                $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?')->execute([$first_name . ' ' . $last_name, $newEmail, $_SESSION['user']['id']]);
                
                // Update linked employee record if exists
                if (!empty($_POST['employee_id'])) {
                    $newPhone = trim($_POST['phone'] ?? '');
                    $newAddress = trim($_POST['address'] ?? '');
                    
                    // Tax fields
                    $tin = trim($_POST['tin'] ?? '');
                    $sss = trim($_POST['sss'] ?? '');
                    $philhealth = trim($_POST['philhealth'] ?? '');
                    $pagibig = trim($_POST['pagibig'] ?? '');
                    
                    $photoPath = null;
                    if (!empty($_FILES['photo']['tmp_name'])) {
                        $uploadDir = __DIR__ . '/uploads/photos/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                        $filename = uniqid('emp_', true) . '.' . $ext;
                        $destination = $uploadDir . $filename;
                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                            $photoPath = 'uploads/photos/' . $filename;
                            
                            // Store photo path in session so header avatar updates immediately
                            $_SESSION['user']['photo'] = $photoPath;
                        }
                    }
                    
                    if ($photoPath) {
                        $pdo->prepare('UPDATE employees SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, tin = ?, sss = ?, philhealth = ?, pagibig = ?, photo = ? WHERE id = ?')
                            ->execute([$first_name, $last_name, $newEmail, $newPhone, $newAddress, $tin, $sss, $philhealth, $pagibig, $photoPath, $_POST['employee_id']]);
                    } else {
                        $pdo->prepare('UPDATE employees SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, tin = ?, sss = ?, philhealth = ?, pagibig = ? WHERE id = ?')
                            ->execute([$first_name, $last_name, $newEmail, $newPhone, $newAddress, $tin, $sss, $philhealth, $pagibig, $_POST['employee_id']]);
                    }
                }
                
                // Update Session
                $_SESSION['user']['name'] = $first_name . ' ' . $last_name;
                $_SESSION['user']['email'] = $newEmail;
                
                $message = 'Personal Info updated successfully.';
            }
        }
    }
}

// Fetch linked employee
$linkedEmployee = null;
$empStmt = $pdo->prepare('SELECT *, CONCAT(first_name, \' \', last_name) AS full_name FROM employees WHERE email = ? LIMIT 1');
$empStmt->execute([$_SESSION['user']['email']]);
$linkedEmployee = $empStmt->fetch();

$companyName = get_setting($pdo, 'company_name', 'Café HRMS');
$companyAddress = get_setting($pdo, 'company_address', '');
$appUrl = get_setting($pdo, 'app_url', '');

$smtpHost = get_setting($pdo, 'smtp_host', '');
$smtpPort = get_setting($pdo, 'smtp_port', '587');
$smtpUser = get_setting($pdo, 'smtp_user', '');
$smtpPass = get_setting($pdo, 'smtp_pass', '');
$smtpFromEmail = get_setting($pdo, 'smtp_from_email', 'noreply@cafehrms.local');
$smtpFromName = get_setting($pdo, 'smtp_from_name', 'Cafe HRMS');

$pageTitle = 'Settings';
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <?php if ($message): ?>
        <div class="bg-primary/10 text-primary p-4 rounded-lg flex items-center shadow-sm">
            <i class="fa-solid fa-circle-info mr-3 text-lg"></i>
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
    <!-- Company Settings -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="bg-slate-50 border-b border-slate-200 p-4">
            <h3 class="font-bold text-slate-800"><i class="fa-solid fa-building text-primary mr-2"></i> Company Settings</h3>
        </div>
        <div class="p-6">
            <form autocomplete="off" method="POST" action="settings" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="company">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Company Name</label>
                        <input type="text" name="company_name" value="<?= h($companyName) ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">App URL</label>
                        <input type="text" name="app_url" value="<?= h($appUrl) ?>" placeholder="http://localhost/AGAIN" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary focus:border-primary">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Company Address</label>
                        <input type="text" name="company_address" value="<?= h($companyAddress) ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary focus:border-primary">
                    </div>
                </div>
                <div class="pt-2 text-right">
                    <button type="submit" class="bg-primary hover:bg-indigo-700 text-white px-5 py-2 rounded-lg font-medium shadow-sm transition">Save Company Settings</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Email & SMTP Settings -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="bg-slate-50 border-b border-slate-200 p-4">
            <h3 class="font-bold text-slate-800"><i class="fa-solid fa-envelope text-primary mr-2"></i> Email / SMTP Settings</h3>
            <p class="text-xs text-slate-500 mt-1">Configure your email provider (e.g. Gmail App Password, SendGrid, Brevo) to send Interview Invites and Password Resets.</p>
        </div>
        <div class="p-6">
            <form autocomplete="off" method="POST" action="settings" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="smtp">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">SMTP Host</label>
                        <input type="text" name="smtp_host" value="<?= h($smtpHost) ?>" placeholder="smtp.gmail.com" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">SMTP Port</label>
                        <input type="text" name="smtp_port" value="<?= h($smtpPort) ?>" placeholder="587" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">SMTP Username (Email)</label>
                        <input type="text" name="smtp_user" value="<?= h($smtpUser) ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">SMTP Password (App Password)</label>
                        <input type="password" name="smtp_pass" value="<?= h($smtpPass) ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">From Email Address</label>
                        <input type="email" name="smtp_from_email" value="<?= h($smtpFromEmail) ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">From Name</label>
                        <input type="text" name="smtp_from_name" value="<?= h($smtpFromName) ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary focus:border-primary">
                    </div>
                </div>
                <div class="pt-2 text-right">
                    <button type="submit" class="bg-primary hover:bg-indigo-700 text-white px-5 py-2 rounded-lg font-medium shadow-sm transition">Save Email Settings</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Kiosk Settings -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="bg-slate-50 border-b border-slate-200 p-4">
            <h3 class="font-bold text-slate-800"><i class="fa-solid fa-desktop text-primary mr-2"></i> Kiosk Terminal Settings</h3>
            <p class="text-xs text-slate-500 mt-1">Configure the secure PIN used to lock and unlock the employee timepunch terminal.</p>
        </div>
        <div class="p-6">
            <form autocomplete="off" method="POST" action="settings" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="kiosk">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Administrator Logout PIN</label>
                        <?php $kioskPin = get_setting($pdo, 'kiosk_pin', '1234'); ?>
                        <input type="text" name="kiosk_pin" value="<?= h($kioskPin) ?>" maxlength="4" pattern="\d{4}" title="4-digit PIN" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary focus:border-primary tracking-widest font-mono">
                        <p class="text-xs text-slate-500 mt-1">Must be exactly 4 digits. Used to close terminal.</p>
                    </div>
                </div>
                <div class="pt-2 text-right">
                    <button type="submit" class="bg-primary hover:bg-indigo-700 text-white px-5 py-2 rounded-lg font-medium shadow-sm transition">Save Kiosk Settings</button>
                </div>
            </form>
        </div>
    </div>

    <?php endif; ?>

    <!-- Personal Information -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
        <div class="bg-slate-50 border-b border-slate-200 p-4">
            <h3 class="font-bold text-slate-800"><i class="fa-solid fa-user text-primary mr-2"></i> Personal Information</h3>
        </div>
        <div class="p-6">
            <form autocomplete="off" method="POST" action="settings" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="personal_info">
                <?php if ($linkedEmployee): ?>
                    <input type="hidden" name="employee_id" value="<?= $linkedEmployee['id'] ?>">
                <?php endif; ?>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                        <input type="text" value="<?= h($_SESSION['user']['username'] ?? '') ?>" disabled class="w-full rounded-lg border-slate-300 p-2 border bg-slate-100 text-slate-500 cursor-not-allowed">
                        <p class="text-[10px] text-slate-400 mt-1">Username cannot be changed.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">ID Photo</label>
                        <input type="file" name="photo" accept="image/*" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">First Name</label>
                        <input type="text" name="first_name" required value="<?= h($linkedEmployee['first_name'] ?? '') ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Last Name</label>
                        <input type="text" name="last_name" required value="<?= h($linkedEmployee['last_name'] ?? '') ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email (Recovery)</label>
                        <input type="email" name="email" value="<?= h($_SESSION['user']['email']) ?>" required class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary focus:border-primary">
                    </div>
                    
                    <?php if ($linkedEmployee): ?>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Phone Number</label>
                            <input type="text" name="phone" value="<?= h($linkedEmployee['phone']) ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary focus:border-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Address</label>
                            <input type="text" name="address" value="<?= h($linkedEmployee['address']) ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary focus:border-primary">
                        </div>
                        
                        <div class="col-span-1 md:col-span-2 pt-4 border-t border-slate-200 mt-2">
                            <h4 class="font-semibold text-slate-700 mb-3"><i class="fa-solid fa-id-card text-primary mr-1"></i> Government IDs</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">TIN</label>
                                    <input type="text" name="tin" value="<?= h($linkedEmployee['tin'] ?? '') ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary focus:border-primary" placeholder="000-000-000-000">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">SSS Number</label>
                                    <input type="text" name="sss" value="<?= h($linkedEmployee['sss'] ?? '') ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary focus:border-primary" placeholder="00-0000000-0">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">PhilHealth Number</label>
                                    <input type="text" name="philhealth" value="<?= h($linkedEmployee['philhealth'] ?? '') ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary focus:border-primary" placeholder="00-000000000-0">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Pag-IBIG Number (MID/RTN)</label>
                                    <input type="text" name="pagibig" value="<?= h($linkedEmployee['pagibig'] ?? '') ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary focus:border-primary" placeholder="0000-0000-0000">
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="pt-2 text-right">
                    <button type="submit" class="bg-primary hover:bg-indigo-700 text-white px-5 py-2 rounded-lg font-medium shadow-sm transition">Update Personal Info</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Password -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
        <div class="bg-slate-50 border-b border-slate-200 p-4">
            <h3 class="font-bold text-slate-800"><i class="fa-solid fa-lock text-primary mr-2"></i> Change Password</h3>
        </div>
        <div class="p-6 flex flex-col md:flex-row gap-8">
            <div class="flex-1 space-y-4">
                <form autocomplete="off" method="POST" action="settings" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="password">
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Current Password</label>
                        <input type="password" name="current_password" required class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">New Password</label>
                        <input type="password" name="new_password" required class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Confirm New Password</label>
                        <input type="password" name="confirm_password" required class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary focus:border-primary">
                    </div>
                    
                    <div class="pt-2 text-right">
                        <button type="submit" class="bg-secondary hover:bg-slate-700 text-white px-5 py-2 rounded-lg font-medium shadow-sm transition">Change Password</button>
                    </div>
                </form>
            </div>
            <div class="w-full md:w-1/3 bg-slate-50 rounded-lg border border-slate-200 p-4 flex flex-col items-center justify-center text-center">
                <div class="w-16 h-16 bg-white rounded-full border border-slate-200 shadow-sm flex items-center justify-center mb-3">
                    <i class="fa-solid fa-shield-halved text-2xl text-slate-400"></i>
                </div>
                <h4 class="font-bold text-slate-700 text-sm mb-1">Password Security</h4>
                <p class="text-xs text-slate-500">Ensure your account is using a long, random password to stay secure.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

