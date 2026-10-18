<?php
// Ensure bootstrap is available if needed for configuration or sessions
require_once __DIR__ . '/app/bootstrap.php';

$pageTitle = 'Enterprise Café Management & POS Suite';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fika &middot; Slow Hours | Enterprise Café Management</title>
    <link rel="icon" type="image/svg+xml" href="public/assets/logo.svg">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        espresso: {
                            50: '#fbf8f5',
                            100: '#f5efe9',
                            200: '#ebdcd0',
                            300: '#dbc0ad',
                            400: '#c59d83',
                            700: '#5c3821',
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
<body class="bg-stone-900 text-stone-100 font-sans antialiased selection:bg-amber-500 selection:text-white">

    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-50 bg-stone-950/80 backdrop-blur-md border-b border-stone-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="index.php" class="flex items-center gap-3 group">
                    <img src="public/assets/logo.svg" alt="Fika Logo" class="w-12 h-12 object-contain transition-transform group-hover:scale-105 duration-200">
                    <div>
                        <span class="text-xl font-black tracking-wider text-white">FIKA</span>
                        <span class="text-xs uppercase tracking-widest text-amber-500 block font-bold">Slow Hours</span>
                    </div>
                </a>
            </div>

            <!-- Desktop Nav Items -->
            <nav class="hidden md:flex items-center gap-8 text-sm font-semibold text-stone-300">
                <a href="#modules" class="hover:text-amber-400 transition-colors">Modules</a>
                <a href="#architecture" class="hover:text-amber-400 transition-colors">Enterprise Platform</a>
                <a href="careers.php" class="hover:text-amber-400 transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-briefcase text-amber-500 text-xs"></i>
                    <span>Careers</span>
                </a>
            </nav>

            <!-- Actions -->
            <div class="flex items-center gap-3">
                <a href="careers.php" class="hidden sm:inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-amber-400 border border-amber-500/40 hover:bg-amber-500/10 rounded-xl transition-all">
                    <span>We're Hiring</span>
                </a>
                <a href="login.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-amber-600 to-amber-500 hover:from-amber-500 hover:to-amber-600 text-stone-950 font-bold text-sm rounded-xl shadow-lg shadow-amber-900/30 transition-all transform hover:-translate-y-0.5">
                    <i class="fa-solid fa-arrow-right-to-bracket text-xs"></i>
                    <span>Staff Login</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="relative overflow-hidden pt-12 pb-24 sm:pt-20 sm:pb-32">
        <!-- Radial Glow Background -->
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[700px] bg-amber-600/10 blur-[130px] rounded-full pointer-events-none"></div>
        <div class="absolute top-1/3 left-1/4 w-[400px] h-[400px] bg-espresso-800/30 blur-[100px] rounded-full pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <!-- Premium Center Logo (Hero Visual) -->
            <div class="flex justify-center mb-8">
                <div class="relative group">
                    <div class="absolute -inset-1 rounded-full bg-gradient-to-r from-amber-600 to-amber-400 opacity-30 blur group-hover:opacity-60 transition duration-500"></div>
                    <img src="public/assets/logo.svg" alt="Fika Logo Large" class="relative w-48 h-48 sm:w-56 sm:h-56 object-contain drop-shadow-2xl">
                </div>
            </div>

            <!-- Hero Badge -->
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-stone-800/80 border border-stone-700/80 text-amber-400 text-xs sm:text-sm font-semibold mb-6">
                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                <span>Enterprise Café Management Suite &bull; Slow Hours Architecture</span>
            </div>

            <!-- Headline -->
            <h1 class="text-4xl sm:text-6xl font-black text-white tracking-tight leading-tight sm:leading-none max-w-4xl mx-auto">
                Specialty Hospitality <br class="hidden sm:inline" />
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-400 via-amber-200 to-amber-500">
                    Engineered for Precision.
                </span>
            </h1>

            <p class="mt-6 text-base sm:text-xl text-stone-300 max-w-2xl mx-auto leading-relaxed">
                The unified operating system for high-volume specialty cafés. Connecting multi-branch POS, biometric attendance, statutory payroll, audited inventory COGS, and real-time ledger accounting.
            </p>

            <!-- CTA Buttons -->
            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="login.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-8 py-3.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-base shadow-xl shadow-amber-950/40 transition-all transform hover:-translate-y-0.5">
                    <i class="fa-solid fa-lock"></i>
                    <span>Access Staff Backoffice</span>
                </a>
                <a href="careers.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-8 py-3.5 rounded-xl bg-stone-800/90 hover:bg-stone-700 border border-stone-700 text-white font-bold text-base transition-all">
                    <i class="fa-solid fa-coffee text-amber-400"></i>
                    <span>Join Our Team (Careers)</span>
                </a>
            </div>

            <!-- Key Feature Badges -->
            <div class="mt-14 pt-10 border-t border-stone-800/80 grid grid-cols-2 md:grid-cols-4 gap-4 text-stone-400 text-xs sm:text-sm font-medium">
                <div class="flex items-center justify-center gap-2">
                    <i class="fa-solid fa-check-circle text-amber-500"></i>
                    <span>Role-Based PAM Access</span>
                </div>
                <div class="flex items-center justify-center gap-2">
                    <i class="fa-solid fa-check-circle text-amber-500"></i>
                    <span>Audited Recipe COGS</span>
                </div>
                <div class="flex items-center justify-center gap-2">
                    <i class="fa-solid fa-check-circle text-amber-500"></i>
                    <span>Branch-Scoped Ledger</span>
                </div>
                <div class="flex items-center justify-center gap-2">
                    <i class="fa-solid fa-check-circle text-amber-500"></i>
                    <span>Maker-Checker Controls</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Modules Showcase Section -->
    <section id="modules" class="py-20 bg-stone-950 border-t border-stone-800/80 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span class="text-xs uppercase font-extrabold tracking-widest text-amber-500">Comprehensive Ecosystem</span>
                <h2 class="text-3xl sm:text-4xl font-black text-white mt-2 tracking-tight">Four Integrated Enterprise Engines</h2>
                <p class="text-stone-400 mt-3 text-sm sm:text-base">
                    No fragmented third-party add-ons. Every transaction, punch, recipe, and expense directly reconciles across core ledgers.
                </p>
            </div>

            <!-- Modules Grid (4 Pillars) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Module 1: POS -->
                <div class="p-8 rounded-2xl bg-stone-900 border border-stone-800 hover:border-amber-500/40 transition-all duration-300 relative group">
                    <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center text-xl mb-6 group-hover:scale-110 transition-transform">
                        <i class="fa-solid fa-cash-register"></i>
                    </div>
                    <div class="text-xs font-bold uppercase tracking-wider text-amber-500 mb-1">Store Front Operations</div>
                    <h3 class="text-2xl font-bold text-white mb-3">POS & Register Engine</h3>
                    <p class="text-stone-400 text-sm leading-relaxed mb-6">
                        Rapid register checkout with dual-custody supervisor overrides, dynamic beverage size/milk modifiers, PayMongo QR integration, and strict cashier shift float reconciliations.
                    </p>
                    <ul class="space-y-2.5 text-xs text-stone-300">
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-shield text-amber-400"></i>
                            <span>Dual-custody supervisor PINs for voids and refunds</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-mobile-screen text-amber-400"></i>
                            <span>Touch-optimized customer self-ordering Kiosk terminal</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-receipt text-amber-400"></i>
                            <span>Atomic Bill-of-Materials inventory deduction per order</span>
                        </li>
                    </ul>
                </div>

                <!-- Module 2: HRMS & Attendance -->
                <div class="p-8 rounded-2xl bg-stone-900 border border-stone-800 hover:border-amber-500/40 transition-all duration-300 relative group">
                    <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center text-xl mb-6 group-hover:scale-110 transition-transform">
                        <i class="fa-solid fa-users-gear"></i>
                    </div>
                    <div class="text-xs font-bold uppercase tracking-wider text-amber-500 mb-1">Human Capital & ATS</div>
                    <h3 class="text-2xl font-bold text-white mb-3">HRMS, Biometrics & ESS</h3>
                    <p class="text-stone-400 text-sm leading-relaxed mb-6">
                        Biometric clock-in terminals with hardware token binding, automated grace periods, PhilHealth/SSS statutory payroll calculation, shift swap board, and employee self-service.
                    </p>
                    <ul class="space-y-2.5 text-xs text-stone-300">
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-fingerprint text-amber-400"></i>
                            <span>Device-bound terminal punch verification with lockout protection</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-calendar-days text-amber-400"></i>
                            <span>Weekly Shift Board with peer drop/claim approval workflow</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-user-lock text-amber-400"></i>
                            <span>Employee Self-Service (ESS) payslip and leave management</span>
                        </li>
                    </ul>
                </div>

                <!-- Module 3: Finance & Reconcile -->
                <div class="p-8 rounded-2xl bg-stone-900 border border-stone-800 hover:border-amber-500/40 transition-all duration-300 relative group">
                    <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center text-xl mb-6 group-hover:scale-110 transition-transform">
                        <i class="fa-solid fa-scale-balanced"></i>
                    </div>
                    <div class="text-xs font-bold uppercase tracking-wider text-amber-500 mb-1">Treasury & Accounting</div>
                    <h3 class="text-2xl font-bold text-white mb-3">Finance, Budgets & Reconcile</h3>
                    <p class="text-stone-400 text-sm leading-relaxed mb-6">
                        Integer centavos ledger calculation. Live executive P&L with ingredient COGS deduction, monthly store operating expense budgets, and automated 4-way payment anomaly reconciliation.
                    </p>
                    <ul class="space-y-2.5 text-xs text-stone-300">
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-coins text-amber-400"></i>
                            <span>Integer centavos calculations avoiding IEEE floating-point drift</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-magnifying-glass-dollar text-amber-400"></i>
                            <span>Reconciliation detection for uncaptured payments & overpayments</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-chart-line text-amber-400"></i>
                            <span>Strict ledger-based COGS formula linked to paid orders</span>
                        </li>
                    </ul>
                </div>

                <!-- Module 4: Inventory & Procurement -->
                <div class="p-8 rounded-2xl bg-stone-900 border border-stone-800 hover:border-amber-500/40 transition-all duration-300 relative group">
                    <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center text-xl mb-6 group-hover:scale-110 transition-transform">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <div class="text-xs font-bold uppercase tracking-wider text-amber-500 mb-1">Supply Chain & Recipes</div>
                    <h3 class="text-2xl font-bold text-white mb-3">Inventory & Procurement</h3>
                    <p class="text-stone-400 text-sm leading-relaxed mb-6">
                        Live branch stock tracking with low-threshold alerts, multi-item purchase orders with Maker-Checker approvals, atomic goods receiving, and multi-ingredient recipe cost yields.
                    </p>
                    <ul class="space-y-2.5 text-xs text-stone-300">
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-truck-ramp-box text-amber-400"></i>
                            <span>Purchase order lifecycle: DRAFT &rarr; APPROVED &rarr; RECEIVED</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-flask text-amber-400"></i>
                            <span>Composite recipes mapping dairy, beans, and syrups per cup</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-arrow-down-wide-short text-amber-400"></i>
                            <span>Maker-checker stock adjustment threshold verification</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Architecture & Security Highlights -->
    <section id="architecture" class="py-20 bg-stone-900 border-t border-stone-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-gradient-to-br from-stone-950 to-stone-900 border border-stone-800 rounded-3xl p-8 sm:p-12 shadow-2xl relative overflow-hidden">
                <div class="max-w-2xl">
                    <span class="text-xs font-bold uppercase tracking-widest text-amber-500">Security Architecture</span>
                    <h3 class="text-2xl sm:text-3xl font-black text-white mt-2">Zero-Trust Enterprise Café Architecture</h3>
                    <p class="text-stone-400 text-sm sm:text-base mt-4 leading-relaxed">
                        Fika is crafted specifically for enterprise café operations with strict regulatory separation, data isolation between branches, and immutable audit logs on all monetary actions.
                    </p>

                    <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="flex items-start gap-3 p-4 rounded-xl bg-stone-900/80 border border-stone-800">
                            <i class="fa-solid fa-building text-amber-400 mt-1"></i>
                            <div>
                                <h4 class="text-sm font-bold text-white">Branch Data Isolation</h4>
                                <p class="text-xs text-stone-400 mt-1">Managers and accountants can strictly access records belonging to their assigned store.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-4 rounded-xl bg-stone-900/80 border border-stone-800">
                            <i class="fa-solid fa-user-check text-amber-400 mt-1"></i>
                            <div>
                                <h4 class="text-sm font-bold text-white">Dual-Custody Approvals</h4>
                                <p class="text-xs text-stone-400 mt-1">Self-approvals blocked; orders and adjustments require independent manager sign-off.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-4 rounded-xl bg-stone-900/80 border border-stone-800">
                            <i class="fa-solid fa-lock text-amber-400 mt-1"></i>
                            <div>
                                <h4 class="text-sm font-bold text-white">Encrypted Bank & Tax Data</h4>
                                <p class="text-xs text-stone-400 mt-1">Sensitive employee bank details and numbers are AES-256 encrypted at rest.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-4 rounded-xl bg-stone-900/80 border border-stone-800">
                            <i class="fa-solid fa-file-shield text-amber-400 mt-1"></i>
                            <div>
                                <h4 class="text-sm font-bold text-white">Protected Storage</h4>
                                <p class="text-xs text-stone-400 mt-1">Candidate resumes and receipts are stored outside web roots and served via authenticated RBAC proxies.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Banner -->
    <section class="py-16 bg-gradient-to-r from-espresso-800 via-espresso-900 to-stone-950 text-white border-t border-stone-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl sm:text-4xl font-black mb-4 tracking-tight">Ready to access the Fika Backoffice?</h2>
            <p class="text-stone-300 text-sm sm:text-base max-w-xl mx-auto mb-8">
                Authorized café staff, branch managers, and central administrators can log into their designated portal.
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="login.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-base shadow-xl transition-all">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                    <span>Open Staff Portal</span>
                </a>
                <a href="careers.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 rounded-xl bg-stone-800 hover:bg-stone-700 text-white font-bold text-base border border-stone-700 transition-all">
                    <i class="fa-solid fa-briefcase text-amber-400"></i>
                    <span>View Career Opportunities</span>
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-stone-950 text-stone-400 py-12 border-t border-stone-800 text-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-3">
                    <img src="public/assets/logo.svg" alt="Fika Logo Small" class="w-8 h-8 object-contain">
                    <div>
                        <div class="text-white font-bold text-sm">FIKA &middot; Slow Hours</div>
                        <div class="text-xs text-stone-500">Enterprise Hospitality Management &bull; PHP 8 Native</div>
                    </div>
                </div>
                <div class="flex flex-wrap justify-center gap-6 text-xs font-medium text-stone-400">
                    <a href="#modules" class="hover:text-amber-400 transition-colors">Modules</a>
                    <a href="#architecture" class="hover:text-amber-400 transition-colors">Architecture</a>
                    <a href="careers.php" class="hover:text-amber-400 transition-colors">Careers</a>
                    <a href="login.php" class="hover:text-amber-400 transition-colors">Staff Login</a>
                </div>
                <div class="text-xs text-stone-500 text-center md:text-right">
                    &copy; <?= date('Y') ?> Fika Café System. All rights reserved.
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
