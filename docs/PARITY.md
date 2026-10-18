# Fika Café HRMS & POS — System Parity Checklist

This document maps every legacy PHP page and API in `cafe_hrms/` and `cafe_pos/`, as well as addon features introduced on commit `f766c6b` (`feature/addons`), to their corresponding state in the `rebuild/core` architecture.

- **Total Legacy PHP Files Covered:** 85 (66 in `cafe_hrms/`, 19 in `cafe_pos/`)
- **Total Addon Feature Rows Covered:** 9 (from commit `f766c6b`)
- **Total Checklist Rows:** 94
- **Statuses Used:**
  - `PORTED`: Completely rewritten and active in `rebuild/core` under new security, RBAC, and architecture standards.
  - `PARTIAL`: Partially migrated, schema present, or backend API ported without complete dedicated UI.
  - `MISSING`: Not yet ported or scheduled in Day 1–2 rebuild.
  - `DROP-PROPOSED`: Candidate for decommissioning/removal with stated rationale (owner decides; never deleted unilaterally).

---

## 1. Legacy HRMS Core Pages (`cafe_hrms/*.php`)

| Old File | Feature | New File in Rebuild (or none) | Status | Notes |
| :--- | :--- | :--- | :--- | :--- |
| `cafe_hrms/account_management.php` | User accounts directory and role administration | `fika_hrms/fika_hrms_users.php` | `PORTED` | Rebuilt with strict PAM roles, branch assignment, password hashing, and `user.manage` RBAC permission. |
| `cafe_hrms/alter_schema.php` | Unversioned SQL schema alterations | None (`migrations/`) | `DROP-PROPOSED` | Legacy unversioned migration script; superseded by numbered, idempotent SQL files in `/migrations`. |
| `cafe_hrms/applicant_form.php` | Candidate job application intake form | None | `MISSING` | Public intake form for ATS candidate resumes, valid IDs, and contact info. |
| `cafe_hrms/applicants.php` | Tabular list of job applicants | None | `MISSING` | Legacy ATS applicant directory with search and stage filters. |
| `cafe_hrms/applications.php` | ATS recruitment pipeline Kanban board | None | `MISSING` | Interactive drag-and-drop / stage pipeline across candidate hiring phases. |
| `cafe_hrms/attendance.php` | Daily attendance time logs and KPI metrics | `fika_hrms/fika_hrms_attendance.php`, `fika_hrms/api/fika_hrms_attendance_adjust.php` | `PORTED` | Rebuilt with branch scoping, tardiness/overtime/night-differential calculation, and audited manual adjustments. |
| `cafe_hrms/careers.php` | Public careers job openings portal | None | `MISSING` | Public job board listing open roles across cafe branches. |
| `cafe_hrms/cli_encrypt_employees.php` | CLI encryption tool for existing employee data | `tools/migrate_encryption.php` | `PORTED` | Replaced by `tools/migrate_encryption.php` utilizing OpenSSL AES-256-CBC and HMAC verification via `Crypto`. |
| `cafe_hrms/config.php` | Hardcoded legacy database credentials & settings | `config/config.php` (untracked), `config/config.example.php` | `PORTED` | Hardcoded secrets eliminated in adherence to Rule 10; configuration loaded from environment or untracked config. |
| `cafe_hrms/create_db.php` | Raw database and table creation script | `tools/migrate.php`, `migrations/001_core.sql` | `DROP-PROPOSED` | One-off scratch database creation script; superseded by standard migration runner `tools/migrate.php`. |
| `cafe_hrms/dashboard.php` | Primary HRMS executive overview & KPI metrics | `fika_hrms/fika_hrms_dashboard.php` | `PORTED` | Rebuilt with role-based widgets, active headcount, attendance rate, and branch-scoped data isolation. |
| `cafe_hrms/data_update.php` | Recipe and modifier data fix script | `migrations/002_pos_inventory.sql` | `DROP-PROPOSED` | One-off database correction script; superseded by migration fixtures. |
| `cafe_hrms/data_update_reconfig.php` | Modifier group reconfiguration patch | `migrations/002_pos_inventory.sql` | `DROP-PROPOSED` | One-off legacy data fix script; superseded by versioned migrations. |
| `cafe_hrms/db_corrections.php` | Applicant table column patch script | None (`migrations/`) | `DROP-PROPOSED` | One-off scratch DB patch; superseded by migration scripts. |
| `cafe_hrms/db_corrections2.php` | Interview scorecards table patch script | None (`migrations/`) | `DROP-PROPOSED` | One-off scratch DB patch; superseded by migration scripts. |
| `cafe_hrms/db_corrections3.php` | Schema alteration patch script | None (`migrations/`) | `DROP-PROPOSED` | One-off scratch DB patch; superseded by migration scripts. |
| `cafe_hrms/dump_interviews.php` | Scratch utility to dump interview table | None | `DROP-PROPOSED` | Debug utility; violates Rule 14 (no debug scripts served in web directories). |
| `cafe_hrms/employee_action.php` | Backend handler for employee promotion/rehire/termination | `fika_hrms/api/fika_hrms_employee_status.php` | `PARTIAL` | Active status toggle ported; dedicated compensation raise and termination audit reasons need dedicated handlers. |
| `cafe_hrms/employee_form.php` | Create and edit employee profile form | `fika_hrms/fika_hrms_employee_form.php`, `fika_hrms/api/fika_hrms_employee_save.php` | `PORTED` | Rebuilt with sensitive field encryption, branch assignment, hourly wage handling, and `staff_class` scoping. |
| `cafe_hrms/employee_view.php` | Consolidated employee 360 profile dossier | `fika_hrms/fika_hrms_employee_form.php`, `fika_hrms/fika_hrms_payslip.php` | `PARTIAL` | Individual modules (payroll, attendance, leaves) are accessible, but unified tabbed 360 view is not yet rebuilt. |
| `cafe_hrms/employees.php` | Master employee directory | `fika_hrms/fika_hrms_employees.php` | `PORTED` | Rebuilt with server-side pagination, branch scoping, and `employee.view` RBAC permission checks. |
| `cafe_hrms/ess.php` | Employee Self-Service (ESS) personal portal | `fika_hrms/fika_hrms_payslip.php` | `MISSING` | Self-service leave balances and personal attendance dashboard missing; payslip view ported. |
| `cafe_hrms/ess_board.php` | Company bulletin and announcements board | None | `MISSING` | Internal announcements feature not included in Day 1–2 rebuild scope. |
| `cafe_hrms/export.php` | Statutory CSV remittance exports (SSS, PhilHealth, Pag-IBIG) | `fika_hrms/api/fika_hrms_payroll_export.php` | `PARTIAL` | Rebuilt payroll run export exists; statutory monthly agency remittance export formats are missing. |
| `cafe_hrms/file_proxy.php` | Secure file downloader / proxy | `fika_hrms/api/fika_hrms_file_proxy.php` | `PORTED` | Rebuilt with strict role permission check (`employee.view`), MIME streaming, and path traversal protection. |
| `cafe_hrms/finance_budgeting.php` | Branch monthly budgeting & budget allocation | `fika_finance/fika_finance_dashboard.php` | `PARTIAL` | `branch_budgets` schema exists in migration 003, but interactive budgeting and variance UI is placeholder. |
| `cafe_hrms/finance_reports.php` | Consolidated monthly financial statements and P&L | `fika_finance/fika_finance_dashboard.php` | `PARTIAL` | SQL views `v_revenue_monthly` and `v_cogs_monthly` ported in migration 004; UI dashboard is placeholder. |
| `cafe_hrms/find_schema.php` | Utility script querying `information_schema` | None | `DROP-PROPOSED` | Scratch debug script; unsafe for web-served production directory. |
| `cafe_hrms/fix_remaining.php` | Legacy file search-and-replace script | None | `DROP-PROPOSED` | Scratch maintenance script; superseded. |
| `cafe_hrms/fix_syntax.php` | Legacy syntax patch script | None | `DROP-PROPOSED` | Scratch maintenance script; superseded. |
| `cafe_hrms/forgot_password.php` | Self-service password reset request page | None | `MISSING` | Email password reset request flow not included in Day 1–2 rebuild. |
| `cafe_hrms/functions.php` | Legacy procedural helper library | `app/core/Crypto.php`, `app/core/Output.php`, `app/core/Money.php` | `PORTED` | Procedural functions refactored into OOP single-responsibility classes with unit tests. |
| `cafe_hrms/index.php` | Root redirect to dashboard/login | `fika_hrms/fika_hrms_dashboard.php` | `PORTED` | Rebuilt modules have dedicated entrypoints requiring authentication. |
| `cafe_hrms/init.php` | Procedural session & database bootstrap | `app/bootstrap.php` | `PORTED` | Replaced by centralized `app/bootstrap.php` with security headers, PSR autoloader, and PDO config. |
| `cafe_hrms/interview_workspace.php` | ATS candidate interview scorecard workspace | None | `MISSING` | Candidate evaluation rubrics, interview questions, and scorecard submission interface. |
| `cafe_hrms/inventory.php` | HRMS inventory stock levels and restock management | `fika_inventory/fika_inventory_dashboard.php` | `PARTIAL` | `inventory_items`, `inventory_stock`, `inventory_ledger` schema ported in 002; full UI is placeholder. |
| `cafe_hrms/kiosk.php` | Touchscreen employee attendance clock-in terminal | `fika_hrms/fika_hrms_clock.php`, `fika_hrms/api/fika_hrms_clock_punch.php` | `PORTED` | Rebuilt with device token binding (`Device`), PIN verification, rate-limit lockout, and shift enforcement. |
| `cafe_hrms/leave.php` | Leave request submission and approval workflow | `fika_hrms/fika_hrms_leave.php`, `fika_hrms/api/fika_hrms_leave_create.php`, `fika_hrms/api/fika_hrms_leave_decide.php` | `PORTED` | Rebuilt with branch scoping, maker-checker enforcement (self-approval blocked), and leave ledger tracking. |
| `cafe_hrms/login.php` | HRMS user authentication page | `app/core/Auth.php` | `PARTIAL` | Authentication logic, lockout protection, and CSRF ported in `Auth.php`; standalone unified login view pending. |
| `cafe_hrms/logout.php` | Session logout termination handler | `app/core/Auth.php` | `PORTED` | Handled via `Auth::logout()` destroying sessions and clearing auth cookies. |
| `cafe_hrms/menu_list.php` | Menu item pricing and recipe BOM configuration | None | `MISSING` | POS catalog management UI (recipes, ingredients, modifiers, base prices) not yet ported. |
| `cafe_hrms/payroll.php` | Payroll cutoff generation and approval dashboard | `fika_hrms/fika_hrms_payroll.php`, `fika_hrms/fika_hrms_payroll_run.php` | `PORTED` | Rebuilt with strict approval chain, maker-checker validation, branch scoping, and certified attendance. |
| `cafe_hrms/performance.php` | Employee performance review summary | None | `MISSING` | Performance evaluation records and department rating summary missing from rebuild. |
| `cafe_hrms/performance_form.php` | Performance appraisal evaluation form | None | `MISSING` | Multi-criteria performance evaluation grading form missing from rebuild. |
| `cafe_hrms/pos_dashboard.php` | HRMS overview of POS register sessions and sales | `fika_pos/fika_pos_dashboard.php` | `PORTED` | Rebuilt with branch scoping and `pos.view` permission checks. |
| `cafe_hrms/procurement.php` | Store purchase orders & procurement requests | None | `MISSING` | Supply requisition forms, purchase orders, and vendor approval workflow not included in Day 1–2. |
| `cafe_hrms/raise_form.php` | Compensation adjustment & promotion form | `fika_hrms/fika_hrms_employee_form.php` | `PARTIAL` | Hourly wage editable on employee form; dedicated compensation change approval workflow missing. |
| `cafe_hrms/read_notif.php` | Dismiss user notification endpoint | None | `MISSING` | Notification center backend endpoint missing from rebuild. |
| `cafe_hrms/rehire_form.php` | Terminated employee reactivation wizard | `fika_hrms/api/fika_hrms_employee_status.php` | `PARTIAL` | Status activation exists in `fika_hrms_employee_status.php`; formal rehire audit wizard is missing. |
| `cafe_hrms/replace_salary_rate.php` | Data migration script converting monthly to hourly rates | `migrations/013_hourly_rate.sql` | `DROP-PROPOSED` | One-off patch script; superseded by migration 013. |
| `cafe_hrms/reports.php` | Statutory reports portal index | `fika_hrms/api/fika_hrms_payroll_export.php` | `PARTIAL` | Payroll register export exists; statutory report navigation page missing. |
| `cafe_hrms/reset_password.php` | Token-based password reset form | None | `MISSING` | Self-service password recovery flow missing from rebuild. |
| `cafe_hrms/roles.php` | Procedural role name constants | `config/permissions.php`, `app/core/Rbac.php` | `PORTED` | Replaced by PAM-compliant role constants and granular permission maps in `permissions.php`. |
| `cafe_hrms/settings.php` | Global statutory rates, tax brackets, and cutoff settings | `fika_hrms/fika_hrms_settings.php`, `fika_hrms/api/fika_hrms_settings_save.php` | `PORTED` | Rebuilt with split contribution rates, standard cutoff rules, and strict admin RBAC gating. |
| `cafe_hrms/shift_board.php` | Visual employee shift scheduling calendar | None | `MISSING` | Weekly branch shift schedule and employee shift assignment calendar not ported. |
| `cafe_hrms/show_tables.php` | Utility script querying database tables | None | `DROP-PROPOSED` | Scratch debug script; unsafe for web-served production directory. |
| `cafe_hrms/system_accounts.php` | User credential management for administrative staff | `fika_hrms/fika_hrms_users.php` | `PORTED` | Rebuilt with branch scoping, strong password validation, and PAM role assignment. |
| `cafe_hrms/terminate_form.php` | Formal employee termination and exit wizard | `fika_hrms/api/fika_hrms_employee_status.php` | `PARTIAL` | Deactivation supported in status API; separation reason and exit clearance workflow missing. |
| `cafe_hrms/test_col.php` | Debug script testing column types | None | `DROP-PROPOSED` | Scratch debug script; unsafe for web-served production directory. |
| `cafe_hrms/update_kiosk_role.php` | Database role permission updater | `migrations/010_seed_roles_permissions.sql`, `migrations/014_seed_roles_permissions.sql` | `DROP-PROPOSED` | One-off patch script; superseded by versioned migrations. |
| `cafe_hrms/verify.php` | Candidate email verification handler | None | `MISSING` | Email token verification handler for public job applicants missing from rebuild. |

---

## 2. Legacy HRMS API Endpoints (`cafe_hrms/api/*.php`)

| Old File | Feature | New File in Rebuild (or none) | Status | Notes |
| :--- | :--- | :--- | :--- | :--- |
| `cafe_hrms/api/convert_to_employee.php` | Candidate-to-employee onboarding conversion | None | `MISSING` | Automatically provisions employee record and payroll profile from approved applicant. |
| `cafe_hrms/api/get_applicant_drawer.php` | Dynamic applicant details drawer payload | None | `MISSING` | Fetches interview notes, scorecard ratings, and candidate attachments for ATS drawer. |
| `cafe_hrms/api/inventory_crud.php` | Inventory master list item CRUD API | None | `MISSING` | Backend CRUD endpoint for inventory items; schema exists in migration 002. |
| `cafe_hrms/api/menu_crud.php` | POS menu catalog and recipe BOM CRUD API | None | `MISSING` | Backend CRUD endpoint for menu products, modifier bindings, and recipe BOM deductions. |
| `cafe_hrms/api/update_applicant_stage.php` | ATS Kanban candidate stage change API | None | `MISSING` | Updates applicant recruitment phase with audit logging. |

---

## 3. Legacy POS Core Pages & APIs (`cafe_pos/*.php` & `cafe_pos/api/*.php`)

| Old File | Feature | New File in Rebuild (or none) | Status | Notes |
| :--- | :--- | :--- | :--- | :--- |
| `cafe_pos/cashier.php` | POS Cashier register frontend interface | None | `MISSING` | Interactive cashier register UI (cart, product catalog, modifier selection, checkout). Backend APIs are ported. |
| `cafe_pos/database.php` | Legacy POS database connection script | `app/bootstrap.php` | `PORTED` | Unified into single application bootstrap PDO instance. |
| `cafe_pos/kiosk.php` | Customer self-service ordering kiosk frontend | `fika_pos/fika_pos_kiosk.php`, `fika_pos/fika_pos_dashboard.php`, `fika_pos/api/fika_pos_kiosk_order.php`, `fika_pos/api/fika_pos_kiosk_status.php` | `PORTED` | Rebuilt touch self-service kiosk UI with device token auth, order-shift separation, age limit filter, and status polling. |
| `cafe_pos/temp_mig.php` | POS inventory transaction patch script | `migrations/002_pos_inventory.sql` | `DROP-PROPOSED` | One-off migration scratch file; superseded by numbered migrations. |
| `cafe_pos/temp_mig2.php` | POS database migration patch script | `migrations/002_pos_inventory.sql` | `DROP-PROPOSED` | One-off migration scratch file; superseded by numbered migrations. |
| `cafe_pos/update_db.php` | POS database schema updater | `migrations/002_pos_inventory.sql` | `DROP-PROPOSED` | One-off migration scratch file; superseded by numbered migrations. |
| `cafe_pos/api/menu.php` | POS menu catalog JSON API | None | `MISSING` | JSON endpoint delivering active menu items, categories, and modifiers to POS register. |
| `cafe_pos/api/orders.php` | POS order creation and line-item calculation API | `fika_pos/api/fika_pos_order_create.php` | `PORTED` | Rebuilt with branch scoping, server-side price recalculation, and inventory stock reservation. |
| `cafe_pos/api/orders_cancel.php` | Cancel unpaid pending POS order | `fika_pos/api/fika_pos_order_void_refund.php` | `PORTED` | Order cancellation and void handling consolidated into `fika_pos_order_void_refund.php`. |
| `cafe_pos/api/orders_complete.php` | Mark order fulfilled / kitchen completed | None | `MISSING` | Order completion trigger for barista/kitchen queue not yet ported. |
| `cafe_pos/api/orders_get.php` | Retrieve order details & receipt JSON API | None | `MISSING` | Order detail retrieval endpoint for receipt printing or status lookup missing. |
| `cafe_pos/api/orders_pay.php` | POS order payment processing API | `fika_pos/api/fika_pos_order_pay_cash.php` | `PARTIAL` | Cash payment processing ported; digital payment processing (PayMongo) exists on `feature/addons`. |
| `cafe_pos/api/orders_pending.php` | List open/pending orders for kitchen display | None | `MISSING` | Active open orders queue endpoint for kitchen/barista display missing. |
| `cafe_pos/api/orders_void_refund.php` | Managerial void / refund with inventory restock | `fika_pos/api/fika_pos_order_void_refund.php` | `PORTED` | Rebuilt with `pos.void` permission check, audit logging, and automatic inventory ledger restock. |
| `cafe_pos/api/paymongo_checkout.php` | PayMongo digital checkout session creator | None | `MISSING` | Implemented and hardened on `feature/addons` (`payment_intent_create.php`), pending merge into `rebuild/core`. |
| `cafe_pos/api/paymongo_webhook.php` | PayMongo digital payment confirmation webhook | None | `MISSING` | Hardened webhook with signature verification and row-locking on `feature/addons` (`payment_webhook.php`). |
| `cafe_pos/api/pos_auth.php` | Cashier register PIN unlock and authentication | `fika_pos/api/fika_pos_unlock.php` | `PORTED` | Rebuilt with registered device validation (`Device`), lockout protection, and CSRF enforcement. |
| `cafe_pos/api/pos_sessions.php` | Cash register session open and close API | `fika_pos/api/fika_pos_register_open.php`, `fika_pos/api/fika_pos_register_close.php` | `PORTED` | Rebuilt with integer centavos (`Money`), expected cash calculation, variance audit, and register locking. |
| `cafe_pos/api/sales_summary.php` | Cashier shift sales summary breakdown | `fika_pos/api/fika_pos_register_close.php` | `PARTIAL` | Shift totals calculated during register close; standalone mid-shift summary endpoint missing. |

---

## 4. Addon Features Introduced in `feature/addons` (Commit `f766c6b`)

| Addon Path / Feature | Description | New File in Rebuild (or none) | Status | Notes |
| :--- | :--- | :--- | :--- | :--- |
| `index.php` | Public café landing page | None | `MISSING` | Brand landing page introducing the café, ambiance, menu preview, and portal links. |
| `login.php` | Unified central login portal | None (`app/core/Auth.php`) | `MISSING` | Standalone unified login page directing authenticated users to HRMS or POS based on role. |
| `cafe_hrms/settlement_reports.php` | Daily POS register settlement reports | None | `MISSING` | HR/Finance view to audit closed POS cash sessions, actual vs expected cash, and cashier variances. |
| `cafe_pos/receipt.php` & `cafe_pos/partials/receipt.php` | Thermal printable receipt | None | `MISSING` | Standard 58mm/80mm thermal receipt generator displaying order items, VAT, and discounts. |
| `cafe_pos/api/payment_intent_create.php` | PayMongo payment intent creation API | None | `MISSING` | Hardened digital payment intent creation (GCash, PayMaya, Card) using server-side keys. |
| `cafe_pos/api/payment_status.php` | PayMongo transaction polling API | None | `MISSING` | Polling endpoint for kiosk and cashier UI to monitor digital transaction status. |
| `cafe_pos/api/payment_webhook.php` | Hardened PayMongo webhook endpoint | None | `MISSING` | Signature verification, pessimistic row locking (`FOR UPDATE`), and replay protection. |
| `migrations/017_add_menu_item_images.sql` & uploads | Menu item image catalog & uploads | None | `MISSING` | Image storage in `uploads/menu/` with `.htaccess` execution denial and POS visual cards. |
| `migrations/020_order_kiosk_tokens.sql` | Kiosk terminal session tokens | `migrations/019_order_kiosk_tokens.sql` | `PORTED` | Rebuilt with hashed order-bound tokens, 30-min expiration, device token auth, and age limit setting. |

---

## 5. Old-System Features With NO Row in Day 1–3 Plans

The following features were operational in the legacy system (`cafe_hrms` / `cafe_pos`), but are currently **unplanned** in the Day 1–3 rebuild scopes and require product owner prioritization:

1. **Full Applicant Tracking System (ATS) & Recruitment Pipeline:**
   - Public careers job board (`careers.php`, `applicant_form.php`)
   - Candidate email verification workflow (`verify.php`)
   - Interactive Kanban recruitment board (`applications.php`, `api/get_applicant_drawer.php`, `api/update_applicant_stage.php`)
   - Candidate interview scorecard workspace with structured grading rubrics (`interview_workspace.php`)
   - Automated candidate-to-employee onboarding conversion pipeline (`api/convert_to_employee.php`)
2. **Employee Performance Evaluation & Appraisal System:**
   - Performance review dashboard and departmental rating averages (`performance.php`)
   - Manager/Barista multi-criteria appraisal submission form with rubrics (`performance_form.php`)
3. **Branch Shift Scheduling & Shift Board:**
   - Weekly branch shift assignment calendar and roster management grid (`shift_board.php`)
4. **Employee Self-Service (ESS) Personal Portal:**
   - Dedicated employee personal dashboard with leave balance widgets and attendance calendar (`ess.php`)
   - Company announcement bulletins and news board (`ess_board.php`)
   - User notification center and notification dismissal API (`read_notif.php`)
5. **Formal Employee Lifecycle Change Wizards:**
   - Promotion and compensation raise proposal wizard (`raise_form.php`)
   - Formal employee termination, exit interview, and clearance wizard (`terminate_form.php`)
   - Formal employee rehire and service restoration wizard (`rehire_form.php`)
6. **Procurement & Purchase Requisition System:**
   - Branch supply purchase request forms, vendor ordering, and purchase order approval workflow (`procurement.php`)
7. **Self-Service Password Recovery:**
   - Email-based forgot password request flow (`forgot_password.php`)
   - Token verification and password reset interface (`reset_password.php`)
8. **Kitchen / Barista Order Fulfillment Queue:**
   - Real-time kitchen pending orders queue endpoint (`api/orders_pending.php`)
   - Barista kitchen fulfillment trigger marking orders as served (`api/orders_complete.php`)
9. **Statutory Agency Compliance Remittance Reports:**
   - Formatted CSV remittance generators matching government agency specifications: SSS R-3, PhilHealth RF-1, Pag-IBIG MCRF, and BIR Form 1601-C (`export.php`, `reports.php`).
