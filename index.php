<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/core/Output.php';

$logoUrl = 'public/assets/logo.svg';

// Query 4 active products for Menu Highlights
global $pdo;
$menuHighlights = [];
try {
    $stmt = $pdo->query("SELECT id, name, category, price, price_cents, image_path FROM products WHERE is_active = 1 AND deleted_at IS NULL ORDER BY id ASC LIMIT 4");
    if ($stmt) {
        $menuHighlights = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $menuHighlights = [];
}

// Query Flagship Branch for Visit Us section
$branch = null;
try {
    $branchStmt = $pdo->query("SELECT id, name, address, contact_number FROM branches WHERE id = 1 LIMIT 1");
    if ($branchStmt) {
        $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $branch = null;
}

$branch = $branch ?: [
    'name' => 'Fika Flagship',
    'address' => 'Bayan, Dasmarinas City, Cavite',
    'contact_number' => '0917-888-3452'
];

// Fallback high-res café imagery for items without uploaded photos
$defaultImages = [
    'https://images.unsplash.com/photo-1541167760496-1628856ab772?auto=format&fit=crop&w=800&q=80', // Spanish Latte
    'https://images.unsplash.com/photo-1515823662972-da6a2e4d3002?auto=format&fit=crop&w=800&q=80', // Iced Matcha Latte
    'https://images.unsplash.com/photo-1555507036-ab1f4038808a?auto=format&fit=crop&w=800&q=80', // Butter Croissant
    'https://images.unsplash.com/photo-1485808191679-5f86510681a2?auto=format&fit=crop&w=800&q=80'  // Caramel Macchiato
];
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fika - Slow Hours | Specialty Café & Roastery</title>
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
<body class="bg-cream-50 text-espresso-950 font-sans antialiased selection:bg-amber-600 selection:text-white">

    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-50 bg-cream-50/90 backdrop-blur-md border-b border-cream-200/80 transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <!-- Brand Logo -->
            <a href="index.php" class="flex items-center gap-3.5 group">
                <div class="relative w-12 h-12 flex-shrink-0 flex items-center justify-center">
                    <img src="<?= e($logoUrl) ?>" alt="Fika Logo" class="w-12 h-12 object-contain transition-transform group-hover:scale-105 duration-200" onerror="this.style.display='none'; document.getElementById('logo-nav-fallback').classList.remove('hidden');">
                    <div id="logo-nav-fallback" class="hidden w-12 h-12 rounded-full bg-espresso-900 text-amber-300 flex items-center justify-center font-serif font-black text-xl shadow-inner border border-amber-600/40">
                        <span>F</span>
                    </div>
                </div>
                <div>
                    <span class="text-2xl font-black tracking-wider text-espresso-900 font-serif leading-none block">FIKA</span>
                    <span class="text-[10px] uppercase tracking-[0.25em] text-amber-700 font-bold block mt-1">Slow Hours</span>
                </div>
            </a>

            <!-- Nav Links (Only Menu and Our Story - no careers redundancy) -->
            <nav class="hidden md:flex items-center gap-10 text-sm font-semibold text-espresso-800">
                <a href="#menu" class="hover:text-amber-700 transition-colors">Menu</a>
                <a href="#origins" class="hover:text-amber-700 transition-colors">Origins</a>
                <a href="#story" class="hover:text-amber-700 transition-colors">Our Story</a>
                <a href="#visit" class="hover:text-amber-700 transition-colors">Visit Us</a>
            </nav>

            <!-- Backoffice & Careers CTA -->
            <div class="flex items-center gap-4">
                <a href="applicant_form.php" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold uppercase tracking-wider text-amber-800 bg-amber-100/80 hover:bg-amber-200 rounded-full border border-amber-300/60 shadow-xs transition-all">
                    <i class="fa-solid fa-sparkles text-amber-600 text-[10px]"></i>
                    <span>Join Our Crew</span>
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
                Fika - <span class="italic font-normal text-amber-400">Slow Hours</span>
            </h1>

            <p class="mt-6 text-xl sm:text-2xl text-cream-200 font-serif italic max-w-2xl mx-auto font-normal">
                &ldquo;Take the time to sip.&rdquo;
            </p>

            <p class="mt-4 text-sm sm:text-base text-cream-300/80 max-w-xl mx-auto leading-relaxed">
                Step away from the rush. Specialty small-batch espresso, handcrafted seasonal pours, and freshly baked Swedish pastries prepared with intentional care.
            </p>

            <!-- Call to Actions -->
            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="#menu" class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-8 py-4 rounded-full bg-amber-500 hover:bg-amber-400 text-espresso-950 font-bold text-sm tracking-wider uppercase shadow-xl shadow-amber-950/50 transition-all transform hover:-translate-y-0.5">
                    <span>Explore Menu</span>
                    <i class="fa-solid fa-arrow-down text-xs"></i>
                </a>
                <a href="#story" class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-8 py-4 rounded-full bg-espresso-900/90 hover:bg-espresso-800 text-cream-100 font-bold text-sm tracking-wider uppercase border border-amber-500/30 transition-all transform hover:-translate-y-0.5">
                    <i class="fa-solid fa-mug-hot text-amber-400 text-xs"></i>
                    <span>Our Story</span>
                </a>
            </div>

            <!-- Café Pillars -->
            <div class="mt-16 pt-10 border-t border-cream-200/10 grid grid-cols-2 md:grid-cols-4 gap-6 text-cream-300 text-xs uppercase tracking-widest font-semibold">
                <div class="flex items-center justify-center gap-2">
                    <i class="fa-solid fa-seedling text-amber-400"></i>
                    <span>Highland Benguet Beans</span>
                </div>
                <div class="flex items-center justify-center gap-2">
                    <i class="fa-solid fa-fire-burner text-amber-400"></i>
                    <span>Small-Batch Roast</span>
                </div>
                <div class="flex items-center justify-center gap-2">
                    <i class="fa-solid fa-bread-slice text-amber-400"></i>
                    <span>Fresh Butter Bakes</span>
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
                        // Price calculation via Money::format()
                        $priceCents = (int)($item['price_cents'] ?? round(((float)$item['price']) * 100));
                        $formattedPrice = Money::format($priceCents);
                        
                        // Image resolution
                        $imgSrc = !empty($item['image_path']) ? $item['image_path'] : ($defaultImages[$idx % count($defaultImages)]);
                    ?>
                        <!-- Premium Card (Large image h-64, rounded-2xl, subtle shadow, hover lift) -->
                        <div class="group bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-2xl border border-cream-200/80 transition-all duration-300 flex flex-col transform hover:-translate-y-2">
                            <div class="relative h-64 overflow-hidden bg-espresso-100">
                                <img src="<?= e($imgSrc) ?>" alt="<?= e($item['name']) ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                                <div class="absolute top-3 right-3 px-3.5 py-1 bg-espresso-950/85 backdrop-blur-md text-amber-300 font-bold text-xs uppercase tracking-wider rounded-full border border-amber-500/25 shadow-sm">
                                    <?= e($item['category'] ?? 'Specialty') ?>
                                </div>
                            </div>
                            <div class="p-6 flex-1 flex flex-col justify-between">
                                <div>
                                    <h3 class="font-serif font-bold text-xl text-espresso-900 group-hover:text-amber-700 transition-colors line-clamp-1">
                                        <?= e($item['name']) ?>
                                    </h3>
                                </div>
                                <div class="mt-6 pt-4 border-t border-cream-100 flex items-center justify-between">
                                    <span class="text-2xl font-black text-espresso-900 font-serif">
                                        <?= e($formattedPrice) ?>
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-cream-100 text-espresso-700 text-[11px] font-semibold border border-cream-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        <span>Available in-store</span>
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

    <!-- Section 1: Coffee Origins -->
    <section id="origins" class="py-24 bg-cream-50 relative overflow-hidden border-b border-cream-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                <!-- Left Column: Origin Story & Benguet Highlands -->
                <div>
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-amber-100 text-amber-800 text-xs font-bold uppercase tracking-wider mb-4 border border-amber-200">
                        <i class="fa-solid fa-mountain text-amber-600 text-xs"></i>
                        <span>Philippine Highland Terroir</span>
                    </div>
                    <h2 class="text-3xl sm:text-5xl font-black font-serif text-espresso-900 leading-tight">
                        Coffee Origins &bull;<br>
                        The Benguet Highlands
                    </h2>
                    <div class="w-16 h-1 bg-amber-600 mt-4 rounded-full"></div>

                    <p class="mt-6 text-espresso-800 leading-relaxed text-base sm:text-lg">
                        Sourced from the highlands of Benguet, our beans are roasted to perfection.
                    </p>
                    <p class="mt-4 text-espresso-700/90 leading-relaxed text-sm sm:text-base">
                        Grown at over 1,500 meters above sea level under cooling mountain mists, our Arabica cherries ripen slowly on volcanic soil. This extended maturation yields a cup of remarkable balance—alive with delicate floral top notes, citrus acidity, and a smooth, lingering dark cacao finish.
                    </p>

                    <!-- Terroir Pillars -->
                    <div class="mt-8 pt-6 border-t border-cream-200 grid grid-cols-3 gap-4 text-center">
                        <div class="p-4 rounded-xl bg-white border border-cream-200 shadow-xs">
                            <span class="block font-serif text-2xl font-black text-espresso-900">1,500m</span>
                            <span class="text-[11px] uppercase tracking-wider text-amber-700 font-bold block mt-1">Highland Elevation</span>
                        </div>
                        <div class="p-4 rounded-xl bg-white border border-cream-200 shadow-xs">
                            <span class="block font-serif text-2xl font-black text-espresso-900">100%</span>
                            <span class="text-[11px] uppercase tracking-wider text-amber-700 font-bold block mt-1">Direct Trade</span>
                        </div>
                        <div class="p-4 rounded-xl bg-white border border-cream-200 shadow-xs">
                            <span class="block font-serif text-2xl font-black text-espresso-900">Small Batch</span>
                            <span class="text-[11px] uppercase tracking-wider text-amber-700 font-bold block mt-1">Artisan Roasted</span>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Image of Coffee Beans -->
                <div class="relative">
                    <div class="relative z-10 rounded-3xl overflow-hidden shadow-2xl border-4 border-white">
                        <img src="https://images.unsplash.com/photo-1559056199-641a0ac8b55e?auto=format&fit=crop&w=1200&q=85" alt="Freshly roasted specialty coffee beans" class="w-full h-[460px] object-cover">
                    </div>
                    <div class="absolute -bottom-6 -left-6 w-36 h-36 bg-amber-400/20 rounded-full blur-2xl pointer-events-none"></div>
                    <div class="absolute -top-6 -right-6 w-40 h-40 bg-espresso-900/10 rounded-full blur-2xl pointer-events-none"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Our Story Section -->
    <section id="story" class="py-24 bg-cream-100/60 relative overflow-hidden border-b border-cream-200">
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

    <!-- Section 2: The Fika Experience (Gallery) -->
    <section id="experience" class="py-24 bg-cream-50 relative overflow-hidden border-b border-cream-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <span class="text-xs uppercase font-extrabold tracking-[0.25em] text-amber-700 block">Visual Atmosphere</span>
                <h2 class="text-3xl sm:text-5xl font-black font-serif text-espresso-900 mt-2">The Fika Experience</h2>
                <div class="w-16 h-1 bg-amber-600 mx-auto mt-4 rounded-full"></div>
                <p class="text-espresso-800/80 mt-4 text-sm sm:text-base leading-relaxed">
                    Warm natural light, the gentle hum of our Mahlkönig grinders, and peaceful spaces crafted for slow conversations.
                </p>
            </div>

            <!-- 4-Column Image Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Gallery 1: Café Interior -->
                <div class="group relative h-80 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300">
                    <img src="https://images.unsplash.com/photo-1554118811-1e0d58224f24?auto=format&fit=crop&w=800&q=80" alt="Cozy Fika Café interior" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                    <div class="absolute inset-0 bg-gradient-to-t from-espresso-950/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-6">
                        <span class="text-white font-serif text-sm font-bold">Sunlit Sanctuary</span>
                    </div>
                </div>

                <!-- Gallery 2: Conversations & Sips -->
                <div class="group relative h-80 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300">
                    <img src="https://images.unsplash.com/photo-1517256064527-09c73fc73e38?auto=format&fit=crop&w=800&q=80" alt="Guests enjoying coffee and conversation" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                    <div class="absolute inset-0 bg-gradient-to-t from-espresso-950/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-6">
                        <span class="text-white font-serif text-sm font-bold">Meaningful Moments</span>
                    </div>
                </div>

                <!-- Gallery 3: Fresh Pastries -->
                <div class="group relative h-80 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300">
                    <img src="https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=800&q=80" alt="Fresh artisan baked goods" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                    <div class="absolute inset-0 bg-gradient-to-t from-espresso-950/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-6">
                        <span class="text-white font-serif text-sm font-bold">Artisan Morning Bakes</span>
                    </div>
                </div>

                <!-- Gallery 4: Slow Extraction -->
                <div class="group relative h-80 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300">
                    <img src="https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?auto=format&fit=crop&w=800&q=80" alt="Master barista pour over craft" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                    <div class="absolute inset-0 bg-gradient-to-t from-espresso-950/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-6">
                        <span class="text-white font-serif text-sm font-bold">Precision Extraction</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section 3: Guest Testimonial -->
    <section class="py-24 bg-espresso-950 text-white relative overflow-hidden border-b border-espresso-900">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-espresso-900/60 via-espresso-950 to-espresso-950 pointer-events-none"></div>
        <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <!-- 5 Star Rating -->
            <div class="flex items-center justify-center gap-1.5 text-amber-400 text-lg mb-6">
                <i class="fa-solid fa-star"></i>
                <i class="fa-solid fa-star"></i>
                <i class="fa-solid fa-star"></i>
                <i class="fa-solid fa-star"></i>
                <i class="fa-solid fa-star"></i>
            </div>

            <!-- Quote Block -->
            <blockquote class="font-serif italic text-2xl sm:text-4xl text-cream-100 leading-snug font-normal max-w-3xl mx-auto">
                &ldquo;The perfect spot to slow down. The Spanish Latte is unmatched.&rdquo;
            </blockquote>

            <!-- Attribution -->
            <div class="mt-8">
                <p class="font-bold text-base text-amber-300 tracking-wider uppercase text-xs">&mdash; Maria S.</p>
                <p class="text-xs text-cream-400/70 mt-1">Daily Guest &bull; Regular Patron</p>
            </div>
        </div>
    </section>

    <!-- Section 4: Visit Us -->
    <section id="visit" class="py-24 bg-cream-100/70 relative overflow-hidden border-b border-cream-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto bg-white rounded-3xl p-8 sm:p-14 border border-cream-200 shadow-xl">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-10 items-center">
                    <div>
                        <span class="text-xs uppercase font-extrabold tracking-[0.25em] text-amber-700 block mb-2">Our Flagship Sanctuary</span>
                        <h2 class="text-3xl sm:text-4xl font-black font-serif text-espresso-900"><?= e($branch['name']) ?></h2>
                        <div class="w-14 h-1 bg-amber-600 mt-3 rounded-full mb-6"></div>

                        <div class="space-y-5 text-sm text-espresso-800">
                            <!-- Address -->
                            <div class="flex items-start gap-3.5">
                                <div class="w-9 h-9 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <i class="fa-solid fa-location-dot text-sm"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-espresso-950">Address</p>
                                    <p class="text-espresso-700 mt-0.5"><?= e($branch['address']) ?></p>
                                </div>
                            </div>

                            <!-- Phone -->
                            <?php if (!empty($branch['contact_number'])): ?>
                            <div class="flex items-start gap-3.5">
                                <div class="w-9 h-9 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <i class="fa-solid fa-phone text-sm"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-espresso-950">Phone</p>
                                    <p class="text-espresso-700 mt-0.5"><?= e($branch['contact_number']) ?></p>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Opening Hours -->
                            <div class="flex items-start gap-3.5">
                                <div class="w-9 h-9 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <i class="fa-regular fa-clock text-sm"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-espresso-950">Opening Hours</p>
                                    <p class="text-espresso-700 mt-0.5">Monday &ndash; Sunday: 7:00 AM &ndash; 10:00 PM</p>
                                </div>
                            </div>
                        </div>

                        <!-- Directions Button -->
                        <div class="mt-8">
                            <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($branch['address']) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2.5 px-6 py-3.5 rounded-full bg-espresso-900 hover:bg-espresso-800 text-cream-100 font-bold text-xs uppercase tracking-wider transition-all shadow-md transform hover:-translate-y-0.5 border border-amber-600/30">
                                <i class="fa-solid fa-diamond-turn-right text-amber-400"></i>
                                <span>Get Directions</span>
                            </a>
                        </div>
                    </div>

                    <!-- Visual Accent Card -->
                    <div class="relative h-64 sm:h-80 rounded-2xl overflow-hidden shadow-inner bg-espresso-100">
                        <img src="https://images.unsplash.com/photo-1521017432531-fbd92d768814?auto=format&fit=crop&w=800&q=80" alt="Fika café front" class="w-full h-full object-cover">
                        <div class="absolute bottom-4 left-4 right-4 bg-espresso-950/80 backdrop-blur-md rounded-xl p-3.5 text-cream-100 text-xs border border-white/10 flex items-center justify-between">
                            <span>Dine-in &bull; Outdoor Patio &bull; Free Wi-Fi</span>
                            <span class="text-amber-400 font-bold">Open Daily</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section 5: Join the Fika Club (Newsletter) -->
    <section id="newsletter" class="py-24 bg-espresso-900 text-white relative overflow-hidden">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <div class="w-16 h-16 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center mx-auto mb-6 text-2xl shadow-inner">
                <i class="fa-regular fa-envelope-open"></i>
            </div>
            <h2 class="text-3xl sm:text-5xl font-black font-serif text-cream-50 leading-tight">Join the Fika Club</h2>
            <div class="w-16 h-1 bg-amber-500 mx-auto mt-4 rounded-full"></div>
            <p class="text-cream-200/80 mt-4 text-sm sm:text-base max-w-xl mx-auto leading-relaxed">
                Receive private roaster cupping notes, secret seasonal pastry drop invites, and mindful slow-coffee rituals delivered once a month.
            </p>

            <form action="#newsletter" method="GET" class="mt-10 max-w-md mx-auto flex flex-col sm:flex-row gap-3">
                <input type="email" placeholder="Enter your email address" required class="flex-1 px-5 py-4 rounded-full bg-espresso-950/90 border border-amber-500/30 text-cream-100 placeholder-cream-400/50 text-sm focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20">
                <button type="submit" class="px-8 py-4 rounded-full bg-amber-500 hover:bg-amber-400 text-espresso-950 font-bold text-xs uppercase tracking-wider shadow-lg shadow-amber-950/40 transition-all transform hover:-translate-y-0.5">
                    Subscribe
                </button>
            </form>
            <p class="text-[11px] text-cream-400/50 mt-4">We respect your slow hours. No spam, unsubscribe anytime.</p>
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
                            <img src="<?= e($logoUrl) ?>" alt="Fika Logo" class="w-10 h-10 object-contain" onerror="this.style.display='none'; document.getElementById('logo-footer-fallback').classList.remove('hidden');">
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
                        <li><a href="#origins" class="hover:text-amber-300 transition-colors">Coffee Origins</a></li>
                        <li><a href="#story" class="hover:text-amber-300 transition-colors">Our Story & Roastery</a></li>
                        <li><a href="#visit" class="hover:text-amber-300 transition-colors">Visit Our Café</a></li>
                        <li><a href="applicant_form.php" class="hover:text-amber-300 transition-colors">Join Our Team (Careers)</a></li>
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
                        <span>Staff Login</span>
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
