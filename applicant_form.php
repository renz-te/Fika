<?php
require_once __DIR__ . '/app/bootstrap.php';

global $pdo;

$error = null;
$success = false;
$applicantId = null;

// Ensure storage directory exists
$storageDir = APP_ROOT . '/storage/resumes';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

// Fetch active job openings for the dropdown
$stmt = $pdo->query("SELECT id, title, department, location FROM job_openings WHERE status = 'OPEN' ORDER BY id ASC");
$openings = $stmt->fetchAll();

// Fetch active branches for the dropdown
$branchStmt = $pdo->query("SELECT id, name FROM branches ORDER BY id ASC");
$branches = $branchStmt->fetchAll(PDO::FETCH_ASSOC);

// Preselected job opening and branch
$selectedJobId = (int)($_GET['job_id'] ?? ($_POST['job_opening_id'] ?? 0));
if (!$selectedJobId && !empty($openings)) {
    $selectedJobId = (int)$openings[0]['id'];
}
$selectedBranchId = (int)($_GET['branch_id'] ?? ($_POST['branch_id'] ?? 0));
if (!$selectedBranchId && !empty($branches)) {
    $selectedBranchId = (int)$branches[0]['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // 1. Rate Limiting Check: max 3 submissions per IP per hour
    try {
        $rateStmt = $pdo->prepare("
            SELECT COUNT(*) FROM applicant_rate_limits 
            WHERE ip_address = ? AND created_at > (NOW() - INTERVAL 1 HOUR)
        ");
        $rateStmt->execute([$clientIp]);
        $submissionCount = (int)$rateStmt->fetchColumn();

        if ($submissionCount >= 3) {
            http_response_code(429);
            $error = 'Too many applications submitted from your network. Please wait an hour before submitting again.';
        }
    } catch (\Throwable $t) {
        // Fallback if table rate limits check fails
    }

    if (!$error) {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $jobOpeningId = (int)($_POST['job_opening_id'] ?? 0);
        $branchIdRaw = (int)($_POST['branch_id'] ?? 0);

        // 2. Validate inputs
        if ($firstName === '' || $lastName === '' || $email === '' || $phone === '') {
            http_response_code(400);
            $error = 'Please fill out all required personal and contact information.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            $error = 'Please enter a valid email address.';
        } else {
            // 3. Validate Job Opening
            $jobStmt = $pdo->prepare("SELECT * FROM job_openings WHERE id = ? AND status = 'OPEN' LIMIT 1");
            $jobStmt->execute([$jobOpeningId]);
            $jobOpening = $jobStmt->fetch();

            if (!$jobOpening) {
                http_response_code(400);
                $error = 'Invalid or inactive job opening selected.';
            } elseif (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                $error = 'Please upload a valid resume in PDF format.';
            } else {
                // 4. Handle file upload via Upload::handle()
                $uploadedFilename = Upload::handle($_FILES['resume'], $storageDir);

                // Enforce strictly PDF only
                if (!$uploadedFilename || !str_ends_with(strtolower($uploadedFilename), '.pdf')) {
                    if ($uploadedFilename && file_exists($storageDir . '/' . $uploadedFilename)) {
                        unlink($storageDir . '/' . $uploadedFilename);
                    }
                    http_response_code(400);
                    $error = 'Resume must be a valid PDF document (maximum 5MB). Disguised or non-PDF files are strictly rejected.';
                } else {
                    // 5. Insert record into applicants table with status 'NEW'
                    $positionTitle = $jobOpening['title'];

                    // Official roles (Manager, HR, Accountant) have branch assigned by CHR
                    $isOfficial = (bool)preg_match('/(Manager|HR|Accountant)/i', $positionTitle);
                    $branchId = (!$isOfficial && $branchIdRaw > 0) ? $branchIdRaw : null;
                    if (!$isOfficial && $branchId === null) {
                        $branchId = 1;
                    }

                    $insertStmt = $pdo->prepare("
                        INSERT INTO applicants 
                            (job_opening_id, branch_id, first_name, last_name, email, phone, position, position_applied, resume, resume_path, status, created_at)
                        VALUES 
                            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'NEW', NOW())
                    ");

                    $insertStmt->execute([
                        $jobOpeningId,
                        $branchId,
                        $firstName,
                        $lastName,
                        $email,
                        $phone,
                        $positionTitle,
                        $positionTitle,
                        $uploadedFilename,
                        $uploadedFilename
                    ]);

                    $applicantId = (int)$pdo->lastInsertId();
                    $success = true;

                    // Log rate limit
                    try {
                        $logRate = $pdo->prepare("INSERT INTO applicant_rate_limits (ip_address, created_at) VALUES (?, NOW())");
                        $logRate->execute([$clientIp]);
                    } catch (\Throwable $t) {}

                    // If API or JSON client requested
                    $isApi = (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
                        || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'));

                    if ($isApi) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'applicant_id' => $applicantId,
                            'status' => 'NEW',
                            'resume' => $uploadedFilename
                        ]);
                        exit;
                    }
                }
            }
        }
    }

    // If API requested and there was an error
    $isApi = (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
        || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'));
    if ($error && $isApi) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $error]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Form &middot; Fika Slow Hours</title>
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
<body class="bg-stone-900 text-stone-100 min-h-screen flex flex-col font-sans antialiased selection:bg-amber-500 selection:text-white">

    <!-- Header Navigation -->
    <header class="bg-stone-950/80 backdrop-blur-md border-b border-stone-800">
        <div class="max-w-4xl mx-auto px-4 h-20 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3 group">
                <img src="public/assets/logo.svg" alt="Fika Logo" class="w-10 h-10 object-contain">
                <div>
                    <span class="text-lg font-black tracking-wider text-white">FIKA</span>
                    <span class="text-[11px] uppercase tracking-widest text-amber-500 block font-bold">Applicant Intake</span>
                </div>
            </a>
            <a href="index.php" class="text-xs text-stone-400 hover:text-amber-400 transition-colors flex items-center gap-1.5 font-medium">
                <i class="fa-solid fa-arrow-left text-[10px]"></i>
                <span>Back to Home</span>
            </a>
        </div>
    </header>

    <main class="flex-1 max-w-4xl w-full mx-auto px-4 py-10 sm:py-14">
        <?php if ($success): ?>
            <!-- Success Confirmation Screen -->
            <div class="bg-stone-950 border border-stone-800 rounded-3xl p-8 sm:p-12 text-center shadow-2xl">
                <div class="w-16 h-16 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center text-3xl mx-auto mb-6">
                    <i class="fa-solid fa-check"></i>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black text-white">Application Received!</h1>
                <p class="text-stone-300 text-sm sm:text-base mt-3 max-w-lg mx-auto">
                    Thank you for applying to Fika &middot; Slow Hours. Your application has been logged into our recruitment system with reference ID <span class="text-amber-400 font-bold">#<?= $applicantId ?></span>.
                </p>
                <div class="mt-6 p-4 rounded-xl bg-stone-900 border border-stone-800 max-w-md mx-auto text-xs text-stone-400">
                    Our team reviews submissions continuously. If your qualifications match our current shift requirements, our hiring manager will reach out via email or phone.
                </div>
                <div class="mt-8 flex justify-center gap-4">
                    <a href="index.php" class="px-6 py-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs uppercase tracking-wider transition-all">
                        Return Home
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Applicant Intake Form -->
            <div class="bg-stone-950 border border-stone-800 rounded-3xl shadow-2xl overflow-hidden">
                <div class="p-8 sm:p-10 border-b border-stone-800">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 text-amber-400 text-xs font-bold uppercase tracking-wider mb-3">
                        <i class="fa-solid fa-id-card-clip"></i>
                        <span>Candidate Intake Form</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black text-white">Join the Slow Hours Team</h1>
                    <p class="text-stone-400 text-xs sm:text-sm mt-1">
                        Please provide accurate personal information and attach your resume in PDF format.
                    </p>
                </div>

                <div class="p-8 sm:p-10">
                    <?php if ($error): ?>
                        <div class="mb-6 p-4 rounded-xl bg-red-950/60 border border-red-800 text-red-300 text-xs flex items-start gap-3">
                            <i class="fa-solid fa-triangle-exclamation text-red-400 mt-0.5 text-sm"></i>
                            <div class="font-medium"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="applicant_form.php" enctype="multipart/form-data" class="space-y-6">
                        <!-- Position & Branch Selectors -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="job_opening_id" class="block text-xs font-bold uppercase tracking-wider text-stone-300 mb-2">
                                    Position Applied For *
                                </label>
                                <select id="job_opening_id" name="job_opening_id" required
                                    class="w-full px-4 py-3 bg-stone-900 border border-stone-800 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500">
                                    <?php foreach ($openings as $job): ?>
                                        <option value="<?= (int)$job['id'] ?>" data-title="<?= htmlspecialchars($job['title'], ENT_QUOTES, 'UTF-8') ?>" <?= $selectedJobId === (int)$job['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($job['title'], ENT_QUOTES, 'UTF-8') ?> &mdash; <?= htmlspecialchars($job['department'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="branch_id" class="block text-xs font-bold uppercase tracking-wider text-stone-300 mb-2">
                                    Preferred Branch Location *
                                </label>
                                <select id="branch_id" name="branch_id" required
                                    class="w-full px-4 py-3 bg-stone-900 border border-stone-800 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-stone-950">
                                    <option value="" id="branch_unassigned_opt" class="hidden">-- Branch Assigned by CHR --</option>
                                    <?php foreach ($branches as $br): ?>
                                        <option value="<?= (int)$br['id'] ?>" <?= $selectedBranchId === (int)$br['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($br['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p id="branch_hint" class="text-xs text-amber-500/90 mt-2 hidden flex items-center gap-1.5 font-medium">
                                    <i class="fa-solid fa-circle-info text-[11px]"></i>
                                    <span>Branch assigned by CHR upon hire.</span>
                                </p>
                            </div>
                        </div>

                        <!-- Name Fields -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="first_name" class="block text-xs font-bold uppercase tracking-wider text-stone-300 mb-2">
                                    First Name *
                                </label>
                                <input type="text" id="first_name" name="first_name" required
                                    value="<?= htmlspecialchars($_POST['first_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    placeholder="e.g. Maria"
                                    class="w-full px-4 py-3 bg-stone-900 border border-stone-800 rounded-xl text-white text-sm placeholder-stone-600 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500">
                            </div>
                            <div>
                                <label for="last_name" class="block text-xs font-bold uppercase tracking-wider text-stone-300 mb-2">
                                    Last Name *
                                </label>
                                <input type="text" id="last_name" name="last_name" required
                                    value="<?= htmlspecialchars($_POST['last_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    placeholder="e.g. Santos"
                                    class="w-full px-4 py-3 bg-stone-900 border border-stone-800 rounded-xl text-white text-sm placeholder-stone-600 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500">
                            </div>
                        </div>

                        <!-- Contact Fields -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="email" class="block text-xs font-bold uppercase tracking-wider text-stone-300 mb-2">
                                    Email Address *
                                </label>
                                <input type="email" id="email" name="email" required
                                    value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    placeholder="maria.santos@example.com"
                                    class="w-full px-4 py-3 bg-stone-900 border border-stone-800 rounded-xl text-white text-sm placeholder-stone-600 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500">
                            </div>
                            <div>
                                <label for="phone" class="block text-xs font-bold uppercase tracking-wider text-stone-300 mb-2">
                                    Mobile Phone Number *
                                </label>
                                <input type="tel" id="phone" name="phone" required
                                    value="<?= htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    placeholder="+63 912 345 6789"
                                    class="w-full px-4 py-3 bg-stone-900 border border-stone-800 rounded-xl text-white text-sm placeholder-stone-600 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500">
                            </div>
                        </div>

                        <!-- Resume Upload -->
                        <div>
                            <label for="resume" class="block text-xs font-bold uppercase tracking-wider text-stone-300 mb-2">
                                Resume / Curriculum Vitae (PDF only, max 5MB) *
                            </label>
                            <div class="p-4 rounded-xl bg-stone-900 border border-dashed border-stone-700 hover:border-amber-500/60 transition-colors">
                                <input type="file" id="resume" name="resume" accept="application/pdf,.pdf" required
                                    class="w-full text-xs text-stone-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-amber-500 file:text-stone-950 hover:file:bg-amber-400 file:cursor-pointer cursor-pointer">
                                <p class="text-[11px] text-stone-500 mt-2">
                                    <i class="fa-solid fa-circle-info text-amber-500/80 mr-1"></i>
                                    Only verifiable PDF documents are accepted. Disguised scripts will be automatically rejected.
                                </p>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-4 border-t border-stone-800">
                            <button type="submit"
                                class="w-full py-4 px-6 bg-gradient-to-r from-amber-600 to-amber-500 hover:from-amber-500 hover:to-amber-600 text-stone-950 font-bold text-sm uppercase tracking-wider rounded-xl shadow-xl shadow-amber-900/30 transition-all transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                                <i class="fa-solid fa-paper-plane text-xs"></i>
                                <span>Submit Job Application</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="bg-stone-950 text-stone-500 py-8 border-t border-stone-800 text-xs text-center">
        &copy; <?= date('Y') ?> Fika Café System &bull; Slow Hours Architecture. All rights reserved.
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const jobSelect = document.getElementById('job_opening_id');
        const branchSelect = document.getElementById('branch_id');
        const branchHint = document.getElementById('branch_hint');
        const unassignedOpt = document.getElementById('branch_unassigned_opt');

        if (!jobSelect || !branchSelect) return;

        function updateBranchState() {
            const selectedOpt = jobSelect.options[jobSelect.selectedIndex];
            const title = selectedOpt ? (selectedOpt.getAttribute('data-title') || selectedOpt.textContent || '') : '';
            const isOfficial = /(Manager|HR|Accountant)/i.test(title);

            if (isOfficial) {
                if (unassignedOpt) unassignedOpt.classList.remove('hidden');
                branchSelect.value = '';
                branchSelect.disabled = true;
                branchSelect.removeAttribute('required');
                if (branchHint) branchHint.classList.remove('hidden');
            } else {
                if (unassignedOpt) unassignedOpt.classList.add('hidden');
                branchSelect.disabled = false;
                branchSelect.setAttribute('required', 'required');
                if (!branchSelect.value && branchSelect.options.length > 1) {
                    for (let i = 0; i < branchSelect.options.length; i++) {
                        if (branchSelect.options[i].value !== '') {
                            branchSelect.selectedIndex = i;
                            break;
                        }
                    }
                }
                if (branchHint) branchHint.classList.add('hidden');
            }
        }

        jobSelect.addEventListener('change', updateBranchState);
        updateBranchState();
    });
    </script>
</body>
</html>
