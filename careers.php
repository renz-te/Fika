<?php
if (!function_exists('e')) {
    function e(?string $str): string {
        return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// Hardcoded curated job openings for legacy main branch
$openings = [
    [
        'id' => 1,
        'title' => 'Head Barista',
        'department' => 'Operations',
        'type' => 'Full-Time',
        'location' => 'Main Roastery & Flagship',
        'description' => 'Lead specialty espresso extraction calibration, latte art standards, and mentor the barista crew in our signature Slow Hours experience.',
        'requirements' => 'Minimum 2 years specialty coffee experience, dial-in mastery, and team leadership skills.'
    ],
    [
        'id' => 2,
        'title' => 'Barista',
        'department' => 'Operations',
        'type' => 'Full-Time / Part-Time',
        'location' => 'Branch 2 (Espresso Rd)',
        'description' => 'Handcraft specialty espresso, pourovers, and signature beverages while offering warm, attentive hospitality to our café guests.',
        'requirements' => 'Passion for hospitality, customer service orientation, and eagerness to learn third-wave coffee techniques.'
    ],
    [
        'id' => 3,
        'title' => 'Branch Accountant',
        'department' => 'Finance',
        'type' => 'Full-Time',
        'location' => 'Central Backoffice',
        'description' => 'Manage daily cashier drawer reconciliation, operating expense disbursements, inventory audits, and local tax compliance.',
        'requirements' => 'Degree in Accountancy or Finance, experience in retail/restaurant accounting, and high attention to detail.'
    ],
    [
        'id' => 4,
        'title' => 'Café Shift Supervisor',
        'department' => 'Operations',
        'type' => 'Full-Time',
        'location' => 'All Branches',
        'description' => 'Oversee floor operations during peak hours, coordinate barista breaks, monitor ingredient inventory levels, and ensure guest satisfaction.',
        'requirements' => 'At least 1 year supervisory experience in hospitality or high-volume café environment.'
    ]
];

// Dynamic logo path for local subdirectory and production root
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$cleanDir = str_replace('\\', '/', $scriptDir);
$baseDir = ($cleanDir === '/' || $cleanDir === '.') ? '' : rtrim($cleanDir, '/');
$logoUrl = $baseDir . '/public/assets/logo.svg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Careers &amp; Opportunities &middot; Fika Slow Hours</title>
    <link rel="icon" type="image/svg+xml" href="<?= $logoUrl ?>">
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

    <!-- Header Navigation -->
    <header class="sticky top-0 z-50 bg-espresso-950/90 backdrop-blur-md border-b border-espresso-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="index.php" class="flex items-center gap-3.5 group">
                    <div class="relative w-12 h-12 flex-shrink-0 flex items-center justify-center">
                        <img src="<?= $logoUrl ?>" alt="Fika Logo" class="w-12 h-12 object-contain transition-transform group-hover:scale-105 duration-200" onerror="this.style.display='none'; document.getElementById('logo-careers-fallback').classList.remove('hidden');">
                        <div id="logo-careers-fallback" class="hidden w-12 h-12 rounded-full bg-espresso-900 text-amber-300 flex items-center justify-center font-serif font-black text-xl shadow-inner border border-amber-600/40">
                            <span>F</span>
                        </div>
                    </div>
                    <div>
                        <span class="text-2xl font-black tracking-wider text-cream-50 font-serif leading-none block">FIKA</span>
                        <span class="text-[10px] uppercase tracking-[0.25em] text-amber-400 font-bold block mt-1">Careers Portal</span>
                    </div>
                </a>
            </div>

            <div class="flex items-center gap-4">
                <a href="index.php" class="text-sm font-semibold text-cream-300 hover:text-white transition-colors">
                    Home
                </a>
                <a href="login.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-espresso-900 hover:bg-espresso-800 text-amber-300 border border-amber-600/30 text-xs font-bold uppercase tracking-wider rounded-full shadow-md transition-all transform hover:-translate-y-0.5">
                    <i class="fa-solid fa-lock text-xs"></i>
                    <span>Staff Login</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Banner -->
    <section class="py-16 sm:py-24 bg-gradient-to-b from-espresso-950 via-espresso-900 to-espresso-950 border-b border-espresso-900 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs font-bold uppercase tracking-wider mb-6">
                <i class="fa-solid fa-sparkles text-amber-400"></i>
                <span>Talent &amp; Hospitality Recruitment</span>
            </div>
            <h1 class="text-4xl sm:text-6xl font-black font-serif text-cream-50 tracking-tight leading-tight">
                Craft Exceptional <span class="italic font-normal text-amber-400">Slow Hours</span> With Us
            </h1>
            <p class="mt-6 text-cream-200 text-base sm:text-lg max-w-2xl mx-auto leading-relaxed">
                We believe in precision coffee craftsmanship, intentional guest hospitality, and empowering our team with transparent scheduling, competitive compensation, and clear growth paths.
            </p>
        </div>
    </section>

    <!-- Job Openings List -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8 pb-4 border-b border-espresso-900">
            <div>
                <h2 class="text-2xl sm:text-3xl font-black font-serif text-cream-50">Current Opportunities</h2>
                <p class="text-xs text-cream-300/80 mt-1">Accepting applications for <?= count($openings) ?> active position(s)</p>
            </div>
            <div class="text-xs text-amber-400 font-semibold flex items-center gap-2">
                <i class="fa-solid fa-location-dot"></i>
                <span>All Branches &amp; Roasteries</span>
            </div>
        </div>

        <?php if (empty($openings)): ?>
            <div class="p-12 text-center rounded-2xl bg-espresso-900/60 border border-espresso-800">
                <i class="fa-solid fa-mug-hot text-amber-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-bold text-white">No Open Positions Currently</h3>
                <p class="text-cream-300 text-sm mt-2">Check back soon or follow our social channels for recruitment updates.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <?php foreach ($openings as $job): ?>
                    <div class="flex flex-col justify-between p-8 rounded-2xl bg-espresso-900/70 border border-espresso-800 hover:border-amber-500/50 transition-all duration-300 group shadow-lg">
                        <div>
                            <!-- Header Badges -->
                            <div class="flex items-center justify-between gap-2 mb-4">
                                <span class="px-3 py-1 rounded-full bg-amber-500/10 text-amber-300 text-xs font-bold uppercase tracking-wider border border-amber-500/20">
                                    <?= e($job['department']) ?>
                                </span>
                                <span class="px-3 py-1 rounded-full bg-espresso-800 text-cream-200 text-xs font-medium border border-espresso-700">
                                    <?= e($job['type']) ?>
                                </span>
                            </div>

                            <!-- Title & Location -->
                            <h3 class="text-2xl font-black font-serif text-cream-50 group-hover:text-amber-400 transition-colors">
                                <?= e($job['title']) ?>
                            </h3>
                            <div class="flex items-center gap-2 text-xs text-amber-300/80 mt-2 mb-4">
                                <i class="fa-solid fa-location-dot text-amber-400"></i>
                                <span><?= e($job['location']) ?></span>
                            </div>

                            <!-- Description -->
                            <p class="text-cream-200 text-sm leading-relaxed mb-6">
                                <?= e($job['description']) ?>
                            </p>

                            <!-- Requirements Snapshot -->
                            <div class="p-4 rounded-xl bg-espresso-950/80 border border-espresso-800 mb-8">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-amber-400 mb-1 flex items-center gap-1.5">
                                    <i class="fa-solid fa-circle-check text-xs"></i>
                                    <span>Key Requirements</span>
                                </div>
                                <p class="text-cream-300/90 text-xs leading-relaxed">
                                    <?= e($job['requirements']) ?>
                                </p>
                            </div>
                        </div>

                        <!-- Apply Button -->
                        <div class="pt-6 border-t border-espresso-800">
                            <a href="applicant_form.php?job_id=<?= (int)$job['id'] ?>&position=<?= urlencode($job['title']) ?>"
                               class="w-full inline-flex items-center justify-center gap-2 py-3.5 px-6 bg-amber-500 hover:bg-amber-400 text-espresso-950 font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-amber-950/50 transition-all transform hover:-translate-y-0.5">
                                <span>Apply for Position</span>
                                <i class="fa-solid fa-arrow-right text-[10px]"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="bg-espresso-950 text-cream-300 py-10 border-t border-espresso-900 text-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <img src="public/assets/logo.svg" alt="Fika Logo" class="w-6 h-6 object-contain">
                <span>&copy; <?= date('Y') ?> Fika Café &middot; Slow Hours Careers Portal. All rights reserved.</span>
            </div>
            <div class="flex items-center gap-6">
                <a href="index.php" class="hover:text-amber-300 transition-colors">Main Site</a>
                <a href="login.php" class="hover:text-amber-300 transition-colors">Staff Backoffice</a>
            </div>
        </div>
    </footer>

</body>
</html>
