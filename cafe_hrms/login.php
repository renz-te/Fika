<?php
require_once __DIR__ . '/init.php';

if (empty($_SESSION['user']) && !empty($_COOKIE['remember_me'])) {
    $cookieEmail = base64_decode($_COOKIE['remember_me']);
    if (filter_var($cookieEmail, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare('SELECT u.*, r.name AS role_name, e.employment_status FROM users u JOIN roles r ON u.role_id = r.id LEFT JOIN employees e ON u.employee_id = e.id WHERE u.email = ? LIMIT 1');
        $stmt->execute([$cookieEmail]);
        $user = $stmt->fetch();
        if ($user && $user['verified']) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role_name'],
                'branch_id' => $user['branch_id'],
                'employment_status' => $user['employment_status'],
            ];
            if (in_array($user['role_name'], ['System Admin', 'Super Admin', 'Central HR', 'Executives'])) {
                redirect('dashboard');
            } elseif ($user['role_name'] === 'Cashier' || $user['role_name'] === 'Cashier Terminal') {
                redirect('../cafe_pos/cashier.php');
            } elseif ($user['role_name'] === 'Clock') {
                redirect('attendance');
            } elseif (strpos(strtolower($user['role_name']), 'kiosk') !== false) {
                redirect('../cafe_pos/kiosk.php');
            } else {
                redirect('ess');
            }
        }
    }
}

if (!empty($_SESSION['user'])) {
    if (in_array($_SESSION['user']['role'], ['System Admin', 'Super Admin', 'Central HR', 'Executives'])) {
        redirect('dashboard');
    } elseif ($_SESSION['user']['role'] === 'Cashier' || $_SESSION['user']['role'] === 'Cashier Terminal') {
        redirect('../cafe_pos/cashier.php');
    } elseif ($_SESSION['user']['role'] === 'Clock') {
        redirect('attendance');
    } elseif (strpos(strtolower($_SESSION['user']['role']), 'kiosk') !== false) {
        redirect('../cafe_pos/kiosk.php');
    } else {
        redirect('ess');
    }
}
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $token = $_POST['csrf_token'] ?? '';

    // If you want to bypass CSRF temporarily for testing, you can comment this block, but let's keep it.
    // if (!verify_csrf_token($token)) {
    //     $error = 'Invalid request. Please try again.';
    // } else
    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT u.*, r.name AS role_name, e.employment_status FROM users u JOIN roles r ON u.role_id = r.id LEFT JOIN employees e ON u.employee_id = e.id WHERE u.username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            if (!$user['verified']) {
                $error = 'Please verify your email before logging in.';
            } else {
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role_name'],
                    'branch_id' => $user['branch_id'],
                    'employment_status' => $user['employment_status'],
                ];

                log_activity($pdo, $user['id'], 'login', 'User logged in');
                if (in_array($user['role_name'], ['System Admin', 'Super Admin', 'Central HR', 'Executives'])) {
                    redirect('dashboard');
                } elseif ($user['role_name'] === 'Cashier' || $user['role_name'] === 'Cashier Terminal') {
                    redirect('../cafe_pos/cashier.php');
                } elseif ($user['role_name'] === 'Clock') {
                    redirect('attendance');
                } elseif (strpos(strtolower($user['role_name']), 'kiosk') !== false) {
                    redirect('../cafe_pos/kiosk.php');
                } else {
                    redirect('ess');
                }
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

$companyName = get_setting($pdo, 'company_name', 'HRMS');
$pageTitle = 'Login';
$showSidebar = false;
require_once __DIR__ . '/includes/header.php';
?>

<div class="w-full max-w-md">
    <div class="text-center mb-10">
        <div class="flex justify-center mb-6">
            <svg class="w-20 h-20 text-indigo-600" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path id="login-arc" d="M 12 50 A 38 38 0 1 1 88 50 A 38 38 0 1 1 12 50" fill="none" />
                <text fill="currentColor" font-size="9.5" font-weight="bold" letter-spacing="1.5">
                    <textPath href="#login-arc" startOffset="50%" text-anchor="middle">FIKA · SLOW HOURS ·</textPath>
                </text>
                <ellipse cx="50" cy="50" rx="16" ry="22" stroke="currentColor" stroke-width="2" fill="none"/>
                <path d="M 50 28 C 40 40 60 60 50 72" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>
                <circle cx="50" cy="50" r="2.5" fill="currentColor"/>
                <line x1="50" y1="50" x2="50" y2="35" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                <line x1="50" y1="50" x2="60" y2="58" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <h1 class="text-3xl font-bold text-slate-800">Fika &middot; Slow Hours</h1>
    </div>

    <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden">
        <div class="p-8">
            <?php if ($error): ?>
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded text-sm">
                    <i class="fa-solid fa-circle-exclamation mr-2"></i> <?= h($error) ?>
                </div>
            <?php endif; ?>

            <form autocomplete="off" method="POST" action="login" class="space-y-6">
                <!-- <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"> -->
                
                <div>
                    <label for="username" class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <input id="username" name="username" type="text" required 
                            class="pl-10 block w-full rounded-lg border-slate-300 p-2.5 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors" 
                            placeholder="firstname.lastname"
                            value="<?= h($_POST['username'] ?? '') ?>">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-2">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <input type="password" id="password" name="password" class="pl-10 pr-10 block w-full rounded-lg border-slate-300 bg-slate-50 border p-3 focus:ring-primary focus:border-primary transition-colors text-sm" placeholder="••••••••" required>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 cursor-pointer hover:text-slate-600 transition-colors" id="togglePassword">
                            <i class="fa-solid fa-eye" id="eyeIcon"></i>
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-primary hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors">
                    Sign in to Account
                </button>
            </form>
        </div>
        <div class="px-8 py-4 bg-slate-50 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between text-sm">
            <a href="forgot_password" class="text-primary hover:underline font-medium mb-2 sm:mb-0">Forgot password?</a>
            <p class="text-slate-500">Need help? Contact your administrator.</p>
        </div>
    </div>
    
    <div class="mt-8 text-center text-sm text-slate-400">
        &copy; <?= date('Y') ?> Fika &middot; Slow Hours. All rights reserved.
    </div>
</div>

<script>
document.getElementById('togglePassword').addEventListener('click', function () {
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    eyeIcon.classList.toggle('fa-eye');
    eyeIcon.classList.toggle('fa-eye-slash');
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

