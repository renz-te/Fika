<?php
/**
 * tools/seed_legacy_test_data.php
 *
 * Seeds a mock/test legacy database (default: fika_legacy_test)
 * with representative legacy records across HRMS and POS domains,
 * including intentional orphan references to test resilient error handling.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    exit;
}

$legacyHost = getenv('LEGACY_DB_HOST') ?: '127.0.0.1';
$legacyUser = getenv('LEGACY_DB_USER') ?: 'root';
$legacyPass = getenv('LEGACY_DB_PASS') !== false ? getenv('LEGACY_DB_PASS') : '';
$legacyDb   = getenv('LEGACY_DB_NAME') ?: 'fika_legacy_test';

echo "=== Seeding Legacy Test Database: {$legacyDb} on {$legacyHost} ===\n";

try {
    // 1. Connect without db to ensure database exists
    $dsnNoDb = "mysql:host={$legacyHost};charset=utf8mb4";
    $pdoRoot = new PDO($dsnNoDb, $legacyUser, $legacyPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `{$legacyDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // 2. Connect to legacy database
    $dsn = "mysql:host={$legacyHost};dbname={$legacyDb};charset=utf8mb4";
    $pdo = new PDO($dsn, $legacyUser, $legacyPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // 3. Drop existing test tables
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $tablesToDrop = [
        'activity_logs', 'branch_budgets', 'payments', 'order_items', 'orders',
        'cash_sessions', 'recipes', 'products', 'inventory_transactions',
        'inventory_stock', 'inventory', 'payroll', 'schedules', 'leaves',
        'attendance', 'interview_scorecards', 'interviews', 'applicants',
        'users', 'employees', 'roles', 'branches'
    ];
    foreach ($tablesToDrop as $tbl) {
        $pdo->exec("DROP TABLE IF EXISTS `{$tbl}`");
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // 4. Create Tables
    $pdo->exec("
        CREATE TABLE `branches` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(150) NOT NULL,
            `address` TEXT,
            `contact_number` VARCHAR(50),
            `status` VARCHAR(20) DEFAULT 'Active',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE `roles` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE `employees` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `employee_id` VARCHAR(50) NOT NULL,
            `branch_id` INT NULL,
            `first_name` VARCHAR(100) NOT NULL,
            `last_name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(150),
            `phone` VARCHAR(50),
            `position` VARCHAR(100),
            `department` VARCHAR(100),
            `employment_type` VARCHAR(50) DEFAULT 'Full-Time',
            `employment_status` VARCHAR(50) DEFAULT 'Regular',
            `status` VARCHAR(20) DEFAULT 'Active',
            `basic_salary` DECIMAL(12,2) DEFAULT 0.00,
            `hourly_rate` DECIMAL(10,2) DEFAULT 0.00,
            `date_hired` DATE,
            `emergency_contact` VARCHAR(255),
            `address` TEXT,
            `tin` VARCHAR(50),
            `sss` VARCHAR(50),
            `philhealth` VARCHAR(50),
            `pagibig` VARCHAR(50),
            `bank_account` VARCHAR(100),
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100),
            `username` VARCHAR(100) NOT NULL UNIQUE,
            `email` VARCHAR(150) NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `role_id` INT NOT NULL,
            `employee_id` INT NULL,
            `branch_id` INT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE `applicants` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `job_opening_id` INT NULL,
            `first_name` VARCHAR(100) NOT NULL,
            `last_name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(150) NOT NULL,
            `phone` VARCHAR(50),
            `position` VARCHAR(100),
            `position_applied` VARCHAR(100),
            `resume` VARCHAR(255),
            `resume_path` VARCHAR(255),
            `status` VARCHAR(50) DEFAULT 'New',
            `notes` TEXT,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE `interviews` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `applicant_id` INT NOT NULL,
            `interviewer_id` INT NULL,
            `scheduled_at` DATETIME,
            `notes` TEXT,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE `interview_scorecards` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `interview_id` INT NOT NULL,
            `technical_score` INT DEFAULT 0,
            `communication_score` INT DEFAULT 0,
            `culture_fit_score` INT DEFAULT 0,
            `recommendation` VARCHAR(50),
            `notes` TEXT,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE `attendance` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `employee_id` INT NOT NULL,
            `branch_id` INT NOT NULL,
            `clock_in` DATETIME,
            `clock_out` DATETIME,
            `work_date` DATE,
            `source` VARCHAR(20) DEFAULT 'DEVICE',
            `status` VARCHAR(20) DEFAULT 'CLOSED',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE `leaves` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `employee_id` INT NOT NULL,
            `type` VARCHAR(50),
            `date_from` DATE,
            `date_to` DATE,
            `days` DECIMAL(5,2) DEFAULT 1.00,
            `reason` TEXT,
            `status` VARCHAR(50) DEFAULT 'Approved',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE `schedules` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `branch_id` INT NOT NULL,
            `employee_id` INT NOT NULL,
            `shift_date` DATE,
            `start_time` TIME,
            `end_time` TIME,
            `shift_type` VARCHAR(50) DEFAULT 'Standard',
            `status` VARCHAR(50) DEFAULT 'PUBLISHED',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE `payroll` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `employee_id` INT NOT NULL,
            `period_start` DATE,
            `period_end` DATE,
            `basic_pay` DECIMAL(12,2) DEFAULT 0.00,
            `overtime_pay` DECIMAL(12,2) DEFAULT 0.00,
            `gross_pay` DECIMAL(12,2) DEFAULT 0.00,
            `sss_deduction` DECIMAL(12,2) DEFAULT 0.00,
            `philhealth_deduction` DECIMAL(12,2) DEFAULT 0.00,
            `pagibig_deduction` DECIMAL(12,2) DEFAULT 0.00,
            `tax_deduction` DECIMAL(12,2) DEFAULT 0.00,
            `net_pay` DECIMAL(12,2) DEFAULT 0.00,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE `inventory` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `item_name` VARCHAR(150) NOT NULL,
            `unit` VARCHAR(50) DEFAULT 'pcs',
            `unit_cost` DECIMAL(12,2) DEFAULT 0.00,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE `inventory_stock` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `branch_id` INT NOT NULL,
            `inventory_id` INT NOT NULL,
            `current_stock` DECIMAL(10,2) DEFAULT 0.00,
            `low_stock_threshold` DECIMAL(10,2) DEFAULT 5.00,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE `inventory_transactions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `branch_id` INT NOT NULL,
            `inventory_id` INT NOT NULL,
            `type` VARCHAR(50) DEFAULT 'Restock',
            `quantity` DECIMAL(10,2) DEFAULT 0.00,
            `unit_cost` DECIMAL(12,2) DEFAULT 0.00,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE `products` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(150) NOT NULL,
            `category` VARCHAR(100),
            `price` DECIMAL(12,2) DEFAULT 0.00,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE `recipes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `product_id` INT NOT NULL,
            `modifier_id` INT NULL,
            `inventory_id` INT NOT NULL,
            `quantity_required` DECIMAL(10,2) DEFAULT 1.00,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE `cash_sessions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `branch_id` INT NOT NULL,
            `cashier_id` INT NULL,
            `starting_cash` DECIMAL(12,2) DEFAULT 0.00,
            `expected_cash` DECIMAL(12,2) DEFAULT 0.00,
            `ending_cash` DECIMAL(12,2) DEFAULT 0.00,
            `status` VARCHAR(20) DEFAULT 'CLOSED',
            `opened_at` DATETIME,
            `closed_at` DATETIME,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE `orders` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_number` VARCHAR(50) NOT NULL,
            `branch_id` INT NOT NULL,
            `pos_session_id` INT NULL,
            `cashier_id` INT NULL,
            `total_amount` DECIMAL(12,2) DEFAULT 0.00,
            `discount_amount` DECIMAL(12,2) DEFAULT 0.00,
            `vat_amount` DECIMAL(12,2) DEFAULT 0.00,
            `final_amount` DECIMAL(12,2) DEFAULT 0.00,
            `order_status` VARCHAR(50) DEFAULT 'COMPLETED',
            `payment_status` VARCHAR(50) DEFAULT 'PAID',
            `payment_method` VARCHAR(50) DEFAULT 'CASH',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE `order_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `quantity` INT DEFAULT 1,
            `price` DECIMAL(12,2) DEFAULT 0.00,
            `subtotal` DECIMAL(12,2) DEFAULT 0.00,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE `payments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_id` INT NOT NULL,
            `payment_method` VARCHAR(50) DEFAULT 'CASH',
            `amount_paid` DECIMAL(12,2) DEFAULT 0.00,
            `change_amount` DECIMAL(12,2) DEFAULT 0.00,
            `status` VARCHAR(50) DEFAULT 'paid',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE `branch_budgets` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `branch_id` INT NOT NULL,
            `budget_month` VARCHAR(7) NOT NULL,
            `allocated_amount` DECIMAL(15,2) DEFAULT 0.00,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE `activity_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NULL,
            `action` VARCHAR(100) NOT NULL,
            `details` TEXT,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");

    echo "Tables successfully created.\n";

    // 5. Seed Representative Records
    // Known bcrypt hash for 'Password123!'
    $samplePasswordHash = password_hash('Password123!', PASSWORD_BCRYPT);

    // 5.1 Branches
    $pdo->exec("
        INSERT INTO `branches` (`id`, `name`, `address`, `contact_number`, `status`, `created_at`) VALUES
        (101, 'Makati Flagship', '101 Ayala Ave, Makati City', '09171234567', 'Active', '2026-01-01 08:00:00'),
        (102, 'BGC High Street', '9th Ave, Bonifacio Global City', '09177654321', 'Active', '2026-02-01 08:00:00');
    ");

    // 5.2 Roles
    $pdo->exec("
        INSERT INTO `roles` (`id`, `name`) VALUES
        (1, 'System Admin'),
        (2, 'Central HR'),
        (7, 'Barista');
    ");

    // 5.3 Employees (Includes 1 regular employee and 1 ORPHAN employee referencing branch_id 99999)
    $pdo->exec("
        INSERT INTO `employees` (`id`, `employee_id`, `branch_id`, `first_name`, `last_name`, `email`, `phone`, `position`, `department`, `employment_type`, `employment_status`, `status`, `basic_salary`, `hourly_rate`, `date_hired`, `emergency_contact`, `address`, `tin`, `sss`, `philhealth`, `pagibig`, `bank_account`, `created_at`) VALUES
        (201, 'EMP-LEGACY-001', 101, 'Juan', 'Dela Cruz', 'juan.delacruz@fika.test', '09181112222', 'Barista', 'Operations', 'Full-Time', 'Regular', 'Active', 25000.00, 150.00, '2025-06-01', 'Maria Dela Cruz (09189998888)', '45 Makati Ave, Makati', '123-456-789-000', '04-1234567-8', '12-345678901-2', '1234-5678-9012', 'BDO-123456789', '2025-06-01 09:00:00'),
        (202, 'EMP-LEGACY-002', 102, 'Maria', 'Santos', 'maria.santos@fika.test', '09183334444', 'Cashier', 'Operations', 'Full-Time', 'Regular', 'Active', 22000.00, 130.00, '2025-07-01', 'Jose Santos (09187776666)', '12 BGC Street, Taguig', '987-654-321-000', '04-7654321-8', '98-765432109-8', '9876-5432-1098', 'BPI-987654321', '2025-07-01 09:00:00'),
        (299, 'EMP-LEGACY-ORPHAN', 99999, 'Orphan', 'Worker', 'orphan.worker@fika.test', '09185556666', 'Floating Staff', 'Operations', 'Part-Time', 'Probationary', 'Active', 18000.00, 110.00, '2026-01-15', 'None', 'Unknown', '000-000-000-000', '00-0000000-0', '00-000000000-0', '0000-0000-0000', 'None', '2026-01-15 09:00:00');
    ");

    // 5.4 Users
    $pdo->prepare("
        INSERT INTO `users` (`id`, `name`, `username`, `email`, `password`, `role_id`, `employee_id`, `branch_id`, `created_at`) VALUES
        (301, 'System Admin', 'legacy_admin', 'admin@fika.test', ?, 1, 201, 101, '2025-06-01 10:00:00'),
        (302, 'Maria Santos', 'legacy_maria', 'maria.santos@fika.test', ?, 7, 202, 102, '2025-07-01 10:00:00');
    ")->execute([$samplePasswordHash, $samplePasswordHash]);

    // 5.5 Applicants + Interviews + Interview Scorecards
    $pdo->exec("
        INSERT INTO `applicants` (`id`, `job_opening_id`, `first_name`, `last_name`, `email`, `phone`, `position`, `position_applied`, `resume`, `resume_path`, `status`, `notes`, `created_at`) VALUES
        (401, NULL, 'Carlos', 'Reyes', 'carlos.reyes@email.test', '09191234567', 'Barista', 'Barista', 'resume_401.pdf', 'storage/resumes/resume_401.pdf', 'Shortlisted', 'Legacy candidate applied online.', '2026-02-10 14:00:00'),
        (402, NULL, 'Elena', 'Torres', 'elena.torres@email.test', '09197654321', 'Cashier', 'Cashier', 'resume_402.pdf', 'storage/resumes/resume_402.pdf', 'New', 'Walk-in applicant.', '2026-02-11 15:30:00');

        INSERT INTO `interviews` (`id`, `applicant_id`, `interviewer_id`, `scheduled_at`, `notes`, `created_at`) VALUES
        (501, 401, 301, '2026-02-15 10:00:00', 'Strong candidate with specialty coffee background.', '2026-02-12 09:00:00');

        INSERT INTO `interview_scorecards` (`id`, `interview_id`, `technical_score`, `communication_score`, `culture_fit_score`, `recommendation`, `notes`, `created_at`) VALUES
        (601, 501, 4, 5, 4, 'Hire', 'Excellent latte art calibration and customer presence.', '2026-02-15 11:00:00');
    ");

    // 5.6 Attendance
    $pdo->exec("
        INSERT INTO `attendance` (`id`, `employee_id`, `branch_id`, `clock_in`, `clock_out`, `work_date`, `source`, `status`, `created_at`) VALUES
        (701, 201, 101, '2026-02-01 07:02:15', '2026-02-01 15:05:42', '2026-02-01', 'DEVICE', 'CLOSED', '2026-02-01 15:06:00'),
        (702, 202, 102, '2026-02-01 07:55:00', '2026-02-01 16:01:10', '2026-02-01', 'MANUAL', 'CLOSED', '2026-02-01 16:02:00');
    ");

    // 5.7 Leaves
    $pdo->exec("
        INSERT INTO `leaves` (`id`, `employee_id`, `type`, `date_from`, `date_to`, `days`, `reason`, `status`, `created_at`) VALUES
        (801, 201, 'SICK', '2026-02-05', '2026-02-05', 1.00, 'Seasonal flu and fever', 'Approved', '2026-02-04 18:00:00');
    ");

    // 5.8 Schedules
    $pdo->exec("
        INSERT INTO `schedules` (`id`, `branch_id`, `employee_id`, `shift_date`, `start_time`, `end_time`, `shift_type`, `status`, `created_at`) VALUES
        (901, 101, 201, '2026-02-02', '07:00:00', '15:00:00', 'Morning', 'PUBLISHED', '2026-01-30 10:00:00');
    ");

    // 5.9 Payroll
    $pdo->exec("
        INSERT INTO `payroll` (`id`, `employee_id`, `period_start`, `period_end`, `basic_pay`, `overtime_pay`, `gross_pay`, `sss_deduction`, `philhealth_deduction`, `pagibig_deduction`, `tax_deduction`, `net_pay`, `created_at`) VALUES
        (1001, 201, '2026-02-01', '2026-02-15', 12500.00, 1500.00, 14000.00, 500.00, 300.00, 100.00, 1000.00, 12100.00, '2026-02-16 12:00:00');
    ");

    // 5.10 Inventory
    $pdo->exec("
        INSERT INTO `inventory` (`id`, `item_name`, `unit`, `unit_cost`, `created_at`) VALUES
        (1101, 'Espresso Beans Arabica', 'kg', 450.00, '2026-01-01 08:00:00'),
        (1102, 'Full Cream Fresh Milk', 'L', 95.00, '2026-01-01 08:00:00');

        INSERT INTO `inventory_stock` (`id`, `branch_id`, `inventory_id`, `current_stock`, `low_stock_threshold`, `created_at`) VALUES
        (1201, 101, 1101, 30.00, 5.00, '2026-01-01 09:00:00'),
        (1202, 101, 1102, 50.00, 10.00, '2026-01-01 09:00:00');

        INSERT INTO `inventory_transactions` (`id`, `branch_id`, `inventory_id`, `type`, `quantity`, `unit_cost`, `created_at`) VALUES
        (1301, 101, 1101, 'Restock', 30.00, 450.00, '2026-01-02 10:00:00');
    ");

    // 5.11 Products
    $pdo->exec("
        INSERT INTO `products` (`id`, `name`, `category`, `price`, `is_active`, `created_at`) VALUES
        (1401, 'Caffe Latte 12oz', 'Hot Coffee', 150.00, 1, '2026-01-01 08:00:00'),
        (1402, 'Iced Americano 16oz', 'Iced Coffee', 130.00, 1, '2026-01-01 08:00:00');

        INSERT INTO `recipes` (`id`, `product_id`, `modifier_id`, `inventory_id`, `quantity_required`, `created_at`) VALUES
        (1451, 1401, NULL, 1101, 0.02, '2026-01-01 08:00:00'),
        (1452, 1401, NULL, 1102, 0.25, '2026-01-01 08:00:00');
    ");

    // 5.12 Cash Sessions
    $pdo->exec("
        INSERT INTO `cash_sessions` (`id`, `branch_id`, `cashier_id`, `starting_cash`, `expected_cash`, `ending_cash`, `status`, `opened_at`, `closed_at`, `created_at`) VALUES
        (1501, 101, 302, 5000.00, 5280.00, 5280.00, 'CLOSED', '2026-02-01 07:00:00', '2026-02-01 16:00:00', '2026-02-01 16:00:00');
    ");

    // 5.13 Orders (Includes 1 standard order and 1 ORPHAN order referencing cashier_id 99999)
    $pdo->exec("
        INSERT INTO `orders` (`id`, `order_number`, `branch_id`, `pos_session_id`, `cashier_id`, `total_amount`, `discount_amount`, `vat_amount`, `final_amount`, `order_status`, `payment_status`, `payment_method`, `created_at`) VALUES
        (1601, 'ORD-LEGACY-001', 101, 1501, 302, 150.00, 0.00, 16.07, 150.00, 'COMPLETED', 'PAID', 'CASH', '2026-02-01 10:15:00'),
        (1699, 'ORD-LEGACY-ORPHAN', 101, 1501, 99999, 130.00, 0.00, 13.93, 130.00, 'COMPLETED', 'PAID', 'GCASH', '2026-02-01 11:20:00');

        INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`, `subtotal`, `created_at`) VALUES
        (1701, 1601, 1401, 1, 150.00, 150.00, '2026-02-01 10:15:00'),
        (1702, 1699, 1402, 1, 130.00, 130.00, '2026-02-01 11:20:00');

        INSERT INTO `payments` (`id`, `order_id`, `payment_method`, `amount_paid`, `change_amount`, `status`, `created_at`) VALUES
        (1801, 1601, 'CASH', 200.00, 50.00, 'paid', '2026-02-01 10:16:00'),
        (1802, 1699, 'GCASH', 130.00, 0.00, 'paid', '2026-02-01 11:21:00');
    ");

    // 5.14 Branch Budgets
    $pdo->exec("
        INSERT INTO `branch_budgets` (`id`, `branch_id`, `budget_month`, `allocated_amount`, `created_at`) VALUES
        (1901, 101, '2026-02', 120000.00, '2026-01-25 10:00:00');
    ");

    // 5.15 Activity Logs (Includes 1 standard log and 1 orphan user log)
    $pdo->exec("
        INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `details`, `created_at`) VALUES
        (2001, 301, 'LOGIN', 'Admin authenticated from 192.168.1.100', '2026-02-01 07:00:00'),
        (2002, 99999, 'SYSTEM_REBOOT', 'Automated maintenance cycle', '2026-02-01 03:00:00');
    ");

    echo "=== Legacy Test Data Seeded Successfully ===\n";
    echo "- Branches: 2\n";
    echo "- Roles: 3\n";
    echo "- Employees: 3 (including 1 ORPHAN with non-existent branch_id=99999)\n";
    echo "- Users: 2 (bcrypt passwords)\n";
    echo "- Applicants: 2 (with interview & scorecard scores)\n";
    echo "- Attendance: 2\n";
    echo "- Leaves: 1\n";
    echo "- Schedules: 1\n";
    echo "- Payroll: 1\n";
    echo "- Inventory & Stock: 2 items, 2 stock, 1 txn\n";
    echo "- Products: 2 (with recipes)\n";
    echo "- Cash Sessions: 1\n";
    echo "- Orders: 2 (including 1 ORPHAN with non-existent cashier_id=99999)\n";
    echo "- Order Items: 2\n";
    echo "- Payments: 2\n";
    echo "- Branch Budgets: 1 (120,000.00 PHP)\n";
    echo "- Activity Logs: 2 (including 1 orphan user log)\n\n";

} catch (\Throwable $e) {
    echo "SEEDING ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
