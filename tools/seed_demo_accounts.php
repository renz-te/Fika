<?php
/**
 * tools/seed_demo_accounts.php
 *
 * Seeds role-based demo accounts and their linked employee records.
 *
 * Usage:
 *   php tools/seed_demo_accounts.php
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

require_once __DIR__ . '/../app/bootstrap.php';

global $pdo;

echo "====================================================================\n";
echo "Fika Café — Role-Based Demo Account Seeder\n";
echo "====================================================================\n\n";

// 1. Verify branches 1 and 2 exist; create if missing
echo "[1/3] Checking branches ID 1 and 2...\n";

$branchCheck = $pdo->prepare("SELECT id, name FROM branches WHERE id = ?");

// Branch 1
$branchCheck->execute([1]);
$branch1 = $branchCheck->fetch(PDO::FETCH_ASSOC);
if (!$branch1) {
    $ins = $pdo->prepare("INSERT INTO branches (id, name, address, contact_number, status) VALUES (1, 'Fika Makati', 'Ayala Ave, Makati City', '0917-100-0001', 'Active')");
    $ins->execute();
    echo "  + Created Branch 1: Fika Makati\n";
} else {
    echo "  * Branch 1 exists: {$branch1['name']}\n";
}

// Branch 2
$branchCheck->execute([2]);
$branch2 = $branchCheck->fetch(PDO::FETCH_ASSOC);
if (!$branch2) {
    $ins = $pdo->prepare("INSERT INTO branches (id, name, address, contact_number, status) VALUES (2, 'Fika BGC', 'High Street, BGC, Taguig', '0917-100-0002', 'Active')");
    $ins->execute();
    echo "  + Created Branch 2: Fika BGC\n";
} else {
    echo "  * Branch 2 exists: {$branch2['name']}\n";
}

// 2. Define Demo Accounts
$passwordPlain = 'Password123!';
$passwordHash = password_hash($passwordPlain, PASSWORD_DEFAULT);

$demoAccounts = [
    [
        'username'      => 'chr_user',
        'role_id'       => 2,
        'role_name'     => 'Central HR (CHR)',
        'branch_id'     => null,
        'branch_label'  => 'Global (HQ)',
        'first_name'    => 'Central',
        'last_name'     => 'HR',
        'email'         => 'chr_user@fika.local',
        'employee_code' => 'EMP-CHR-001',
        'position'      => 'Central HR',
        'department'    => 'Human Resources',
        'staff_class'   => 'HQ',
    ],
    [
        'username'      => 'ga_user',
        'role_id'       => 3,
        'role_name'     => 'Global Accountant (GA)',
        'branch_id'     => null,
        'branch_label'  => 'Global (HQ)',
        'first_name'    => 'Global',
        'last_name'     => 'Accountant',
        'email'         => 'ga_user@fika.local',
        'employee_code' => 'EMP-GA-001',
        'position'      => 'Global Accountant',
        'department'    => 'Finance',
        'staff_class'   => 'HQ',
    ],
    [
        'username'      => 'bm_makati',
        'role_id'       => 6,
        'role_name'     => 'Branch Manager (BM)',
        'branch_id'     => 1,
        'branch_label'  => 'Branch 1 (Makati)',
        'first_name'    => 'Maria',
        'last_name'     => 'Manager',
        'email'         => 'bm_makati@fika.local',
        'employee_code' => 'EMP-BM-001',
        'position'      => 'Branch Manager',
        'department'    => 'Operations',
        'staff_class'   => 'OFFICIAL',
    ],
    [
        'username'      => 'bhr_makati',
        'role_id'       => 5,
        'role_name'     => 'Branch HR (BHR)',
        'branch_id'     => 1,
        'branch_label'  => 'Branch 1 (Makati)',
        'first_name'    => 'Pedro',
        'last_name'     => 'HR',
        'email'         => 'bhr_makati@fika.local',
        'employee_code' => 'EMP-BHR-001',
        'position'      => 'Branch HR',
        'department'    => 'Human Resources',
        'staff_class'   => 'OFFICIAL',
    ],
    [
        'username'      => 'ba_makati',
        'role_id'       => 4,
        'role_name'     => 'Branch Accountant (BA)',
        'branch_id'     => 1,
        'branch_label'  => 'Branch 1 (Makati)',
        'first_name'    => 'Juan',
        'last_name'     => 'Accountant',
        'email'         => 'ba_makati@fika.local',
        'employee_code' => 'EMP-BA-001',
        'position'      => 'Branch Accountant',
        'department'    => 'Finance',
        'staff_class'   => 'OFFICIAL',
    ],
];

echo "\n[2/3] Seeding employees and linked user accounts...\n";

$results = [];

foreach ($demoAccounts as $acc) {
    // A. Check or Insert Employee
    $empStmt = $pdo->prepare("SELECT id FROM employees WHERE email = ? OR employee_code = ? LIMIT 1");
    $empStmt->execute([$acc['email'], $acc['employee_code']]);
    $empId = $empStmt->fetchColumn();

    if (!$empId) {
        $insEmp = $pdo->prepare("
            INSERT INTO employees (
                employee_code, branch_id, first_name, last_name, email,
                position, department, employment_type, status, staff_class,
                basic_salary, hourly_rate, date_hired
            ) VALUES (
                :employee_code, :branch_id, :first_name, :last_name, :email,
                :position, :department, 'REGULAR', 'ACTIVE', :staff_class,
                45000.00, 260.00, CURDATE()
            )
        ");
        $insEmp->execute([
            ':employee_code' => $acc['employee_code'],
            ':branch_id'     => $acc['branch_id'],
            ':first_name'    => $acc['first_name'],
            ':last_name'     => $acc['last_name'],
            ':email'         => $acc['email'],
            ':position'      => $acc['position'],
            ':department'    => $acc['department'],
            ':staff_class'   => $acc['staff_class'],
        ]);
        $empId = (int)$pdo->lastInsertId();
        echo "  + Inserted employee: {$acc['first_name']} {$acc['last_name']} (ID: {$empId})\n";
    } else {
        $empId = (int)$empId;
        $updEmp = $pdo->prepare("
            UPDATE employees
            SET branch_id = :branch_id, first_name = :first_name, last_name = :last_name,
                position = :position, department = :department, status = 'ACTIVE',
                deleted_at = NULL
            WHERE id = :id
        ");
        $updEmp->execute([
            ':branch_id'  => $acc['branch_id'],
            ':first_name' => $acc['first_name'],
            ':last_name'  => $acc['last_name'],
            ':position'   => $acc['position'],
            ':department' => $acc['department'],
            ':id'         => $empId,
        ]);
        echo "  * Updated employee: {$acc['first_name']} {$acc['last_name']} (ID: {$empId})\n";
    }

    // B. Check or Insert User
    $uStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $uStmt->execute([$acc['username']]);
    $userId = $uStmt->fetchColumn();

    if (!$userId) {
        $insUser = $pdo->prepare("
            INSERT INTO users (username, password, email, role_id, employee_id, branch_id)
            VALUES (:username, :password, :email, :role_id, :employee_id, :branch_id)
        ");
        $insUser->execute([
            ':username'    => $acc['username'],
            ':password'    => $passwordHash,
            ':email'       => $acc['email'],
            ':role_id'     => $acc['role_id'],
            ':employee_id' => $empId,
            ':branch_id'   => $acc['branch_id'],
        ]);
        $userId = (int)$pdo->lastInsertId();
        echo "  + Inserted user: {$acc['username']} (ID: {$userId}, Role: {$acc['role_id']})\n";
    } else {
        $userId = (int)$userId;
        $updUser = $pdo->prepare("
            UPDATE users
            SET password = :password, email = :email, role_id = :role_id,
                employee_id = :employee_id, branch_id = :branch_id, deleted_at = NULL
            WHERE id = :id
        ");
        $updUser->execute([
            ':password'    => $passwordHash,
            ':email'       => $acc['email'],
            ':role_id'     => $acc['role_id'],
            ':employee_id' => $empId,
            ':branch_id'   => $acc['branch_id'],
            ':id'          => $userId,
        ]);
        echo "  * Updated user: {$acc['username']} (ID: {$userId}, Role: {$acc['role_id']})\n";
    }

    $results[] = [
        'username'        => $acc['username'],
        'role'            => $acc['role_name'],
        'branch'          => $acc['branch_label'],
        'linked_employee' => "{$acc['first_name']} {$acc['last_name']}",
        'password'        => $passwordPlain,
    ];
}

// 3. Print Summary Table
echo "\n[3/3] Demo Accounts Table:\n\n";

$colUsername = 12;
$colRole     = 28;
$colBranch   = 20;
$colEmployee = 20;
$colPassword = 14;

$line = '+' . str_repeat('-', $colUsername + 2)
      . '+' . str_repeat('-', $colRole + 2)
      . '+' . str_repeat('-', $colBranch + 2)
      . '+' . str_repeat('-', $colEmployee + 2)
      . '+' . str_repeat('-', $colPassword + 2)
      . '+';

echo $line . "\n";
printf(
    "| %-{$colUsername}s | %-{$colRole}s | %-{$colBranch}s | %-{$colEmployee}s | %-{$colPassword}s |\n",
    'Username', 'Role', 'Branch', 'Linked Employee', 'Password'
);
echo $line . "\n";

foreach ($results as $r) {
    printf(
        "| %-{$colUsername}s | %-{$colRole}s | %-{$colBranch}s | %-{$colEmployee}s | %-{$colPassword}s |\n",
        $r['username'],
        $r['role'],
        $r['branch'],
        $r['linked_employee'],
        $r['password']
    );
}
echo $line . "\n\n";
echo "Demo account seeding completed successfully.\n";
