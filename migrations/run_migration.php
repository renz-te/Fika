<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=hrms;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. Create branches table
    $pdo->exec("CREATE TABLE IF NOT EXISTS branches (
      id INT AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(150) NOT NULL,
      address TEXT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("INSERT IGNORE INTO branches (id, name, address) VALUES (1, 'Main Branch', '123 Coffee Boulevard, Cityville')");

    // 2. Roles
    $pdo->exec("UPDATE users SET role_id = 8 WHERE role_id = 3");
    $pdo->exec("DELETE FROM roles WHERE id = 3");
    $pdo->exec("UPDATE roles SET name = 'Central HR' WHERE name = 'HR'");
    $pdo->exec("INSERT IGNORE INTO roles (name) VALUES ('Global Accountant'), ('Executive')");

    // 3. Inject branch_id
    $tables = ['users', 'employees', 'applicants', 'schedules'];
    foreach ($tables as $table) {
        try {
            $pdo->exec("ALTER TABLE $table ADD COLUMN branch_id INT NULL");
            $pdo->exec("ALTER TABLE $table ADD CONSTRAINT fk_{$table}_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL");
        } catch (PDOException $e) {
            echo "Column might exist for $table: " . $e->getMessage() . "\n";
        }
    }

    // Assign default branch
    $pdo->exec("UPDATE users u JOIN roles r ON u.role_id = r.id SET u.branch_id = 1 WHERE r.name IN ('Barista', 'Head Barista', 'Branch Admin')");
    $pdo->exec("UPDATE employees SET branch_id = 1");
    $pdo->exec("UPDATE applicants SET branch_id = 1");
    $pdo->exec("UPDATE schedules SET branch_id = 1");

    // 4. Inject employment_status
    try {
        $pdo->exec("ALTER TABLE employees ADD COLUMN employment_status ENUM('Trainee', 'Probationary', 'Regular') NOT NULL DEFAULT 'Regular'");
    } catch (PDOException $e) {
        echo "Column might exist for employment_status: " . $e->getMessage() . "\n";
    }

    $pdo->exec("UPDATE employees SET employment_status = 'Trainee' WHERE status = 'Trainee'");
    $pdo->exec("UPDATE employees SET employment_status = 'Probationary' WHERE status = 'Probationary'");
    $pdo->exec("UPDATE employees SET employment_status = 'Regular' WHERE status IN ('Active', 'Regular')");
    
    $pdo->exec("UPDATE employees SET status = 'Active' WHERE status NOT IN ('Archived')");

    echo "Migration Complete!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
