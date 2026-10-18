<?php
require_once __DIR__ . '/app/bootstrap.php';

global $pdo;

// Fetch open positions
$stmt = $pdo->query("SELECT * FROM job_openings WHERE status = 'OPEN' ORDER BY id ASC");
$openings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Careers & Opportunities &middot; Fika Slow Hours</title>
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
    <header class="sticky top-0 z-50 bg-stone-950/80 backdrop-blur-md border-b border-stone-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="index.php" class="flex items-center gap-3 group">
                    <img src="public/assets/logo.svg" alt="Fika Logo" class="w-12 h-12 object-contain transition-transform group-hover:scale-105 duration-200">
                    <div>
                        <span class="text-xl font-black tracking-wider text-white">FIKA</span>
                        <span class="text-xs uppercase tracking-widest text-amber-500 block font-bold">Careers Portal</span>
                    </div>
                </a>
            </div>

            <div class="flex items-center gap-4">
                <a href="index.php" class="text-sm font-semibold text-stone-400 hover:text-white transition-colors">
                    Home
                </a>
                <a href="login.php" class="inline-flex items-center gap-2 px-4 py-2 bg-stone-800 hover:bg-stone-700 text-amber-400 border border-stone-700 text-sm font-bold rounded-xl transition-all">
                    <i class="fa-solid fa-lock text-xs"></i>
                    <span>Staff Login</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Banner -->
    <section class="py-16 sm:py-20 bg-gradient-to-b from-stone-950 to-stone-900 border-b border-stone-800 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs font-bold uppercase tracking-wider mb-4">
                <i class="fa-solid fa-sparkles"></i>
                <span>Talent &amp; Hospitality Recruitment</span>
            </div>
            <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tight">
                Craft Exceptional Slow Hours With Us
            </h1>
            <p class="mt-4 text-stone-300 text-sm sm:text-base max-w-2xl mx-auto leading-relaxed">
                We believe in precision coffee craftsmanship, intentional guest hospitality, and empowering our team with transparent scheduling, competitive compensation, and clear growth paths.
            </p>
        </div>
    </section>

    <!-- Job Openings List -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8 pb-4 border-b border-stone-800">
            <div>
                <h2 class="text-2xl font-black text-white">Current Job Openings</h2>
                <p class="text-xs text-stone-400 mt-1">Found <?= count($openings) ?> active position(s) accepting applications</p>
            </div>
            <div class="text-xs text-stone-400">
                <span>Filter: All Departments</span>
            </div>
        </div>

        <?php if (empty($openings)): ?>
            <div class="p-12 text-center rounded-2xl bg-stone-950 border border-stone-800">
                <i class="fa-solid fa-mug-hot text-amber-500 text-4xl mb-4"></i>
                <h3 class="text-lg font-bold text-white">No Open Positions Currently</h3>
                <p class="text-stone-400 text-sm mt-2">Check back soon or check our social media channels for hiring announcements.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($openings as $job): ?>
                    <div class="flex flex-col justify-between p-6 rounded-2xl bg-stone-950 border border-stone-800 hover:border-amber-500/50 transition-all duration-200 group">
                        <div>
                            <!-- Header Badges -->
                            <div class="flex items-center justify-between gap-2 mb-4">
                                <span class="px-2.5 py-1 rounded-md bg-amber-500/10 text-amber-400 text-[11px] font-bold uppercase tracking-wider border border-amber-500/20">
                                    <?= htmlspecialchars($job['department'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span class="px-2.5 py-1 rounded-md bg-stone-800 text-stone-300 text-[11px] font-medium border border-stone-700">
                                    <?= htmlspecialchars($job['type'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>

                            <!-- Title & Location -->
                            <h3 class="text-xl font-bold text-white group-hover:text-amber-400 transition-colors">
                                <?= htmlspecialchars($job['title'], ENT_QUOTES, 'UTF-8') ?>
                            </h3>
                            <div class="flex items-center gap-2 text-xs text-stone-400 mt-1.5 mb-4">
                                <i class="fa-solid fa-location-dot text-amber-500"></i>
                                <span><?= htmlspecialchars($job['location'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>

                            <!-- Description -->
                            <p class="text-stone-300 text-xs sm:text-sm line-clamp-3 leading-relaxed mb-4">
                                <?= htmlspecialchars($job['description'], ENT_QUOTES, 'UTF-8') ?>
                            </p>

                            <!-- Requirements Snapshot -->
                            <div class="p-3 rounded-xl bg-stone-900 border border-stone-800 mb-6">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-amber-500 mb-1">
                                    Requirements
                                </div>
                                <p class="text-stone-400 text-xs line-clamp-2">
                                    <?= htmlspecialchars($job['requirements'], ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </div>
                        </div>

                        <!-- Apply Button -->
                        <div class="pt-4 border-t border-stone-800/80">
                            <a href="applicant_form.php?job_id=<?= (int)$job['id'] ?>&position=<?= urlencode($job['title']) ?>"
                               class="w-full inline-flex items-center justify-center gap-2 py-3 px-4 bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg transition-all transform hover:-translate-y-0.5">
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
    <footer class="bg-stone-950 text-stone-400 py-10 border-t border-stone-800 text-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <img src="public/assets/logo.svg" alt="Fika Logo" class="w-6 h-6 object-contain">
                <span>&copy; <?= date('Y') ?> Fika &middot; Slow Hours Careers Portal. All rights reserved.</span>
            </div>
            <div class="flex items-center gap-4 text-stone-400">
                <a href="index.php" class="hover:text-amber-400 transition-colors">Main Site</a>
                <a href="login.php" class="hover:text-amber-400 transition-colors">Staff Login</a>
            </div>
        </div>
    </footer>

</body>
</html>
