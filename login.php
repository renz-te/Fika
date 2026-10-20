<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection using legacy config
$dbConfig = require __DIR__ . '/cafe_hrms/config.php';
$pdo = null;
try {
    $dsn = "mysql:host={$dbConfig['db']['host']};dbname={$dbConfig['db']['name']};charset={$dbConfig['db']['charset']}";
    $pdo = new PDO($dsn, $dbConfig['db']['user'], $dbConfig['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'"
    ]);
} catch (\Throwable $e) {
    $pdo = null;
}

if (!function_exists('e')) {
    function e(?string $str): string {
        return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// Helper: Determine target portal based on legacy user role
function get_legacy_role_redirect(string $role): string {
    if (in_array($role, ['Admin', 'System Admin', 'Super Admin', 'Central HR', 'Executives', 'Branch Manager', 'HR'], true)) {
        return 'cafe_hrms/dashboard.php';
    } elseif (in_array($role, ['Cashier', 'Cashier Terminal', 'Barista'], true)) {
        return 'cafe_pos/cashier.php';
    } elseif (stripos($role, 'kiosk') !== false) {
        return 'cafe_pos/kiosk.php';
    } elseif ($role === 'Clock') {
        return 'cafe_hrms/attendance.php';
    } else {
        return 'cafe_hrms/ess.php';
    }
}

// Handle Logout request
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (session_id()) {
        session_destroy();
    }
    header('Location: login.php');
    exit;
}

// If already logged in, redirect directly to user's portal
if (!empty($_SESSION['user']['role'])) {
    header('Location: ' . get_legacy_role_redirect($_SESSION['user']['role']));
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both your username and password.';
    } elseif (!$pdo) {
        $error = 'Database connection unavailable. Please check server configuration.';
    } else {
        $stmt = $pdo->prepare('
            SELECT u.*, r.name AS role_name, e.employment_status 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            LEFT JOIN employees e ON u.employee_id = e.id 
            WHERE u.username = ? OR u.email = ? 
            LIMIT 1
        ');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Check email verification if flag exists
            if (isset($user['verified']) && !$user['verified']) {
                $error = 'Please verify your email before logging in.';
            } else {
                // Set exact legacy session structure required by cafe_hrms/init.php & cafe_pos/cashier.php
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'name' => $user['name'] ?? $user['username'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role_name'],
                    'branch_id' => $user['branch_id'] ?? 1,
                    'employment_status' => $user['employment_status'] ?? 'Active',
                ];

                // Log activity if table exists
                try {
                    $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'login', 'User logged in', NOW())");
                    $logStmt->execute([$user['id']]);
                } catch (\Throwable $t) {}

                header('Location: ' . get_legacy_role_redirect($user['role_name']));
                exit;
            }
        } else {
            $error = 'Invalid credentials. Please verify your username and password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Backoffice Portal &middot; Fika Slow Hours</title>
    <link rel="icon" type="image/svg+xml" href="public/assets/logo.svg">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,500;0,600;0,700;0,900;1,400;1,600&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        serif: ['"Playfair Display"', 'serif'],
                    },
                    colors: {
                        espresso: {
                            50: '#fbf8f5',
                            100: '#f5efe9',
                            200: '#ebdcd0',
                            300: '#dbc0ad',
                            400: '#c59d83',
                            700: '#5c3821',
                            800: '#422415',
                            900: '#2c180e',
                            950: '#1a0d07'
                        },
                        amber: {
                            50: '#fffbeb',
                            100: '#fef3c7',
                            200: '#fde68a',
                            300: '#fcd34d',
                            400: '#fbbf24',
                            500: '#f59e0b',
                            600: '#d97706',
                            700: '#b45309',
                            800: '#92400e',
                            900: '#78350f'
                        },
                        cream: {
                            50: '#fdfbf7',
                            100: '#fbf7f0',
                            200: '#f5eee1',
                            300: '#ece1ce',
                            400: '#decbb0'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-espresso-950 text-cream-100 min-h-screen flex flex-col justify-between items-center px-4 py-8 antialiased selection:bg-amber-600 selection:text-white font-sans">

    <!-- Top Navigation Simple Header -->
    <div class="w-full max-w-md flex justify-between items-center mb-6">
        <a href="index.php" class="text-xs text-cream-300 hover:text-amber-400 transition-colors flex items-center gap-1.5 font-medium">
            <i class="fa-solid fa-arrow-left text-[10px]"></i>
            <span>Back to Café Homepage</span>
        </a>
        <a href="careers.php" class="text-xs text-amber-400 hover:text-amber-300 font-semibold transition-colors">
            Looking for Careers?
        </a>
    </div>

    <!-- Centered Login Card -->
    <div class="w-full max-w-md">
        <!-- Brand Header -->
        <div class="text-center mb-8">
            <div class="inline-block relative group">
                <div class="relative w-24 h-24 mx-auto flex items-center justify-center">
                    <img src="public/assets/logo.svg" alt="Fika Logo" class="w-24 h-24 object-contain drop-shadow-xl" onerror="this.style.display='none'; document.getElementById('logo-login-fallback').classList.remove('hidden');">
                    <div id="logo-login-fallback" class="hidden w-20 h-20 rounded-full bg-espresso-900 text-amber-300 flex items-center justify-center font-serif font-black text-3xl shadow-inner border border-amber-600/40">
                        <span>F</span>
                    </div>
                </div>
            </div>
            <h1 class="text-3xl font-black font-serif text-cream-50 mt-3 tracking-tight">Staff Backoffice Portal</h1>
            <p class="text-xs text-cream-300/80 mt-1">Authorized personnel &bull; Enter credentials to launch console</p>
        </div>

        <!-- Card Container -->
        <div class="bg-espresso-900/80 border border-espresso-800 rounded-3xl shadow-2xl overflow-hidden backdrop-blur-sm">
            <div class="p-8">
                <?php if ($error): ?>
                    <div class="mb-6 p-4 rounded-xl bg-rose-950/80 border border-rose-800 text-rose-200 text-xs flex items-start gap-3">
                        <i class="fa-solid fa-circle-exclamation text-rose-400 mt-0.5 text-sm"></i>
                        <div class="font-medium"><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" class="space-y-5" autocomplete="off">
                    <!-- Username Field -->
                    <div>
                        <label for="username" class="block text-xs font-bold uppercase tracking-wider text-amber-400 mb-1.5">
                            Username or Staff Email
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-cream-400">
                                <i class="fa-solid fa-user text-xs"></i>
                            </div>
                            <input type="text" id="username" name="username" required
                                value="<?= e($_POST['username'] ?? '') ?>"
                                placeholder="e.g. admin or juan.delacruz"
                                class="w-full pl-10 pr-4 py-3 bg-espresso-950 border border-espresso-800 rounded-xl text-cream-100 text-sm placeholder-espresso-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition-all">
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-xs font-bold uppercase tracking-wider text-amber-400">
                                Password
                            </label>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-cream-400">
                                <i class="fa-solid fa-lock text-xs"></i>
                            </div>
                            <input type="password" id="password" name="password" required
                                placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                                class="w-full pl-10 pr-10 py-3 bg-espresso-950 border border-espresso-800 rounded-xl text-cream-100 text-sm placeholder-espresso-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition-all">
                            <button type="button" id="togglePasswordBtn" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-cream-400 hover:text-amber-400 transition-colors">
                                <i class="fa-solid fa-eye text-xs" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button type="submit" class="w-full py-4 px-4 bg-amber-500 hover:bg-amber-400 text-espresso-950 font-bold text-xs uppercase tracking-wider rounded-xl shadow-xl shadow-amber-950/50 transition-all transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                            <i class="fa-solid fa-arrow-right-to-bracket text-xs"></i>
                            <span>Sign In to System</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Footer Note inside Card -->
            <div class="px-8 py-4 bg-espresso-950/70 border-t border-espresso-800 text-center text-xs text-cream-400/70">
                <span>Account locked? Contact your Central HR or System Administrator.</span>
            </div>
        </div>

        <!-- Security Badge -->
        <div class="mt-6 text-center text-[11px] text-cream-400/60 flex items-center justify-center gap-2">
            <i class="fa-solid fa-shield-halved text-amber-500"></i>
            <span>Encrypted Session &bull; Role-Based Backoffice Routing</span>
        </div>
    </div>

    <!-- Page Footer -->
    <footer class="mt-8 text-center text-xs text-cream-400/50">
        &copy; <?= date('Y') ?> Fika Café System &bull; Slow Hours Architecture
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
