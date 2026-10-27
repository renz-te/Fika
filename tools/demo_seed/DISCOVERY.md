# Phase D0 Discovery Notes for Demo Seeding

This document captures the factual findings from the active database (`fika_scratch`) and repository code reading, adhering strictly to Rule 4 ("Facts come from the database and code reading, never from memory").

---

## 1. Connected Database Name and Host

* **Database Host:** `127.0.0.1` (Local MySQL instance)
* **Database Name:** `fika_scratch`
* **Port:** `3306`
* **Evidence / Source:**
  * File: `config/config.php` lines 5–11:
    ```php
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'fika_scratch',
        'user' => 'root',
        'pass' => '',
    ]
    ```
  * Query Verification:
    ```sql
    SELECT DATABASE() AS db_name, @@hostname AS host_name, @@port AS port;
    -- Result: db_name = 'fika_scratch', host_name = '127.0.0.1' (localhost), port = 3306
    ```
  * Local host verification: Host is strictly `127.0.0.1` / `localhost`.

---

## 2. Table Existence and Full `SHOW CREATE TABLE` Definitions

Of the 25 table names specified in the task prompt:
* **EXISTS (17 tables):** `products`, `modifiers`, `recipes`, `inventory_stock`, `orders`, `order_items`, `order_item_modifiers`, `cash_sessions`, `open_shifts`, `employees`, `payroll_runs`, `expenses`, `expense_categories`, `branch_budgets`, `contribution_rates`, `branches`, `users`, `roles`.
* **DOES NOT EXIST (8 tables named in prompt):**
  1. `inventory` — **DOES NOT EXIST** (Active table in schema is `inventory_items`)
  2. `inventory_transactions` — **DOES NOT EXIST** (Active table in schema is `inventory_ledger`)
  3. `pos_inventory_transactions` — **DOES NOT EXIST** (Active table in schema is `order_ledger_deductions`)
  4. `schedules` — **DOES NOT EXIST** (Core table - STOP CONDITION; active table in schema is `shifts`)
  5. `attendance` — **DOES NOT EXIST** (Core table - STOP CONDITION; active table in schema is `attendance_logs`)
  6. `leaves` — **DOES NOT EXIST** (Active tables in schema are `leave_requests` and `leave_types`)
  7. `payroll` — **DOES NOT EXIST** (Core table - STOP CONDITION; active tables in schema are `payroll_runs` and `payroll_items`)

### Complete `SHOW CREATE TABLE` Definitions for Existing and Active Equivalent Tables

#### `products` (EXISTS)
```sql
CREATE TABLE `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_id` int DEFAULT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_cents` int NOT NULL DEFAULT '0',
  `cost_per_unit` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cost_per_unit_cents` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `legacy_id` (`legacy_id`),
  KEY `fk_products_category` (`category_id`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=990008 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `modifiers` (EXISTS)
```sql
CREATE TABLE `modifiers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `group_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_cents` int NOT NULL DEFAULT '0',
  `product_id` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `legacy_id` (`legacy_id`),
  KEY `idx_modifiers_group` (`group_name`),
  KEY `fk_modifiers_product` (`product_id`),
  CONSTRAINT `fk_modifiers_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=990014 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `recipes` (EXISTS)
```sql
CREATE TABLE `recipes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int DEFAULT NULL,
  `modifier_id` int DEFAULT NULL,
  `inventory_id` int NOT NULL,
  `quantity_required` decimal(10,4) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `legacy_id` (`legacy_id`),
  KEY `fk_recipes_product` (`product_id`),
  KEY `fk_recipes_modifier` (`modifier_id`),
  KEY `fk_recipes_inventory` (`inventory_id`),
  CONSTRAINT `fk_recipes_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory_items` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_recipes_modifier` FOREIGN KEY (`modifier_id`) REFERENCES `modifiers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_recipes_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=990022 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `inventory` — **DOES NOT EXIST**
Active equivalent table is `inventory_items`:
```sql
CREATE TABLE `inventory_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `legacy_id` (`legacy_id`)
) ENGINE=InnoDB AUTO_INCREMENT=990010 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `inventory_stock` (EXISTS)
```sql
CREATE TABLE `inventory_stock` (
  `id` int NOT NULL AUTO_INCREMENT,
  `branch_id` int NOT NULL,
  `inventory_id` int NOT NULL,
  `current_stock` decimal(10,2) NOT NULL DEFAULT '0.00',
  `low_stock_threshold` decimal(10,2) NOT NULL DEFAULT '5.00',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_branch_inventory` (`branch_id`,`inventory_id`),
  UNIQUE KEY `legacy_id` (`legacy_id`),
  KEY `fk_stock_inventory` (`inventory_id`),
  CONSTRAINT `fk_stock_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_stock_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory_items` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=990028 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `inventory_transactions` — **DOES NOT EXIST**
Active equivalent table is `inventory_ledger`:
```sql
CREATE TABLE `inventory_ledger` (
  `id` int NOT NULL AUTO_INCREMENT,
  `branch_id` int NOT NULL,
  `inventory_id` int NOT NULL,
  `type` enum('Restock','Spoilage','Sold','Transfer','Adjustment','SALE','VOID_RESTOCK','PURCHASE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_cost_snapshot` decimal(12,2) NOT NULL DEFAULT '0.00',
  `user_id` int NOT NULL COMMENT 'Logged by',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `order_id` int DEFAULT NULL,
  `po_id` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `legacy_id` (`legacy_id`),
  KEY `fk_ledger_branch_id` (`branch_id`),
  KEY `fk_ledger_inventory_id` (`inventory_id`),
  KEY `fk_ledger_user_id` (`user_id`),
  CONSTRAINT `fk_ledger_branch_id` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_ledger_inventory_id` FOREIGN KEY (`inventory_id`) REFERENCES `inventory_items` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_ledger_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `pos_inventory_transactions` — **DOES NOT EXIST**
Active equivalent idempotency lock table is `order_ledger_deductions`:
```sql
CREATE TABLE `order_ledger_deductions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `branch_id` int NOT NULL,
  `deducted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_deductions_order_id` (`order_id`),
  KEY `idx_order_deductions_branch` (`branch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `orders` (EXISTS)
```sql
CREATE TABLE `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch_id` int NOT NULL,
  `pos_session_id` int DEFAULT NULL,
  `cashier_id` int NOT NULL,
  `order_type` enum('Dine-in','Takeout') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Dine-in',
  `order_status` enum('PENDING','IN_PROGRESS','COMPLETED','CANCELLED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `payment_status` enum('UNPAID','PAID','REFUNDED','FAILED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UNPAID',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `final_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `legacy_id` int DEFAULT NULL,
  `is_test` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  UNIQUE KEY `legacy_id` (`legacy_id`),
  KEY `idx_orders_branch_status` (`branch_id`,`payment_status`),
  KEY `fk_orders_pos_session` (`pos_session_id`),
  KEY `fk_orders_cashier` (`cashier_id`),
  CONSTRAINT `fk_orders_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_orders_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_orders_pos_session` FOREIGN KEY (`pos_session_id`) REFERENCES `cash_sessions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=990022 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `order_items` (EXISTS)
```sql
CREATE TABLE `order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `price_snapshot` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_snapshot_cents` int NOT NULL DEFAULT '0',
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `legacy_id` (`legacy_id`),
  KEY `fk_order_items_order` (`order_id`),
  KEY `fk_order_items_product` (`product_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=990024 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `order_item_modifiers` (EXISTS)
```sql
CREATE TABLE `order_item_modifiers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_item_id` int NOT NULL,
  `modifier_id` int NOT NULL,
  `price_snapshot` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `legacy_id` (`legacy_id`),
  KEY `fk_oim_order_item` (`order_item_id`),
  KEY `fk_oim_modifier` (`modifier_id`),
  CONSTRAINT `fk_oim_modifier` FOREIGN KEY (`modifier_id`) REFERENCES `modifiers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_oim_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=990022 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `cash_sessions` (EXISTS)
```sql
CREATE TABLE `cash_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `branch_id` int NOT NULL,
  `user_id` int NOT NULL,
  `device_id` int DEFAULT NULL,
  `opening_cash` decimal(10,2) NOT NULL DEFAULT '0.00',
  `closing_cash` decimal(10,2) DEFAULT NULL,
  `actual_cash` decimal(10,2) DEFAULT NULL,
  `discrepancy` decimal(10,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('OPEN','CLOSED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OPEN',
  `opened_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `open_user_id` int GENERATED ALWAYS AS (if((`status` = _utf8mb4'OPEN'),`user_id`,NULL)) VIRTUAL,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `legacy_id` (`legacy_id`),
  UNIQUE KEY `uq_open_user_session` (`open_user_id`),
  KEY `fk_cash_sessions_branch` (`branch_id`),
  KEY `fk_cash_sessions_user` (`user_id`),
  KEY `fk_cash_sessions_device` (`device_id`),
  CONSTRAINT `fk_cash_sessions_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cash_sessions_device` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cash_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=990011 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `schedules` — **DOES NOT EXIST** (STOP CONDITION)
Active equivalent table is `shifts`:
```sql
CREATE TABLE `shifts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `branch_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `shift_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `shift_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Regular',
  `status` enum('DRAFT','PENDING_BM','PENDING_BA','APPROVED','PUBLISHED','REJECTED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `edit_reason` text COLLATE utf8mb4_unicode_ci,
  `bm_screened_by` int DEFAULT NULL,
  `ba_approved_by` int DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `created_by` int NOT NULL,
  `updated_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `legacy_id` (`legacy_id`),
  KEY `idx_shifts_branch_date` (`branch_id`,`shift_date`),
  KEY `idx_shifts_employee_date` (`employee_id`,`shift_date`),
  KEY `idx_shifts_status` (`status`),
  KEY `fk_shifts_created_by` (`created_by`),
  CONSTRAINT `fk_shifts_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_shifts_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_shifts_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=990018 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `open_shifts` (EXISTS)
```sql
CREATE TABLE `open_shifts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `branch_id` int NOT NULL,
  `shift_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `role_required` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slots_available` int NOT NULL DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('OPEN','FILLED','CANCELLED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OPEN',
  `created_by` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `legacy_id` (`legacy_id`),
  KEY `idx_open_shifts_branch_date` (`branch_id`,`shift_date`),
  KEY `idx_open_shifts_status` (`status`),
  KEY `fk_open_shifts_creator` (`created_by`),
  CONSTRAINT `fk_open_shifts_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_open_shifts_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=990013 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `attendance` — **DOES NOT EXIST** (STOP CONDITION)
Active equivalent table is `attendance_logs`:
```sql
CREATE TABLE `attendance_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `branch_id` int NOT NULL,
  `device_id` int DEFAULT NULL,
  `clock_in` datetime NOT NULL,
  `clock_out` datetime DEFAULT NULL,
  `work_date` date NOT NULL,
  `source` enum('DEVICE','MANUAL') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DEVICE',
  `status` enum('OPEN','CLOSED','ADJUSTED','ABSENT') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OPEN',
  `adjusted_by` int DEFAULT NULL,
  `adjust_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `open_employee_id` int GENERATED ALWAYS AS (if((`status` = _utf8mb4'OPEN'),`employee_id`,NULL)) VIRTUAL,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `legacy_id` (`legacy_id`),
  UNIQUE KEY `uq_open_log` (`open_employee_id`),
  KEY `fk_alog_emp` (`employee_id`),
  KEY `fk_alog_branch` (`branch_id`),
  KEY `fk_alog_device` (`device_id`),
  KEY `fk_alog_adj` (`adjusted_by`),
  CONSTRAINT `fk_alog_adj` FOREIGN KEY (`adjusted_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_alog_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_alog_device` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_alog_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `leaves` — **DOES NOT EXIST**
Active equivalent tables are `leave_requests` and `leave_types`:
```sql
CREATE TABLE `leave_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `days` decimal(5,2) NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `med_cert_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('PENDING','APPROVED','REJECTED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `decided_by` int DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `legacy_id` (`legacy_id`),
  KEY `fk_lreq_emp` (`employee_id`),
  KEY `fk_lreq_type` (`type`),
  KEY `fk_lreq_decider` (`decided_by`),
  CONSTRAINT `fk_lreq_decider` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_lreq_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_lreq_type` FOREIGN KEY (`type`) REFERENCES `leave_types` (`code`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `leave_types` (
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_paid` tinyint(1) NOT NULL DEFAULT '0',
  `annual_days` int NOT NULL DEFAULT '0',
  `min_service_months` int NOT NULL DEFAULT '0',
  `statutory` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `employees` (EXISTS)
```sql
CREATE TABLE `employees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_code` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch_id` int DEFAULT NULL,
  `first_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `emergency_contact` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employment_type` enum('REGULAR','PROBATIONARY','PART_TIME') COLLATE utf8mb4_unicode_ci DEFAULT 'PROBATIONARY',
  `status` enum('ACTIVE','INACTIVE','SEPARATED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVE',
  `attendance_mode` enum('CLOCK','FIXED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CLOCK',
  `staff_class` enum('CREW','OFFICIAL','HQ') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CREW',
  `sick_leave_balance` int NOT NULL DEFAULT '14',
  `basic_salary` decimal(12,2) NOT NULL DEFAULT '0.00',
  `hourly_rate` decimal(10,2) NOT NULL DEFAULT '0.00',
  `date_hired` date DEFAULT NULL,
  `termination_date` date DEFAULT NULL,
  `termination_reason` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_no_enc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tin_enc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sss_no_enc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `philhealth_no_enc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pagibig_no_enc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `pin_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_code` (`employee_code`),
  UNIQUE KEY `legacy_id` (`legacy_id`),
  KEY `fk_employees_branch` (`branch_id`),
  CONSTRAINT `fk_employees_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=990028 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `payroll` — **DOES NOT EXIST** (STOP CONDITION)
Active equivalent tables are `payroll_runs` and `payroll_items`.

#### `payroll_runs` (EXISTS)
```sql
CREATE TABLE `payroll_runs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `scope` enum('BRANCH','OFFICIALS','HQ') COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch_id` int DEFAULT NULL,
  `payee_employee_id` int DEFAULT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `status` enum('DRAFT','GENERATED','APPROVED','RELEASED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `processed_by` int DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `released_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `legacy_id` (`legacy_id`),
  KEY `fk_prun_branch` (`branch_id`),
  KEY `fk_prun_payee` (`payee_employee_id`),
  KEY `fk_prun_processor` (`processed_by`),
  KEY `fk_prun_approver` (`approved_by`),
  KEY `fk_prun_releaser` (`released_by`),
  CONSTRAINT `fk_prun_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_prun_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_prun_payee` FOREIGN KEY (`payee_employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_prun_processor` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_prun_releaser` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `payroll_items` (EXISTS - Related to payroll_runs)
```sql
CREATE TABLE `payroll_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `payroll_run_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `basic_pay` decimal(12,2) NOT NULL DEFAULT '0.00',
  `overtime_pay` decimal(12,2) NOT NULL DEFAULT '0.00',
  `bonus_pay` decimal(12,2) NOT NULL DEFAULT '0.00',
  `gross_pay` decimal(12,2) NOT NULL DEFAULT '0.00',
  `sss_deduction` decimal(12,2) NOT NULL DEFAULT '0.00',
  `philhealth_deduction` decimal(12,2) NOT NULL DEFAULT '0.00',
  `pagibig_deduction` decimal(12,2) NOT NULL DEFAULT '0.00',
  `employer_sss` decimal(12,2) NOT NULL DEFAULT '0.00',
  `employer_philhealth` decimal(12,2) NOT NULL DEFAULT '0.00',
  `employer_pagibig` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_deduction` decimal(12,2) NOT NULL DEFAULT '0.00',
  `net_pay` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `legacy_id` (`legacy_id`),
  KEY `fk_pitems_run_id` (`payroll_run_id`),
  KEY `fk_pitems_employee_id` (`employee_id`),
  CONSTRAINT `fk_pitems_employee_id` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_pitems_run_id` FOREIGN KEY (`payroll_run_id`) REFERENCES `payroll_runs` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `expenses` (EXISTS)
```sql
CREATE TABLE `expenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `branch_id` int NOT NULL,
  `category_id` int NOT NULL,
  `amount_cents` bigint NOT NULL,
  `expense_date` date NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `payee` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('DRAFT','APPROVED','VOIDED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `created_by` int NOT NULL,
  `approved_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `legacy_id` (`legacy_id`),
  KEY `fk_exp_branch_id` (`branch_id`),
  KEY `fk_exp_cat_id` (`category_id`),
  KEY `fk_exp_user_id` (`created_by`),
  KEY `fk_exp_approver_id` (`approved_by`),
  CONSTRAINT `fk_exp_approver_id` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_exp_branch_id` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_exp_cat_id` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_exp_user_id` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=990013 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `expense_categories` (EXISTS)
```sql
CREATE TABLE `expense_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=990009 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `branch_budgets` (EXISTS)
```sql
CREATE TABLE `branch_budgets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `branch_id` int NOT NULL,
  `budget_month` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'YYYY-MM format',
  `allocated_amount_cents` bigint NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `allocated_labor_budget` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_branch_budget` (`branch_id`,`budget_month`),
  UNIQUE KEY `legacy_id` (`legacy_id`),
  CONSTRAINT `fk_budgets_branch_id` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `contribution_rates` (EXISTS - EMPTY)
```sql
CREATE TABLE `contribution_rates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` enum('SSS','PhilHealth','PagIBIG','Tax') COLLATE utf8mb4_unicode_ci NOT NULL,
  `lower_bound` decimal(12,2) NOT NULL DEFAULT '0.00',
  `upper_bound` decimal(12,2) NOT NULL DEFAULT '0.00',
  `employee_share` decimal(12,2) NOT NULL DEFAULT '0.00',
  `employer_share` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `branches` (EXISTS)
```sql
CREATE TABLE `branches` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `contact_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Active','Inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `legacy_id` (`legacy_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `users` (EXISTS)
```sql
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_id` int NOT NULL,
  `branch_id` int DEFAULT NULL,
  `employee_id` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `legacy_id` (`legacy_id`),
  KEY `fk_users_role` (`role_id`),
  KEY `fk_users_branch` (`branch_id`),
  KEY `fk_users_employee` (`employee_id`),
  CONSTRAINT `fk_users_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=10040 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `roles` (EXISTS)
```sql
CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `legacy_id` (`legacy_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10000 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Recipes, Modifiers, Inventory, and `allowed_modifier_groups`

1. **How recipes link to products AND to modifiers:**
   * Table `recipes` contains both nullable foreign keys: `product_id` (FK to `products.id`) and `modifier_id` (FK to `modifiers.id`), plus non-null `inventory_id` (FK to `inventory_items.id`) and `quantity_required`:
     * A base product ingredient link has `product_id = <product_id>`, `modifier_id = NULL`, `inventory_id = <item_id>`.
     * A modifier ingredient link (e.g. extra syrup, milk alternative) has `modifier_id = <modifier_id>`, `product_id = NULL` (or optionally set), `inventory_id = <item_id>`.
   * Reference: `recipes` CREATE TABLE definition in Item 2; `app/core/InventoryDeduction.php` lines 58–92.

2. **Where unit and cost per unit live:**
   * They live in `inventory_items`:
     * `inventory_items.unit` (`varchar(50)`): e.g. `'grams'`, `'ml'`, `'pieces'`, `'kg'`.
     * `inventory_items.unit_cost` (`decimal(12,2)`): standard cost per unit.
   * In `products`: `products.cost_per_unit` (`decimal(10,2)`) and `products.cost_per_unit_cents` (`int`) exist for product-level snapshotting.

3. **Where per-branch quantity lives:**
   * Table: `inventory_stock`
     * `inventory_stock.current_stock` (`decimal(10,2)`)
     * `inventory_stock.low_stock_threshold` (`decimal(10,2)`)
   * Unique constraint: `UNIQUE KEY uq_branch_inventory (branch_id, inventory_id)`.

4. **What `allowed_modifier_groups` contains:**
   * In legacy code (`cafe_pos/js/cashier.js` line 267), `product.allowed_modifier_groups` was expected as a comma-separated string (e.g. `'Size,Milk,Add-ons'`).
   * **In the active MySQL database (`fika_scratch.products`), column `allowed_modifier_groups` DOES NOT EXIST!**
   * Proof:
     ```sql
     SHOW COLUMNS FROM products LIKE 'allowed_modifier_groups';
     -- Result: Empty set (0 rows)
     ```
   * Modifiers in `modifiers` instead carry `group_name` (`varchar(50)`) and an optional `product_id` parent FK.

---

## 4. POS Inventory Deduction and Finance Reports Derivation

### A. How POS Deducts Stock on a Sale
1. **Code Path:**
   * Service class: `app/core/InventoryDeduction.php` -> Method: `InventoryDeduction::deductOrder($orderId, $branchId, $userId, $orderNumber)`
   * Triggered by:
     * `fika_pos/api/fika_pos_order_create.php` line 144 (`InventoryDeduction::deductOrder($orderId, $branchId, $user['id'], $orderNumber)`)
     * `fika_pos/api/fika_pos_order_pay_cash.php` line 74
     * `fika_pos/api/fika_pos_payment_webhook.php` line 77
2. **Tables and Columns Updated:**
   * `order_ledger_deductions`: Inserts `(order_id, branch_id, deducted_at)`. Idempotency enforced by `UNIQUE KEY uq_order_deductions_order_id (order_id)`. If duplicate, deduction is safely skipped.
   * `recipes`: Reads `inventory_id` and `quantity_required` for all `order_items.product_id` and `order_item_modifiers.modifier_id`.
   * `inventory_stock`: Updates `current_stock = current_stock - usedQuantity` for `(branch_id, inventory_id)`.
   * `inventory_ledger`: Appends an audit row per deducted ingredient:
     * `branch_id = $branchId`
     * `inventory_id = $inventoryId`
     * `type = 'SALE'`
     * `quantity = -$totalUsed` (negative deduction)
     * `unit_cost_snapshot = $unitCost`
     * `user_id = $userId`
     * `order_id = $orderId`
     * `remarks = "POS Deduction for Order {$orderNumber}"`

### B. How `fika_finance_reports.php` Derives P&L
File: `fika_finance/fika_finance_reports.php` lines 50–120:
1. **Gross Revenue:**
   * Derived from `orders`:
     ```sql
     SELECT COALESCE(SUM(final_amount), 0) AS gross_revenue_pesos
     FROM orders
     WHERE payment_status = 'PAID'
       AND is_test = 0
       AND DATE(created_at) BETWEEN :start_date AND :end_date
       [AND branch_id = :branch_id]
     ```
   * Converted to integer cents in memory: `ROUND($row['gross_revenue_pesos'] * 100)`.
   * Net revenue subtracts approved/processed refund amounts.
2. **Cost of Goods Sold (COGS):**
   * Derived directly from `inventory_ledger` joined with `orders`:
     ```sql
     SELECT COALESCE(SUM(ROUND(ABS(il.quantity) * il.unit_cost_snapshot * 100)), 0) AS total_cogs_cents
     FROM inventory_ledger il
     JOIN orders o ON il.order_id = o.id
     WHERE il.type = 'SALE'
       AND o.payment_status = 'PAID'
       AND o.is_test = 0
       AND DATE(o.created_at) BETWEEN :start_date AND :end_date
       [AND il.branch_id = :branch_id]
     ```
3. **Payroll Labor Cost:**
   * Derived from `payroll_items` joined with `payroll_runs`:
     ```sql
     SELECT COALESCE(SUM(ROUND(
         (CASE WHEN pi.gross_pay > 0 THEN pi.gross_pay ELSE (pi.basic_pay + pi.overtime_pay + pi.bonus_pay) END
          + pi.employer_sss + pi.employer_philhealth + pi.employer_pagibig) * 100
     )), 0) AS total_payroll_cents
     FROM payroll_items pi
     JOIN payroll_runs pr ON pi.payroll_run_id = pr.id
     WHERE pr.status = 'RELEASED'
       AND pr.period_start >= :start_date AND pr.period_end <= :end_date
       [AND pr.branch_id = :branch_id]
     ```

---

## 5. Can a Modifier Group be REQUIRED in the POS?

* **Finding: NO generalized mechanism exists.** Neither the database schema nor the API enforces required modifier groups.
* **Code Proof:**
  * In `fika_pos/api/fika_pos_order_create.php` lines 112–126:
    ```php
    if (!empty($item['modifiers']) && is_array($item['modifiers'])) {
        foreach ($item['modifiers'] as $modId) {
            ...
        }
    }
    ```
    Modifiers are strictly optional in the backend payload. An order item without modifiers is accepted without error.
  * In `cafe_pos/js/cashier.js` lines 285–287 and 350–368:
    * When `openModifierModal` is called, it hardcodes an initial selection for group `Size` to `'Tall'`:
      ```javascript
      const tallMod = menuData.modifiers.find(m => m.name === 'Tall');
      if (tallMod) selectedModifiers.add(tallMod.id);
      ```
    * In `renderModalModifiers()`:
      * If `groupName === 'Size'`, clicking an item deselects other size modifiers and selects the clicked one (radio button behavior). Because the modal opens with `'Tall'` already pre-selected, a size is always present unless the modifier data lacks a `'Tall'` modifier.
      * For `Milk` and `Add-ons`, items can be toggled on/off freely.
    * When clicking "Add to Cart" (`btnModalAdd.onclick`, line 404), there is **zero validation** checking if required modifier groups have been selected.
  * In table `modifiers`: There is NO `is_required` or `min_selection` column.

---

## 6. Every Active Branch

Query:
```sql
SELECT id, name, status FROM branches WHERE status = 'Active' ORDER BY id;
```
Results (3 Active branches):
1. **Branch 1:** General Trias Branch (`id = 1`, `status = 'Active'`)
2. **Branch 2:** Trece Martires Branch (`id = 2`, `status = 'Active'`)
3. **Branch 3:** Imus Branch (`id = 3`, `status = 'Active'`)

---

## 7. Every USER and Role Coverage Per Branch

### A. All Users in the Database
```
ID    | Username            | Email                        | Role ID | Role Name        | Branch ID | Branch Name            | Emp ID | Employee Full Name
------+---------------------+------------------------------+---------+------------------+-----------+------------------------+--------+--------------------
1     | maria.clara         | maria.clara@fika.local       | 2       | CHR              | NULL      | NULL (Global/HQ)       | 1      | Maria Clara
2     | jose.rizal          | jose.rizal@fika.local        | 3       | GA               | NULL      | NULL (Global/HQ)       | 2      | Jose Rizal
10006 | admin               | admin@fika.local             | 1       | ADMIN            | NULL      | NULL (Global/HQ)       | 3      | System Administrator
10007 | juan.delacruz       | juan.delacruz@fika.local     | 6       | BM               | 1         | General Trias Branch   | 4      | Juan Dela Cruz
10008 | ana.reyes           | ana.reyes@fika.local         | 5       | BHR              | 1         | General Trias Branch   | 5      | Ana Reyes
10009 | pedro.santos        | pedro.santos@fika.local      | 4       | BA               | 1         | General Trias Branch   | 6      | Pedro Santos
10010 | miguel.cruz         | miguel.cruz@fika.local       | 7       | STAFF            | 1         | General Trias Branch   | 7      | Miguel Cruz
10011 | sofia.garcia        | sofia.garcia@fika.local      | 7       | STAFF            | 1         | General Trias Branch   | 8      | Sofia Garcia
10012 | carlo.mendoza       | carlo.mendoza@fika.local     | 7       | STAFF            | 1         | General Trias Branch   | 9      | Carlo Mendoza
10013 | bea.torres          | bea.torres@fika.local        | 7       | STAFF            | 1         | General Trias Branch   | 10     | Bea Torres
10014 | leo.flores          | leo.flores@fika.local        | 7       | STAFF            | 1         | General Trias Branch   | 11     | Leo Flores
10015 | andres.bonifacio    | andres.bonifacio@fika.local  | 6       | BM               | 2         | Trece Martires Branch  | 12     | Andres Bonifacio
10016 | gabriela.silang     | gabriela.silang@fika.local   | 5       | BHR              | 2         | Trece Martires Branch  | 13     | Gabriela Silang
10017 | emilio.aguinaldo    | emilio.aguinaldo@fika.local  | 4       | BA               | 2         | Trece Martires Branch  | 14     | Emilio Aguinaldo
10018 | melchora.aquino     | melchora.aquino@fika.local   | 7       | STAFF            | 2         | Trece Martires Branch  | 15     | Melchora Aquino
10019 | apolinario.mabini   | apolinario.mabini@fika.local | 7       | STAFF            | 2         | Trece Martires Branch  | 16     | Apolinario Mabini
10020 | marcelo.pilar       | marcelo.pilar@fika.local     | 7       | STAFF            | 2         | Trece Martires Branch  | 17     | Marcelo Pilar
10021 | gregorio.delpilar   | gregorio.delpilar@fika.local | 7       | STAFF            | 2         | Trece Martires Branch  | 18     | Gregorio Del Pilar
10022 | teresa.magbanua     | teresa.magbanua@fika.local   | 7       | STAFF            | 2         | Trece Martires Branch  | 19     | Teresa Magbanua
10023 | jose.laurel         | jose.laurel@fika.local       | 6       | BM               | 3         | Imus Branch            | 20     | Jose Laurel
10024 | ramon.magsaysay     | ramon.magsaysay@fika.local   | 5       | BHR              | 3         | Imus Branch            | 21     | Ramon Magsaysay
10025 | sergio.osmena       | sergio.osmena@fika.local     | 4       | BA               | 3         | Imus Branch            | 22     | Sergio Osmena
10026 | manuel.quezon       | manuel.quezon@fika.local     | 7       | STAFF            | 3         | Imus Branch            | 23     | Manuel Quezon
10027 | elpidio.quirino     | elpidio.quirino@fika.local   | 7       | STAFF            | 3         | Imus Branch            | 24     | Elpidio Quirino
10028 | diosdado.macapagal  | diosdado.macapagal@fika.local| 7       | STAFF            | 3         | Imus Branch            | 25     | Diosdado Macapagal
10029 | ferdinand.marcos    | ferdinand.marcos@fika.local  | 7       | STAFF            | 3         | Imus Branch            | 26     | Ferdinand Marcos
10030 | corazon.aquino      | corazon.aquino@fika.local    | 7       | STAFF            | 3         | Imus Branch            | 27     | Corazon Aquino
10031 | gentri.clock        | gentri.clock@fika.local      | 12      | Clock            | 1         | General Trias Branch   | NULL   | [Device User]
10032 | gentri.kiosk        | gentri.kiosk@fika.local      | 4       | BA               | 1         | General Trias Branch   | NULL   | [Terminal User]
10033 | gentri.cashier      | gentri.cashier@fika.local    | 9       | Cashier Terminal | 1         | General Trias Branch   | NULL   | [Terminal User]
10034 | trece.clock         | trece.clock@fika.local       | 12      | Clock            | 2         | Trece Martires Branch  | NULL   | [Device User]
10035 | trece.kiosk         | trece.kiosk@fika.local       | 4       | BA               | 2         | Trece Martires Branch  | NULL   | [Terminal User]
10036 | trece.cashier       | trece.cashier@fika.local     | 9       | Cashier Terminal | 2         | Trece Martires Branch  | NULL   | [Terminal User]
10037 | imus.clock          | imus.clock@fika.local        | 12      | Clock            | 3         | Imus Branch            | NULL   | [Device User]
10038 | imus.kiosk          | imus.kiosk@fika.local        | 4       | BA               | 3         | Imus Branch            | NULL   | [Terminal User]
10039 | imus.cashier        | imus.cashier@fika.local      | 9       | Cashier Terminal | 3         | Imus Branch            | NULL   | [Terminal User]
```

### B. Required Roles Coverage Verification
1. **Central HR (global):** **EXISTS** (`id = 1`, `username = maria.clara`, `role_id = 2`, `role = CHR`, `branch_id = NULL`)
2. **Global Accountant (global):** **EXISTS** (`id = 2`, `username = jose.rizal`, `role_id = 3`, `role = GA`, `branch_id = NULL`)
3. **Branch 1 (General Trias Branch):**
   * Branch HR: **EXISTS** (`id = 10008`, `username = ana.reyes`, `role = BHR`)
   * Branch Manager: **EXISTS** (`id = 10007`, `username = juan.delacruz`, `role = BM`)
   * Branch Accountant: **EXISTS** (`id = 10009`, `username = pedro.santos`, `role = BA`)
4. **Branch 2 (Trece Martires Branch):**
   * Branch HR: **EXISTS** (`id = 10016`, `username = gabriela.silang`, `role = BHR`)
   * Branch Manager: **EXISTS** (`id = 10015`, `username = andres.bonifacio`, `role = BM`)
   * Branch Accountant: **EXISTS** (`id = 10017`, `username = emilio.aguinaldo`, `role = BA`)
5. **Branch 3 (Imus Branch):**
   * Branch HR: **EXISTS** (`id = 10024`, `username = ramon.magsaysay`, `role = BHR`)
   * Branch Manager: **EXISTS** (`id = 10023`, `username = jose.laurel`, `role = BM`)
   * Branch Accountant: **EXISTS** (`id = 10025`, `username = sergio.osmena`, `role = BA`)

### C. Crew Employees Per Branch (Count, Category, Rates)
* **General Trias Branch (Branch 1): 5 Crew Employees**
  1. `EMP-1007` Miguel Cruz — Head Barista, REGULAR, Hourly Rate: ₱96.15, Monthly Basic: ₱20,000.00
  2. `EMP-1008` Sofia Garcia — Head Barista, REGULAR, Hourly Rate: ₱96.15, Monthly Basic: ₱20,000.00
  3. `EMP-1009` Carlo Mendoza — Barista, REGULAR, Hourly Rate: ₱76.92, Monthly Basic: ₱16,000.00
  4. `EMP-1010` Bea Torres — Barista, REGULAR, Hourly Rate: ₱76.92, Monthly Basic: ₱16,000.00
  5. `EMP-1011` Leo Flores — Barista, REGULAR, Hourly Rate: ₱76.92, Monthly Basic: ₱16,000.00
* **Trece Martires Branch (Branch 2): 5 Crew Employees**
  1. `EMP-1015` Melchora Aquino — Head Barista, REGULAR, Hourly Rate: ₱96.15, Monthly Basic: ₱20,000.00
  2. `EMP-1016` Apolinario Mabini — Head Barista, REGULAR, Hourly Rate: ₱96.15, Monthly Basic: ₱20,000.00
  3. `EMP-1017` Marcelo Pilar — Barista, REGULAR, Hourly Rate: ₱76.92, Monthly Basic: ₱16,000.00
  4. `EMP-1018` Gregorio Del Pilar — Barista, REGULAR, Hourly Rate: ₱76.92, Monthly Basic: ₱16,000.00
  5. `EMP-1019` Teresa Magbanua — Barista, REGULAR, Hourly Rate: ₱76.92, Monthly Basic: ₱16,000.00
* **Imus Branch (Branch 3): 5 Crew Employees**
  1. `EMP-1023` Manuel Quezon — Head Barista, REGULAR, Hourly Rate: ₱96.15, Monthly Basic: ₱20,000.00
  2. `EMP-1024` Elpidio Quirino — Head Barista, REGULAR, Hourly Rate: ₱96.15, Monthly Basic: ₱20,000.00
  3. `EMP-1025` Diosdado Macapagal — Barista, REGULAR, Hourly Rate: ₱76.92, Monthly Basic: ₱16,000.00
  4. `EMP-1026` Ferdinand Marcos — Barista, REGULAR, Hourly Rate: ₱76.92, Monthly Basic: ₱16,000.00
  5. `EMP-1027` Corazon Aquino — Barista, REGULAR, Hourly Rate: ₱76.92, Monthly Basic: ₱16,000.00

---

## 8. Role Permissions for Roster and Payroll Pages & Who Can Assign Shifts

### A. Roster / Shift Board Pages
* **`fika_hrms/fika_hrms_shift_board.php`:**
  * Guard: `Rbac::require_permission('schedule.view')` (line 6).
  * Allowed roles from `role_permissions`:
    * Role 1: `ADMIN` (Super Admin)
    * Role 2: `CHR` (Central HR) — *Note: Roster link is hidden from CHR sidebar in `header.php` line 48: `!in_array($sessRoleId, [1, 2], true)`*
    * Role 4: `BA` (Branch Accountant) — views budget widget & approves labor spend
    * Role 5: `BHR` (Branch HR) — creates and drafts schedules
    * Role 6: `BM` (Branch Manager) — screens manpower and approves
    * Role 7: `STAFF` — views published schedules and claims open shifts
* **Who can assign/create shifts:**
  * In `fika_hrms/api/fika_hrms_shift_save.php` lines 34–42:
    ```php
    $isBM = ($userRole === 6) || in_array($userRoleName, ['BM', 'Branch Manager'], true);
    if ($isBM || !in_array($userRole, [1, 5], true)) {
        http_response_code(403);
        exit(json_encode([
            'success' => false,
            'error' => 'Only Branch HR can create shifts.'
        ]));
    }
    ```
  * **Result:** **ONLY Branch HR (Role 5) and ADMIN (Role 1) can create/assign shifts.** Branch Manager (Role 6) is strictly prohibited from creating shifts (blocked both in UI and API).
* **4-Step Roster Segregation of Duties (SoD):**
  1. Step 1 (BHR): Creates shift (`DRAFT`), modifies, then submits to BM (`PENDING_BM`).
  2. Step 2 (BM): Screens shift roster, edits pending shifts if needed, then submits to BA (`PENDING_BA`).
  3. Step 3 (BA): Evaluates total scheduled hours against `branch_budgets`, approves budget (`APPROVED`).
  4. Step 4 (BHR/BM): Publishes the roster to crew (`PUBLISHED`).

### B. Payroll Pages
* **`fika_hrms/fika_hrms_payroll.php`:**
  * Guard: `Rbac::require_permission('payroll.view')` (line 4).
  * Allowed roles:
    * `ADMIN` (Role 1), `CHR` (Role 2), `GA` (Role 3), `BA` (Role 4), `BHR` (Role 5), `BM` (Role 6).
  * Action capabilities enforced by RBAC:
    * `payroll.generate`: CHR (Role 2) and BHR (Role 5).
    * `payroll.approve`: GA (Role 3) and BA (Role 4).
    * `payroll.release`: BM (Role 6).
    * `payroll.delete`: CHR (Role 2), GA (Role 3), BHR (Role 5).
    * `payroll.export`: ADMIN (Role 1), CHR (Role 2), GA (Role 3), BA (Role 4).
* **Legacy `cafe_hrms/payroll.php`:**
  * Allowed roles (line 6): `ROLE_SUPER_ADMIN`, `ROLE_EXECUTIVE`, `ROLE_CENTRAL_HR`, `ROLE_GLOBAL_ACCOUNTANT`, `ROLE_BRANCH_MANAGER`.

---

## 9. Attendance Columns Read by Payroll & Allowed Status ENUM Values

### A. Attendance Columns Read by Payroll
1. **In Legacy Payroll (`cafe_hrms/payroll.php` line 47):**
   * Reads from table `attendance`:
     * `time_in`
     * `time_out`
     * `break_in`
     * `break_out`
     * `overtime_hours`
     * `night_differential_hours`
     * `late_minutes`
2. **In Active Rebuilt Payroll (`app/lib/attendance_summary.php` & `app/payroll/payroll_engine.php`):**
   * Reads from active table `attendance_logs`:
     * `clock_in`
     * `clock_out`
     * `work_date`
     * `status` (filtered for `CLOSED`, `ADJUSTED`, `ABSENT`)
   * Dynamic calculations via `AttendanceCalc::calculate()` compute:
     * `worked_minutes`
     * `late_minutes` (evaluated against `settings.late_grace_minutes`, default 15 mins)
     * `overtime_minutes`
     * `night_diff_minutes` (10:00 PM to 06:00 AM)
   * Reads from `leave_requests` & `leave_types`:
     * `days`, `is_paid` where `status = 'APPROVED'`.

### B. Allowed Status Values Across Domain Entities

| Domain Area | Table Name | Status Column ENUM Values |
| :--- | :--- | :--- |
| **Attendance** | `attendance_logs` | `OPEN`, `CLOSED`, `ADJUSTED`, `ABSENT` |
| **Schedules / Shifts** | `shifts` | `DRAFT`, `PENDING_BM`, `PENDING_BA`, `APPROVED`, `PUBLISHED`, `REJECTED` |
| **Open Shifts** | `open_shifts` | `OPEN`, `FILLED`, `CANCELLED` |
| **Leaves** | `leave_requests` | `PENDING`, `APPROVED`, `REJECTED` |
| **Payroll Runs** | `payroll_runs` | `DRAFT`, `GENERATED`, `APPROVED`, `RELEASED` |
| **Operating Expenses** | `expenses` | `DRAFT`, `APPROVED`, `VOIDED` |
| **POS Orders** | `orders` | `order_status`: `PENDING`, `IN_PROGRESS`, `COMPLETED`, `CANCELLED`<br>`payment_status`: `UNPAID`, `PAID`, `REFUNDED`, `FAILED` |
| **Cash Sessions** | `cash_sessions` | `OPEN`, `CLOSED` |

---

## 10. `contribution_rates` Row Count and Types

* **Row Count:** **`0` rows** (`SELECT COUNT(*) FROM contribution_rates` returns `0`).
* **Table Schema Types:**
  * Column `type`: `enum('SSS','PhilHealth','PagIBIG','Tax')`
* **Finding:** While the `contribution_rates` table exists in the schema, it is completely empty. The active payroll engine (`app/payroll/contributions.php` lines 1–49) uses hardcoded DOLE formulas:
  * SSS: 4.5% Employee share, 9.5% Employer share (`monthlyBasePayCentavos * 0.045 / 0.095`).
  * PhilHealth: 2.5% Employee share, 2.5% Employer share (5% total split equally).
  * Pag-IBIG: Fixed ₱100.00 (`10000` centavos) Employee share, ₱100.00 Employer share.
* **CRITICAL STOP CONDITION TRIGGERED:** `contribution_rates` is empty.

---

## 11. What `finance_budgeting.php` Auto-Seeds When Opened (Exact Lines)

* In `main` branch historical commit `7413c4bba4daee7e3bc5655bc05e4f3131de83b4`, file `cafe_hrms/finance_budgeting.php` lines 94–184 auto-seeds the following on every GET request:
  1. **Branch Labor Budget (lines 100–108):**
     * Checks if `branch_budgets` has an entry for `$seed_branch_id` and `$current_month` (`date('Y-m')`).
     * If missing, executes:
       ```sql
       INSERT INTO branch_budgets (branch_id, budget_month, allocated_labor_budget)
       VALUES (?, ?, 150000.00)
       ON DUPLICATE KEY UPDATE allocated_labor_budget = allocated_labor_budget;
       ```
  2. **Standard Expense Categories (lines 111–118):**
     * Ensures categories exist: `'Utilities'`, `'Supplies'`, `'Maintenance'`, `'Marketing'`, `'Rent'`, `'Miscellaneous'`.
     * Executes `INSERT IGNORE INTO expense_categories (name) VALUES (?)`.
  3. **Sample Operational Expenses (lines 121–180):**
     * If current month has `< 3` expenses or lacks `'Coffee Beans Restock'`, inserts 3 approved expense records:
       * Utilities: `Electricity Bill (Manila Electric Co.)`, Amount: `12450.00`, Date: `date('Y-m-05')`, Status: `'Approved'`.
       * Supplies: `Coffee Beans Restock (Cordillera Roasters)`, Amount: `18200.00`, Date: `date('Y-m-10')`, Status: `'Approved'`.
       * Maintenance: `Espresso Machine Maintenance (Barista Tech)`, Amount: `6500.00`, Date: `date('Y-m-15')`, Status: `'Approved'`.
* **On Current Branch (`feature/demo-data`):**
  * `cafe_hrms/finance_budgeting.php` does **NOT** contain this auto-seed code. It runs a read-only query on GET and only inserts on explicit POST actions (`save_budget` line 51).

---

## 12. Session Stubbing Technique in `tests/payroll_chain_test.php`

In commit `07aa28a181175d67646dcd73bae6975a761854fb`, `tests/payroll_chain_test.php` lines 42–50, 78–95, and 110–125 invokes `payroll.php` handlers in an isolated subprocess with stubbed superglobals:

```php
function run_eval($phpCode) {
    global $rootDir;
    $tmp = sys_get_temp_dir() . '/eval_test_' . uniqid() . '.php';
    $code = "<?php\nchdir(" . var_export($rootDir, true) . ");\nsession_start();\n" . $phpCode;
    file_put_contents($tmp, $code);
    $out = shell_exec("php " . escapeshellarg($tmp) . " 2>&1");
    @unlink($tmp);
    return $out;
}

// Example invocation for POST handler:
$response = run_eval(<<<PHP
\$_SESSION['user'] = ['id' => 2, 'role' => 'Branch HR', 'branch_id' => 1];
\$_SESSION['csrf_token'] = 'token_abc';
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_POST['csrf_token'] = 'token_abc';
\$_POST['action'] = 'submit_approval';
\$_POST['payroll_id'] = $testId;
\$_SERVER['PHP_SELF'] = 'payroll.php';
require 'cafe_hrms/payroll.php';
PHP);

// Example invocation for GET handler:
$html = run_eval(<<<PHP
\$_SESSION['user'] = ['id' => 2, 'role' => 'Branch HR', 'branch_id' => 1];
\$_SESSION['csrf_token'] = 'token_abc';
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SERVER['PHP_SELF'] = 'payroll.php';
ob_start();
require 'cafe_hrms/payroll.php';
echo ob_get_clean();
PHP);
```

---

## 13. GO / NO-GO List for Later Phases (D2–D10)

| Phase | Description | Supported by DB Schema? | Status | Missing / Required Schema Adjustments |
| :--- | :--- | :---: | :---: | :--- |
| **D2** | Catalog, Products, Modifiers & Recipes | Yes | **GO** | Table `inventory_items` must be used instead of `inventory`. Column `allowed_modifier_groups` does not exist on `products` table and must not be queried. |
| **D3** | Inventory Stock & Restock Ledger | Yes | **GO** | Table `inventory_ledger` must be used instead of `inventory_transactions`. Stock is keyed on `(branch_id, inventory_id)`. |
| **D4** | Cash Sessions, POS Orders & Stock Deduction | Yes | **GO** | Table `order_ledger_deductions` must be used instead of `pos_inventory_transactions`. Orders use `final_amount` and `orders.payment_status = 'PAID'`. |
| **D5** | Roster & Shift Schedules | Partially | **NO-GO / CONDITIONAL** | Table `schedules` does not exist (**Core Stop Condition**). Active table is `shifts`. Must use `shifts.status` ENUM (`DRAFT`, `PENDING_BM`, `PENDING_BA`, `APPROVED`, `PUBLISHED`). |
| **D6** | Open Shifts & Claims | Yes | **GO** | Table `open_shifts` exists with columns `branch_id`, `shift_date`, `role_required`, `status`. |
| **D7** | Attendance Logs & Time Records | Partially | **NO-GO / CONDITIONAL** | Table `attendance` does not exist (**Core Stop Condition**). Active table is `attendance_logs`. Calculated columns (`overtime_hours`, `late_minutes`, `night_differential_hours`) are not table columns; they are computed via `AttendanceCalc`. |
| **D8** | Leave Requests & Leave Types | Partially | **GO** (with mapped table) | Table `leaves` does not exist. Active table is `leave_requests` (FK to `leave_types.code`). |
| **D9** | Payroll Runs, Payslips & Contributions | Partially | **NO-GO** | Table `payroll` does not exist (**Core Stop Condition**; active tables are `payroll_runs` and `payroll_items`). Furthermore, `contribution_rates` is completely empty (**Critical Stop Condition**). |
| **D10** | Operating Expenses & Branch Budgets | Yes | **GO** | Supported by `expenses`, `expense_categories`, and `branch_budgets`. Status values: `'DRAFT'`, `'APPROVED'`, `'VOIDED'`. |

---

## STOP CONDITIONS REPORT

The task specifies:
> "STOP CONDITIONS: stop and report if any of these roles is missing for a branch, if contribution_rates is empty, if the DB host is not local, or if a core table (products, recipes, schedules, attendance, payroll, expenses) does not exist."

### Triggered Stop Conditions:
1. **Core Table `schedules` DOES NOT EXIST** (Database has `shifts`, not `schedules`).
2. **Core Table `attendance` DOES NOT EXIST** (Database has `attendance_logs`, not `attendance`).
3. **Core Table `payroll` DOES NOT EXIST** (Database has `payroll_runs` and `payroll_items`, not `payroll`).
4. **`contribution_rates` IS EMPTY** (`COUNT(*)` = 0).

### Satisfied Conditions:
1. **Database Host is Local:** Host is `127.0.0.1`.
2. **All Required Roles Exist Across All Branches:** Central HR (global), Global Accountant (global), and for each of the 3 active branches: Branch Manager, Branch HR, and Branch Accountant all exist.
3. **Core Tables `products`, `recipes`, `expenses` EXIST.**
