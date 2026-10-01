<?php
require_once __DIR__ . '/init.php';
$token = $_GET['token'] ?? null;
$message = null;
$error = null;
$valid = false;

if ($token) {
    $stmt = $pdo->prepare('SELECT pr.user_id, u.email, u.name FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE pr.token = ? ORDER BY pr.created_at DESC LIMIT 1');
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if ($reset) {
        $valid = true;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = $_POST['password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            
            if (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters.';
            } elseif ($password && $password === $confirm) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                $stmt->execute([$hash, $reset['user_id']]);
                
                // Invalidate all tokens for this user
                $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$reset['user_id']]);
                
                $message = 'Your password has been successfully reset.';
                $valid = false;
            } else {
                $error = 'Passwords do not match.';
            }
        }
    } else {
        $error = 'Invalid or expired reset token. Please request a new one.';
    }
} else {
    $error = 'Reset token is missing from the URL.';
}

$companyName = get_setting($pdo, 'company_name', 'HRMS');
$pageTitle = 'Reset Password';
$showSidebar = false;
require_once __DIR__ . '/includes/header.php';
?>

<div class="w-full max-w-md">
    <div class="text-center mb-10">
        <div class="inline-flex items-center justify-center h-16 w-16 rounded-2xl bg-indigo-100 text-indigo-600 text-3xl mb-4 shadow-lg shadow-indigo-100/50 border border-indigo-200">
            <i class="fa-solid fa-lock"></i>
        </div>
        <h1 class="text-3xl font-bold text-slate-800">Reset Password</h1>
        <p class="text-slate-500 mt-2">Create a new, strong password.</p>
    </div>

    <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden">
        <div class="p-8">
            <?php if ($error): ?>
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded text-sm flex items-start">
                    <i class="fa-solid fa-circle-exclamation mt-0.5 mr-2"></i>
                    <div><?= h($error) ?></div>
                </div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="mb-6 bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded text-sm flex items-start">
                    <i class="fa-solid fa-circle-check mt-0.5 mr-2"></i>
                    <div><?= $message ?></div>
                </div>
                
                <a href="login" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                    Sign In Now
                </a>
            <?php elseif ($valid): ?>
                <form autocomplete="off" method="POST" action="reset_password?token=<?= h($token) ?>" class="space-y-5">
                    <div>
                        <div class="text-sm font-medium text-slate-700 mb-1">Account</div>
                        <div class="text-slate-500 text-sm bg-slate-50 p-2 rounded border border-slate-200">
                            <?= h($reset['name']) ?> (<?= h($reset['email']) ?>)
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700 mb-1">New Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-solid fa-key"></i>
                            </div>
                            <input id="password" name="password" type="password" required 
                                class="pl-10 block w-full rounded-lg border-slate-300 p-2.5 bg-slate-50 border focus:ring-indigo-500 focus:border-indigo-500 transition-colors" 
                                placeholder="Min 6 characters">
                        </div>
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-slate-700 mb-1">Confirm Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-solid fa-check-double"></i>
                            </div>
                            <input id="confirm_password" name="confirm_password" type="password" required 
                                class="pl-10 block w-full rounded-lg border-slate-300 p-2.5 bg-slate-50 border focus:ring-indigo-500 focus:border-indigo-500 transition-colors" 
                                placeholder="Repeat password">
                        </div>
                    </div>

                    <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors mt-6">
                        Update Password
                    </button>
                </form>
            <?php endif; ?>
            
            <?php if (!$valid && !$message): ?>
                <div class="mt-6 text-center">
                    <a href="forgot_password" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 hover:underline">
                        Request a new reset link
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

