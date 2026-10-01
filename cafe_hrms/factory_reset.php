<?php
require_once __DIR__ . '/init.php';

try {
    // Disable foreign key checks for truncation
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    // TRUNCATE TABLES
    $tables = [
        'schedules', 'open_shifts', 'attendance', 'leaves', 
        'payroll', 'applicant_logs', 'applicants', 'employees'
    ];
    
    foreach ($tables as $t) {
        $pdo->exec("TRUNCATE TABLE $t");
    }
    
    // DELETE all users except Super Admin (ID 1 usually)
    $pdo->exec("DELETE FROM users WHERE id != 1");
    
    // Add username column if not exists
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN username VARCHAR(100) NULL AFTER name");
    } catch(Exception $e) {}
    
    // Update Super Admin username
    $pdo->exec("UPDATE users SET username = 'admin' WHERE id = 1");
    
    // Ensure Roles exist
    $roles = ['Employee', 'HR', 'Head Barista', 'Barista']; // Let's make sure HR role exists
    $roleIds = [];
    foreach($roles as $r) {
        $stmt = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
        $stmt->execute([$r]);
        $rId = $stmt->fetchColumn();
        if (!$rId) {
            $pdo->prepare('INSERT INTO roles (name) VALUES (?)')->execute([$r]);
            $rId = $pdo->lastInsertId();
        }
        $roleIds[$r] = $rId;
    }

    $staffData = [
        // 5 Head Baristas (FT)
        ['Juan Dela Cruz', 'Head Barista', 'Full-Time'],
        ['Maria Clara', 'Head Barista', 'Full-Time'],
        ['Jose Rizal', 'Head Barista', 'Full-Time'],
        ['Andres Bonifacio', 'Head Barista', 'Full-Time'],
        ['Emilio Aguinaldo', 'Head Barista', 'Full-Time'],
        // 5 FT Baristas
        ['Apolinario Mabini', 'Barista', 'Full-Time'],
        ['Marcelo Del Pilar', 'Barista', 'Full-Time'],
        ['Gabriela Silang', 'Barista', 'Full-Time'],
        ['Melchora Aquino', 'Barista', 'Full-Time'],
        ['Antonio Luna', 'Barista', 'Full-Time'],
        // 3 FT Trainees
        ['Lapu Lapu', 'Barista', 'Full-Time'],
        ['Diego Silang', 'Barista', 'Full-Time'],
        ['Gregorio DelPilar', 'Barista', 'Full-Time'],
        // 2 PT Baristas
        ['Emilio Jacinto', 'Barista', 'Part-Time'],
        ['Miguel Malvar', 'Barista', 'Part-Time'],
        // 3 PT Trainees
        ['Macario Sakay', 'Barista', 'Part-Time'],
        ['Teresa Magbanua', 'Barista', 'Part-Time'],
        ['Trinidad Tecson', 'Barista', 'Part-Time'],
        // 4 HR
        ['Manuel Quezon', 'HR', 'Full-Time'],
        ['Sergio Osmena', 'HR', 'Full-Time'],
        ['Jose Laurel', 'HR', 'Full-Time'],
        ['Ramon Magsaysay', 'HR', 'Full-Time'],
    ];

    $passHash = password_hash('password123', PASSWORD_DEFAULT);

    foreach ($staffData as $index => $data) {
        $fullName = $data[0];
        $position = $data[1];
        $category = $data[2];
        
        $isTrainee = false;
        if (in_array($fullName, ['Lapu Lapu', 'Diego Silang', 'Gregorio DelPilar', 'Macario Sakay', 'Teresa Magbanua', 'Trinidad Tecson'])) {
            $isTrainee = true;
        }
        
        $salary = ($category === 'Full-Time') ? 20000 : 100;
        $email = strtolower(str_replace(' ', '', $fullName)) . '@cafehrms.local';
        $username = strtolower(str_replace(' ', '.', $fullName));
        $empCode = 'EMP-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT);
        
        $status = $isTrainee ? 'Trainee' : 'Active';
        
        if ($isTrainee) {
            $daysAgo = rand(5, 25);
            $randomHiredDate = date('Y-m-d', strtotime("-$daysAgo days"));
        } else {
            $daysAgo = rand(85, 95);
            $randomHiredDate = date('Y-m-d', strtotime("-$daysAgo days"));
        }
        
        $nameParts = explode(' ', $fullName, 2);
        $firstName = $nameParts[0];
        $lastName = isset($nameParts[1]) ? $nameParts[1] : '';
        
        $pdo->prepare("INSERT INTO employees (employee_id, first_name, last_name, email, phone, position, employment_category, hourly_rate, status, date_hired) VALUES (?, ?, ?, ?, '09123456789', ?, ?, ?, ?, ?)")
            ->execute([$empCode, $firstName, $lastName, $email, $position, $category, $salary, $status, $randomHiredDate]);
            
        $empId = $pdo->lastInsertId();
        
        $roleName = ($position === 'HR') ? 'HR' : 'Employee';
        $rId = $roleIds[$roleName];
        
        $pdo->prepare("INSERT INTO users (name, username, email, password, role_id, verified, employee_id, created_at) VALUES (?, ?, ?, ?, ?, 1, ?, NOW())")
            ->execute([$fullName, $username, $email, $passHash, $rId, $empId]);
    }
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    echo "Factory Reset Complete! Inserted 22 Staff members.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
