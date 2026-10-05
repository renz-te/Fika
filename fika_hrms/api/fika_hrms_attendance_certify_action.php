<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('hr.attendance.certify');

$input = json_decode(file_get_contents('php://input'), true);
$scope = trim($input['scope'] ?? '');
$branchId = !empty($input['branch_id']) ? (int)$input['branch_id'] : null;
$employeeId = !empty($input['employee_id']) ? (int)$input['employee_id'] : null;
$periodStart = trim($input['period_start'] ?? '');
$periodEnd = trim($input['period_end'] ?? '');
$action = trim($input['action'] ?? 'certify');
$user = Auth::user();

if (!in_array($scope, ['BRANCH', 'OFFICIALS', 'HQ']) || empty($periodStart) || empty($periodEnd) || !in_array($action, ['certify', 'reopen'])) {
    http_response_code(400);
    exit(json_encode(["error" => "Valid scope, period_start, period_end, and action are required."]));
}

if ($scope === 'BRANCH' && !$branchId) {
    http_response_code(400);
    exit(json_encode(["error" => "Branch ID is required for BRANCH scope."]));
}

if ($scope === 'HQ' && !$employeeId) {
    http_response_code(400);
    exit(json_encode(["error" => "Employee ID is required for HQ scope."]));
}

function getScopeCondition(string $scope): string {
    switch ($scope) {
        case 'BRANCH': 
            return "e.id NOT IN (SELECT employee_id FROM users WHERE employee_id IS NOT NULL)";
        case 'OFFICIALS':
            return "e.id IN (SELECT employee_id FROM users WHERE employee_id IS NOT NULL AND branch_id IS NOT NULL)";
        case 'HQ':
            return "e.id IN (SELECT employee_id FROM users WHERE employee_id IS NOT NULL AND branch_id IS NULL)";
    }
    return "1=0";
}

global $pdo;

try {
    $pdo->beginTransaction();
    
    if ($scope === 'BRANCH') {
        Rbac::assert_branch_access($branchId);
    }
    
    // Maker-Checker: Check if certifier belongs to the scope being certified
    if ($user['employee_id']) {
        $scopeCond = getScopeCondition($scope);
        $checkSql = "SELECT 1 FROM employees e WHERE e.id = ? AND $scopeCond";
        $checkParams = [$user['employee_id']];
        
        if ($scope === 'BRANCH') {
            $checkSql .= " AND e.branch_id = ?";
            $checkParams[] = $branchId;
        } elseif ($scope === 'HQ') {
            $checkSql .= " AND e.id = ?";
            $checkParams[] = $employeeId;
        }
        
        $stmt = $pdo->prepare($checkSql);
        $stmt->execute($checkParams);
        if ($stmt->fetch()) {
            throw new Exception("Conflict of interest: You cannot certify a run that contains your own attendance.");
        }
    }
    
    // Check if already certified
    $chkStmt = $pdo->prepare("
        SELECT id, status FROM attendance_certifications 
        WHERE scope = ? AND IFNULL(branch_id, 0) = ? AND IFNULL(employee_id, 0) = ?
        AND period_start = ? AND period_end = ?
        ORDER BY id DESC LIMIT 1
    ");
    $chkStmt->execute([$scope, $branchId ?? 0, $employeeId ?? 0, $periodStart, $periodEnd]);
    $cert = $chkStmt->fetch();
    
    if ($action === 'certify') {
        if ($cert && $cert['status'] === 'CERTIFIED') {
            throw new Exception("This period is already certified for the selected scope.");
        }
        
        // Insert certification
        $stmt = $pdo->prepare("
            INSERT INTO attendance_certifications (scope, branch_id, employee_id, period_start, period_end, certified_by, status)
            VALUES (?, ?, ?, ?, ?, ?, 'CERTIFIED')
        ");
        $stmt->execute([$scope, $branchId, $employeeId, $periodStart, $periodEnd, $user['id']]);
        $certId = $pdo->lastInsertId();
        
        Audit::log('ATTENDANCE_CERTIFIED', "User {$user['id']} certified attendance for Scope={$scope}, Branch={$branchId}, Emp={$employeeId} from {$periodStart} to {$periodEnd}");
        
        $pdo->commit();
        echo json_encode(["success" => true, "message" => "Attendance certified successfully.", "cert_id" => $certId]);
    } else { // reopen
        if (!$cert || $cert['status'] !== 'CERTIFIED') {
            throw new Exception("This period is not currently certified.");
        }
        $reason = trim($input['reason'] ?? '');
        if (empty($reason)) {
            throw new Exception("Reason is required to reopen certification.");
        }
        
        $upd = $pdo->prepare("UPDATE attendance_certifications SET status = 'REOPENED' WHERE id = ?");
        $upd->execute([$cert['id']]);
        
        Audit::log('ATTENDANCE_REOPENED', "User {$user['id']} reopened certification {$cert['id']}. Reason: {$reason}");
        
        $pdo->commit();
        echo json_encode(["success" => true, "message" => "Attendance certification reopened successfully."]);
    }
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    exit(json_encode(["error" => $e->getMessage()]));
}
