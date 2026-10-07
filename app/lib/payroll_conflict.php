<?php

/**
 * Validates payroll approval chain and SOD rules.
 * Throws Exception if a conflict is detected.
 */
function payroll_conflict(array $user, array $run, string $action): void {
    global $pdo;

    // Fetch user's role name
    $stmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
    $stmt->execute([$user['role_id']]);
    $role = $stmt->fetchColumn();

    $scope = $run['scope'];
    
    // Maker-Checker: Cannot approve/release if they generated it
    if (in_array($action, ['APPROVE', 'RELEASE'])) {
        if ((int)$run['processed_by'] === (int)$user['id']) {
            throw new Exception("Separation of Duties violation: The user who generated the payroll cannot also {$action} it.");
        }
    }

    // Maker-Checker: Cannot contain own pay (except maybe delete? delete is fine. But generate/approve/release should not contain own pay)
    // Wait, generate already checks "not my own pay" inside the generator, but we can do it here too if run has an ID.
    if ($user['employee_id'] && !empty($run['id']) && in_array($action, ['APPROVE', 'RELEASE'])) {
        $check = $pdo->prepare("SELECT id FROM payroll_items WHERE payroll_run_id = ? AND employee_id = ?");
        $check->execute([$run['id'], $user['employee_id']]);
        if ($check->fetch()) {
            throw new Exception("Maker-Checker violation: You cannot {$action} a payroll run that contains your own pay.");
        }
    }

    // Chain Rules
    if ($scope === 'BRANCH') {
        if ($action === 'GENERATE') {
            if (!in_array($role, ['BHR', 'GA', 'ADMIN'])) {
                throw new Exception("Only BHR (or GA/ADMIN fallback) can draft a BRANCH payroll.");
            }
        } else if ($action === 'APPROVE') {
            if (!in_array($role, ['BA', 'GA', 'ADMIN'])) {
                throw new Exception("Only BA (or GA/ADMIN fallback) can approve a BRANCH payroll.");
            }
        } else if (in_array($action, ['RELEASE', 'DELETE'])) {
            if (!in_array($role, ['BA', 'GA', 'ADMIN'])) {
                throw new Exception("Only BA (or GA/ADMIN fallback) can {$action} a BRANCH payroll.");
            }
        }
    } else if ($scope === 'OFFICIALS') {
        if ($action === 'GENERATE') {
            if (!in_array($role, ['CHR', 'ADMIN'])) {
                throw new Exception("Only CHR (or ADMIN fallback) can draft an OFFICIALS payroll.");
            }
        } else if ($action === 'APPROVE') {
            if (!in_array($role, ['GA', 'ADMIN'])) {
                throw new Exception("Only GA (or ADMIN fallback) can approve an OFFICIALS payroll.");
            }
        } else if (in_array($action, ['RELEASE', 'DELETE'])) {
            if (!in_array($role, ['GA', 'ADMIN'])) {
                throw new Exception("Only GA (or ADMIN fallback) can {$action} an OFFICIALS payroll.");
            }
        }
    } else if ($scope === 'HQ') {
        if ($action === 'GENERATE') {
            if (!in_array($role, ['CHR', 'GA', 'ADMIN'])) {
                throw new Exception("Only CHR/GA/ADMIN can draft an HQ payroll.");
            }
        } else if ($action === 'APPROVE') {
            if ($role !== 'ADMIN') {
                throw new Exception("Only ADMIN can approve an HQ payroll.");
            }
        } else if (in_array($action, ['RELEASE', 'DELETE'])) {
            if ($role !== 'ADMIN') {
                throw new Exception("Only ADMIN can {$action} an HQ payroll.");
            }
        }
    }
}
