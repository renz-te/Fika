# Fika Café — Production Cutover Runbook & Rollback Plan

> **Document Version:** 1.0 (Phase 18C)  
> **Target Environment:** InfinityFree Web Hosting (PHP 8.2 / MySQL 8.0 / Apache)  
> **Source Branch:** `rebuild/core`  
> **Production Branch:** `main`  
> **Target Production Directory:** `/htdocs/`  
> **Estimated Maintenance Window:** 30–45 minutes  
> **Audience:** Fika Café Owner / System Administrator (Manual Execution Guide)

---

## Overview & Architecture Notice

This runbook outlines the exact step-by-step procedure for transitioning Fika Café from the legacy system to the unified architecture (`rebuild/core`). 

Because InfinityFree provides a shared cPanel environment **without SSH/CLI access**, deployment relies on:
1. **GitHub Actions (`ftp-deploy.yml`)** for automated, whitelisted file synchronization (109 deployable files).
2. **Manual FTP uploads** for sensitive configuration files (`.env` and `config/config.php`).
3. **phpMyAdmin** for database backup importation and schema verification.

---

## Phase 1: Pre-Flight Checklist (Local Environment)

Execute these steps on your local development machine (Laragon PowerShell terminal) **before** touching production.

### Step 1.1: Ensure You Are on `rebuild/core`
Open PowerShell in `c:\laragon\www\Fika\Fika`:
```powershell
git checkout rebuild/core
git status
```
*Expected Result:* `On branch rebuild/core`, working tree clean.

### Step 1.2: Backup the Local Unified Database
Create a full SQL dump of the local unified database `fika_unified`:
```powershell
mysqldump -u root fika_unified > backup_before_cutover.sql
```
Verify the dump was created and is non-empty:
```powershell
Get-Item backup_before_cutover.sql | Select-Object Name, Length
```
*Expected Result:* `backup_before_cutover.sql` size should be > 100 KB.

### Step 1.3: Backup the Local Environment Configuration
Copy your local `.env` to a secure backup copy:
```powershell
Copy-Item .env .env.backup
```

### Step 1.4: Verify Legacy Data Parity
Ensure all data from legacy `hrms` and `cafe_pos` is completely reconciled in the target structure:
```powershell
php tools/verify_parity.php
```
*Expected Result:* Output ends with:
```text
====================================================================
FINAL INTEGRITY VERIFICATION: ALL PARITY CHECKS PASSED
====================================================================
```

### Step 1.5: Execute the Master Security Regression Suite
Run the 10-trap security suite to verify auth, webhooks, maker-checker, and upload hardening:
```powershell
php tests/security/run_all.php
```
*Expected Result:*
```text
====================================================================
SECURITY REGRESSION: 10/10 TRAPS PASSED
Exit Code: 0
====================================================================
```

---

## Phase 2: InfinityFree Hosting Preparation

### Step 2.1: Gather InfinityFree Database Credentials
1. Log in to your **InfinityFree Client Area** (`https://dash.infinityfree.com/`).
2. Select your hosting account for Fika Café.
3. Click on **Manage** > **Control Panel** (cPanel / VistaPanel).
4. Click on **MySQL Databases**.
5. Note down your production database credentials:
   - **MySQL Hostname:** e.g., `sql102.infinityfree.com` (or `sqlXXX.epizy.com` — **NEVER** `localhost`)
   - **MySQL Database Name:** e.g., `if0_38291024_fika_prod`
   - **MySQL Username:** e.g., `if0_38291024`
   - **MySQL Password:** Your InfinityFree vPanel password

### Step 2.2: Prepare the Production Configuration Files Locally
Create a dedicated staging folder outside git or on your desktop for manual FTP upload:

#### A. Production `.env` (`.env.production`)
Create a file named `.env` for production with the following content:
```ini
PAYMONGO_PUBLIC_KEY=YOUR_PUBLIC_KEY_HERE
PAYMONGO_SECRET_KEY=YOUR_SECRET_KEY_HERE
PAYMONGO_WEBHOOK_SECRET=YOUR_WEBHOOK_SECRET_HERE
PAYMONGO_IS_TEST=true
```
> [!IMPORTANT]
> Keep `PAYMONGO_IS_TEST=true` during initial cutover. This allows safe simulation of GCash/Maya checkouts without charging live payment cards. You can change it to `false` and update keys once live PayMongo merchant verification is completed.

#### B. Production `config/config.php` (`config.production.php`)
Create a production `config.php` containing your InfinityFree database details:
```php
<?php
// Production Configuration — InfinityFree
return [
    'app' => [
        'env' => 'production',
        'base_url' => 'https://your-domain.infinityfreeapp.com', // Replace with your exact domain
        'timezone' => 'Asia/Manila',
        'session_timeout' => 3600,
    ],
    
    'db' => [
        'host' => 'sql102.infinityfree.com', // Replace with your MySQL Hostname
        'name' => 'if0_38291024_fika_prod',  // Replace with your Database Name
        'user' => 'if0_38291024',            // Replace with your MySQL Username
        'pass' => 'YourSecretVPanelPassword',// Replace with your MySQL Password
        'charset' => 'utf8mb4',
    ],
    
    'security' => [
        'app_key' => 'fika_prod_secure_app_key_8492019482019482', // 32-char secure random string
    ],
    
    'paymongo' => [
        'public_key'     => getenv('PAYMONGO_PUBLIC_KEY') ?: '',
        'secret_key'     => getenv('PAYMONGO_SECRET_KEY') ?: '',
        'webhook_secret' => getenv('PAYMONGO_WEBHOOK_SECRET') ?: '',
    ]
];
```

### Step 2.3: Upload Configuration Files via FileZilla / FTP
1. Open FileZilla (or your preferred FTP client).
2. Connect using your InfinityFree FTP credentials:
   - **Host:** `ftpupload.net` (Port 21)
   - **Username:** Your account username (e.g., `if0_38291024`)
   - **Password:** Your vPanel account password
   - **Encryption:** Use explicit FTP over TLS if available
3. Navigate to remote directory: `/htdocs/`
4. Upload your prepared `.env` file directly to `/htdocs/.env`.
5. Navigate into `/htdocs/config/` (create folder if missing).
6. Upload your prepared `config.php` to `/htdocs/config/config.php`.

> [!CAUTION]
> NEVER commit `config/config.php` or `.env` to Git. GitHub Actions deployment strictly excludes these files to ensure production secrets are never overwritten or leaked.

---

## Phase 3: Database Migration on Production

### Step 3.1: Open phpMyAdmin
1. In the InfinityFree Control Panel, click on **phpMyAdmin**.
2. Click **Connect** next to your database name (`if0_38291024_fika_prod`).
3. Select the database on the left sidebar.

### Step 3.2: Import Database SQL Dump
1. Click the **Import** tab on the top menu.
2. Under "File to import", click **Choose File** and select `backup_before_cutover.sql` from your computer.
3. Ensure **Character set of the file** is `utf-8`.
4. Leave other options as default (SQL format).
5. Click **Import** (or **Go**) at the bottom.
6. Wait for the green success notification: `"Import has been successfully finished, XXX queries executed."`

### Step 3.3: Verify Production Table Structure
Click the **SQL** tab in phpMyAdmin and run:
```sql
SHOW TABLES;
```
Verify that all core tables are present (30+ tables):
- `users`, `roles`, `role_permissions`, `branches`, `employees`
- `employee_documents`, `leave_requests`, `shifts`, `open_shifts`
- `attendance_records`, `payroll_runs`, `payroll_items`, `contribution_rates`
- `products`, `modifiers`, `categories`, `branch_products`
- `recipes`, `inventory_items`, `inventory_stock`, `inventory_ledger`
- `inventory_adjustments`, `suppliers`, `purchase_orders`, `purchase_order_items`
- `expenses`, `expense_categories`, `budgets`
- `orders`, `order_items`, `order_item_modifiers`, `cash_sessions`
- `payments`, `order_kiosk_tokens`, `devices`, `audit_logs`, `settings`

---

## Phase 4: Deployment via GitHub Actions

### Step 4.1: Merge `rebuild/core` into `main`
On your local machine, push `rebuild/core` and merge into `main`:
```powershell
# 1. Push latest rebuild/core branch
git push origin rebuild/core

# 2. Switch to main
git checkout main
git pull origin main

# 3. Merge rebuild/core into main
git merge rebuild/core --no-ff -m "chore(cutover): merge rebuild/core for v1.0 production cutover"

# 4. Push to trigger GitHub Actions
git push origin main
```

### Step 4.2: Monitor the GitHub Actions Deployment
1. Go to your GitHub repository: `https://github.com/renz-te/Fika/actions`
2. Click on the latest workflow run: **Deploy via FTP** (`.github/workflows/ftp-deploy.yml`).
3. Monitor the job logs:
   - Checkout repository ✅
   - Sync Files via `SamKirkland/FTP-Deploy-Action@v4.3.5` ✅
   - Ensure upload log reports **~109 files** synced.
   - Verify that `.env`, `config/config.php`, `tests/`, `tools/`, `migrations/`, and `.sql` were **NOT** uploaded.

### Step 4.3: Verify Public Endpoint Availability
Open your browser and visit:
1. **Public Landing Page:** `https://your-domain.infinityfreeapp.com/`
   - Should load modern Fika Café landing page with logo, menu highlights, and careers link.
2. **Staff Login Page:** `https://your-domain.infinityfreeapp.com/login.php`
   - Should display clean login interface.

---

## Phase 5: Production Smoke Test

Perform this end-to-end walkthrough immediately after deployment.

### Step 5.1: Admin Authentication
1. Go to `https://your-domain.infinityfreeapp.com/login.php`.
2. Log in with admin credentials:
   - **Username:** `admin`
   - **Password:** `Password123!` (or your configured admin password).
3. Confirm redirect to `fika_hrms/fika_hrms_dashboard.php` or main management dashboard.
4. Verify navigation between **HRMS**, **POS**, **Finance**, and **Inventory**.

### Step 5.2: POS Kiosk Order & Mock Checkout
1. Navigate to POS Kiosk screen: `https://your-domain.infinityfreeapp.com/fika_pos/fika_pos_kiosk.php`.
2. Select **Dine In** or **Take Out**.
3. Add a product (e.g. *Americano*) and customize with an extra shot modifier.
4. Click **Proceed to Checkout**.
5. Select **GCash** or **PayMaya** as payment method.
6. Verify redirect to the Mock Checkout page:
   - URL: `.../fika_pos/fika_pos_mock_checkout.php?order_id=...`
   - Since `PAYMONGO_IS_TEST=true`, the mock page displays order total and "Authorize Mock Payment" button.
7. Click **Authorize Mock Payment**.
8. Verify payment completes with green success checkmark and redirect to order completion screen.

### Step 5.3: Receipt Generation & Print Verification
1. Click **View / Print Receipt**.
2. Verify receipt contains:
   - Café Header ("FIKA CAFÉ"), Branch name, Address, TIN.
   - Order Number, Cashier/Kiosk identifier, Date/Time.
   - Line items, modifier breakdown, Subtotal, VAT breakdown, and Payment Method (`GCASH` or `MAYA`).
3. Confirm thermal print view renders correctly without styling glitches.

### Step 5.4: Verify Inventory Ledger Deduction
1. As ADMIN, navigate to **Inventory Stock**:
   `https://your-domain.infinityfreeapp.com/fika_inventory/fika_inventory_stock.php`
2. Check the inventory items linked to the ordered product's recipe (e.g., *Espresso Beans*).
3. Verify that the stock quantity was reduced by the recipe amount.
4. Check **Inventory Ledger** to confirm an entry exists with reason/remarks matching the order number.

### Step 5.5: Staff Logout
1. Click the user avatar in the header and click **Logout**.
2. Confirm session terminates and redirects to `/login.php`.

---

## Phase 6: Emergency Rollback Plan

If a critical issue occurs during cutover (e.g., catastrophic fatal error, database corruption, or fatal hosting incompatibility), execute this rollback procedure immediately.

### Rollback Criteria:
- Staff cannot log in (systemic 500 error).
- POS cannot process cash or digital orders.
- Database integrity errors or foreign key corruption.

### Step 6.1: Revert Code on GitHub
In your local PowerShell terminal:
```powershell
git checkout main
git pull origin main

# Revert the cutover merge commit
git revert HEAD -m 1

# Push the revert to trigger automatic deployment of previous stable state
git push origin main
```
*Wait 2–3 minutes for GitHub Actions to complete syncing the previous files.*

### Step 6.2: Restore Production Database
1. Open phpMyAdmin on InfinityFree.
2. Select `if0_38291024_fika_prod`.
3. Check all tables and click **Drop** (with foreign key checks disabled or drop each table).
4. Click **Import** tab.
5. Select the legacy pre-migration database dump (e.g., `backup_before_real_import.sql` or your pre-cutover legacy backup).
6. Click **Go** to restore the previous database state.

### Step 6.3: Restore Configuration Files
If configurations were modified:
1. Re-upload `.env.backup` to `/htdocs/.env` via FileZilla.
2. Re-upload your legacy `config/config.php` via FileZilla.

### Step 6.4: Validate Rollback State
1. Visit `https://your-domain.infinityfreeapp.com/`.
2. Confirm previous operational state is fully restored.
3. Document root cause of failure before attempting another cutover.

---

## Phase 7: Post-Cutover Monitoring (First 7 Days)

| Day | Focus Area | Action Items |
| :--- | :--- | :--- |
| **Day 1** | **Error Logs & Auth** | Inspect php error log via FTP or InfinityFree Control Panel. Check `audit_logs` for any `LOGIN_FAILED` spikes. |
| **Day 2** | **POS & Cash Drawers** | Verify cash sessions balance (`starting_cash` vs `expected_cash` vs `ending_cash`). |
| **Day 3** | **PayMongo Webhooks** | Query `audit_logs` for `action = 'PAYMENT_MISMATCH'` or `MOCK_PAYMENT_REJECTED`. Verify centavo conversions. |
| **Day 4** | **Inventory Deductions** | Audit `order_ledger_deductions` against daily orders count. Ensure 0 missing deductions and 0 duplicate deductions. |
| **Day 5** | **HRMS Timeclock & Shifts**| Verify employee attendance logs, break calculations, and shift claim swaps in `open_shifts`. |
| **Day 6** | **Finance & Procurement** | Verify purchase orders, expense auto-approval thresholds, and maker-checker approval locks. |
| **Day 7** | **Final Operational Sign-off** | Review overall system performance and database growth in phpMyAdmin. Transition PayMongo to Live credentials if approved. |

---

## Phase 8: Troubleshooting Guide

### 1. Issue: `.env` file not loading (`Config::get('paymongo.secret_key')` is null / HTTP 503)
- **Symptom:** PayMongo digital payments return `503 Service Unavailable: payment gateway not configured` or webhook returns 503.
- **Cause 1: Dotfile Hidden/Blocked:** Some FTP clients skip uploading hidden dotfiles (`.env`).
  - *Fix:* In FileZilla, go to **Server** > check **Force showing hidden files**. Re-upload `.env`.
- **Cause 2: Incorrect File Encoding:** Windows Notepad can save `.env` with UTF-8 BOM or UTF-16, which corrupts key parsing.
  - *Fix:* Open `.env` in VS Code or Notepad++. Ensure encoding is **UTF-8 (without BOM)** and line endings are **LF** or **CRLF**.
- **Cause 3: Missing in `/htdocs/`:**
  - *Fix:* Ensure the file is at the root `/htdocs/.env`, NOT `/htdocs/config/.env`.

### 2. Issue: Database Connection Failed (`Database Connection Error`)
- **Symptom:** White screen or error message `Database Connection Error. Please check your configuration.`
- **Cause 1: `localhost` specified instead of remote host:** InfinityFree MySQL server is **never** on `localhost`.
  - *Fix:* Open `/htdocs/config/config.php` and verify:
    ```php
    'host' => 'sqlXXX.infinityfree.com', // Must match MySQL Hostname in vPanel
    ```
- **Cause 2: Database Name Prefix Missing:** InfinityFree databases always require the account prefix.
  - *Fix:* Ensure `'name' => 'if0_38291024_fika_prod'`, not just `fika_prod`.
- **Cause 3: Account vPanel Password Changed:**
  - *Fix:* Update `'pass'` in `config.php` to match your current InfinityFree account password.

### 3. Issue: PayMongo Webhook Failing (`401 Unauthorized` or `Invalid signature`)
- **Symptom:** Customer pays, but order remains `PENDING` and unpaid.
- **Cause 1: Webhook Secret Mismatch:**
  - *Fix:* Log in to the [PayMongo Dashboard](https://dashboard.paymongo.com/) > **Developers** > **Webhooks**. Copy the exact **Signing Secret** (starts with `whsk_` or `whsec_`) and update `PAYMONGO_WEBHOOK_SECRET` in `/htdocs/.env`.
- **Cause 2: Webhook URL Misconfigured in PayMongo:**
  - *Fix:* Ensure the registered Webhook endpoint URL in PayMongo is:
    ```text
    https://your-domain.infinityfreeapp.com/fika_pos/api/fika_pos_payment_webhook.php
    ```
- **Cause 3: Server Clock Skew:**
  - *Fix:* PayMongo signatures expire after 300 seconds. Ensure the server timezone is set to `'Asia/Manila'` in `config/config.php`.

---

## Owner's Quick Reference Command Sheet

```powershell
# 1. Pre-cutover local database dump
mysqldump -u root fika_unified > backup_before_cutover.sql

# 2. Local parity & security checks
php tools/verify_parity.php
php tests/security/run_all.php

# 3. Deploy rebuild/core to production via GitHub Actions
git checkout main
git pull origin main
git merge rebuild/core --no-ff -m "chore(cutover): merge rebuild/core to main"
git push origin main

# 4. Emergency code rollback (if needed)
git revert HEAD -m 1
git push origin main
```
