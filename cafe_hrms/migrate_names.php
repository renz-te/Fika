<?php
require_once __DIR__ . '/init.php';

try {
    // 1. Employees table
    $pdo->exec("ALTER TABLE employees CHANGE full_name first_name VARCHAR(150)");
    $pdo->exec("ALTER TABLE employees ADD last_name VARCHAR(150) AFTER first_name");
    
    // Migrate data for employees
    $emps = $pdo->query("SELECT id, first_name FROM employees")->fetchAll();
    foreach ($emps as $emp) {
        $parts = explode(' ', $emp['first_name'], 2);
        $first = $parts[0];
        $last = isset($parts[1]) ? $parts[1] : '';
        $stmt = $pdo->prepare("UPDATE employees SET first_name = ?, last_name = ? WHERE id = ?");
        $stmt->execute([$first, $last, $emp['id']]);
    }
    echo "Employees table updated.\n";

    // 2. Applicants table
    $pdo->exec("ALTER TABLE applicants CHANGE full_name first_name VARCHAR(150)");
    $pdo->exec("ALTER TABLE applicants ADD last_name VARCHAR(150) AFTER first_name");
    
    // Migrate data for applicants
    $apps = $pdo->query("SELECT id, first_name FROM applicants")->fetchAll();
    foreach ($apps as $app) {
        $parts = explode(' ', $app['first_name'], 2);
        $first = $parts[0];
        $last = isset($parts[1]) ? $parts[1] : '';
        $stmt = $pdo->prepare("UPDATE applicants SET first_name = ?, last_name = ? WHERE id = ?");
        $stmt->execute([$first, $last, $app['id']]);
    }
    echo "Applicants table updated.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
