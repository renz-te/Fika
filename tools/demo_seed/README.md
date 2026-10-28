# Fika Café — Realistic Demo Data Seeding Framework

This directory contains the production-grade, idempotent demo data seeding framework for the Fika Café management system (PHP 8.3, MySQL, PDO).

The framework seeds a coherent, commercially realistic multi-branch operating dataset spanning from August 2026 through October 2026 (anchored on October 28, 2026).

---

## Architecture & Principles

1. **Idempotent & Re-runnable**: Every phase checks for existing records prior to inserting. Running a phase repeatedly produces zero duplicate rows and zero errors.
2. **Tracked Lifecycle (`demo_seed`)**: All seeded records are tracked in the `demo_seed` registry table with table name, row ID, phase name, and created timestamp.
3. **Safe Reversible Deletions**: Running with `--reset-demo` safely deletes tracked demo records in reverse dependency order while leaving all pre-existing baseline records, migrations, and schema intact.
4. **Dry Run by Default**: CLI scripts run in read-only audit/dry-run mode by default. Data is only written to the database when the `--apply` flag is explicitly passed.
5. **No Synthetic Entities**: Uses only existing branches (1, 2, 3), roles, and user accounts. Never invents branches or users.
6. **Strict Live Engine Integration**: POS sales deductions use the live `InventoryDeduction` engine; payroll math enforces statutory contribution tables and Separation of Duties (Maker-Checker); finance reconciles to the exact SQL queries used in `fika_finance_reports.php`.

---

## Command Reference & Usage

All commands are executed from the repository root via the master runner `tools/seed_demo.php`:

### 1. Dry Run (Read-Only Preview)
```bash
# Preview Phase 2 (Dry Run)
php tools/seed_demo.php --phase=2

# Preview Phase 8 (Dry Run)
php tools/seed_demo.php --phase=8

# Preview Phase 10 Reconciliation & Sanity Checks
php tools/seed_demo.php --phase=10
```

### 2. Apply Mode (Write to Database)
```bash
# Apply a specific phase
php tools/seed_demo.php --phase=2 --apply
php tools/seed_demo.php --phase=3 --apply
...
php tools/seed_demo.php --phase=9 --apply
```

### 3. Reset Demo Data
```bash
# Preview reset of all demo data (Dry Run)
php tools/seed_demo.php --reset-demo

# Preview reset of a specific phase (Dry Run)
php tools/seed_demo.php --reset-demo --phase=8

# Execute reset of all demo records across all tables
php tools/seed_demo.php --reset-demo --apply

# Execute reset of a specific phase
php tools/seed_demo.php --reset-demo --phase=8 --apply
```

---

## Canonical Phase Execution Order

To rebuild the demo environment from scratch, execute the phases in exact numerical order:

| Phase | Script | Domain | Output / Records Seeded |
|---|---|---|---|
| **D1** | `phase_1.php` | System Discovery | Verifies local database connection and baseline integrity. |
| **D2** | `phase_2.php` | Inventory Items & Stock | 27 catalog items (raw materials, packaging, bakery goods) and 81 stock balances across 3 branches. |
| **D3** | `phase_3.php` | Menu Products & Modifiers | 3 categories (Cold, Hot, Pastries), 17 menu products, and 14 modifiers. |
| **D4** | `phase_4.php` | Recipes & BOM | 66 recipes linking products and modifiers to packaging and raw ingredients. |
| **D5** | `phase_5.php` | Shifts & Leave | 6 approved leaves and 405 published shifts across 6 weeks (W40 to W45). |
| **D6** | `phase_6.php` | Attendance Logs | 333 attendance logs (on-time, late, overtime, 3 absences, and 9 open shifts for today). |
| **D7** | `phase_7.php` | Payroll & Contributions | 69 statutory rates (SSS, PhilHealth, Pag-IBIG, WHT), 7 certifications, 9 payroll runs, and 45 payroll line items. |
| **D8** | `phase_8.php` | POS Orders & Restocks | 405 inventory restocks, 954 paid orders, 1,716 items, 1,609 modifiers, and 8,139 inventory ledger transactions. |
| **D9** | `phase_9.php` | Budgets & Expenses | 9 branch monthly budgets, 54 operating expenses (rent, utilities, supplies, maintenance, marketing, misc) with maker-checker states. |
| **D10** | `phase_10.php` | P&L Proof & Sanity | Zero-variance reconciliation against live financial queries, pricing/margin audit, and shift roster collision checks. |

### Sequential Re-Seed Command
```bash
php tools/seed_demo.php --phase=1 --apply
php tools/seed_demo.php --phase=2 --apply
php tools/seed_demo.php --phase=3 --apply
php tools/seed_demo.php --phase=4 --apply
php tools/seed_demo.php --phase=5 --apply
php tools/seed_demo.php --phase=6 --apply
php tools/seed_demo.php --phase=7 --apply
php tools/seed_demo.php --phase=8 --apply
php tools/seed_demo.php --phase=9 --apply
php tools/seed_demo.php --phase=10 --apply
```

---

## Full Assumptions List (For Owner Review)

### 1. Branches & Organization
- **Operational Scope**: Exactly 3 active physical branches:
  - Branch 1: `General Trias Branch` (Cavite)
  - Branch 2: `Trece Martires Branch` (Cavite)
  - Branch 3: `Imus Branch` (Cavite)
- **Staffing**: Each branch operates with:
  - 1 Branch Manager (BM) — Official staff class, exempt from daily punch-clock.
  - 1 Branch HR (BHR) — Shift scheduler and payroll maker.
  - 1 Branch Accountant (BA) — Expense maker/checker and payroll reviewer.
  - 5 Crew Members (Baristas/Cashiers) — Hourly/daily attendance clocking.

### 2. Inventory & Unit Costs
- **Raw Ingredient Unit Costs**:
  - Espresso beans: ₱0.45 / g (₱450.00 / kg)
  - Fresh whole milk: ₱0.10 / ml (₱100.00 / L)
  - Commercial Ice: ₱0.01 / g
  - Ceremonial Matcha powder: ₱0.60 / g
  - Condensed milk: ₱0.15 / g
  - Flavor Syrups (Caramel, Chocolate, Strawberry): ₱0.15 / ml
  - Frappe base powder: ₱0.25 / g
  - Black tea leaves: ₱0.50 / g
  - Tapioca pearls: ₱0.12 / g
  - Non-dairy creamer: ₱0.08 / g
- **Packaging Unit Costs**:
  - Cold Cups: 16oz (₱3.00), 22oz (₱4.00)
  - Hot Cups: 12oz (₱2.50), 16oz (₱3.50)
  - Lids: Dome lid (₱1.50), Hot sip lid (₱1.00)
  - Straw: ₱0.50
- **Finished Bakery Goods Wholesale Unit Costs**:
  - Butter Croissant: ₱35.00
  - Blueberry Pie: ₱45.00
  - Lemon Bread: ₱30.00
  - Chocolate Cake slice: ₱55.00
  - Strawberry Shortcake slice: ₱60.00

### 3. Product Catalog Pricing & Margins
- **Beverage Retail Prices**:
  - Cold Beverages: ₱79.00 to ₱129.00 base (Medium 16oz).
  - Hot Beverages: ₱89.00 to ₱99.00 base (Medium 12oz).
  - Size Upgrade (Large 22oz cold / 16oz hot): +₱20.00.
  - Extra Espresso Shot: +₱30.00.
  - Add-ons (Pearls, Whipped Cream, Caramel Drizzle): +₱15.00.
- **Bakery Retail Prices**: ₱85.00 to ₱125.00.
- **Commercial Gross Margins**:
  - Core Beverages: 57.3% to 82.5% gross margin.
  - Pastries: 52.0% to 64.7% gross margin.
  - Add-on Modifiers: 60.0% to 80.0% gross margin.

### 4. Rostering & Shift Scheduling
- **Shift Templates**:
  - Opening Shift: 06:00 – 14:00 (8 hours)
  - Mid Shift: 10:00 – 18:00 (8 hours)
  - Closing Shift: 14:00 – 22:00 (8 hours)
- **Workload**: Exactly 5 working shifts per employee per full week (40 scheduled hours/week).
- **Branch Coverage**: 25 crew shifts per branch per week (minimum 1 opener, 1 mid, 1 closer daily; 2 on peak days).

### 5. Attendance & Timekeeping
- **Grace Period**: 15 minutes past shift start. Clock-ins > 15 minutes late accrue tardiness deductions.
- **Overtime Threshold**: Work exceeding 8 hours per shift logged as overtime.
- **Exceptions**: Exactly 3 recorded unexcused absences (1 per branch on 2026-10-15) and 3 unscheduled rest-day coverages.
- **Current Day (2026-10-28)**: 9 crew members clocked in on active OPEN shifts (as of morning hours).

### 6. Statutory Contributions & Taxes (2026 Rules)
- **SSS (Social Security System)**: 2025/2026 contribution schedule with regular SSS + WISP (Workers' Investment and Savings Program) split between employee and employer.
- **PhilHealth**: 5.0% total premium rate (2.5% employee share, 2.5% employer share) with ₱500.00 minimum and ₱5,000.00 maximum cap.
- **Pag-IBIG**: Maximum mandated contribution of ₱200.00 employee share and ₱200.00 employer share for basic compensation exceeding ₱5,000.00/month.
- **Withholding Tax (BIR TRAIN)**: Semi-monthly progressive tax withholding scale.
- **Cutoff Allocation**: Statutory contributions are split exactly 50/50 between Cutoff 1 (1st–15th) and Cutoff 2 (16th–end of month).

### 7. POS Sales Volume & Tender Mix
- **Daily Volume**: 10 to 12 completed transactions per branch per day.
- **Payment Method Distribution**:
  - Cash: ~72% of transactions (exact centavos change calculated).
  - GCash: ~20% of transactions (digital reference tracked).
  - Maya: ~8% of transactions (digital reference tracked).
- **Inventory Replenishment**: Weekly restock deliveries every Monday morning replenishing stock above reorder thresholds, preventing negative inventory.

### 8. Monthly Operating Expenses (Opex) & Budgets
- **Expense Categories & Standard Monthly Amounts**:
  - Commercial Space Rent: ₱34,650 – ₱36,750 / branch / month.
  - Electricity & Water Utilities: ₱9,220 – ₱9,790 / branch / month.
  - Store & Cleaning Supplies: ₱13,490 – ₱14,340 / branch / month.
  - Equipment Maintenance: ₱5,740 – ₱6,090 / branch / month.
  - Local Marketing: ₱6,310 – ₱6,700 / branch / month.
  - Miscellaneous Operations: ₱2,660 – ₱2,830 / branch / month.
- **Maker-Checker Realism**:
  - 50 expenses are APPROVED with distinct maker (BHR/BA) and checker (BA/GA) users.
  - 3 expenses are DRAFT (awaiting accountant review).
  - 1 expense is VOIDED with mandatory vendor duplicate reason.
