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
    // Graceful fallback if database connection fails
    $pdo = null;
}

if (!function_exists('e')) {
    function e(?string $str): string {
        return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// 1. Query Menu Highlights from cafe_pos.products (with fallback to products)
$menuHighlights = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, name, category, price, image_path FROM cafe_pos.products WHERE is_active = 1 ORDER BY id ASC LIMIT 4");
        if ($stmt) {
            $menuHighlights = $stmt->fetchAll();
        }
    } catch (\Throwable $t) {
        try {
            $stmt = $pdo->query("SELECT id, name, category, price, image_path FROM products WHERE is_active = 1 ORDER BY id ASC LIMIT 4");
            if ($stmt) {
                $menuHighlights = $stmt->fetchAll();
            }
        } catch (\Throwable $t2) {
            $menuHighlights = [];
        }
    }
}

// 2. Query Locations from branches table
$branches = [];
if ($pdo) {
    try {
        $bStmt = $pdo->query("SELECT id, name, address, contact_number, status FROM branches WHERE status = 'Active' ORDER BY id ASC");
        if ($bStmt) {
            $branches = $bStmt->fetchAll();
        }
    } catch (\Throwable $t) {
        $branches = [];
    }
}

// High-res curated fallback images for café drink highlights
$defaultImages = [
    'https://images.unsplash.com/photo-1541167760496-1628856ab772?auto=format&fit=crop&w=800&q=80', // Latte art
    'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?auto=format&fit=crop&w=800&q=80', // Rich espresso
    'https://images.unsplash.com/photo-1517701550927-30cf4ba1dba5?auto=format&fit=crop&w=800&q=80', // Iced brew
    'https://images.unsplash.com/photo-1509785307050-d4066910ec1e?auto=format&fit=crop&w=800&q=80'  // Pour over
];

// Dynamic logo path for local subdirectory and production root
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$cleanDir = str_replace('\\', '/', $scriptDir);
$baseDir = ($cleanDir === '/' || $cleanDir === '.') ? '' : rtrim($cleanDir, '/');
$logoUrl = $baseDir . '/public/assets/logo.svg';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fika &middot; Slow Hours | Specialty Café & Roastery</title>
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
<body class="bg-cream-50 text-espresso-950 font-sans antialiased selection:bg-amber-600 selection:text-white">

    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-50 bg-cream-50/90 backdrop-blur-md border-b border-cream-200/80 transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <!-- Brand Logo -->
            <a href="index.php" class="flex items-center gap-3.5 group">
                <div class="relative w-12 h-12 flex-shrink-0 flex items-center justify-center">
                    <img src="<?= $logoUrl ?>" alt="Fika Logo" class="w-12 h-12 object-contain transition-transform group-hover:scale-105 duration-200" onerror="this.style.display='none'; document.getElementById('logo-nav-fallback').classList.remove('hidden');">
                    <div id="logo-nav-fallback" class="hidden w-12 h-12 rounded-full bg-espresso-900 text-amber-300 flex items-center justify-center font-serif font-black text-xl shadow-inner border border-amber-600/40">
                        <span>F</span>
                    </div>
                </div>
                <div>
                    <span class="text-2xl font-black tracking-wider text-espresso-900 font-serif leading-none block">FIKA</span>
                    <span class="text-[10px] uppercase tracking-[0.25em] text-amber-700 font-bold block mt-1">Slow Hours</span>
                </div>
            </a>

            <!-- Nav Links -->
            <nav class="hidden md:flex items-center gap-8 text-sm font-semibold text-espresso-800">
                <a href="#menu" class="hover:text-amber-700 transition-colors">Menu</a>
                <a href="#story" class="hover:text-amber-700 transition-colors">Our Story</a>
                <a href="#locations" class="hover:text-amber-700 transition-colors">Locations</a>
                <a href="careers.php" class="hover:text-amber-700 transition-colors flex items-center gap-1.5">
                    <span>Careers</span>
                    <span class="px-1.5 py-0.5 text-[10px] font-bold bg-amber-100 text-amber-800 rounded-full border border-amber-200">Hiring</span>
                </a>
            </nav>

            <!-- Backoffice CTA -->
            <div class="flex items-center gap-3">
                <a href="careers.php" class="hidden lg:inline-flex items-center gap-2 px-4 py-2 text-xs font-bold uppercase tracking-wider text-amber-800 hover:text-amber-900 transition-colors">
                    Join Our Crew
                </a>
                <a href="login.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-espresso-900 hover:bg-espresso-800 text-amber-100 font-bold text-xs uppercase tracking-wider rounded-full shadow-md shadow-espresso-950/20 transition-all transform hover:-translate-y-0.5 border border-amber-600/30">
                    <i class="fa-solid fa-mug-hot text-amber-400 text-xs"></i>
                    <span>Staff Login</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="relative min-h-[85vh] flex items-center justify-center overflow-hidden bg-espresso-950 text-white">
        <!-- Background Imagery with Warm Espresso Vignette -->
        <div class="absolute inset-0 bg-cover bg-center opacity-45 mix-blend-overlay" style="background-image: url('https://images.unsplash.com/photo-1501339847302-ac426a4a7cbb?auto=format&fit=crop&w=2000&q=85');"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-espresso-950 via-espresso-900/80 to-espresso-950/90 pointer-events-none"></div>

        <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-24 text-center">
            <!-- Warm Pill Badge -->
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-espresso-800/80 border border-amber-500/30 text-amber-300 text-xs sm:text-sm font-semibold mb-8 shadow-lg backdrop-blur-sm">
                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                <span class="tracking-widest uppercase text-[11px]">The Swedish Ritual of Slow Coffee</span>
            </div>

            <!-- Hero Headline -->
            <h1 class="text-5xl sm:text-7xl lg:text-8xl font-black font-serif tracking-tight text-cream-50 leading-[1.05]">
                Fika &middot; <span class="italic font-normal text-amber-400">Slow Hours</span>
            </h1>

            <p class="mt-6 text-xl sm:text-2xl text-cream-200 font-serif italic max-w-2xl mx-auto font-normal">
                &ldquo;Take the time to sip.&rdquo;
            </p>

            <p class="mt-4 text-sm sm:text-base text-cream-300/80 max-w-xl mx-auto leading-relaxed">
                Step away from the rush. Specialty small-batch espresso, handcrafted seasonal pours, and freshly baked pastries prepared with intentional care.
            </p>

            <!-- Call to Actions -->
            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="#menu" class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-8 py-4 rounded-full bg-amber-500 hover:bg-amber-400 text-espresso-950 font-bold text-sm tracking-wider uppercase shadow-xl shadow-amber-950/50 transition-all transform hover:-translate-y-0.5">
                    <span>Explore Menu</span>
                    <i class="fa-solid fa-arrow-down text-xs"></i>
                </a>
                <a href="#locations" class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-8 py-4 rounded-full bg-espresso-900/90 hover:bg-espresso-800 text-cream-100 font-bold text-sm tracking-wider uppercase border border-amber-500/30 transition-all transform hover:-translate-y-0.5">
                    <i class="fa-solid fa-location-dot text-amber-400 text-xs"></i>
                    <span>Find Our Cafés</span>
                </a>
            </div>

            <!-- Café Pillars -->
            <div class="mt-16 pt-10 border-t border-cream-200/10 grid grid-cols-2 md:grid-cols-4 gap-6 text-cream-300 text-xs uppercase tracking-widest font-semibold">
                <div class="flex items-center justify-center gap-2">
                    <i class="fa-solid fa-seedling text-amber-400"></i>
                    <span>Direct Trade Beans</span>
                </div>
                <div class="flex items-center justify-center gap-2">
                    <i class="fa-solid fa-fire-burner text-amber-400"></i>
                    <span>Small-Batch Roast</span>
                </div>
                <div class="flex items-center justify-center gap-2">
                    <i class="fa-solid fa-bread-slice text-amber-400"></i>
                    <span>House Bakes Daily</span>
                </div>
                <div class="flex items-center justify-center gap-2">
                    <i class="fa-solid fa-heart text-amber-400"></i>
                    <span>Zero Rush Space</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Menu Highlights Section -->
    <section id="menu" class="py-24 bg-cream-100/70 border-b border-cream-200 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <span class="text-xs uppercase font-extrabold tracking-[0.25em] text-amber-700 block">Crafted with Purpose</span>
                <h2 class="text-3xl sm:text-5xl font-black font-serif text-espresso-900 mt-2">Menu Highlights</h2>
                <div class="w-16 h-1 bg-amber-600 mx-auto mt-4 rounded-full"></div>
                <p class="text-espresso-800/80 mt-4 text-sm sm:text-base leading-relaxed">
                    A taste of our specialty offerings. Sourced responsibly from high-altitude micro-lots and extracted to exacting brew ratios.
                </p>
            </div>

            <?php if (!empty($menuHighlights)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                    <?php foreach ($menuHighlights as $idx => $item): 
                        $price = (float)($item['price'] ?? 0);
                        $formattedPrice = '₱' . number_format($price, 2);
                        $imgSrc = !empty($item['image_path']) ? $item['image_path'] : ($defaultImages[$idx % count($defaultImages)]);
                    ?>
                        <div class="group bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl border border-cream-200 transition-all duration-300 flex flex-col transform hover:-translate-y-1">
                            <div class="relative h-56 overflow-hidden bg-espresso-100">
                                <img src="<?= e($imgSrc) ?>" alt="<?= e($item['name']) ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                                <div class="absolute top-3 right-3 px-3 py-1 bg-espresso-950/80 backdrop-blur-sm text-amber-300 font-bold text-xs uppercase tracking-wider rounded-full border border-amber-500/20">
                                    <?= e($item['category'] ?? 'Specialty') ?>
                                </div>
                            </div>
                            <div class="p-6 flex-1 flex flex-col justify-between">
                                <div>
                                    <h3 class="font-serif font-bold text-xl text-espresso-900 group-hover:text-amber-700 transition-colors line-clamp-1">
                                        <?= e($item['name']) ?>
                                    </h3>
                                    <p class="text-xs text-espresso-700/70 mt-1 uppercase tracking-wider">
                                        Single Origin &bull; Artisan Crafted
                                    </p>
                                </div>
                                <div class="mt-6 pt-4 border-t border-cream-100 flex items-center justify-between">
                                    <span class="text-lg font-black text-espresso-900 font-serif">
                                        <?= e($formattedPrice) ?>
                                    </span>
                                    <span class="inline-flex items-center text-xs font-bold text-amber-700 group-hover:text-amber-800">
                                        <span>Order at Counter</span>
                                        <i class="fa-solid fa-chevron-right text-[10px] ml-1"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Graceful empty state -->
                <div class="bg-white rounded-2xl p-12 text-center max-w-md mx-auto border border-cream-200 shadow-sm">
                    <div class="w-16 h-16 rounded-full bg-cream-100 text-amber-700 flex items-center justify-center mx-auto mb-4 text-2xl">
                        <i class="fa-solid fa-mug-saucer"></i>
                    </div>
                    <h3 class="font-serif font-bold text-xl text-espresso-900">New Seasonal Menu Brewing</h3>
                    <p class="text-sm text-espresso-700/80 mt-2">
                        Our baristas are curating the next seasonal rotation. Visit our counters today to explore current roaster selects.
                    </p>
                </div>
            <?php endif; ?>

            <div class="text-center mt-12">
                <p class="text-xs uppercase tracking-widest text-espresso-700/70">
                    Oat, almond, and lactose-free milk options available on request.
                </p>
            </div>
        </div>
    </section>

    <!-- Our Story Section -->
    <section id="story" class="py-24 bg-cream-50 relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                <!-- Visual Collage -->
                <div class="relative">
                    <div class="relative z-10 rounded-3xl overflow-hidden shadow-2xl border-4 border-white">
                        <img src="https://images.unsplash.com/photo-1442512595331-e89e73853f31?auto=format&fit=crop&w=1000&q=80" alt="Artisan brewing coffee" class="w-full h-[450px] object-cover">
                    </div>
                    <div class="hidden sm:block absolute -bottom-8 -right-8 w-64 rounded-2xl overflow-hidden shadow-2xl border-4 border-white z-20">
                        <img src="https://images.unsplash.com/photo-1511920170033-f8396924c348?auto=format&fit=crop&w=600&q=80" alt="Fresh cinnamon buns" class="w-full h-48 object-cover">
                    </div>
                    <div class="absolute -top-6 -left-6 w-32 h-32 bg-amber-400/20 rounded-full blur-2xl pointer-events-none"></div>
                </div>

                <!-- Story Narrative -->
                <div>
                    <span class="text-xs uppercase font-extrabold tracking-[0.25em] text-amber-700 block">The Art of Pausing</span>
                    <h2 class="text-3xl sm:text-5xl font-black font-serif text-espresso-900 mt-2 leading-tight">
                        More Than Coffee.<br>
                        A State of Mind.
                    </h2>
                    <div class="w-16 h-1 bg-amber-600 mt-4 rounded-full"></div>

                    <p class="mt-6 text-espresso-800 leading-relaxed text-base sm:text-lg">
                        In Sweden, <strong class="text-espresso-950 font-semibold">fika</strong> is not a coffee break—it is a cultural imperative. It means carving out sacred time in the middle of a chaotic day to pause, savor a warm beverage, and share meaningful conversation.
                    </p>

                    <p class="mt-4 text-espresso-700 leading-relaxed text-sm sm:text-base">
                        At Fika Café, we created <span class="font-serif italic text-amber-800">Slow Hours</span> as an antidote to rush-hour culture. From our calibrated extraction profiles to our quiet, sun-drenched oak seating, everything is deliberately paced so you can unwind and reconnect.
                    </p>

                    <div class="mt-8 pt-6 border-t border-cream-200 grid grid-cols-2 gap-6">
                        <div>
                            <span class="block font-serif text-3xl font-black text-espresso-900">100%</span>
                            <span class="text-xs uppercase tracking-wider text-espresso-700 font-semibold">Ethically Sourced Arabica</span>
                        </div>
                        <div>
                            <span class="block font-serif text-3xl font-black text-espresso-900">0 min</span>
                            <span class="text-xs uppercase tracking-wider text-espresso-700 font-semibold">Pressure to Leave</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Locations Section -->
    <section id="locations" class="py-24 bg-cream-100/60 border-t border-cream-200 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <span class="text-xs uppercase font-extrabold tracking-[0.25em] text-amber-700 block">Come Sit With Us</span>
                <h2 class="text-3xl sm:text-5xl font-black font-serif text-espresso-900 mt-2">Our Locations</h2>
                <div class="w-16 h-1 bg-amber-600 mx-auto mt-4 rounded-full"></div>
                <p class="text-espresso-800/80 mt-4 text-sm sm:text-base">
                    Find your neighborhood sanctuary. Each Fika space is designed with cozy corners and natural light.
                </p>
            </div>

            <?php if (!empty($branches)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php foreach ($branches as $branch): ?>
                        <div class="bg-white rounded-2xl p-8 border border-cream-200 shadow-sm hover:shadow-lg transition-all flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <div class="w-10 h-10 rounded-full bg-cream-100 text-amber-700 flex items-center justify-center text-lg">
                                        <i class="fa-solid fa-mug-hot"></i>
                                    </div>
                                    <span class="px-2.5 py-1 text-[11px] font-bold bg-amber-100/80 text-amber-800 rounded-full border border-amber-200">
                                        Open Daily
                                    </span>
                                </div>
                                <h3 class="font-serif font-bold text-2xl text-espresso-900 mb-2">
                                    <?= e($branch['name']) ?>
                                </h3>
                                <p class="text-sm text-espresso-700/90 leading-relaxed mb-4">
                                    <i class="fa-solid fa-location-dot text-amber-600 mr-2"></i>
                                    <?= !empty($branch['address']) ? e($branch['address']) : 'Specialty Café & Espresso Bar' ?>
                                </p>
                                <?php if (!empty($branch['contact_number'])): ?>
                                    <p class="text-xs text-espresso-600 flex items-center gap-2">
                                        <i class="fa-solid fa-phone text-amber-600"></i>
                                        <span><?= e($branch['contact_number']) ?></span>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <div class="mt-8 pt-6 border-t border-cream-100 flex items-center justify-between text-xs text-espresso-600">
                                <span class="flex items-center gap-1.5">
                                    <i class="fa-regular fa-clock text-amber-600"></i>
                                    <span>7:00 AM &ndash; 10:00 PM</span>
                                </span>
                                <span class="font-bold text-amber-800">Dine-in &bull; Takeout</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Graceful fallback -->
                <div class="bg-white rounded-2xl p-10 text-center max-w-md mx-auto border border-cream-200">
                    <p class="text-espresso-800 font-serif text-lg font-bold">Main Flagship Roastery</p>
                    <p class="text-sm text-espresso-600 mt-2">123 Coffee Boulevard, Cityville &bull; Open 7am - 10pm</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-espresso-950 text-cream-200 pt-16 pb-12 border-t border-espresso-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-10 pb-12 border-b border-espresso-800/80">
                <!-- Brand Info -->
                <div class="md:col-span-2">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="relative w-10 h-10 flex-shrink-0 flex items-center justify-center">
                            <img src="<?= $logoUrl ?>" alt="Fika Logo" class="w-10 h-10 object-contain" onerror="this.style.display='none'; document.getElementById('logo-footer-fallback').classList.remove('hidden');">
                            <div id="logo-footer-fallback" class="hidden w-10 h-10 rounded-full bg-espresso-900 text-amber-400 flex items-center justify-center font-serif font-black text-lg border border-amber-500/30">
                                <span>F</span>
                            </div>
                        </div>
                        <div>
                            <span class="text-2xl font-black tracking-wider text-cream-50 font-serif leading-none block">FIKA</span>
                            <span class="text-[9px] uppercase tracking-[0.25em] text-amber-400 font-bold block mt-1">Slow Hours</span>
                        </div>
                    </div>
                    <p class="text-xs sm:text-sm text-cream-300/80 max-w-sm leading-relaxed mb-6">
                        Dedicated to slow hours, intentional sips, and the timeless tradition of European café culture.
                    </p>
                    <!-- Social Icons -->
                    <div class="flex items-center gap-4 text-amber-400">
                        <a href="#" aria-label="Instagram" class="w-9 h-9 rounded-full bg-espresso-900 hover:bg-amber-600 hover:text-espresso-950 flex items-center justify-center transition-colors">
                            <i class="fa-brands fa-instagram text-sm"></i>
                        </a>
                        <a href="#" aria-label="Facebook" class="w-9 h-9 rounded-full bg-espresso-900 hover:bg-amber-600 hover:text-espresso-950 flex items-center justify-center transition-colors">
                            <i class="fa-brands fa-facebook-f text-sm"></i>
                        </a>
                        <a href="#" aria-label="TikTok" class="w-9 h-9 rounded-full bg-espresso-900 hover:bg-amber-600 hover:text-espresso-950 flex items-center justify-center transition-colors">
                            <i class="fa-brands fa-tiktok text-sm"></i>
                        </a>
                    </div>
                </div>

                <!-- Navigation Links -->
                <div>
                    <h4 class="text-xs uppercase tracking-widest text-amber-400 font-bold mb-4">Explore</h4>
                    <ul class="space-y-2.5 text-xs text-cream-300">
                        <li><a href="#menu" class="hover:text-amber-300 transition-colors">Menu Highlights</a></li>
                        <li><a href="#story" class="hover:text-amber-300 transition-colors">Our Story & Roastery</a></li>
                        <li><a href="#locations" class="hover:text-amber-300 transition-colors">Café Locations</a></li>
                        <li><a href="careers.php" class="hover:text-amber-300 transition-colors">Join Our Team (Careers)</a></li>
                    </ul>
                </div>

                <!-- Operations & Staff -->
                <div>
                    <h4 class="text-xs uppercase tracking-widest text-amber-400 font-bold mb-4">Operations</h4>
                    <p class="text-xs text-cream-300/80 mb-4 leading-relaxed">
                        Authorized staff and management backoffice portal access.
                    </p>
                    <a href="login.php" class="inline-flex items-center gap-2 px-4 py-2 bg-espresso-900 hover:bg-espresso-800 text-amber-300 hover:text-amber-200 text-xs font-semibold rounded-lg border border-espresso-800 transition-colors">
                        <i class="fa-solid fa-lock text-[10px]"></i>
                        <span>Backoffice Login</span>
                    </a>
                </div>
            </div>

            <!-- Copyright Notice -->
            <div class="pt-8 flex flex-col sm:flex-row items-center justify-between text-xs text-cream-400/60">
                <p>&copy; <?= date('Y') ?> Fika Café. All rights reserved. &bull; Slow Hours Roastery</p>
                <p class="mt-2 sm:mt-0 font-serif italic text-cream-300/80">Take the time to sip.</p>
            </div>
        </div>
    </footer>

</body>
</html>
