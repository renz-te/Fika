<?php
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

$positions = [
    'Head Barista',
    'Barista',
    'Branch Accountant',
    'Café Shift Supervisor'
];

$selectedPosition = trim($_GET['position'] ?? ($_POST['position_applied'] ?? ''));
if ($selectedPosition === '' && !empty($positions)) {
    $selectedPosition = $positions[0];
}

$error = null;
$success = false;
$applicantId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $positionApplied = trim($_POST['position_applied'] ?? '');

    if ($firstName === '' || $lastName === '' || $email === '' || $phone === '') {
        $error = 'Please fill out all required contact and personal details.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif (!in_array($positionApplied, $positions, true)) {
        $error = 'Please select a valid open position.';
    } elseif (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select and upload your resume in PDF format.';
    } elseif ($_FILES['resume']['size'] > 5 * 1024 * 1024) {
        $error = 'Resume file size exceeds the 5MB maximum limit.';
    } else {
        // Procedural upload validation using finfo
        $tmpPath = $_FILES['resume']['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        $origName = $_FILES['resume']['name'] ?? '';
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if ($mimeType !== 'application/pdf' || $ext !== 'pdf') {
            $error = 'Invalid file type. Only standard PDF documents (.pdf) are accepted.';
        } else {
            $uploadDir = __DIR__ . '/cafe_hrms/uploads/applicants';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $uniqueFilename = 'resume_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
            $destination = $uploadDir . '/' . $uniqueFilename;

            if (!move_uploaded_file($tmpPath, $destination)) {
                $error = 'Failed to save uploaded resume. Please try again.';
            } else {
                // Insert into legacy applicants table
                try {
                    if (!$pdo) {
                        throw new Exception('Database unavailable');
                    }

                    $insertStmt = $pdo->prepare("
                        INSERT INTO applicants 
                            (first_name, last_name, email, phone, position_applied, stage, resume, created_at)
                        VALUES 
                            (?, ?, ?, ?, ?, 'New', ?, NOW())
                    ");

                    $insertStmt->execute([
                        $firstName,
                        $lastName,
                        $email,
                        $phone,
                        $positionApplied,
                        $uniqueFilename
                    ]);

                    $applicantId = (int)$pdo->lastInsertId();
                    $success = true;
                } catch (\Throwable $t) {
                    if (file_exists($destination)) {
                        @unlink($destination);
                    }
                    $error = 'Database error recording application: ' . $t->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Application &middot; Fika Slow Hours</title>
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
<body class="bg-espresso-950 text-cream-100 min-h-screen flex flex-col font-sans antialiased selection:bg-amber-600 selection:text-white">

    <!-- Top Navigation Header -->
    <header class="bg-espresso-950/90 backdrop-blur-md border-b border-espresso-900 sticky top-0 z-30">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 h-20 flex items-center justify-between">
            <a href="careers.php" class="flex items-center gap-3.5 group">
                <div class="relative w-11 h-11 flex-shrink-0 flex items-center justify-center">
                    <img src="public/assets/logo.svg" alt="Fika Logo" class="w-11 h-11 object-contain transition-transform group-hover:scale-105 duration-200" onerror="this.style.display='none'; document.getElementById('logo-form-fallback').classList.remove('hidden');">
                    <div id="logo-form-fallback" class="hidden w-11 h-11 rounded-full bg-espresso-900 text-amber-300 flex items-center justify-center font-serif font-black text-xl shadow-inner border border-amber-600/40">
                        <span>F</span>
                    </div>
                </div>
                <div>
                    <span class="text-xl font-black tracking-wider text-cream-50 font-serif leading-none block">FIKA</span>
                    <span class="text-[9px] uppercase tracking-[0.25em] text-amber-400 font-bold block mt-1">Application Intake</span>
                </div>
            </a>

            <a href="careers.php" class="text-xs font-semibold text-cream-300 hover:text-amber-300 transition-colors flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-left text-[10px]"></i>
                <span>Back to Open Positions</span>
            </a>
        </div>
    </header>

    <!-- Main Form Container -->
    <main class="flex-1 max-w-2xl w-full mx-auto px-4 sm:px-6 py-12">

        <?php if ($success): ?>
            <!-- Success Screen -->
            <div class="bg-espresso-900/90 border border-amber-500/40 rounded-3xl p-8 sm:p-12 text-center shadow-2xl backdrop-blur-sm">
                <div class="w-16 h-16 rounded-full bg-amber-500/20 text-amber-400 border border-amber-500/40 flex items-center justify-center text-2xl mx-auto mb-6">
                    <i class="fa-solid fa-check"></i>
                </div>
                <h2 class="text-3xl font-black font-serif text-cream-50">Application Received</h2>
                <p class="text-amber-300 font-serif italic text-lg mt-1">&ldquo;Take the time to sip.&rdquo;</p>

                <p class="mt-6 text-cream-200 text-sm leading-relaxed max-w-md mx-auto">
                    Thank you, <strong class="text-white"><?= e($firstName) ?></strong>. Your application for <strong class="text-amber-300"><?= e($positionApplied) ?></strong> (Ref #<?= $applicantId ?>) has been successfully recorded. Our management team will review your credentials shortly.
                </p>

                <div class="mt-8 pt-6 border-t border-espresso-800 flex flex-col sm:flex-row items-center justify-center gap-4">
                    <a href="careers.php" class="w-full sm:w-auto px-8 py-3.5 bg-amber-500 hover:bg-amber-400 text-espresso-950 font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-lg">
                        Explore More Roles
                    </a>
                    <a href="index.php" class="w-full sm:w-auto px-8 py-3.5 bg-espresso-950 hover:bg-espresso-800 text-cream-200 font-semibold text-xs rounded-xl border border-espresso-800 transition-colors">
                        Return to Homepage
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Application Form -->
            <div class="bg-espresso-900/80 border border-espresso-800 rounded-3xl p-8 sm:p-10 shadow-2xl backdrop-blur-sm">
                <div class="mb-8 pb-6 border-b border-espresso-800">
                    <span class="text-xs uppercase font-extrabold tracking-[0.25em] text-amber-400 block">Candidate Profile</span>
                    <h1 class="text-3xl font-black font-serif text-cream-50 mt-1">Submit Your Application</h1>
                    <p class="text-xs text-cream-300/80 mt-1.5">Join the team shaping intentional, slow coffee hours.</p>
                </div>

                <?php if ($error): ?>
                    <div class="mb-6 p-4 rounded-xl bg-rose-950/80 border border-rose-800 text-rose-200 text-xs flex items-start gap-3">
                        <i class="fa-solid fa-circle-exclamation text-rose-400 mt-0.5 text-sm"></i>
                        <span class="font-medium"><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="applicant_form.php?position=<?= urlencode($selectedPosition) ?>" enctype="multipart/form-data" class="space-y-6">
                    <!-- Position Selection -->
                    <div>
                        <label for="position_applied" class="block text-xs font-bold uppercase tracking-wider text-amber-400 mb-2">
                            Applying For Position
                        </label>
                        <select id="position_applied" name="position_applied" class="w-full px-4 py-3 bg-espresso-950 border border-espresso-800 rounded-xl text-cream-100 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500">
                            <?php foreach ($positions as $pos): ?>
                                <option value="<?= e($pos) ?>" <?= $pos === $selectedPosition ? 'selected' : '' ?>>
                                    <?= e($pos) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Name Fields -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="first_name" class="block text-xs font-bold uppercase tracking-wider text-cream-300 mb-2">
                                First Name <span class="text-amber-400">*</span>
                            </label>
                            <input type="text" id="first_name" name="first_name" required
                                   value="<?= e($_POST['first_name'] ?? '') ?>"
                                   placeholder="e.g. Maria"
                                   class="w-full px-4 py-3 bg-espresso-950 border border-espresso-800 rounded-xl text-cream-100 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500">
                        </div>
                        <div>
                            <label for="last_name" class="block text-xs font-bold uppercase tracking-wider text-cream-300 mb-2">
                                Last Name <span class="text-amber-400">*</span>
                            </label>
                            <input type="text" id="last_name" name="last_name" required
                                   value="<?= e($_POST['last_name'] ?? '') ?>"
                                   placeholder="e.g. Clara"
                                   class="w-full px-4 py-3 bg-espresso-950 border border-espresso-800 rounded-xl text-cream-100 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500">
                        </div>
                    </div>

                    <!-- Contact Fields -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="email" class="block text-xs font-bold uppercase tracking-wider text-cream-300 mb-2">
                                Email Address <span class="text-amber-400">*</span>
                            </label>
                            <input type="email" id="email" name="email" required
                                   value="<?= e($_POST['email'] ?? '') ?>"
                                   placeholder="e.g. maria@example.com"
                                   class="w-full px-4 py-3 bg-espresso-950 border border-espresso-800 rounded-xl text-cream-100 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500">
                        </div>
                        <div>
                            <label for="phone" class="block text-xs font-bold uppercase tracking-wider text-cream-300 mb-2">
                                Contact Number <span class="text-amber-400">*</span>
                            </label>
                            <input type="tel" id="phone" name="phone" required
                                   value="<?= e($_POST['phone'] ?? '') ?>"
                                   placeholder="e.g. +63 917 123 4567"
                                   class="w-full px-4 py-3 bg-espresso-950 border border-espresso-800 rounded-xl text-cream-100 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500">
                        </div>
                    </div>

                    <!-- Resume Upload (PDF) -->
                    <div>
                        <label for="resume" class="block text-xs font-bold uppercase tracking-wider text-cream-300 mb-2">
                            Curriculum Vitae / Resume (PDF Only, Max 5MB) <span class="text-amber-400">*</span>
                        </label>
                        <div class="p-6 bg-espresso-950 border-2 border-dashed border-espresso-800 hover:border-amber-500/50 rounded-2xl text-center transition-colors">
                            <i class="fa-solid fa-file-pdf text-amber-400 text-3xl mb-3"></i>
                            <p class="text-xs text-cream-300 mb-3">Upload your PDF resume showcasing experience and training.</p>
                            <input type="file" id="resume" name="resume" accept="application/pdf,.pdf" required
                                   class="block w-full text-xs text-cream-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-amber-500 file:text-espresso-950 hover:file:bg-amber-400 cursor-pointer">
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-4">
                        <button type="submit" class="w-full py-4 px-6 bg-amber-500 hover:bg-amber-400 text-espresso-950 font-bold text-xs uppercase tracking-wider rounded-xl shadow-xl shadow-amber-950/60 transition-all transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                            <span>Submit Application</span>
                            <i class="fa-solid fa-paper-plane text-xs"></i>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

    </main>

    <!-- Footer -->
    <footer class="bg-espresso-950 border-t border-espresso-900 py-6 text-center text-xs text-cream-400/60">
        &copy; <?= date('Y') ?> Fika Café &middot; Slow Hours Careers Portal
    </footer>

</body>
</html>
