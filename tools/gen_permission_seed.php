<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

$permissions = require __DIR__ . '/../config/permissions.php';

$roles = [
    'Super Admin' => $permissions, // all permissions
    'Admin' => array_diff($permissions, ['roles.manage', 'branches.manage']),
    'Branch Manager' => [
        'pos.access', 'pos.void_order', 'pos.refund_order',
        'inventory.view', 'inventory.manage',
        'employees.view', 'attendance.view', 'attendance.manage'
    ],
    'Cashier' => ['pos.access'],
    'Barista' => ['inventory.view'],
    'HR' => [
        'employees.view', 'employees.manage',
        'attendance.view', 'attendance.manage',
        'payroll.view', 'payroll.manage'
    ]
];

$sql = "-- ==========================================================\n";
$sql .= "-- Migration: 010_seed_roles_permissions.sql\n";
$sql .= "-- Description: Insert default roles and permissions\n";
$sql .= "-- ==========================================================\n\n";

$sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

$sql .= "-- Clear existing (if needed for idempotency)\n";
$sql .= "TRUNCATE TABLE `role_permissions`;\n";
$sql .= "DELETE FROM `roles`;\n";
$sql .= "ALTER TABLE `roles` AUTO_INCREMENT = 1;\n\n";

$roleId = 1;
foreach ($roles as $roleName => $rolePerms) {
    $sql .= "-- Role: {$roleName}\n";
    $sql .= "INSERT INTO `roles` (`id`, `name`, `description`) VALUES ({$roleId}, '{$roleName}', 'Default {$roleName} role');\n";
    
    if (!empty($rolePerms)) {
        $sql .= "INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES\n";
        $values = [];
        foreach ($rolePerms as $perm) {
            $values[] = "({$roleId}, '{$perm}')";
        }
        $sql .= implode(",\n", $values) . ";\n";
    }
    $sql .= "\n";
    $roleId++;
}

$sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

$outPath = __DIR__ . '/../migrations/010_seed_roles_permissions.sql';
file_put_contents($outPath, $sql);

echo "Seed SQL generated at: {$outPath}\n";
