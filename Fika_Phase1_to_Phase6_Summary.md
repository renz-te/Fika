# Fika Cafe HRMS & POS: Development Journey (Phase 1 to Phase 6)

## Phase 1: Project Initialization & Database Baseline
- **Dual Database Architecture:** Established the core dual-database schema mapping (`hrms` and `cafe_pos`) to isolate employee operations from transactional retail data while maintaining relational cross-links.
- **Access Control & Auth:** Developed the baseline user management and authentication system with role-based routing (Super Admin, Admin, HR Manager, Head Barista, Cashier).
- **Core Entities:** Structured the foundational tables for organizational Branches, Employees, and Users.
- **Environment Management:** Set up configuration handling (`init.php`, `database.php`) to dynamically handle environment switching (local vs production).

## Phase 2: ATS & Employee Lifecycle
- **Applicant Tracking System (ATS):** Built the recruitment UI and pipeline to track applicants through various hiring stages.
- **Interview Workspace:** Created a dedicated UI for evaluating candidates, uploading resumes, and tracking interview notes.
- **Seamless Onboarding:** Automated the transition pipeline that converts an "Approved" applicant directly into an active Employee, initializing their payroll and statutory profile.

## Phase 3A: POS Operations & Inventory Architecture
- **Menu Management:** Built full CRUD capabilities for the cafe's menu, including category mapping and complex product modifiers.
- **Transactional Core:** Engineered the POS order handling (`orders`, `order_items`), cart logic, and checkout flows.
- **Inventory & COGS:** Linked the POS system to an inventory ledger. Implemented recipe mapping where menu items and modifiers deduct specific base units from inventory, dynamically calculating the Cost of Goods Sold (COGS).

## Phase 3B: Advanced Timekeeping, Leaves & Payroll Integration
- **Attendance Integration:** Rewrote the payroll `generate_drafts` logic to dynamically read from the `attendance` table. Automated the subtraction of unpaid break times from total daily hours.
- **Overtime & Premiums:** Programmed the system to automatically calculate and apply `overtime_hours`, `night_differential_hours`, and `late_minutes` using employee-specific hourly rates and premiums (e.g., 125% for OT, 110% for ND).
- **Leave Management:** Integrated the `leaves` table to classify approved time off as either "paid" or "unpaid" against specific payroll cutoffs.
- **Statutory Math:** Finalized the complex, bracket-based deduction formulas for PhilHealth, Pag-IBIG, SSS, and BIR withholding tax.

## Phase 4: P&L and Budgeting Alignment
- **Cross-Module Aggregation:** Bridged the HR labor schema and POS sales schema to generate an accurate, real-time Profit & Loss (P&L) dashboard.
- **True Revenue Tracking:** Rewrote the revenue aggregation queries to calculate gross sales directly through `branch_id`, bypassing flawed user-mapping assumptions.
- **Master Data Views:** Designed a comprehensive Master View via cross-joins of branches and months, ensuring all branches (even those without explicit budgets) appear in financial reports.
- **True Labor Costing:** Calculated dynamic Labor Costs directly from released payrolls by combining `gross_pay` with `employer_sss`, `employer_philhealth`, and `employer_pagibig` contributions.

## Phase 5: Compliance Reports and POS Controls
- **Statutory Exportation:** Built automated monthly CSV remittance exports grouping contributions by branch and month for SSS, PhilHealth, Pag-IBIG, and BIR.
- **Payroll Register:** Created a comprehensive CSV Payroll Register export function that dumps all finalized (Released) records into a standardized tabular format.
- **POS Governance:** Implemented audit-ready cash-session management (Open/Close Register) with strict variance tracking.
- **Loss Prevention:** Secured discounting, voiding, and refund operations behind HR/Admin supervision. Integrated an automatic inventory "Restock" logging mechanism when an order is voided or refunded.

## Phase 6: Hardening and Release
- **Data Encryption (At Rest):** Engineered an OpenSSL AES-256-CBC encryption pipeline powered by a secure `APP_KEY`. Migrated all sensitive statutory employee data (`bank_account`, `tin`, `sss`, `philhealth`, `pagibig`) to be fully encrypted in the database while seamlessly decrypting on-the-fly for authorized viewers and exports.
- **Secure File Uploads:** Hardened the `uploads/` directory against direct URL access using a strict `.htaccess` (`Require all denied`). Engineered a `file_proxy.php` gatekeeper that validates session roles and streams files securely to the frontend.
- **Database Indexing:** Protected the system against table-scans under heavy production load by injecting composite indexes into high-traffic tables (`payroll`, `attendance`, `orders`, `activity_logs`).
- **Production Deployment:** Executed a clean, targeted injection of InfinityFree production database credentials across both the HRMS and POS modules. Successfully deployed to live hosting and securely reverted local development files via Git to ensure no production credentials leaked into source control.
