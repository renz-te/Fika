<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fika • Slow Hours Cafe Operating System</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Tailwind CSS (CDN for zero-db standalone demo) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Outfit', 'Inter', 'sans-serif'],
                        mono: ['Courier New', 'Courier', 'monospace']
                    },
                    colors: {
                        fika: {
                            50: '#fdf8f6',
                            100: '#f2e8e5',
                            200: '#eaddd7',
                            300: '#e0cec7',
                            400: '#d2bab0',
                            500: '#bfa094',
                            600: '#a18072',
                            700: '#846356',
                            800: '#5c4033',
                            900: '#3d281f',
                            950: '#261710'
                        }
                    }
                }
            }
        }
    </script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .glass-header {
            background: rgba(38, 23, 16, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        .hero-pattern {
            background: linear-gradient(135deg, rgba(38, 23, 16, 0.92) 0%, rgba(61, 40, 31, 0.88) 50%, rgba(92, 64, 51, 0.82) 100%),
                        url('https://images.unsplash.com/photo-1501339847302-ac426a4a7cbb?auto=format&fit=crop&w=1920&q=80') center/cover no-repeat;
        }
    </style>
</head>
<body class="bg-stone-50 text-stone-800 font-sans antialiased selection:bg-amber-600 selection:text-white">

    <!-- Top Announcement Bar -->
    <div class="bg-amber-900 text-amber-100 text-xs py-2 px-4 text-center font-medium border-b border-amber-800">
        <span class="inline-flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            Demo Ready &bull; Standalone Zero-DB Architecture &bull; BIR 58mm Thermal Ready
        </span>
    </div>

    <!-- Navigation Bar -->
    <header class="sticky top-0 z-50 glass-header border-b border-stone-800 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <!-- Brand / Logo -->
                <a href="#top" class="flex items-center gap-3 group">
                    <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-amber-500 to-amber-700 flex items-center justify-center text-white shadow-lg shadow-amber-900/40 group-hover:scale-105 transition-transform">
                        <i class="fa-solid fa-mug-hot text-xl"></i>
                    </div>
                    <div>
                        <div class="text-xl font-bold tracking-tight text-white group-hover:text-amber-400 transition-colors">Slow Hours</div>
                        <div class="text-xs font-medium text-stone-400 tracking-wider uppercase">Hospitality Suite</div>
                    </div>
                </a>

                <!-- Desktop Navigation Links -->
                <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-stone-300">
                    <a href="#modules" class="hover:text-amber-400 transition-colors">Modules</a>
                    <a href="#roles" class="hover:text-amber-400 transition-colors">Roles</a>
                    <a href="#auth-flow" class="hover:text-amber-400 transition-colors">How It Works</a>
                </nav>

                <!-- Desktop CTAs -->
                <div class="hidden sm:flex items-center gap-3">
                    <a href="cafe_pos/kiosk.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-stone-600 text-stone-200 text-sm font-semibold hover:bg-stone-800/80 hover:text-white hover:border-stone-500 transition-all">
                        <i class="fa-solid fa-tablet-screen-button text-amber-400"></i>
                        <span>Kiosk Mode</span>
                    </a>
                    <a href="login.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-gradient-to-r from-amber-600 to-amber-700 text-white text-sm font-semibold shadow-md shadow-amber-950/40 hover:from-amber-500 hover:to-amber-600 hover:shadow-lg transition-all active:scale-95">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i>
                        <span>Staff Login</span>
                    </a>
                </div>

                <!-- Mobile Menu Button -->
                <button id="mobile-menu-toggle" type="button" class="sm:hidden p-2.5 rounded-lg text-stone-400 hover:text-white hover:bg-stone-800 focus:outline-none" aria-label="Toggle Navigation">
                    <i id="mobile-menu-icon" class="fa-solid fa-bars text-xl"></i>
                </button>
            </div>

            <!-- Mobile Navigation Drawer -->
            <div id="mobile-menu" class="hidden sm:hidden py-4 border-t border-stone-800 text-stone-300 space-y-3 pb-6">
                <a href="#modules" class="block px-3 py-2 rounded-md hover:bg-stone-800 hover:text-amber-400 font-medium">Modules</a>
                <a href="#roles" class="block px-3 py-2 rounded-md hover:bg-stone-800 hover:text-amber-400 font-medium">Roles & Access</a>
                <a href="#auth-flow" class="block px-3 py-2 rounded-md hover:bg-stone-800 hover:text-amber-400 font-medium">Architecture Flow</a>
                <div class="pt-4 flex flex-col gap-2">
                    <a href="cafe_pos/kiosk.php" class="w-full text-center px-4 py-2.5 rounded-lg border border-stone-600 text-stone-200 text-sm font-semibold hover:bg-stone-800">
                        <i class="fa-solid fa-tablet-screen-button mr-2 text-amber-400"></i>Kiosk Mode
                    </a>
                    <a href="login.php" class="w-full text-center px-4 py-2.5 rounded-lg bg-amber-600 text-white text-sm font-semibold shadow-md hover:bg-amber-500">
                        <i class="fa-solid fa-arrow-right-to-bracket mr-2"></i>Staff Login
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="top" class="relative hero-pattern text-white py-20 sm:py-28 lg:py-36 border-b border-stone-900 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="max-w-3xl">
                <!-- Eyebrow Badge -->
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-500/20 border border-amber-400/30 text-amber-300 text-xs sm:text-sm font-semibold mb-6">
                    <i class="fa-solid fa-sparkles"></i>
                    Enterprise Hospitality & Retail Suite
                </div>

                <!-- Hero Heading -->
                <h1 class="text-3xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-tight sm:leading-none mb-6">
                    Streamlined Operations for Modern Cafes.
                </h1>

                <!-- Hero Subtitle -->
                <p class="text-base sm:text-xl text-stone-300 font-normal leading-relaxed mb-8 max-w-2xl">
                    One unified platform synchronizing Touch POS, Self-Service Kiosks, HRMS & Biometric Clocking, Real-Time Recipe Inventory, and Automated Financial Compliance.
                </p>

                <!-- Action CTAs -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 sm:gap-4 mb-10 w-full sm:w-auto">
                    <a href="login.php" class="inline-flex items-center justify-center gap-2 px-7 py-3.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 text-white font-bold text-base shadow-xl shadow-amber-950/50 hover:from-amber-400 hover:to-amber-500 hover:scale-[1.02] active:scale-95 transition-all text-center">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        <span>Staff Login</span>
                    </a>
                    <a href="cafe_pos/kiosk.php" class="inline-flex items-center justify-center gap-2 px-7 py-3.5 rounded-xl bg-white/10 hover:bg-white/20 border border-white/20 text-white font-bold text-base backdrop-blur-sm active:scale-95 transition-all text-center">
                        <i class="fa-solid fa-tablet-screen-button text-amber-400"></i>
                        <span>Open Kiosk Mode</span>
                    </a>
                </div>

                <!-- Core Highlights Metrics -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-6 border-t border-white/10">
                    <div>
                        <div class="text-2xl font-extrabold text-amber-400">58mm</div>
                        <div class="text-xs text-stone-400 font-medium">BIR Thermal Receipts</div>
                    </div>
                    <div>
                        <div class="text-2xl font-extrabold text-amber-400">100%</div>
                        <div class="text-xs text-stone-400 font-medium">Zero-DB Offline Safe</div>
                    </div>
                    <div>
                        <div class="text-2xl font-extrabold text-amber-400">4 Tiers</div>
                        <div class="text-xs text-stone-400 font-medium">Modular Architecture</div>
                    </div>
                    <div>
                        <div class="text-2xl font-extrabold text-amber-400">Multi</div>
                        <div class="text-xs text-stone-400 font-medium">Branch Data Scoping</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- What It Is: 4 Informational Cards -->
    <section id="modules" class="py-20 bg-stone-100 border-b border-stone-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span class="text-xs font-bold uppercase tracking-wider text-amber-700 bg-amber-100 px-3 py-1 rounded-full">Core Ecosystem</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-stone-900 mt-3 mb-4 tracking-tight">Four Integrated Operational Pillars</h2>
                <p class="text-stone-600 text-base sm:text-lg">Designed specifically for fast-paced specialty coffee shops, franchise branches, and artisanal eateries.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Card 1: POS -->
                <div class="bg-white rounded-2xl p-7 shadow-sm hover:shadow-xl transition-all duration-300 border border-stone-200 flex flex-col justify-between group hover:-translate-y-1">
                    <div>
                        <div class="w-14 h-14 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center text-2xl mb-6 group-hover:bg-amber-600 group-hover:text-white transition-colors">
                            <i class="fa-solid fa-cash-register"></i>
                        </div>
                        <h3 class="text-xl font-bold text-stone-900 mb-2">Point of Sale (POS)</h3>
                        <p class="text-sm text-stone-600 leading-relaxed mb-4">
                            Touch-first cashier terminal with customizable modifiers, PayMongo digital payments, blind register shift closes, and 58mm thermal receipts.
                        </p>
                    </div>
                    <ul class="text-xs text-stone-500 space-y-2 pt-4 border-t border-stone-100 font-medium">
                        <li class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald-600"></i> Cash & PayMongo E-Wallet</li>
                        <li class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald-600"></i> Senior/PWD Tax Calculations</li>
                        <li class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald-600"></i> Reprint & Void Slips</li>
                    </ul>
                </div>

                <!-- Card 2: HRMS -->
                <div class="bg-white rounded-2xl p-7 shadow-sm hover:shadow-xl transition-all duration-300 border border-stone-200 flex flex-col justify-between group hover:-translate-y-1">
                    <div>
                        <div class="w-14 h-14 rounded-xl bg-blue-100 text-blue-800 flex items-center justify-center text-2xl mb-6 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                            <i class="fa-solid fa-users-gear"></i>
                        </div>
                        <h3 class="text-xl font-bold text-stone-900 mb-2">HRMS & Payroll</h3>
                        <p class="text-sm text-stone-600 leading-relaxed mb-4">
                            Full workforce lifecycle management including biometric time tracking, shift rostering, candidate ATS pipelines, and automated payroll runs.
                        </p>
                    </div>
                    <ul class="text-xs text-stone-500 space-y-2 pt-4 border-t border-stone-100 font-medium">
                        <li class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald-600"></i> Strict Role-Based RBAC</li>
                        <li class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald-600"></i> SSS, PhilHealth, Pag-IBIG Engine</li>
                        <li class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald-600"></i> Self-Service Attendance</li>
                    </ul>
                </div>

                <!-- Card 3: Finance -->
                <div class="bg-white rounded-2xl p-7 shadow-sm hover:shadow-xl transition-all duration-300 border border-stone-200 flex flex-col justify-between group hover:-translate-y-1">
                    <div>
                        <div class="w-14 h-14 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center text-2xl mb-6 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <h3 class="text-xl font-bold text-stone-900 mb-2">Finance & Ledger</h3>
                        <p class="text-sm text-stone-600 leading-relaxed mb-4">
                            Real-time sales reconciliation, daily drawer floats, branch budget tracking, and detailed audit trails on every supervisor override.
                        </p>
                    </div>
                    <ul class="text-xs text-stone-500 space-y-2 pt-4 border-t border-stone-100 font-medium">
                        <li class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald-600"></i> Daily Cash Float Audits</li>
                        <li class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald-600"></i> Branch Expenditure Tracking</li>
                        <li class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald-600"></i> BIR Compliant Tax Journal</li>
                    </ul>
                </div>

                <!-- Card 4: Inventory -->
                <div class="bg-white rounded-2xl p-7 shadow-sm hover:shadow-xl transition-all duration-300 border border-stone-200 flex flex-col justify-between group hover:-translate-y-1">
                    <div>
                        <div class="w-14 h-14 rounded-xl bg-purple-100 text-purple-800 flex items-center justify-center text-2xl mb-6 group-hover:bg-purple-600 group-hover:text-white transition-colors">
                            <i class="fa-solid fa-boxes-stacked"></i>
                        </div>
                        <h3 class="text-xl font-bold text-stone-900 mb-2">Recipe Inventory</h3>
                        <p class="text-sm text-stone-600 leading-relaxed mb-4">
                            Fractional recipe deduction per drink sold. Automated restock alerts, purchase orders, wastage logs, and multi-branch transfers.
                        </p>
                    </div>
                    <ul class="text-xs text-stone-500 space-y-2 pt-4 border-t border-stone-100 font-medium">
                        <li class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald-600"></i> Live Recipe-to-Cup Deductions</li>
                        <li class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald-600"></i> Wastage & Spoilage Audits</li>
                        <li class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald-600"></i> Multi-Branch Stock Reorders</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Who It's For: Roles Section -->
    <section id="roles" class="py-20 bg-white border-b border-stone-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span class="text-xs font-bold uppercase tracking-wider text-amber-700 bg-amber-100 px-3 py-1 rounded-full">Tailored Permissions</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-stone-900 mt-3 mb-4 tracking-tight">Built for Every Member of the Team</h2>
                <p class="text-stone-600 text-base sm:text-lg">Granular branch scoping ensures team members only interact with the data and actions relevant to their duty.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6">
                <!-- Role 1: Crew / Barista -->
                <div class="bg-stone-50 rounded-xl p-6 border border-stone-200 text-center hover:border-amber-400 transition-colors">
                    <div class="w-16 h-16 rounded-full bg-amber-100 text-amber-800 flex items-center justify-center text-2xl mx-auto mb-4">
                        <i class="fa-solid fa-mug-saucer"></i>
                    </div>
                    <h3 class="font-bold text-lg text-stone-900 mb-1">Barista / Crew</h3>
                    <p class="text-xs text-amber-700 font-semibold mb-3">POS & Self-Service</p>
                    <p class="text-xs text-stone-600 leading-relaxed">
                        Processes beverage orders, operates cash drawer, requests shifts, and logs daily biometric attendance.
                    </p>
                </div>

                <!-- Role 2: Branch Manager -->
                <div class="bg-stone-50 rounded-xl p-6 border border-stone-200 text-center hover:border-amber-400 transition-colors">
                    <div class="w-16 h-16 rounded-full bg-blue-100 text-blue-800 flex items-center justify-center text-2xl mx-auto mb-4">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                    <h3 class="font-bold text-lg text-stone-900 mb-1">Branch Manager</h3>
                    <p class="text-xs text-blue-700 font-semibold mb-3">Store Operations</p>
                    <p class="text-xs text-stone-600 leading-relaxed">
                        Authorizes voids & refunds, reconciles blind shift closes, manages shift schedules, and approves inventory counts.
                    </p>
                </div>

                <!-- Role 3: Accountant -->
                <div class="bg-stone-50 rounded-xl p-6 border border-stone-200 text-center hover:border-amber-400 transition-colors">
                    <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center text-2xl mx-auto mb-4">
                        <i class="fa-solid fa-calculator"></i>
                    </div>
                    <h3 class="font-bold text-lg text-stone-900 mb-1">Accountant</h3>
                    <p class="text-xs text-emerald-700 font-semibold mb-3">Financial Oversight</p>
                    <p class="text-xs text-stone-600 leading-relaxed">
                        Audits sales ledgers, reconciles digital settlement batches, tracks monthly branch budgets, and generates tax files.
                    </p>
                </div>

                <!-- Role 4: HR Specialist -->
                <div class="bg-stone-50 rounded-xl p-6 border border-stone-200 text-center hover:border-amber-400 transition-colors">
                    <div class="w-16 h-16 rounded-full bg-purple-100 text-purple-800 flex items-center justify-center text-2xl mx-auto mb-4">
                        <i class="fa-solid fa-id-card-clip"></i>
                    </div>
                    <h3 class="font-bold text-lg text-stone-900 mb-1">HR Specialist</h3>
                    <p class="text-xs text-purple-700 font-semibold mb-3">Talent & Payroll</p>
                    <p class="text-xs text-stone-600 leading-relaxed">
                        Evaluates applicants in recruitment pipeline, conducts performance reviews, approves leaves, and runs cutoff payroll.
                    </p>
                </div>

                <!-- Role 5: Owner / Exec -->
                <div class="bg-stone-50 rounded-xl p-6 border border-stone-200 text-center hover:border-amber-400 transition-colors sm:col-span-2 lg:col-span-1">
                    <div class="w-16 h-16 rounded-full bg-rose-100 text-rose-800 flex items-center justify-center text-2xl mx-auto mb-4">
                        <i class="fa-solid fa-crown"></i>
                    </div>
                    <h3 class="font-bold text-lg text-stone-900 mb-1">Owner / Exec</h3>
                    <p class="text-xs text-rose-700 font-semibold mb-3">Enterprise Governance</p>
                    <p class="text-xs text-stone-600 leading-relaxed">
                        Multi-branch consolidated dashboards, revenue growth velocity, net margins, and executive enterprise reporting.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works: Auth Tiers Visual & Text Diagram -->
    <section id="auth-flow" class="py-20 bg-stone-900 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span class="text-xs font-bold uppercase tracking-wider text-amber-400 bg-amber-950/80 border border-amber-800 px-3 py-1 rounded-full">Security Architecture</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-white mt-3 mb-4 tracking-tight">Two-Tier Authentication Framework</h2>
                <p class="text-stone-300 text-base sm:text-lg">Separating physical device authorization from human operator identities protects sensitive data on shared cafe terminals.</p>
            </div>

            <!-- Visual Flowchart Cards -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
                <!-- Tier 1 Card: System Accounts -->
                <div class="bg-stone-800/80 rounded-2xl p-7 border border-stone-700 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">Tier 1: Device Boundary</span>
                            <i class="fa-solid fa-desktop text-amber-400 text-xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-2">System Accounts (Station Session)</h3>
                        <p class="text-sm text-stone-300 mb-6">
                            Fixed hardware accounts dedicated to specific physical terminals. They authenticate the physical device to a specific branch without exposing employee credentials.
                        </p>
                        <div class="space-y-3 text-xs text-stone-300">
                            <div class="p-3 rounded-lg bg-stone-900/60 border border-stone-700/60 flex items-start gap-3">
                                <i class="fa-solid fa-tablet text-amber-400 mt-0.5"></i>
                                <div>
                                    <strong class="text-white block">Kiosk Terminal:</strong>
                                    Public customer-facing menu. No sensitive employee or financial access.
                                </div>
                            </div>
                            <div class="p-3 rounded-lg bg-stone-900/60 border border-stone-700/60 flex items-start gap-3">
                                <i class="fa-solid fa-clock text-blue-400 mt-0.5"></i>
                                <div>
                                    <strong class="text-white block">Clock Station:</strong>
                                    Mounted attendance device for crew time-ins. Restricted to PIN/camera check-in.
                                </div>
                            </div>
                            <div class="p-3 rounded-lg bg-stone-900/60 border border-stone-700/60 flex items-start gap-3">
                                <i class="fa-solid fa-cash-register text-emerald-400 mt-0.5"></i>
                                <div>
                                    <strong class="text-white block">Cashier Terminal:</strong>
                                    Register hardware locked to an active branch shift and physical drawer float.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tier 2 Card: Human Profiles -->
                <div class="bg-stone-800/80 rounded-2xl p-7 border border-stone-700 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-blue-500/20 text-blue-300 border border-blue-500/30">Tier 2: Personnel Boundary</span>
                            <i class="fa-solid fa-fingerprint text-blue-400 text-xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-2">Human Profiles (RBAC Identity)</h3>
                        <p class="text-sm text-stone-300 mb-6">
                            Individual employees authenticated via secure email/password or fast operator PIN. Enforces strict PAM privileges and branch scoping.
                        </p>
                        <div class="space-y-3 text-xs text-stone-300">
                            <div class="p-3 rounded-lg bg-stone-900/60 border border-stone-700/60 flex items-start gap-3">
                                <i class="fa-solid fa-unlock-keyhole text-amber-400 mt-0.5"></i>
                                <div>
                                    <strong class="text-white block">Barista Operator Unlock:</strong>
                                    Quick 4-digit PIN unlock on active registers. Logs sales under specific cashier name.
                                </div>
                            </div>
                            <div class="p-3 rounded-lg bg-stone-900/60 border border-stone-700/60 flex items-start gap-3">
                                <i class="fa-solid fa-shield-halved text-purple-400 mt-0.5"></i>
                                <div>
                                    <strong class="text-white block">Supervisor Dual-Custody:</strong>
                                    Manager PIN authorization required for price overrides, refunds, and void receipts.
                                </div>
                            </div>
                            <div class="p-3 rounded-lg bg-stone-900/60 border border-stone-700/60 flex items-start gap-3">
                                <i class="fa-solid fa-building-user text-rose-400 mt-0.5"></i>
                                <div>
                                    <strong class="text-white block">Web Management Console:</strong>
                                    Full dashboard credentials for HR, Finance, and Executive management roles.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Diagram Flow Representation -->
            <div class="p-6 rounded-2xl bg-stone-950 border border-stone-800">
                <div class="text-xs font-bold uppercase tracking-wider text-stone-400 mb-4 text-center">Execution Order Flow</div>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 text-center">
                    <div class="p-4 rounded-xl bg-stone-900 border border-stone-800">
                        <div class="w-8 h-8 rounded-full bg-amber-500/20 text-amber-400 font-bold flex items-center justify-center mx-auto mb-2 text-xs">1</div>
                        <div class="font-bold text-sm text-white mb-1">Device Auth</div>
                        <div class="text-xs text-stone-400">Terminal binds to Branch ID</div>
                    </div>
                    <div class="p-4 rounded-xl bg-stone-900 border border-stone-800">
                        <div class="w-8 h-8 rounded-full bg-amber-500/20 text-amber-400 font-bold flex items-center justify-center mx-auto mb-2 text-xs">2</div>
                        <div class="font-bold text-sm text-white mb-1">Shift Float</div>
                        <div class="text-xs text-stone-400">Head Barista opens register</div>
                    </div>
                    <div class="p-4 rounded-xl bg-stone-900 border border-stone-800">
                        <div class="w-8 h-8 rounded-full bg-amber-500/20 text-amber-400 font-bold flex items-center justify-center mx-auto mb-2 text-xs">3</div>
                        <div class="font-bold text-sm text-white mb-1">Cashier PIN</div>
                        <div class="text-xs text-stone-400">Operator unlocks terminal</div>
                    </div>
                    <div class="p-4 rounded-xl bg-stone-900 border border-stone-800">
                        <div class="w-8 h-8 rounded-full bg-amber-500/20 text-amber-400 font-bold flex items-center justify-center mx-auto mb-2 text-xs">4</div>
                        <div class="font-bold text-sm text-white mb-1">Scoped Sale</div>
                        <div class="text-xs text-stone-400">Receipt logged with Shift ID</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Bottom Action CTA Banner -->
    <section class="py-16 bg-amber-900 text-white relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <h2 class="text-2xl sm:text-3xl font-extrabold mb-4 tracking-tight">Ready to test the live demonstration?</h2>
            <p class="text-amber-200 text-sm sm:text-base max-w-xl mx-auto mb-8">
                Explore the staff management backoffice or launch customer self-ordering on the kiosk portal.
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="login.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 rounded-xl bg-white text-stone-900 font-bold text-base hover:bg-stone-100 shadow-xl transition-all">
                    <i class="fa-solid fa-arrow-right-to-bracket text-amber-700"></i>
                    <span>Open Staff Portal</span>
                </a>
                <a href="cafe_pos/kiosk.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 rounded-xl bg-amber-800/80 hover:bg-amber-800 text-white font-bold text-base border border-amber-700/80 transition-all">
                    <i class="fa-solid fa-tablet-screen-button text-amber-300"></i>
                    <span>Launch Customer Kiosk</span>
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-stone-950 text-stone-400 py-12 border-t border-stone-800 text-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-amber-600 flex items-center justify-center text-white text-sm font-bold">
                        <i class="fa-solid fa-mug-hot"></i>
                    </div>
                    <div>
                        <div class="text-white font-bold text-sm">Slow Hours Cafe Suite</div>
                        <div class="text-xs text-stone-500">PHP 8 &bull; PDO &bull; Tailwind CSS &bull; Apache 2.4</div>
                    </div>
                </div>
                <div class="flex flex-wrap justify-center gap-6 text-xs font-medium text-stone-400">
                    <a href="#modules" class="hover:text-amber-400 transition-colors">Ecosystem Modules</a>
                    <a href="#roles" class="hover:text-amber-400 transition-colors">Personnel Directory</a>
                    <a href="#auth-flow" class="hover:text-amber-400 transition-colors">Auth Architecture</a>
                    <a href="login.php" class="hover:text-amber-400 transition-colors">Staff Login</a>
                    <a href="cafe_pos/kiosk.php" class="hover:text-amber-400 transition-colors">Kiosk Mode</a>
                </div>
                <div class="text-xs text-stone-500 text-center md:text-right">
                    &copy; <?= date('Y') ?> Slow Hours Cafe. All rights reserved.
                </div>
            </div>
        </div>
    </footer>

    <!-- Interactive Mobile Drawer Script -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggleBtn = document.getElementById('mobile-menu-toggle');
            const mobileMenu = document.getElementById('mobile-menu');
            const menuIcon = document.getElementById('mobile-menu-icon');

            if (toggleBtn && mobileMenu) {
                toggleBtn.addEventListener('click', () => {
                    const isHidden = mobileMenu.classList.toggle('hidden');
                    if (isHidden) {
                        menuIcon.classList.remove('fa-xmark');
                        menuIcon.classList.add('fa-bars');
                    } else {
                        menuIcon.classList.remove('fa-bars');
                        menuIcon.classList.add('fa-xmark');
                    }
                });

                // Auto-close menu on anchor link click
                mobileMenu.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', () => {
                        mobileMenu.classList.add('hidden');
                        menuIcon.classList.remove('fa-xmark');
                        menuIcon.classList.add('fa-bars');
                    });
                });
            }
        });
    </script>
</body>
</html>
