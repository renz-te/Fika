# Phase 1 Report: Core Architecture Setup

## What was built
Established the core directory structure and application foundation based on the rebuild rules. The system is designed to route through a single `app/init.php` file that standardizes security, database access, session management, and configuration loading.

## Files Created
- `config/app.php`: Base configuration variables (timezone, session timeout, base URL).
- `config/database.php`: MySQL database credentials targeting the unified schema (`hrms`).
- `app/init.php`: The primary bootstrap file to load dependencies, set PHP configuration (hiding errors), enforce session timeouts, and initialize CSRF.
- `app/helpers.php`: Utility functions for retrieving config values `config()` and securely formatting money to and from database formats (`from_db_money()`, `to_db_money()`).
- `app/Database.php`: Singleton wrapper for `PDO`, strictly configured to disable emulation and enforce `PDO::FETCH_ASSOC`.
- `app/Auth.php`: Centralized logic for `check()`, `requireLogin()`, `requireRole()`, and `requireCsrf()`.
- `app/AuditLog.php`: Standardized interface to insert into the `activity_logs` table for compliance.

## How to run it
Any new page in the module folders (e.g., `fika_pos/fika_pos_dashboard.php`) simply needs to start with:
```php
<?php
require_once __DIR__ . '/../app/init.php';
Auth::requireLogin();
```

## How to test it
1. Load a basic test script calling `require_once 'app/init.php';`
2. Verify that `config('app.timezone')` correctly loads.
3. Validate that `Database::getConnection()` successfully returns a PDO instance.
4. Attempt to run `Auth::requireRole(['NonExistentRole']);` and expect a 403 Forbidden response.

## Open questions
- **Roles Configuration**: Should roles remain database-driven or should we map them out as constants inside `config/roles.php`?
- **Routing**: Are we planning on implementing a single front controller (`index.php`) to route requests in a future phase, or maintaining the physical file routing (e.g. `fika_pos_dashboard.php`) per module?
- **Audit Logging Table Schema**: The current `AuditLog::log` assumes an `activity_logs` structure (`user_id`, `action`, `details`, `ip_address`, `created_at`). Need to confirm if this matches the unified schema precisely.

## Next Steps
Proceeding to migrate the authentication module (`fika_hrms_login.php`) and scaffolding the primary HRMS / POS placeholder views in the next phase.
