-- ==========================================================
-- Migration: 041_branch_hr_permissions.sql
-- Description: Grant operational HR permissions to Branch HR (Role 5)
--              and budget management to Branch Accountant (Role 4)
-- ==========================================================

-- Role 5: Branch HR (BHR) Permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(5, 'hr.employee.view'),
(5, 'hr.employee.create'),
(5, 'hr.employee.edit'),
(5, 'hr.attendance.view'),
(5, 'hr.attendance.adjust'),
(5, 'attendance.view'),
(5, 'attendance.adjust'),
(5, 'hr.leave.view'),
(5, 'hr.leave.approve'),
(5, 'hr.leave.manage'),
(5, 'leave.view'),
(5, 'leave.approve'),
(5, 'payroll.view'),
(5, 'payroll.generate'),
(5, 'schedule.view'),
(5, 'schedule.manage'),
(5, 'pos.menu.view'),
(5, 'applicant.view');

-- Role 4: Branch Accountant (BA) Permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(4, 'finance.view'),
(4, 'finance.expense.create'),
(4, 'finance.expense.approve'),
(4, 'finance.budget.manage');
