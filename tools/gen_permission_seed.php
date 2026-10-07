<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

$permissions = require __DIR__ . '/../config/permissions.php';

$roles = [
    'ADMIN' => $permissions,
    'CHR' => [
        'users.view', 'roles.view', 'branches.view', 'settings.manage',
        'hr.employee.view', 'hr.employee.create', 'hr.employee.edit', 'hr.employee.view_sensitive',
        'hr.attendance.view', 'hr.attendance.adjust', 'hr.attendance.certify',
        'hr.leave.view', 'hr.leave.manage', 'hr.leave.approve',
        'payroll.view', 'payroll.generate', 'payroll.delete', 'payroll.export'
    ],
    'GA' => [
        'branches.view', 'payroll.view', 'payroll.export', 'payroll.release',
        'finance.view', 'finance.manage',
        'payroll.generate', 'payroll.delete', 'hr.attendance.certify', 'payroll.approve', 'hr.employee.view_sensitive'
    ],
    'BA' => [
        'branches.view', 'payroll.view', 'payroll.approve', 'payroll.release', 'payroll.export', 'finance.view'
    ],
    'BHR' => [
        'users.view', 'branches.view',
        'hr.employee.view', 'hr.employee.create', 'hr.employee.edit', 'hr.employee.view_sensitive',
        'hr.attendance.view', 'hr.attendance.adjust',
        'hr.leave.view', 'hr.leave.manage',
        'payroll.view', 'payroll.generate', 'payroll.delete'
    ],
    'BM' => [
        'users.view', 'branches.view',
        'pos.access', 'pos.void_order', 'pos.refund_order',
        'inventory.view', 'inventory.manage',
        'hr.employee.view',
        'hr.attendance.view', 'hr.attendance.adjust', 'hr.attendance.certify',
        'hr.leave.view', 'hr.leave.approve',
        'payroll.view', 'finance.view'
    ],
    'STAFF' => [
        'pos.access', 'inventory.view'
    ]
];

$sql = "-- ==========================================================\n";
$sql .= "-- Migration: 014_seed_roles_permissions.sql\n";
$sql .= "-- Description: Insert actual PAM roles and permissions\n";
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

$outPath = __DIR__ . '/../migrations/014_seed_roles_permissions.sql';
file_put_contents($outPath, $sql);

echo "Seed SQL generated at: {$outPath}\n";
