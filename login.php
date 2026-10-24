<?php
require_once __DIR__ . '/app/bootstrap.php';

if (isset($_GET['logout'])) {
    Auth::logout();
    header("Location: login.php");
    exit;
}

global $pdo;

function get_role_redirect_url(int $roleId, PDO $pdo, ?int $employeeId = null): string {
    return Auth::getRedirectUrl(['role_id' => $roleId, 'employee_id' => $employeeId]);
}

// If already logged in, redirect based on role
if (Auth::check()) {
    $currentUser = Auth::user();
    $target = Auth::getRedirectUrl($currentUser);
    header("Location: {$target}");
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both your username and password.';
    } else {
        $success = Auth::attemptLogin($username, $password);
        if ($success) {
            $currentUser = Auth::user();
            $target = Auth::getRedirectUrl($currentUser);
            header("Location: {$target}");
            exit;
        } else {
            $error = 'Invalid credentials or account temporarily locked. Please check and try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login &middot; Fika Slow Hours</title>
    <link rel="icon" type="image/svg+xml" href="public/assets/logo.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        espresso: {
                            800: '#4a2e1b',
                            900: '#331d10',
                            950: '#1f1008'
                        },
                        amber: {
                            500: '#f59e0b',
                            600: '#d97706',
                            700: '#b45309'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-stone-900 text-stone-100 min-h-screen flex flex-col justify-between items-center px-4 py-8 antialiased selection:bg-amber-500 selection:text-white">

    <!-- Top Simple Header -->
    <div class="w-full max-w-md flex justify-between items-center mb-6">
        <a href="index.php" class="text-xs text-stone-400 hover:text-amber-400 transition-colors flex items-center gap-1.5 font-medium">
            <i class="fa-solid fa-arrow-left text-[10px]"></i>
            <span>Back to Main Page</span>
        </a>
        <a href="careers.php" class="text-xs text-amber-500 hover:text-amber-400 font-semibold transition-colors">
            Looking for Careers?
        </a>
    </div>

    <!-- Centered Login Card -->
    <div class="w-full max-w-md">
        <!-- Logo & Title Header -->
        <div class="text-center mb-8">
            <div class="inline-block relative group">
                <div class="absolute -inset-1 rounded-full bg-amber-500/20 blur group-hover:bg-amber-500/30 transition duration-300"></div>
                <img src="public/assets/logo.svg" alt="Fika Logo" class="relative w-28 h-28 mx-auto object-contain drop-shadow-lg">
            </div>
            <h1 class="text-2xl font-black text-white mt-4 tracking-tight">Staff Backoffice Portal</h1>
            <p class="text-stone-400 text-xs mt-1">Authorized personnel only &bull; Enter credentials to access your console</p>
        </div>

        <!-- Card Container -->
        <div class="bg-stone-950 border border-stone-800 rounded-2xl shadow-2xl overflow-hidden">
            <div class="p-8">
                <?php if ($error): ?>
                    <div class="mb-6 p-4 rounded-xl bg-red-950/60 border border-red-800/80 text-red-300 text-xs flex items-start gap-3">
                        <i class="fa-solid fa-circle-exclamation text-red-400 mt-0.5 text-sm"></i>
                        <div class="font-medium"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" class="space-y-5" autocomplete="off">
                    <!-- Username Field -->
                    <div>
                        <label for="username" class="block text-xs font-bold uppercase tracking-wider text-stone-300 mb-1.5">
                            Username or Staff ID
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-stone-500">
                                <i class="fa-solid fa-user text-xs"></i>
                            </div>
                            <input type="text" id="username" name="username" required
                                value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="e.g. admin or juan.delacruz"
                                class="w-full pl-10 pr-4 py-3 bg-stone-900 border border-stone-800 rounded-xl text-white text-sm placeholder-stone-600 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition-all">
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-xs font-bold uppercase tracking-wider text-stone-300">
                                Password
                            </label>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-stone-500">
                                <i class="fa-solid fa-lock text-xs"></i>
                            </div>
                            <input type="password" id="password" name="password" required
                                placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                                class="w-full pl-10 pr-10 py-3 bg-stone-900 border border-stone-800 rounded-xl text-white text-sm placeholder-stone-600 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition-all">
                            <button type="button" id="togglePasswordBtn" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-stone-500 hover:text-stone-300 transition-colors">
                                <i class="fa-solid fa-eye text-xs" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button type="submit" class="w-full py-3.5 px-4 bg-gradient-to-r from-amber-600 to-amber-500 hover:from-amber-500 hover:to-amber-600 text-stone-950 font-bold text-sm rounded-xl shadow-lg shadow-amber-900/30 transition-all transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                            <i class="fa-solid fa-right-to-bracket text-xs"></i>
                            <span>Sign In to System</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Footer Note inside Card -->
            <div class="px-8 py-4 bg-stone-900/60 border-t border-stone-800/80 text-center text-xs text-stone-500">
                <span>Account locked? Contact your Central HR or System Administrator.</span>
            </div>
        </div>

        <!-- Security Note -->
        <div class="mt-6 text-center text-[11px] text-stone-500 flex items-center justify-center gap-2">
            <i class="fa-solid fa-shield-halved text-amber-500/70"></i>
            <span>Encrypted Session &bull; PAM Role-Enforced Security</span>
        </div>
    </div>

    <!-- Page Footer -->
    <footer class="mt-8 text-center text-xs text-stone-600">
        &copy; <?= date('Y') ?> Fika Café System &bull; Slow Hours Architecture. All rights reserved.
    </footer>

    <!-- Password Reveal JS -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggleBtn = document.getElementById('togglePasswordBtn');
            const passInput = document.getElementById('password');
            const icon = document.getElementById('togglePasswordIcon');

            if (toggleBtn && passInput && icon) {
                toggleBtn.addEventListener('click', () => {
                    const isPass = passInput.getAttribute('type') === 'password';
                    passInput.setAttribute('type', isPass ? 'text' : 'password');
                    if (isPass) {
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            }
        });
    </script>
</body>
</html>
