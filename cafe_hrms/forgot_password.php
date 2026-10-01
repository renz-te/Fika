<?php
require_once __DIR__ . '/init.php';
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $token = bin2hex(random_bytes(20));
        $stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            $stmt = $pdo->prepare('INSERT INTO password_resets (user_id, token, created_at) VALUES (?, ?, NOW())');
            $stmt->execute([$user['id'], $token]);
            $resetLink = sprintf('%s/reset_password.php?token=%s', rtrim(get_setting($pdo, 'app_url', 'http://localhost/again'), '/'), $token);
            $body = "
                <div style='font-family: sans-serif; color: #333;'>
                    <h2>Password Reset Request</h2>
                    <p>Hello {$user['name']},</p>
                    <p>We received a request to reset your password. If you didn't make this request, you can safely ignore this email.</p>
                    <div style='margin: 30px 0;'>
                        <a href='" . h($resetLink) . "' style='background-color: #4f46e5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;'>Reset Password</a>
                    </div>
                    <p>Or copy and paste this link into your browser:</p>
                    <p><a href='" . h($resetLink) . "'>" . h($resetLink) . "</a></p>
                </div>
            ";
            
            if (!send_email($email, 'Password Reset Request', $body)) {
                // For local dev, display the link if email fails
                $message = 'Password reset link generated. <br><a href="' . h($resetLink) . '" class="text-blue-600 underline">Click here to reset your password</a>.';
            } else {
                $message = 'A password reset email was sent to your address.';
            }
        } else {
            // To prevent email enumeration, still say we sent it
            $message = 'If that email exists in our system, a password reset link has been sent.';
        }
    }
}

$companyName = get_setting($pdo, 'company_name', 'HRMS');
$pageTitle = 'Forgot Password';
$showSidebar = false;
require_once __DIR__ . '/includes/header.php';
?>

<div class="w-full max-w-md">
    <div class="text-center mb-10">
        <div class="inline-flex items-center justify-center h-16 w-16 rounded-2xl bg-indigo-100 text-indigo-600 text-3xl mb-4 shadow-lg shadow-indigo-100/50 border border-indigo-200">
            <i class="fa-solid fa-key"></i>
        </div>
        <h1 class="text-3xl font-bold text-slate-800">Forgot Password?</h1>
        <p class="text-slate-500 mt-2">Enter your email and we'll send you a reset link.</p>
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
            <?php else: ?>
                <form autocomplete="off" method="POST" action="forgot_password" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email Address</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-solid fa-envelope"></i>
                            </div>
                            <input id="email" name="email" type="email" required 
                                class="pl-10 block w-full rounded-lg border-slate-300 p-2.5 bg-slate-50 border focus:ring-indigo-500 focus:border-indigo-500 transition-colors" 
                                placeholder="you@example.com"
                                value="<?= h($_POST['email'] ?? '') ?>">
                        </div>
                    </div>

                    <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                        Send Reset Link
                    </button>
                </form>
            <?php endif; ?>
            
            <div class="mt-6 text-center">
                <a href="login" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 hover:underline">
                    <i class="fa-solid fa-arrow-left mr-1"></i> Back to login
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

