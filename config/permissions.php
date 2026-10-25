<?php

return [
    // Core administration
    'users.view',
    'users.create',
    'users.edit',
    'users.delete',
    'users.manage',
    'user.manage',
    'devices.view',
    'devices.manage',
    'roles.view',
    'roles.manage',
    'branches.view',
    'branches.manage',

    // POS & Inventory
    'pos.access',
    'pos.menu.view',
    'pos.menu.manage',
    'pos.void_order',
    'pos.refund_order',
    'inventory.view',
    'inventory.manage',
    'inventory.adjust',
    'inventory.approve',

    // Procurement
    'procurement.view',
    'procurement.create',
    'procurement.approve',
    'procurement.receive',

    // HRMS & Payroll
    'hr.employee.view',
    'hr.employee.create',
    'hr.employee.edit',
    'hr.employee.view_sensitive',
    'hr.attendance.view',
    'hr.attendance.adjust',
    'hr.attendance.certify',
    'attendance.view',
    'attendance.adjust',
    'hr.leave.view',
    'hr.leave.approve',
    'hr.leave.manage',
    'leave.view',
    'leave.approve',
    'payroll.view',
    'payroll.generate',
    'payroll.approve',
    'payroll.release',
    'payroll.delete',
    'payroll.export',
    'schedule.view',
    'schedule.manage',
    'schedule.claim',
    'applicant.view',
    'settings.manage',

    // Finance
    'finance.view',
    'finance.manage',
    'finance.expense.create',
    'finance.expense.approve',
    'finance.budget.manage',
];
