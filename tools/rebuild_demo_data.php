<?php
/**
 * tools/rebuild_demo_data.php
 *
 * Master Demo Rebuild & Seeding Script
 * Safely resets database tables, generates branches, human users, system devices,
 * ATS applicants, and mock transactions for presentation dashboards.
 *
 * Usage:
 *   php tools/rebuild_demo_data.php
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

require_once __DIR__ . '/../app/bootstrap.php';

global $pdo;

echo "====================================================================\n";
echo "Fika Café — Master Demo Rebuild & Seeding Script\n";
echo "====================================================================\n\n";

// ====================================================================
// TASK 1: DATABASE RESET
// ====================================================================
echo "[1/6] Resetting Database Tables...\n";

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

$tablesToClean = [
    // Orders & Transactions
    'order_item_modifiers',
    'order_items',
    'order_discounts',
    'order_audit',
    'order_kiosk_tokens',
    'order_ledger_deductions',
    'payments',
    'orders',
    'cash_sessions',

    // Finance & HRMS Records
    'expenses',
    'payroll_items',
    'payroll_runs',
    'attendance_logs',
    'attendance_certifications',
    'leave_requests',
    'pending_bonuses',
    'employee_documents',

    // Inventory & Catalog
    'inventory_ledger',
    'inventory_stock',
    'inventory_adjustments',
    'purchase_order_items',
    'po_receipts',
    'purchase_orders',
    'recipes',
    'branch_products',
    'products',
    'categories',
    'inventory_items',

    // Operations & Hardware
    'applicant_logs',
    'applicant_rate_limits',
    'interview_scorecards',
    'interviews',
    'applicants',
    'open_shifts',
    'shifts',
    'devices',
    'branch_budgets',

    // Identity & Hierarchy
    'users',
    'employees',
    'branches',
    'login_attempts',
];

foreach ($tablesToClean as $table) {
    try {
        $pdo->exec("TRUNCATE TABLE `{$table}`");
        echo "  - Truncated table: {$table}\n";
    } catch (PDOException $e) {
        $pdo->exec("DELETE FROM `{$table}`");
        echo "  - Emptied table: {$table}\n";
    }
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "[SUCCESS] Database reset complete.\n\n";

// ====================================================================
// TASK 2: CREATE 3 BRANCHES
// ====================================================================
echo "[2/6] Inserting 3 Branches...\n";

$branchesData = [
    [
        'id' => 1,
        'name' => 'General Trias Branch',
        'address' => 'General Trias City, Cavite',
        'contact_number' => '0917-111-0001',
        'status' => 'Active'
    ],
    [
        'id' => 2,
        'name' => 'Trece Martires Branch',
        'address' => 'Trece Martires City, Cavite',
        'contact_number' => '0917-222-0002',
        'status' => 'Active'
    ],
    [
        'id' => 3,
        'name' => 'Imus Branch',
        'address' => 'Imus City, Cavite',
        'contact_number' => '0917-333-0003',
        'status' => 'Active'
    ]
];

$insBranch = $pdo->prepare("
    INSERT INTO branches (id, name, address, contact_number, status, created_at, updated_at) 
    VALUES (:id, :name, :address, :contact_number, :status, NOW(), NOW())
");

foreach ($branchesData as $b) {
    $insBranch->execute($b);
    echo "  + Created Branch {$b['id']}: {$b['name']} ({$b['address']})\n";
}
echo "[SUCCESS] Branches initialized.\n\n";

// ====================================================================
// TASK 3: GENERATE HUMAN USER ACCOUNTS (PASSWORD = 'password')
// ====================================================================
echo "[3/6] Generating Human User Accounts & Linked Employees...\n";

$passwordHash = password_hash('password', PASSWORD_DEFAULT);
$pinHash = password_hash('1234', PASSWORD_DEFAULT);

$humanAccounts = [
    // Global (Branch ID = NULL)
    [
        'username'      => 'maria.clara',
        'role_id'       => 2,
        'branch_id'     => null,
        'first_name'    => 'Maria',
        'last_name'     => 'Clara',
        'email'         => 'maria.clara@fika.local',
        'position'      => 'Central HR',
        'department'    => 'Human Resources',
        'staff_class'   => 'HQ',
        'basic_salary'  => 35000.00
    ],
    [
        'username'      => 'jose.rizal',
        'role_id'       => 3,
        'branch_id'     => null,
        'first_name'    => 'Jose',
        'last_name'     => 'Rizal',
        'email'         => 'jose.rizal@fika.local',
        'position'      => 'Global Accountant',
        'department'    => 'Finance',
        'staff_class'   => 'HQ',
        'basic_salary'  => 38000.00
    ],
    [
        'username'      => 'admin',
        'role_id'       => 1,
        'branch_id'     => null,
        'first_name'    => 'System',
        'last_name'     => 'Administrator',
        'email'         => 'admin@fika.local',
        'position'      => 'System Administrator',
        'department'    => 'Administration',
        'staff_class'   => 'HQ',
        'basic_salary'  => 50000.00
    ],

    // General Trias Branch (Branch ID 1)
    [
        'username'      => 'juan.delacruz',
        'role_id'       => 6, // BM
        'branch_id'     => 1,
        'first_name'    => 'Juan',
        'last_name'     => 'Dela Cruz',
        'email'         => 'juan.delacruz@fika.local',
        'position'      => 'Branch Manager',
        'department'    => 'Operations',
        'staff_class'   => 'OFFICIAL',
        'basic_salary'  => 30000.00
    ],
    [
        'username'      => 'ana.reyes',
        'role_id'       => 5, // BHR
        'branch_id'     => 1,
        'first_name'    => 'Ana',
        'last_name'     => 'Reyes',
        'email'         => 'ana.reyes@fika.local',
        'position'      => 'Branch HR',
        'department'    => 'Human Resources',
        'staff_class'   => 'OFFICIAL',
        'basic_salary'  => 25000.00
    ],
    [
        'username'      => 'pedro.santos',
        'role_id'       => 4, // BA
        'branch_id'     => 1,
        'first_name'    => 'Pedro',
        'last_name'     => 'Santos',
        'email'         => 'pedro.santos@fika.local',
        'position'      => 'Branch Accountant',
        'department'    => 'Finance',
        'staff_class'   => 'OFFICIAL',
        'basic_salary'  => 25000.00
    ],
    [
        'username'      => 'miguel.cruz',
        'role_id'       => 7, // STAFF
        'branch_id'     => 1,
        'first_name'    => 'Miguel',
        'last_name'     => 'Cruz',
        'email'         => 'miguel.cruz@fika.local',
        'position'      => 'Head Barista',
        'department'    => 'Operations',
        'staff_class'   => 'CREW',
        'basic_salary'  => 20000.00
    ],
    [
        'username'      => 'sofia.garcia',
        'role_id'       => 7, // STAFF
        'branch_id'     => 1,
        'first_name'    => 'Sofia',
        'last_name'     => 'Garcia',
        'email'         => 'sofia.garcia@fika.local',
        'position'      => 'Head Barista',
        'department'    => 'Operations',
        'staff_class'   => 'CREW',
        'basic_salary'  => 20000.00
    ],
    [
        'username'      => 'carlo.mendoza',
        'role_id'       => 7, // Barista
        'branch_id'     => 1,
        'first_name'    => 'Carlo',
        'last_name'     => 'Mendoza',
        'email'         => 'carlo.mendoza@fika.local',
        'position'      => 'Barista',
        'department'    => 'Operations',
        'staff_class'   => 'CREW',
        'basic_salary'  => 16000.00
    ],
    [
        'username'      => 'bea.torres',
        'role_id'       => 7, // Barista
        'branch_id'     => 1,
        'first_name'    => 'Bea',
        'last_name'     => 'Torres',
        'email'         => 'bea.torres@fika.local',
        'position'      => 'Barista',
        'department'    => 'Operations',
        'staff_class'   => 'CREW',
        'basic_salary'  => 16000.00
    ],
    [
        'username'      => 'leo.flores',
        'role_id'       => 7, // Barista
        'branch_id'     => 1,
        'first_name'    => 'Leo',
        'last_name'     => 'Flores',
        'email'         => 'leo.flores@fika.local',
        'position'      => 'Barista',
        'department'    => 'Operations',
        'staff_class'   => 'CREW',
        'basic_salary'  => 16000.00
    ],

    // Trece Martires Branch (Branch ID 2)
    [
        'username'      => 'andres.bonifacio',
        'role_id'       => 6, // BM
        'branch_id'     => 2,
        'first_name'    => 'Andres',
        'last_name'     => 'Bonifacio',
        'email'         => 'andres.bonifacio@fika.local',
        'position'      => 'Branch Manager',
        'department'    => 'Operations',
        'staff_class'   => 'OFFICIAL',
        'basic_salary'  => 30000.00
    ],
    [
        'username'      => 'gabriela.silang',
        'role_id'       => 5, // BHR
        'branch_id'     => 2,
        'first_name'    => 'Gabriela',
        'last_name'     => 'Silang',
        'email'         => 'gabriela.silang@fika.local',
        'position'      => 'Branch HR',
        'department'    => 'Human Resources',
        'staff_class'   => 'OFFICIAL',
        'basic_salary'  => 25000.00
    ],
    [
        'username'      => 'emilio.aguinaldo',
        'role_id'       => 4, // BA
        'branch_id'     => 2,
        'first_name'    => 'Emilio',
        'last_name'     => 'Aguinaldo',
        'email'         => 'emilio.aguinaldo@fika.local',
        'position'      => 'Branch Accountant',
        'department'    => 'Finance',
        'staff_class'   => 'OFFICIAL',
        'basic_salary'  => 25000.00
    ],
    [
        'username'      => 'melchora.aquino',
        'role_id'       => 7, // STAFF
        'branch_id'     => 2,
        'first_name'    => 'Melchora',
        'last_name'     => 'Aquino',
        'email'         => 'melchora.aquino@fika.local',
        'position'      => 'Head Barista',
        'department'    => 'Operations',
        'staff_class'   => 'CREW',
        'basic_salary'  => 20000.00
    ],
    [
        'username'      => 'apolinario.mabini',
        'role_id'       => 7, // STAFF
        'branch_id'     => 2,
        'first_name'    => 'Apolinario',
        'last_name'     => 'Mabini',
        'email'         => 'apolinario.mabini@fika.local',
        'position'      => 'Head Barista',
        'department'    => 'Operations',
        'staff_class'   => 'CREW',
        'basic_salary'  => 20000.00
    ],
    [
        'username'      => 'marcelo.pilar',
        'role_id'       => 7, // Barista
        'branch_id'     => 2,
        'first_name'    => 'Marcelo',
        'last_name'     => 'Pilar',
        'email'         => 'marcelo.pilar@fika.local',
        'position'      => 'Barista',
        'department'    => 'Operations',
        'staff_class'   => 'CREW',
        'basic_salary'  => 16000.00
    ],
    [
        'username'      => 'gregorio.delpilar',
        'role_id'       => 7, // Barista
        'branch_id'     => 2,
        'first_name'    => 'Gregorio',
        'last_name'     => 'Del Pilar',
        'email'         => 'gregorio.delpilar@fika.local',
        'position'      => 'Barista',
        'department'    => 'Operations',
        'staff_class'   => 'CREW',
        'basic_salary'  => 16000.00
    ],
    [
        'username'      => 'teresa.magbanua',
        'role_id'       => 7, // Barista
        'branch_id'     => 2,
        'first_name'    => 'Teresa',
        'last_name'     => 'Magbanua',
        'email'         => 'teresa.magbanua@fika.local',
        'position'      => 'Barista',
        'department'    => 'Operations',
        'staff_class'   => 'CREW',
        'basic_salary'  => 16000.00
    ],

    // Imus Branch (Branch ID 3)
    [
        'username'      => 'jose.laurel',
        'role_id'       => 6, // BM
        'branch_id'     => 3,
        'first_name'    => 'Jose',
        'last_name'     => 'Laurel',
        'email'         => 'jose.laurel@fika.local',
        'position'      => 'Branch Manager',
        'department'    => 'Operations',
        'staff_class'   => 'OFFICIAL',
        'basic_salary'  => 30000.00
    ],
    [
        'username'      => 'ramon.magsaysay',
        'role_id'       => 5, // BHR
        'branch_id'     => 3,
        'first_name'    => 'Ramon',
        'last_name'     => 'Magsaysay',
        'email'         => 'ramon.magsaysay@fika.local',
        'position'      => 'Branch HR',
        'department'    => 'Human Resources',
        'staff_class'   => 'OFFICIAL',
        'basic_salary'  => 25000.00
    ],
    [
        'username'      => 'sergio.osmena',
        'role_id'       => 4, // BA
        'branch_id'     => 3,
        'first_name'    => 'Sergio',
        'last_name'     => 'Osmena',
        'email'         => 'sergio.osmena@fika.local',
        'position'      => 'Branch Accountant',
        'department'    => 'Finance',
        'staff_class'   => 'OFFICIAL',
        'basic_salary'  => 25000.00
    ],
    [
        'username'      => 'manuel.quezon',
        'role_id'       => 7, // STAFF
        'branch_id'     => 3,
        'first_name'    => 'Manuel',
        'last_name'     => 'Quezon',
        'email'         => 'manuel.quezon@fika.local',
        'position'      => 'Head Barista',
        'department'    => 'Operations',
        'staff_class'   => 'CREW',
        'basic_salary'  => 20000.00
    ],
    [
        'username'      => 'elpidio.quirino',
        'role_id'       => 7, // STAFF
        'branch_id'     => 3,
        'first_name'    => 'Elpidio',
        'last_name'     => 'Quirino',
        'email'         => 'elpidio.quirino@fika.local',
        'position'      => 'Head Barista',
        'department'    => 'Operations',
        'staff_class'   => 'CREW',
        'basic_salary'  => 20000.00
    ],
    [
        'username'      => 'diosdado.macapagal',
        'role_id'       => 7, // Barista
        'branch_id'     => 3,
        'first_name'    => 'Diosdado',
        'last_name'     => 'Macapagal',
        'email'         => 'diosdado.macapagal@fika.local',
        'position'      => 'Barista',
        'department'    => 'Operations',
        'staff_class'   => 'CREW',
        'basic_salary'  => 16000.00
    ],
    [
        'username'      => 'ferdinand.marcos',
        'role_id'       => 7, // Barista
        'branch_id'     => 3,
        'first_name'    => 'Ferdinand',
        'last_name'     => 'Marcos',
        'email'         => 'ferdinand.marcos@fika.local',
        'position'      => 'Barista',
        'department'    => 'Operations',
        'staff_class'   => 'CREW',
        'basic_salary'  => 16000.00
    ],
    [
        'username'      => 'corazon.aquino',
        'role_id'       => 7, // Barista
        'branch_id'     => 3,
        'first_name'    => 'Corazon',
        'last_name'     => 'Aquino',
        'email'         => 'corazon.aquino@fika.local',
        'position'      => 'Barista',
        'department'    => 'Operations',
        'staff_class'   => 'CREW',
        'basic_salary'  => 16000.00
    ],
];

$insEmp = $pdo->prepare("
    INSERT INTO employees (
        employee_code, branch_id, first_name, last_name, email,
        position, department, employment_type, status, attendance_mode, staff_class,
        basic_salary, hourly_rate, pin_hash, date_hired, created_at, updated_at
    ) VALUES (
        :code, :branch_id, :first_name, :last_name, :email,
        :position, :department, 'REGULAR', 'ACTIVE', 'CLOCK', :staff_class,
        :basic_salary, :hourly_rate, :pin_hash, '2026-01-15', NOW(), NOW()
    )
");

$insUser = $pdo->prepare("
    INSERT INTO users (
        username, password, email, role_id, employee_id, branch_id, created_at, updated_at
    ) VALUES (
        :username, :password, :email, :role_id, :employee_id, :branch_id, NOW(), NOW()
    )
");

$createdHumanUsers = [];
$empCounter = 1001;

foreach ($humanAccounts as $acc) {
    $empCode = sprintf("EMP-%04d", $empCounter++);
    $hourlyRate = round($acc['basic_salary'] / (26 * 8), 2);

    $insEmp->execute([
        ':code'         => $empCode,
        ':branch_id'    => $acc['branch_id'],
        ':first_name'   => $acc['first_name'],
        ':last_name'    => $acc['last_name'],
        ':email'        => $acc['email'],
        ':position'     => $acc['position'],
        ':department'   => $acc['department'],
        ':staff_class'  => $acc['staff_class'],
        ':basic_salary' => $acc['basic_salary'],
        ':hourly_rate'  => $hourlyRate,
        ':pin_hash'     => $pinHash,
    ]);
    $empId = (int)$pdo->lastInsertId();

    if ($acc['username'] === 'admin') {
        $pdo->prepare("
            INSERT INTO users (id, username, password, email, role_id, employee_id, branch_id, created_at, updated_at)
            VALUES (10006, :username, :password, :email, :role_id, :employee_id, :branch_id, NOW(), NOW())
        ")->execute([
            ':username'    => $acc['username'],
            ':password'    => $passwordHash,
            ':email'       => $acc['email'],
            ':role_id'     => $acc['role_id'],
            ':employee_id' => $empId,
            ':branch_id'   => $acc['branch_id'],
        ]);
        $userId = 10006;
    } else {
        $insUser->execute([
            ':username'    => $acc['username'],
            ':password'    => $passwordHash,
            ':email'       => $acc['email'],
            ':role_id'     => $acc['role_id'],
            ':employee_id' => $empId,
            ':branch_id'   => $acc['branch_id'],
        ]);
        $userId = (int)$pdo->lastInsertId();
    }

    $createdHumanUsers[$acc['username']] = [
        'user_id'     => $userId,
        'employee_id' => $empId,
        'username'    => $acc['username'],
        'role_id'     => $acc['role_id'],
        'position'    => $acc['position'],
        'branch_id'   => $acc['branch_id'],
    ];

    echo "  + User: {$acc['username']} (Role {$acc['role_id']}, Branch: " . ($acc['branch_id'] ?? 'HQ') . ", Emp: {$empCode})\n";
}
echo "[SUCCESS] Generated " . count($humanAccounts) . " human accounts.\n\n";

// ====================================================================
// TASK 4: GENERATE SYSTEM ACCOUNTS & DEVICES (PASSWORD = 'password')
// ====================================================================
echo "[4/6] Creating System Accounts & Device Terminals...\n";

// Ensure Role 9 (Cashier Terminal), Role 12 (Clock), and Role 13 (Kiosk) exist
$pdo->exec("
    INSERT INTO roles (id, name, description) VALUES 
    (9, 'Cashier Terminal', 'Point of sale cashier terminal system role'),
    (12, 'Clock', 'Time and attendance clock station system role'),
    (13, 'Kiosk', 'Self-service kiosk system role')
    ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)
");

// Ensure permissions for POS & Kiosk roles
$rolePerms = [
    [9, 'pos.access'],
    [9, 'pos.order_create'],
    [12, 'pos.access'],
    [13, 'pos.access'],
    [13, 'pos.order_create'],
];
$insRP = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_name) VALUES (?, ?)");
foreach ($rolePerms as [$rId, $pName]) {
    $insRP->execute([$rId, $pName]);
}

$systemAccounts = [
    // General Trias (Branch 1)
    [
        'branch_id'    => 1,
        'username'     => 'gentri.clock',
        'role_id'      => 12,
        'device_name'  => 'General Trias Clock Station',
        'device_token' => 'tok_gentri_clock_123',
        'mac_address'  => '00:1A:2B:01:00:12',
    ],
    [
        'branch_id'    => 1,
        'username'     => 'gentri.kiosk',
        'role_id'      => 13,
        'device_name'  => 'General Trias Self-Service Kiosk',
        'device_token' => 'tok_gentri_kiosk_123',
        'mac_address'  => '00:1A:2B:01:00:04',
    ],
    [
        'branch_id'    => 1,
        'username'     => 'gentri.cashier',
        'role_id'      => 9,
        'device_name'  => 'General Trias Cashier Terminal (POS-01)',
        'device_token' => 'tok_gentri_cashier_123',
        'mac_address'  => '00:1A:2B:01:00:09',
    ],

    // Trece Martires (Branch 2)
    [
        'branch_id'    => 2,
        'username'     => 'trece.clock',
        'role_id'      => 12,
        'device_name'  => 'Trece Martires Clock Station',
        'device_token' => 'tok_trece_clock_123',
        'mac_address'  => '00:1A:2B:02:00:12',
    ],
    [
        'branch_id'    => 2,
        'username'     => 'trece.kiosk',
        'role_id'      => 13,
        'device_name'  => 'Trece Martires Self-Service Kiosk',
        'device_token' => 'tok_trece_kiosk_123',
        'mac_address'  => '00:1A:2B:02:00:04',
    ],
    [
        'branch_id'    => 2,
        'username'     => 'trece.cashier',
        'role_id'      => 9,
        'device_name'  => 'Trece Martires Cashier Terminal (POS-01)',
        'device_token' => 'tok_trece_cashier_123',
        'mac_address'  => '00:1A:2B:02:00:09',
    ],

    // Imus (Branch 3)
    [
        'branch_id'    => 3,
        'username'     => 'imus.clock',
        'role_id'      => 12,
        'device_name'  => 'Imus Clock Station',
        'device_token' => 'tok_imus_clock_123',
        'mac_address'  => '00:1A:2B:03:00:12',
    ],
    [
        'branch_id'    => 3,
        'username'     => 'imus.kiosk',
        'role_id'      => 13,
        'device_name'  => 'Imus Self-Service Kiosk',
        'device_token' => 'tok_imus_kiosk_123',
        'mac_address'  => '00:1A:2B:03:00:04',
    ],
    [
        'branch_id'    => 3,
        'username'     => 'imus.cashier',
        'role_id'      => 9,
        'device_name'  => 'Imus Cashier Terminal (POS-01)',
        'device_token' => 'tok_imus_cashier_123',
        'mac_address'  => '00:1A:2B:03:00:09',
    ],
];

$insDevice = $pdo->prepare("
    INSERT INTO devices (
        branch_id, user_id, device_name, mac_address, device_token, status, created_at, updated_at
    ) VALUES (
        :branch_id, :user_id, :device_name, :mac_address, :device_token, 'Active', NOW(), NOW()
    )
");

$createdSystemAccounts = [];

foreach ($systemAccounts as $sys) {
    // 1. Insert user
    $insUser->execute([
        ':username'    => $sys['username'],
        ':password'    => $passwordHash,
        ':email'       => "{$sys['username']}@fika.local",
        ':role_id'     => $sys['role_id'],
        ':employee_id' => null,
        ':branch_id'   => $sys['branch_id'],
    ]);
    $userId = (int)$pdo->lastInsertId();

    // 2. Insert device
    $insDevice->execute([
        ':branch_id'    => $sys['branch_id'],
        ':user_id'      => $userId,
        ':device_name'  => $sys['device_name'],
        ':mac_address'  => $sys['mac_address'],
        ':device_token' => $sys['device_token'],
    ]);
    $deviceId = (int)$pdo->lastInsertId();

    $createdSystemAccounts[$sys['username']] = [
        'user_id'      => $userId,
        'device_id'    => $deviceId,
        'username'     => $sys['username'],
        'role_id'      => $sys['role_id'],
        'branch_id'    => $sys['branch_id'],
        'device_name'  => $sys['device_name'],
        'device_token' => $sys['device_token'],
    ];

    echo "  + System User: {$sys['username']} (Role {$sys['role_id']}, Branch: {$sys['branch_id']}) -> Device: {$sys['device_name']} [Token: {$sys['device_token']}]\n";
}
echo "[SUCCESS] Generated 9 system accounts and linked devices.\n\n";

// ====================================================================
// TASK 5: SCATTERED ATS APPLICANTS (4 per branch)
// ====================================================================
echo "[5/6] Inserting 12 ATS Applicants Across Branches & Stages...\n";

// Ensure default job openings exist
$pdo->exec("
    INSERT INTO job_openings (id, title, department, location, type, description, requirements, status) VALUES 
    (1, 'Head Barista', 'Operations', 'All Branches', 'Full-Time', 'Lead and mentor the barista team, oversee daily coffee bar operations.', '2+ years specialty coffee experience.', 'OPEN'),
    (2, 'Barista', 'Operations', 'All Branches', 'Full-Time', 'Craft espresso and signature coffee beverages with exceptional customer service.', 'Passionate about coffee and hospitality.', 'OPEN'),
    (3, 'Branch Accountant', 'Finance', 'All Branches', 'Full-Time', 'Handle branch receipts, bank reconciliation, expense audits, and payroll verification.', 'BS Accountancy / Management Accounting.', 'OPEN'),
    (4, 'Branch Manager', 'Store Operations', 'All Branches', 'Full-Time', 'Oversee branch café operations, quality, staff, and customer satisfaction.', '3+ years supervisory/café management experience.', 'OPEN')
    ON DUPLICATE KEY UPDATE title = VALUES(title), status = 'OPEN'
");

$applicantsData = [
    // General Trias (Branch 1)
    [
        'branch_id'      => 1,
        'job_opening_id' => 2,
        'first_name'     => 'Juanito',
        'last_name'      => 'Panganiban',
        'email'          => 'juanito.panganiban@applicant.ph',
        'phone'          => '0918-101-0001',
        'position'       => 'Barista',
        'status'         => 'NEW',
        'stage'          => 'New',
    ],
    [
        'branch_id'      => 1,
        'job_opening_id' => 1,
        'first_name'     => 'Luisa',
        'last_name'      => 'Gonzaga',
        'email'          => 'luisa.gonzaga@applicant.ph',
        'phone'          => '0918-101-0002',
        'position'       => 'Head Barista',
        'status'         => 'REVIEWING',
        'stage'          => 'Initial Interview',
    ],
    [
        'branch_id'      => 1,
        'job_opening_id' => 3,
        'first_name'     => 'Rafael',
        'last_name'      => 'Palma',
        'email'          => 'rafael.palma@applicant.ph',
        'phone'          => '0918-101-0003',
        'position'       => 'Branch Accountant',
        'status'         => 'SHORTLISTED',
        'stage'          => 'Final Interview',
    ],
    [
        'branch_id'      => 1,
        'job_opening_id' => 2,
        'first_name'     => 'Clara',
        'last_name'      => 'Bautista',
        'email'          => 'clara.bautista@applicant.ph',
        'phone'          => '0918-101-0004',
        'position'       => 'Barista',
        'status'         => 'HIRED',
        'stage'          => 'Hireable',
    ],

    // Trece Martires (Branch 2)
    [
        'branch_id'      => 2,
        'job_opening_id' => 2,
        'first_name'     => 'Vicente',
        'last_name'      => 'Lukban',
        'email'          => 'vicente.lukban@applicant.ph',
        'phone'          => '0918-102-0001',
        'position'       => 'Barista',
        'status'         => 'NEW',
        'stage'          => 'New',
    ],
    [
        'branch_id'      => 2,
        'job_opening_id' => 1,
        'first_name'     => 'Teodora',
        'last_name'      => 'Alonzo',
        'email'          => 'teodora.alonzo@applicant.ph',
        'phone'          => '0918-102-0002',
        'position'       => 'Head Barista',
        'status'         => 'REVIEWING',
        'stage'          => 'Initial Interview',
    ],
    [
        'branch_id'      => 2,
        'job_opening_id' => 3,
        'first_name'     => 'Felipe',
        'last_name'      => 'Agoncillo',
        'email'          => 'felipe.agoncillo@applicant.ph',
        'phone'          => '0918-102-0003',
        'position'       => 'Branch Accountant',
        'status'         => 'SHORTLISTED',
        'stage'          => 'Final Interview',
    ],
    [
        'branch_id'      => 2,
        'job_opening_id' => 2,
        'first_name'     => 'Marcella',
        'last_name'      => 'Agoncillo',
        'email'          => 'marcella.agoncillo@applicant.ph',
        'phone'          => '0918-102-0004',
        'position'       => 'Barista',
        'status'         => 'HIRED',
        'stage'          => 'Hireable',
    ],

    // Imus (Branch 3)
    [
        'branch_id'      => 3,
        'job_opening_id' => 2,
        'first_name'     => 'Artemio',
        'last_name'      => 'Ricarte',
        'email'          => 'artemio.ricarte@applicant.ph',
        'phone'          => '0918-103-0001',
        'position'       => 'Barista',
        'status'         => 'NEW',
        'stage'          => 'New',
    ],
    [
        'branch_id'      => 3,
        'job_opening_id' => 1,
        'first_name'     => 'Gliceria',
        'last_name'      => 'Marella',
        'email'          => 'gliceria.marella@applicant.ph',
        'phone'          => '0918-103-0002',
        'position'       => 'Head Barista',
        'status'         => 'REVIEWING',
        'stage'          => 'Initial Interview',
    ],
    [
        'branch_id'      => 3,
        'job_opening_id' => 3,
        'first_name'     => 'Mariano',
        'last_name'      => 'Trias',
        'email'          => 'mariano.trias@applicant.ph',
        'phone'          => '0918-103-0003',
        'position'       => 'Branch Accountant',
        'status'         => 'SHORTLISTED',
        'stage'          => 'Final Interview',
    ],
    [
        'branch_id'      => 3,
        'job_opening_id' => 2,
        'first_name'     => 'Josefa',
        'last_name'      => 'Llanes',
        'email'          => 'josefa.llanes@applicant.ph',
        'phone'          => '0918-103-0004',
        'position'       => 'Barista',
        'status'         => 'HIRED',
        'stage'          => 'Hireable',
    ],
];

$insApp = $pdo->prepare("
    INSERT INTO applicants (
        branch_id, job_opening_id, first_name, last_name, email, phone,
        position, position_applied, status, stage, employment_category, created_at
    ) VALUES (
        :branch_id, :job_opening_id, :first_name, :last_name, :email, :phone,
        :position, :position_applied, :status, :stage, 'Full-Time', NOW()
    )
");

foreach ($applicantsData as $app) {
    $insApp->execute([
        ':branch_id'        => $app['branch_id'],
        ':job_opening_id'   => $app['job_opening_id'],
        ':first_name'       => $app['first_name'],
        ':last_name'        => $app['last_name'],
        ':email'            => $app['email'],
        ':phone'            => $app['phone'],
        ':position'         => $app['position'],
        ':position_applied' => $app['position'],
        ':status'           => $app['status'],
        ':stage'            => $app['stage'],
    ]);
    echo "  + Applicant: {$app['first_name']} {$app['last_name']} -> Branch {$app['branch_id']}, Pos: {$app['position']}, Stage: [{$app['stage']} / {$app['status']}]\n";
}
echo "[SUCCESS] Inserted 12 ATS applicants across 3 branches.\n\n";

// ====================================================================
// TASK 6: GENERATE MOCK TRANSACTIONS (For Dashboard KPIs)
// ====================================================================
echo "[6/6] Generating Mock Transactions (Products, Sales, Expenses, Payroll)...\n";

// 1. Categories & Products
$pdo->exec("
    INSERT INTO categories (id, name, created_at) VALUES 
    (1, 'Espresso & Coffee', NOW()),
    (2, 'Tea & Non-Coffee', NOW()),
    (3, 'Pastries & Bakery', NOW())
    ON DUPLICATE KEY UPDATE name = VALUES(name)
");

$productsData = [
    [
        'id'          => 1,
        'name'        => 'Spanish Latte',
        'category'    => 'Espresso & Coffee',
        'price'       => 150.00,
        'price_cents' => 15000
    ],
    [
        'id'          => 2,
        'name'        => 'Caramel Macchiato',
        'category'    => 'Espresso & Coffee',
        'price'       => 165.00,
        'price_cents' => 16500
    ],
    [
        'id'          => 3,
        'name'        => 'Iced Americano',
        'category'    => 'Espresso & Coffee',
        'price'       => 120.00,
        'price_cents' => 12000
    ],
    [
        'id'          => 4,
        'name'        => 'Ceremonial Matcha Latte',
        'category'    => 'Tea & Non-Coffee',
        'price'       => 175.00,
        'price_cents' => 17500
    ],
    [
        'id'          => 5,
        'name'        => 'Butter Croissant',
        'category'    => 'Pastries & Bakery',
        'price'       => 110.00,
        'price_cents' => 11000
    ]
];

$insProd = $pdo->prepare("
    INSERT INTO products (id, name, category, price, price_cents, is_active, created_at, updated_at)
    VALUES (:id, :name, :category, :price, :price_cents, 1, NOW(), NOW())
    ON DUPLICATE KEY UPDATE name = VALUES(name), price = VALUES(price), price_cents = VALUES(price_cents)
");
$insBranchProd = $pdo->prepare("
    INSERT IGNORE INTO branch_products (branch_id, product_id, is_active) VALUES (?, ?, 1)
");

foreach ($productsData as $p) {
    $insProd->execute($p);
    for ($bId = 1; $bId <= 3; $bId++) {
        $insBranchProd->execute([$bId, $p['id']]);
    }
    echo "  + Product {$p['id']}: {$p['name']} (₱" . number_format($p['price'], 2) . ") linked to all branches\n";
}

// 2. POS Sales: 15 PAID orders distributed across 3 branches over last 3 days
$cashierMap = [
    1 => $createdHumanUsers['carlo.mendoza']['user_id'] ?? 1,
    2 => $createdHumanUsers['marcelo.pilar']['user_id'] ?? 1,
    3 => $createdHumanUsers['diosdado.macapagal']['user_id'] ?? 1,
];

$insOrder = $pdo->prepare("
    INSERT INTO orders (
        order_number, branch_id, cashier_id, total_amount, discount_amount,
        vat_amount, final_amount, order_status, payment_status, payment_method,
        created_at, updated_at
    ) VALUES (
        :order_number, :branch_id, :cashier_id, :total_amount, 0.00,
        :vat_amount, :final_amount, 'PAID', 'PAID', :payment_method,
        :created_at, :updated_at
    )
");

$insOrderItem = $pdo->prepare("
    INSERT INTO order_items (
        order_id, product_id, quantity, price_snapshot, price_snapshot_cents, subtotal, created_at
    ) VALUES (
        :order_id, :product_id, :quantity, :price_snapshot, :price_snapshot_cents, :subtotal, :created_at
    )
");

$insPayment = $pdo->prepare("
    INSERT INTO payments (
        order_id, payment_method, amount_paid, change_amount, status, paid_at, created_at
    ) VALUES (
        :order_id, :payment_method, :amount_paid, 0.00, 'paid', :paid_at, :created_at
    )
");

$orderCount = 0;
for ($bId = 1; $bId <= 3; $bId++) {
    for ($o = 1; $o <= 5; $o++) {
        $orderCount++;
        $daysAgo = ($orderCount % 3);
        $hour = 8 + (($orderCount * 2) % 12);
        $minute = ($orderCount * 13) % 60;
        $orderTime = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days {$hour}:{$minute}:00"));

        // Pick 1 or 2 products
        $p1 = $productsData[($orderCount) % 5];
        $p2 = $productsData[($orderCount + 2) % 5];
        $totalAmount = $p1['price'] + $p2['price'];
        $vatAmount = round($totalAmount * 0.12 / 1.12, 2);
        $payMethod = ($orderCount % 2 === 0) ? 'GCASH' : 'CASH';
        $orderNumber = sprintf("ORD-B%d-%04d", $bId, $orderCount);

        $insOrder->execute([
            ':order_number'    => $orderNumber,
            ':branch_id'       => $bId,
            ':cashier_id'      => $cashierMap[$bId] ?? 1,
            ':total_amount'    => $totalAmount,
            ':vat_amount'      => $vatAmount,
            ':final_amount'    => $totalAmount,
            ':payment_method'  => $payMethod,
            ':created_at'      => $orderTime,
            ':updated_at'      => $orderTime
        ]);
        $orderId = (int)$pdo->lastInsertId();

        // Items
        $insOrderItem->execute([
            ':order_id'             => $orderId,
            ':product_id'           => $p1['id'],
            ':quantity'             => 1,
            ':price_snapshot'       => $p1['price'],
            ':price_snapshot_cents' => $p1['price_cents'],
            ':subtotal'             => $p1['price'],
            ':created_at'           => $orderTime
        ]);
        $insOrderItem->execute([
            ':order_id'             => $orderId,
            ':product_id'           => $p2['id'],
            ':quantity'             => 1,
            ':price_snapshot'       => $p2['price'],
            ':price_snapshot_cents' => $p2['price_cents'],
            ':subtotal'             => $p2['price'],
            ':created_at'           => $orderTime
        ]);

        // Payment
        $insPayment->execute([
            ':order_id'        => $orderId,
            ':payment_method'  => $payMethod,
            ':amount_paid'     => $totalAmount,
            ':paid_at'         => $orderTime,
            ':created_at'      => $orderTime
        ]);
    }
}
echo "  + Created 15 PAID orders with linked items and payments across 3 branches.\n";

// 3. Expenses: 3 APPROVED expenses for General Trias Branch
$gtBmUser = $createdHumanUsers['juan.delacruz']['user_id'] ?? 1;
$gtBaUser = $createdHumanUsers['pedro.santos']['user_id'] ?? 1;

$expensesData = [
    [
        'branch_id'    => 1,
        'category_id'  => 1, // Rent
        'amount_cents' => 3500000, // ₱35,000.00
        'expense_date' => date('Y-m-05'),
        'payee'        => 'Trias Commercial Leasing Inc.',
        'notes'        => 'Monthly commercial retail space lease for General Trias store.',
        'status'       => 'APPROVED',
        'created_by'   => $gtBaUser,
        'approved_by'  => $gtBmUser
    ],
    [
        'branch_id'    => 1,
        'category_id'  => 3, // Supplies
        'amount_cents' => 1250000, // ₱12,500.00
        'expense_date' => date('Y-m-10'),
        'payee'        => 'Cavite Dairy & Specialty Wholesalers',
        'notes'        => 'Barista fresh milk, oat milk, artisanal syrups and coffee filters.',
        'status'       => 'APPROVED',
        'created_by'   => $gtBaUser,
        'approved_by'  => $gtBmUser
    ],
    [
        'branch_id'    => 1,
        'category_id'  => 2, // Utilities
        'amount_cents' => 875000, // ₱8,750.00
        'expense_date' => date('Y-m-18'),
        'payee'        => 'Meralco / Maynilad Cavite',
        'notes'        => 'Electricity and water utility billing for coffee bar equipment.',
        'status'       => 'APPROVED',
        'created_by'   => $gtBaUser,
        'approved_by'  => $gtBmUser
    ],
];

$insExpense = $pdo->prepare("
    INSERT INTO expenses (
        branch_id, category_id, amount_cents, expense_date, payee, notes,
        status, created_by, approved_by, created_at, updated_at
    ) VALUES (
        :branch_id, :category_id, :amount_cents, :expense_date, :payee, :notes,
        :status, :created_by, :approved_by, NOW(), NOW()
    )
");

foreach ($expensesData as $exp) {
    $insExpense->execute($exp);
    echo "  + Expense: {$exp['payee']} - ₱" . number_format($exp['amount_cents'] / 100, 2) . " [{$exp['status']}]\n";
}

// 4. Payroll: 1 RELEASED payroll run for General Trias Branch
$gtBhrUser = $createdHumanUsers['ana.reyes']['user_id'] ?? 1;

$insPayrollRun = $pdo->prepare("
    INSERT INTO payroll_runs (
        scope, branch_id, period_start, period_end, status, processed_by, approved_by, created_at, updated_at
    ) VALUES (
        'BRANCH', 1, :p_start, :p_end, 'RELEASED', :processed_by, :approved_by, NOW(), NOW()
    )
");

$periodStart = date('Y-m-01', strtotime('first day of last month'));
$periodEnd   = date('Y-m-15', strtotime('first day of last month'));

$insPayrollRun->execute([
    ':p_start'       => $periodStart,
    ':p_end'         => $periodEnd,
    ':processed_by'  => $gtBhrUser,
    ':approved_by'   => $gtBaUser,
]);
$payrollRunId = (int)$pdo->lastInsertId();

// Add payroll items for General Trias Crew (Miguel, Sofia, Carlo, Bea, Leo)
$gtCrewEmpUsernames = ['miguel.cruz', 'sofia.garcia', 'carlo.mendoza', 'bea.torres', 'leo.flores'];
$insPayrollItem = $pdo->prepare("
    INSERT INTO payroll_items (
        payroll_run_id, employee_id, basic_pay, overtime_pay, bonus_pay, gross_pay,
        sss_deduction, philhealth_deduction, pagibig_deduction,
        employer_sss, employer_philhealth, employer_pagibig, tax_deduction, net_pay, created_at
    ) VALUES (
        :run_id, :emp_id, :basic, 0.00, 0.00, :gross,
        :sss, :ph, :pagibig,
        :esss, :eph, :epagibig, 0.00, :net, NOW()
    )
");

foreach ($gtCrewEmpUsernames as $uName) {
    if (!isset($createdHumanUsers[$uName])) continue;
    $empId = $createdHumanUsers[$uName]['employee_id'];
    $basic = 10000.00; // Half-month basic
    $sss = 450.00;
    $ph = 250.00;
    $pagibig = 100.00;
    $net = $basic - ($sss + $ph + $pagibig);

    $insPayrollItem->execute([
        ':run_id'   => $payrollRunId,
        ':emp_id'   => $empId,
        ':basic'    => $basic,
        ':gross'    => $basic,
        ':sss'      => $sss,
        ':ph'       => $ph,
        ':pagibig'  => $pagibig,
        ':esss'     => 950.00,
        ':eph'      => 250.00,
        ':epagibig' => 100.00,
        ':net'      => $net,
    ]);
}
echo "  + Created 1 RELEASED payroll run (#{$payrollRunId}) with 5 employee payroll items for General Trias.\n";
echo "[SUCCESS] Mock transactions generated.\n\n";

echo "====================================================================\n";
echo "DEMO REBUILD COMPLETE!\n";
echo "====================================================================\n";
