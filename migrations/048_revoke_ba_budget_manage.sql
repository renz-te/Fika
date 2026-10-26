-- Migration 048: Revoke budget allocation permission from Role 4 (Branch Accountant)
-- GA (Role 3) sets the budget for branches, BA (Role 4) manages expenses against it.

DELETE FROM role_permissions WHERE role_id = 4 AND permission_name = 'finance.budget.manage';
