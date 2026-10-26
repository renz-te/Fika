<?php
require_once __DIR__ . '/app/bootstrap.php';

if (isset($_GET['logout'])) {
    Auth::logout();
    header("Location: login.php");
    exit;
}

global $pdo;

function get_role_redirect_url(int $roleId, PDO $pdo, ?int $employeeId = null): string {
    if (in_array($roleId, [1, 2, 5, 6], true)) {
        return 'fika_hrms/fika_hrms_dashboard.php';
    }
    return Auth::getRedirectUrl(['role_id' => $roleId, 'employee_id' => $employeeId]);
}

// If already logged in, redirect based on role
if (Auth::check()) {
    $currentUser = Auth::user();
    $roleId = (int)($currentUser['role_id'] ?? 0);
    if (in_array($roleId, [1, 2, 5, 6], true)) {
        $target = 'fika_hrms/fika_hrms_dashboard.php';
    } else {
        $target = Auth::getRedirectUrl($currentUser);
    }
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
            $roleId = (int)($currentUser['role_id'] ?? 0);
            if (in_array($roleId, [1, 2, 5, 6], true)) {
                $target = 'fika_hrms/fika_hrms_dashboard.php';
            } else {
                $target = Auth::getRedirectUrl($currentUser);
            }
            header("Location: {$target}");
            exit;
        } else {
            $error = 'Invalid credentials or account temporarily locked. Please check and try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login &middot; Fika Slow Hours</title>
    <link rel="icon" type="image/svg+xml" href="public/assets/logo.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;1,400&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        serif: ['"Playfair Display"', 'serif'],
                    },
                    colors: {
                        amber: {
                            400: '#fbbf24',
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
<body class="h-full bg-stone-950 text-stone-100 font-sans antialiased selection:bg-amber-500 selection:text-white">

    <div class="min-h-screen flex flex-col md:flex-row">
        <!-- Left Side: Hidden on mobile, 50% width on desktop -->
        <div class="hidden md:flex md:w-1/2 relative bg-cover bg-center overflow-hidden flex-col justify-between p-12 lg:p-16"
             style="background-image: url('https://images.unsplash.com/photo-1554118811-1e0d58224f24?auto=format&fit=crop&w=1600&q=80');">
            <!-- Dark Overlay -->
            <div class="absolute inset-0 bg-black/40 backdrop-blur-[2px]"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-stone-950/80 via-stone-950/20 to-stone-950/60"></div>

            <!-- Top Left Brand Badge -->
            <div class="relative z-10 flex items-center justify-between">
                <a href="index.php" class="inline-flex items-center gap-2 text-xs font-semibold text-stone-300 hover:text-white transition-colors bg-black/40 hover:bg-black/60 backdrop-blur-md px-3.5 py-2 rounded-xl border border-white/10">
                    <i class="fa-solid fa-arrow-left text-[10px]"></i>
                    <span>Back to Main Page</span>
                </a>
                <span class="text-[11px] font-bold uppercase tracking-wider text-amber-300 bg-amber-500/20 border border-amber-500/30 px-3 py-1 rounded-full backdrop-blur-md">
                    Slow Hours Architecture
                </span>
            </div>

            <!-- Centered Fika Logo & Tagline -->
            <div class="relative z-10 text-center my-auto px-6">
                <div class="inline-block relative mb-6">
                    <div class="absolute -inset-2 rounded-full bg-amber-500/20 blur-xl"></div>
                    <img src="public/assets/logo.svg" alt="Fika Logo" class="relative w-24 h-24 lg:w-28 lg:h-28 object-contain mx-auto drop-shadow-2xl">
                </div>
                <h2 class="text-3xl lg:text-4xl font-extrabold tracking-tight text-white mb-3">Fika Slow Hours</h2>
                <p class="font-serif italic text-amber-300/90 text-xl lg:text-2xl font-normal tracking-wide">
                    &ldquo;Take the time to sip.&rdquo;
                </p>
                <div class="w-16 h-0.5 bg-amber-500/60 mx-auto mt-6 rounded-full"></div>
            </div>

            <!-- Bottom Note -->
            <div class="relative z-10 text-xs text-stone-400/80 flex items-center justify-between">
                <span>Café System &bull; Artisanal Brews & Operations</span>
                <span>Cavite, Philippines</span>
            </div>
        </div>

        <!-- Right Side: Full width on mobile, 50% width on desktop -->
        <div class="w-full md:w-1/2 flex flex-col justify-between p-6 sm:p-10 lg:p-16 bg-stone-950 min-h-screen">
            <!-- Top Nav -->
            <div class="flex items-center justify-between md:justify-end mb-6">
                <a href="index.php" class="md:hidden inline-flex items-center gap-1.5 text-xs text-stone-400 hover:text-amber-400 transition-colors">
                    <i class="fa-solid fa-arrow-left text-[10px]"></i>
                    <span>Back</span>
                </a>
                <a href="applicant_form.php" class="text-xs text-amber-500 hover:text-amber-400 font-semibold transition-colors flex items-center gap-1.5">
                    <span>Looking for Careers?</span>
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                </a>
            </div>

            <!-- Centered Login Form Container -->
            <div class="w-full max-w-md mx-auto my-auto py-8">
                <!-- Mobile Logo Header -->
                <div class="md:hidden text-center mb-8">
                    <img src="public/assets/logo.svg" alt="Fika Logo" class="w-16 h-16 object-contain mx-auto drop-shadow-lg mb-3">
                    <p class="font-serif italic text-amber-400 text-sm">&ldquo;Take the time to sip.&rdquo;</p>
                </div>

                <!-- Form Header -->
                <div class="mb-8">
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Staff Backoffice Portal</h1>
                    <p class="text-stone-400 text-xs sm:text-sm mt-1.5">Authorized personnel only &bull; Enter credentials to access your console</p>
                </div>

                <!-- Error Alert -->
                <?php if ($error): ?>
                    <div class="mb-6 p-4 rounded-xl bg-rose-950/60 border border-rose-800/80 text-rose-300 text-xs flex items-start gap-3 shadow-sm">
                        <i class="fa-solid fa-circle-exclamation text-rose-400 mt-0.5 text-sm shrink-0"></i>
                        <div class="font-medium"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" action="login.php" class="space-y-5" autocomplete="off">
                    <!-- Username/Staff ID -->
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
                                placeholder="e.g. admin or ana.reyes"
                                class="w-full pl-10 pr-4 py-3 bg-stone-900 border border-stone-800 rounded-xl text-white text-sm placeholder-stone-600 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition-all">
                        </div>
                    </div>

                    <!-- Password -->
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

                    <!-- Submit Button (Amber/Orange) -->
                    <div class="pt-2">
                        <button type="submit" class="w-full py-3.5 px-4 bg-gradient-to-r from-amber-600 to-amber-500 hover:from-amber-500 hover:to-amber-600 text-stone-950 font-bold text-sm rounded-xl shadow-lg shadow-amber-900/30 transition-all transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                            <i class="fa-solid fa-right-to-bracket text-xs"></i>
                            <span>Sign In to System</span>
                        </button>
                    </div>
                </form>

                <!-- Helper & Security Note -->
                <div class="mt-8 pt-6 border-t border-stone-900 flex flex-col items-center gap-3 text-center">
                    <div class="text-[11px] text-stone-500 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-shield-halved text-amber-500/70"></i>
                        <span>Encrypted Session &bull; Role-Enforced Security</span>
                    </div>
                    <p class="text-xs text-stone-500">
                        Account locked or need help? Contact your Central HR or System Administrator.
                    </p>
                </div>
            </div>

            <!-- Bottom Career Link & Copyright -->
            <div class="pt-6 border-t border-stone-900/80 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-stone-500">
                <span>&copy; <?= date('Y') ?> Fika Café System &bull; Slow Hours Architecture.</span>
                <a href="applicant_form.php" class="text-amber-500 hover:text-amber-400 font-medium transition-colors">
                    Looking for Careers? Apply here &rarr;
                </a>
            </div>
        </div>
    </div>

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
