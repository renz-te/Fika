<?php

return [
    // Core administration
    'users.view',
    'users.create',
    'users.edit',
    'users.delete',
    'roles.view',
    'roles.manage',
    'branches.view',
    'branches.manage',

    // POS & Inventory
    'pos.access',
    'pos.void_order',
    'pos.refund_order',
    'inventory.view',
    'inventory.manage',

    // HRMS & Payroll
    'hr.employee.view',
    'hr.employee.create',
    'hr.employee.edit',
    'hr.employee.view_sensitive',
    'hr.attendance.view',
    'hr.attendance.adjust',
    'hr.attendance.certify',
    'hr.leave.view',
    'hr.leave.approve',
    'hr.leave.manage',
    'payroll.view',
    'payroll.generate',
    'payroll.approve',
    'payroll.release',
    'payroll.delete',
    'payroll.export',
    'settings.manage',

    // Finance
    'finance.view',
    'finance.manage',
];
