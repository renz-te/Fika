<?php
require_once __DIR__ . '/init.php';

// Only Kiosk role can access this page
require_role('Kiosk');

$error = null;
$success = null;

// Handle Logout via PIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'logout') {
    $pin = $_POST['pin'] ?? '';
    $expected_pin = get_setting($pdo, 'kiosk_pin', '1234');
    
    if ($pin === $expected_pin) {
        session_unset();
        session_destroy();
        redirect('login');
    } else {
        $error = 'Invalid Logout PIN.';
    }
}
// Handle Clock In/Out
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Authentic credentials, proceed with time punch
            if ($user['id'] === $_SESSION['user']['id']) {
                $error = 'The kiosk terminal account cannot be used to punch in.';
            } else {
                // Fetch actual employee_id based on email
                $empStmt = $pdo->prepare('SELECT id, CONCAT(first_name, " ", last_name) AS full_name FROM employees WHERE email = ? LIMIT 1');
                $empStmt->execute([$user['email']]);
                $employee = $empStmt->fetch();
                
                if (!$employee) {
                    $error = 'Your account is not linked to an active employee profile.';
                } else {
                    $emp_id = $employee['id'];
                    $emp_name = $employee['full_name'];
                    
                    // 1. Orphaned Punch Check
                    // Find any open shifts from a previous day
                    $stmt = $pdo->prepare('SELECT id, time_in FROM attendance WHERE employee_id = ? AND time_out IS NULL AND DATE(time_in) < CURDATE()');
                    $stmt->execute([$emp_id]);
                    $orphans = $stmt->fetchAll();
                    
                    foreach ($orphans as $orphan) {
                        // Auto-close exactly 8 hours after time_in
                        $closeStmt = $pdo->prepare("UPDATE attendance SET time_out = DATE_ADD(time_in, INTERVAL 8 HOUR), status = 'Auto-Closed' WHERE id = ?");
                        $closeStmt->execute([$orphan['id']]);
                    }
                    
                    // 2. Normal Clock In / Clock Out Check for TODAY
                    $stmt = $pdo->prepare('SELECT id, time_out FROM attendance WHERE employee_id = ? AND DATE(time_in) = CURDATE() ORDER BY time_in DESC LIMIT 1');
                    $stmt->execute([$emp_id]);
                    $attendance = $stmt->fetch();
                    
                    if (!$attendance) {
                        // Clock IN (Removed is_late calculation)
                        $stmt = $pdo->prepare('INSERT INTO attendance (employee_id, time_in, status) VALUES (?, NOW(), "Present")');
                        $stmt->execute([$emp_id]);
                        $success = "Welcome, {$emp_name}! You have successfully Clocked IN.";
                    } elseif ($attendance['time_out'] === null) {
                        // Clock OUT
                        // Calculate total hours worked
                        $stmt = $pdo->prepare('UPDATE attendance SET time_out = NOW(), status = "Clocked Out" WHERE id = ?');
                        $stmt->execute([$attendance['id']]);
                        $success = "Goodbye, {$emp_name}! You have successfully Clocked OUT.";
                    } else {
                        // Already clocked out for the day
                        $error = "You have already completed your shift for today.";
                    }
                    
                    // Log the action 
                    log_activity($pdo, $user['id'], 'kiosk_punch', 'Time punch via Kiosk');
                }
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

$companyName = get_setting($pdo, 'company_name', 'HRMS Kiosk');
$pageTitle = 'Time Clock Kiosk';
$showSidebar = false;
require_once __DIR__ . '/includes/header.php';
?>

<div class="w-full max-w-md mx-auto relative">
    
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center h-20 w-20 rounded-full bg-slate-800 text-white text-3xl mb-4 shadow-xl">
            <i class="fa-solid fa-clock"></i>
        </div>
        <!-- Hidden button on the title -->
        <h1 class="text-3xl font-bold text-slate-800 cursor-pointer" onclick="promptLogout()" title="Terminal No. 1"><?= h($companyName) ?></h1>
        <p class="text-slate-500 mt-2 text-lg" id="live-clock"><?= date('h:i A') ?></p>
        <p class="text-slate-400 text-sm"><?= date('l, F j, Y') ?></p>
    </div>

    <div class="bg-white rounded-2xl shadow-2xl border border-slate-100 overflow-hidden relative">
        <!-- Colored top border -->
        <div class="h-2 bg-gradient-to-r from-primary to-indigo-600 w-full absolute top-0 left-0"></div>
        
        <div class="p-8 pt-10">

            <?php if ($success): ?>
                <div class="mb-6 bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded text-center">
                    <i class="fa-solid fa-circle-check text-2xl mb-2 block text-green-600"></i>
                    <span class="font-medium"><?= h($success) ?></span>
                    <p class="text-xs mt-2 text-green-600">Screen will reset in 3 seconds...</p>
                </div>
                <!-- Auto reset screen -->
                <script>
                    setTimeout(function(){ window.location.href = 'kiosk.php'; }, 3500);
                </script>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded text-sm text-center">
                    <i class="fa-solid fa-circle-exclamation mr-1"></i> <?= h($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!$success): ?>
            <form autocomplete="off" method="POST" action="kiosk" class="space-y-6">
                <div>
                    <label for="username" class="sr-only">Username</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <input type="text" id="username" name="username" class="pl-12 block w-full rounded-xl border-slate-300 bg-slate-50 border p-4 focus:ring-primary focus:border-primary transition-colors text-lg" placeholder="Username" required autofocus>
                    </div>
                </div>

                <div>
                    <label for="password" class="sr-only">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <input type="password" id="password" name="password" class="pl-12 block w-full rounded-xl border-slate-300 bg-slate-50 border p-4 focus:ring-primary focus:border-primary transition-colors text-lg" placeholder="Password" required>
                    </div>
                </div>

                <button type="submit" class="w-full flex justify-center py-4 px-4 border border-transparent rounded-xl shadow-md text-lg font-bold text-white bg-slate-800 hover:bg-black focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-800 transition-colors">
                    Clock In / Out
                </button>
                
                <p class="text-center text-xs text-slate-400 mt-4">
                    <i class="fa-solid fa-shield-halved mr-1"></i> Your punch is securely recorded.
                </p>
            </form>
            <?php endif; ?>
                
        </div>
    </div>
</div>

<!-- Hidden Logout Form -->
<form autocomplete="off" id="logoutForm" method="POST" action="kiosk" class="hidden">
    <input type="hidden" name="action" value="logout">
    <input type="hidden" name="pin" id="logoutPin">
</form>

<!-- Logout Modal -->
<div id="logoutModal" class="fixed inset-0 z-50 bg-black/50 hidden flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden transform transition-all">
        <div class="p-6">
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center h-12 w-12 rounded-full bg-slate-100 text-slate-800 text-xl mb-4">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <h3 class="font-bold text-lg text-slate-800">Administrator Logout</h3>
                <p class="text-sm text-slate-500 mt-1">Enter the Kiosk PIN to close this terminal.</p>
            </div>
            <div class="space-y-4">
                <div>
                    <label for="modalPin" class="sr-only">PIN</label>
                    <input type="password" id="modalPin" class="block w-full rounded-xl border-slate-300 bg-slate-50 border p-3 text-center tracking-widest text-xl focus:ring-primary focus:border-primary transition-colors" placeholder="••••" maxlength="4">
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="closeLogoutModal()" class="flex-1 px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition-colors font-medium">Cancel</button>
                    <button type="button" onclick="submitLogout()" class="flex-1 px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-black transition-colors font-medium">Confirm</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Live clock for the kiosk
    setInterval(function() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        document.getElementById('live-clock').textContent = timeString;
    }, 1000);

    const logoutModal = document.getElementById('logoutModal');
    const modalPin = document.getElementById('modalPin');

    function promptLogout() {
        logoutModal.classList.remove('hidden');
        modalPin.value = '';
        setTimeout(() => modalPin.focus(), 100);
    }

    function closeLogoutModal() {
        logoutModal.classList.add('hidden');
    }

    function submitLogout() {
        const pin = modalPin.value;
        if (pin !== "") {
            document.getElementById('logoutPin').value = pin;
            document.getElementById('logoutForm').submit();
        }
    }
    
    modalPin.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            submitLogout();
        }
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

