<?php

/**
 * Validates payroll approval chain and SOD rules.
 * Workflow: HR Generates (Maker) -> Accountant Approves (Checker 1) -> Branch Manager Releases (Checker 2)
 * Throws Exception if a conflict is detected.
 */
function payroll_conflict(array $user, array $run, string $action): void {
    global $pdo;

    // Fetch user's role name
    $stmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
    $stmt->execute([$user['role_id']]);
    $role = $stmt->fetchColumn();

    $scope = $run['scope'] ?? 'BRANCH';
    
    // Maker-Checker: The user who generated the payroll cannot also approve or release it
    if (in_array($action, ['APPROVE', 'RELEASE'])) {
        if ((int)$run['processed_by'] === (int)$user['id']) {
            throw new Exception("Separation of Duties violation: The user who generated the payroll cannot also {$action} it.");
        }
    }

    // Checker 1 to Checker 2: The user who approved the payroll cannot also release it
    if ($action === 'RELEASE') {
        $approverId = (int)($run['approved_by'] ?? 0);
        if (!$approverId && !empty($run['id'])) {
            $stmt = $pdo->prepare("SELECT user_id FROM audit_logs WHERE action = 'PAYROLL_APPROVED' AND details LIKE ? ORDER BY id DESC LIMIT 1");
            $stmt->execute(["Payroll Run ID " . $run['id'] . "%"]);
            $approverId = (int)$stmt->fetchColumn();
        }
        if ($approverId && (int)$user['id'] === $approverId) {
            throw new Exception("Separation of Duties violation: The user who approved the payroll cannot also release it.");
        }
    }

    // Maker-Checker: Cannot contain own pay
    if (!empty($user['employee_id']) && !empty($run['id']) && in_array($action, ['APPROVE', 'RELEASE'])) {
        $check = $pdo->prepare("SELECT id FROM payroll_items WHERE payroll_run_id = ? AND employee_id = ?");
        $check->execute([$run['id'], $user['employee_id']]);
        if ($check->fetch()) {
            throw new Exception("Maker-Checker violation: You cannot {$action} a payroll run that contains your own pay.");
        }
    }

    // Chain Rules: Strict 3-step workflow
    if ($scope === 'BRANCH') {
        if ($action === 'GENERATE') {
            if (!in_array($role, ['BHR', 'CHR', 'GA'])) {
                throw new Exception("Only BHR (or CHR/GA) can draft a BRANCH payroll.");
            }
        } else if ($action === 'APPROVE') {
            if (!in_array($role, ['BA', 'GA'])) {
                throw new Exception("Only BA (or GA fallback) can approve a BRANCH payroll.");
            }
        } else if ($action === 'RELEASE') {
            if (!in_array($role, ['BM'])) {
                throw new Exception("Only Branch Manager (BM) can release a BRANCH payroll.");
            }
        } else if ($action === 'DELETE') {
            if (!in_array($role, ['BHR', 'CHR', 'GA'])) {
                throw new Exception("Only HR can delete a BRANCH payroll draft.");
            }
        }
    } else if ($scope === 'OFFICIALS') {
        if ($action === 'GENERATE') {
            if (!in_array($role, ['CHR', 'GA'])) {
                throw new Exception("Only CHR (or GA fallback) can draft an OFFICIALS payroll.");
            }
        } else if ($action === 'APPROVE') {
            if (!in_array($role, ['GA', 'BA'])) {
                throw new Exception("Only GA (or BA fallback) can approve an OFFICIALS payroll.");
            }
        } else if ($action === 'RELEASE') {
            if (!in_array($role, ['BM', 'CHR'])) {
                throw new Exception("Only authorized management can release an OFFICIALS payroll.");
            }
        } else if ($action === 'DELETE') {
            if (!in_array($role, ['GA', 'CHR'])) {
                throw new Exception("Only HR or GA can delete an OFFICIALS payroll.");
            }
        }
    } else if ($scope === 'HQ') {
        if ($action === 'GENERATE') {
            if (!in_array($role, ['CHR', 'GA'])) {
                throw new Exception("Only CHR or GA can draft an HQ payroll.");
            }
        } else if ($action === 'APPROVE') {
            if (!in_array($role, ['GA', 'BA'])) {
                throw new Exception("Only GA or BA can approve an HQ payroll.");
            }
        } else if ($action === 'RELEASE') {
            if (!in_array($role, ['BM', 'CHR'])) {
                throw new Exception("Only authorized management can release an HQ payroll.");
            }
        } else if ($action === 'DELETE') {
            if (!in_array($role, ['GA', 'CHR'])) {
                throw new Exception("Only HR or GA can delete an HQ payroll.");
            }
        }
    }
}
