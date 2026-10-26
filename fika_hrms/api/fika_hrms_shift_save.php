<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
Rbac::require_permission('schedule.view');

$raw = file_get_contents('php://input');
if (empty($raw) && !empty($GLOBALS['RAW_INPUT'])) {
    $raw = $GLOBALS['RAW_INPUT'];
}
$json = json_decode($raw, true);
$input = is_array($json) ? $json : $_POST;

if (empty($_SERVER['HTTP_X_CSRF_TOKEN']) && empty($_POST['csrf_token']) && !empty($input['csrf_token'])) {
    $_POST['csrf_token'] = $input['csrf_token'];
}
Csrf::requireValid();

global $pdo;

$action = $input['action'] ?? '';
$user = Auth::user();
$userId = (int)($user['id'] ?? 0);
$userRole = (int)($user['role_id'] ?? 0);

$roleStmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
$roleStmt->execute([$userRole]);
$userRoleName = (string)$roleStmt->fetchColumn();

if ($action === 'create') {
    Rbac::require_permission('schedule.manage');

    $isBM = ($userRole === 6) || in_array($userRoleName, ['BM', 'Branch Manager'], true);
    if ($isBM || !in_array($userRole, [1, 5], true)) {
        http_response_code(403);
        exit(json_encode([
            'success' => false,
            'error' => 'Only Branch HR can create shifts.',
            'message' => 'Only Branch HR can create shifts.'
        ]));
    }

    $branchId = (int)($input['branch_id'] ?? ($user['branch_id'] ?? 0));
    $employeeId = (int)($input['employee_id'] ?? 0);
    $shiftDate = trim((string)($input['shift_date'] ?? ''));
    $startTime = trim((string)($input['start_time'] ?? ''));
    $endTime = trim((string)($input['end_time'] ?? ''));
    $shiftType = trim((string)($input['shift_type'] ?? 'Regular'));
    // In SoD, newly created shifts are always DRAFT
    $status = 'DRAFT';

    if (!$branchId || !$employeeId || empty($shiftDate) || empty($startTime) || empty($endTime)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Branch, Employee, Date, and Times are required.']));
    }

    // Branch authorization check
    Rbac::assert_branch_access($branchId);

    // Verify employee belongs to branch
    $empStmt = $pdo->prepare("SELECT id, branch_id FROM employees WHERE id = ? AND deleted_at IS NULL");
    $empStmt->execute([$employeeId]);
    $emp = $empStmt->fetch();
    if (!$emp) {
        http_response_code(404);
        exit(json_encode(['error' => 'Employee not found.']));
    }
    if ($emp['branch_id'] != $branchId) {
        http_response_code(403);
        exit(json_encode(['error' => 'Employee does not belong to this branch.']));
    }

    // Constraint 1 (CRITICAL): Leave Conflict Validation
    $leaveStmt = $pdo->prepare("
        SELECT id FROM leave_requests 
        WHERE employee_id = ? 
          AND status = 'APPROVED'
          AND date_from <= ? AND date_to >= ?
        LIMIT 1
    ");
    $leaveStmt->execute([$employeeId, $shiftDate, $shiftDate]);
    if ($leaveStmt->fetch()) {
        http_response_code(400);
        exit(json_encode(['error' => 'Employee is on approved leave during this date.']));
    }

    // Constraint 4: Overlap Validation
    $overlapStmt = $pdo->prepare("
        SELECT id FROM shifts 
        WHERE shift_date = ? 
          AND employee_id = ?
          AND (start_time < ? AND end_time > ?)
        LIMIT 1
    ");
    $overlapStmt->execute([$shiftDate, $employeeId, $endTime, $startTime]);
    if ($overlapStmt->fetch()) {
        http_response_code(400);
        exit(json_encode(['error' => 'Overlapping shift exists.']));
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO shifts (branch_id, employee_id, shift_date, start_time, end_time, shift_type, status, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $insertStmt->execute([$branchId, $employeeId, $shiftDate, $startTime, $endTime, $shiftType, $status, $userId]);
    $shiftId = (int)$pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Shift created successfully as DRAFT.',
        'shift_id' => $shiftId
    ]);
    exit;

} elseif ($action === 'edit') {
    Rbac::require_permission('schedule.manage');

    $shiftId = (int)($input['shift_id'] ?? 0);
    $employeeId = (int)($input['employee_id'] ?? 0);
    $shiftDate = trim((string)($input['shift_date'] ?? ''));
    $startTime = trim((string)($input['start_time'] ?? ''));
    $endTime = trim((string)($input['end_time'] ?? ''));
    $shiftType = trim((string)($input['shift_type'] ?? 'Regular'));
    $reason = trim((string)($input['reason'] ?? ''));

    if (!$shiftId) {
        http_response_code(400);
        exit(json_encode(['error' => 'Shift ID is required.']));
    }

    $stmt = $pdo->prepare("SELECT * FROM shifts WHERE id = ?");
    $stmt->execute([$shiftId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        http_response_code(404);
        exit(json_encode(['error' => 'Shift not found.']));
    }

    Rbac::assert_branch_access($existing['branch_id']);

    // If shift is currently under review, cannot edit without rejecting first
    if (in_array($existing['status'], ['PENDING_BM', 'PENDING_BA', 'APPROVED'], true)) {
        http_response_code(400);
        exit(json_encode(['error' => "Cannot edit shift while in {$existing['status']} review. Please reject or recall first."]));
    }

    $targetEmpId = $employeeId ?: (int)$existing['employee_id'];
    $targetDate = !empty($shiftDate) ? $shiftDate : $existing['shift_date'];
    $targetStart = !empty($startTime) ? $startTime : $existing['start_time'];
    $targetEnd = !empty($endTime) ? $endTime : $existing['end_time'];

    // Verify target employee belongs to branch
    $empStmt = $pdo->prepare("SELECT id, branch_id FROM employees WHERE id = ? AND deleted_at IS NULL");
    $empStmt->execute([$targetEmpId]);
    $emp = $empStmt->fetch();
    if (!$emp || $emp['branch_id'] != $existing['branch_id']) {
        http_response_code(403);
        exit(json_encode(['error' => 'Employee does not belong to this branch.']));
    }

    // Constraint 1: Leave Conflict Validation
    $leaveStmt = $pdo->prepare("
        SELECT id FROM leave_requests 
        WHERE employee_id = ? 
          AND status = 'APPROVED'
          AND date_from <= ? AND date_to >= ?
        LIMIT 1
    ");
    $leaveStmt->execute([$targetEmpId, $targetDate, $targetDate]);
    if ($leaveStmt->fetch()) {
        http_response_code(400);
        exit(json_encode(['error' => 'Employee is on approved leave during this date.']));
    }

    // Constraint 4: Overlap Validation
    $overlapStmt = $pdo->prepare("
        SELECT id FROM shifts 
        WHERE shift_date = ? 
          AND employee_id = ?
          AND id != ?
          AND (start_time < ? AND end_time > ?)
        LIMIT 1
    ");
    $overlapStmt->execute([$targetDate, $targetEmpId, $shiftId, $targetEnd, $targetStart]);
    if ($overlapStmt->fetch()) {
        http_response_code(400);
        exit(json_encode(['error' => 'Overlapping shift exists.']));
    }

    // Once published, any edit requires a mandatory reason and is audited
    if ($existing['status'] === 'PUBLISHED') {
        if (empty($reason)) {
            http_response_code(400);
            exit(json_encode(['error' => 'A reason is required when editing a published shift.']));
        }
        Audit::log('SHIFT_EDITED', "Shift {$shiftId} edited: {$reason}", $userId, $existing['branch_id']);
        $newStatus = 'PUBLISHED';
    } else {
        // Keeps as DRAFT if it was DRAFT or REJECTED
        $newStatus = 'DRAFT';
    }

    $updateStmt = $pdo->prepare("
        UPDATE shifts 
        SET employee_id = ?, shift_date = ?, start_time = ?, end_time = ?, shift_type = ?, status = ?, edit_reason = ?, updated_by = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$targetEmpId, $targetDate, $targetStart, $targetEnd, $shiftType, $newStatus, $reason ?: $existing['edit_reason'], $userId, $shiftId]);

    echo json_encode([
        'success' => true,
        'message' => 'Shift updated successfully.'
    ]);
    exit;

} elseif ($action === 'submit_to_bm') {
    Rbac::require_permission('schedule.manage');

    $branchId = (int)($input['branch_id'] ?? ($user['branch_id'] ?? 0));
    $dateFrom = trim((string)($input['date_from'] ?? ''));
    $dateTo = trim((string)($input['date_to'] ?? ''));

    if (!$branchId || empty($dateFrom) || empty($dateTo)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Branch ID and Date Range are required.']));
    }
    Rbac::assert_branch_access($branchId);

    // Update all DRAFT and REJECTED shifts for this branch and week
    $update = $pdo->prepare("
        UPDATE shifts 
        SET status = 'PENDING_BM', rejection_reason = NULL, updated_by = ?, updated_at = NOW() 
        WHERE branch_id = ? AND status IN ('DRAFT', 'REJECTED') AND shift_date BETWEEN ? AND ?
    ");
    $update->execute([$userId, $branchId, $dateFrom, $dateTo]);
    $count = $update->rowCount();

    if ($count === 0) {
        http_response_code(400);
        exit(json_encode(['error' => 'No draft or rejected shifts found for this week to submit.']));
    }

    Audit::log('ROSTER_SUBMITTED_TO_BM', "Submitted {$count} shift(s) for Branch {$branchId} ({$dateFrom} to {$dateTo}) to BM manpower review", $userId, $branchId);

    echo json_encode([
        'success' => true,
        'message' => "Successfully submitted {$count} shift(s) to Branch Manager for manpower review."
    ]);
    exit;

} elseif ($action === 'approve_manpower') {
    // Strictly BM (Role 6) or Admin
    if (!in_array($userRole, [1, 6], true) && !in_array($userRoleName, ['BM', 'Branch Manager'], true)) {
        http_response_code(403);
        exit(json_encode(['error' => 'Only Branch Manager can screen and approve manpower.']));
    }
    $branchId = (int)($input['branch_id'] ?? ($user['branch_id'] ?? 0));
    $dateFrom = trim((string)($input['date_from'] ?? ''));
    $dateTo = trim((string)($input['date_to'] ?? ''));

    if (!$branchId || empty($dateFrom) || empty($dateTo)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Branch ID and Date Range are required.']));
    }
    Rbac::assert_branch_access($branchId);

    $update = $pdo->prepare("
        UPDATE shifts 
        SET status = 'PENDING_BA', bm_screened_by = ?, updated_by = ?, updated_at = NOW() 
        WHERE branch_id = ? AND status = 'PENDING_BM' AND shift_date BETWEEN ? AND ?
    ");
    $update->execute([$userId, $userId, $branchId, $dateFrom, $dateTo]);
    $count = $update->rowCount();

    if ($count === 0) {
        http_response_code(400);
        exit(json_encode(['error' => 'No shifts pending BM manpower review found for this week.']));
    }

    Audit::log('ROSTER_MANPOWER_APPROVED', "BM approved manpower for {$count} shift(s) for Branch {$branchId} ({$dateFrom} to {$dateTo})", $userId, $branchId);

    echo json_encode([
        'success' => true,
        'message' => "Manpower approved for {$count} shift(s) and sent to Finance for budget approval."
    ]);
    exit;

} elseif ($action === 'approve_budget') {
    // Strictly BA (Role 4) or GA (Role 3) / Admin
    if (!in_array($userRole, [1, 3, 4], true) && !in_array($userRoleName, ['BA', 'Branch Accountant', 'GA', 'Global Accountant'], true)) {
        http_response_code(403);
        exit(json_encode(['error' => 'Only Branch Accountant can approve schedule labor budget.']));
    }
    $branchId = (int)($input['branch_id'] ?? ($user['branch_id'] ?? 0));
    $dateFrom = trim((string)($input['date_from'] ?? ''));
    $dateTo = trim((string)($input['date_to'] ?? ''));

    if (!$branchId || empty($dateFrom) || empty($dateTo)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Branch ID and Date Range are required.']));
    }
    Rbac::assert_branch_access($branchId);

    $update = $pdo->prepare("
        UPDATE shifts 
        SET status = 'APPROVED', ba_approved_by = ?, updated_by = ?, updated_at = NOW() 
        WHERE branch_id = ? AND status = 'PENDING_BA' AND shift_date BETWEEN ? AND ?
    ");
    $update->execute([$userId, $userId, $branchId, $dateFrom, $dateTo]);
    $count = $update->rowCount();

    if ($count === 0) {
        http_response_code(400);
        exit(json_encode(['error' => 'No shifts pending BA budget approval found for this week.']));
    }

    Audit::log('ROSTER_BUDGET_APPROVED', "BA approved labor budget for {$count} shift(s) for Branch {$branchId} ({$dateFrom} to {$dateTo})", $userId, $branchId);

    echo json_encode([
        'success' => true,
        'message' => "Budget approved for {$count} shift(s) and routed to Branch Manager for publishing."
    ]);
    exit;

} elseif ($action === 'publish') {
    // Strictly BM (Role 6) or Admin
    if (!in_array($userRole, [1, 6], true) && !in_array($userRoleName, ['BM', 'Branch Manager'], true)) {
        http_response_code(403);
        exit(json_encode(['error' => 'Only Branch Manager can publish schedules to staff.']));
    }
    $shiftId = (int)($input['shift_id'] ?? 0);
    $branchId = (int)($input['branch_id'] ?? ($user['branch_id'] ?? 0));
    $dateFrom = trim((string)($input['date_from'] ?? ''));
    $dateTo = trim((string)($input['date_to'] ?? ''));

    if ($shiftId > 0) {
        $stmt = $pdo->prepare("SELECT branch_id, status FROM shifts WHERE id = ?");
        $stmt->execute([$shiftId]);
        $s = $stmt->fetch();
        if (!$s) {
            http_response_code(404);
            exit(json_encode(['error' => 'Shift not found.']));
        }
        Rbac::assert_branch_access($s['branch_id']);
        if ($s['status'] !== 'APPROVED') {
            http_response_code(400);
            exit(json_encode(['error' => 'Only budget-approved shifts can be published to staff.']));
        }

        $update = $pdo->prepare("UPDATE shifts SET status = 'PUBLISHED', updated_by = ?, updated_at = NOW() WHERE id = ?");
        $update->execute([$userId, $shiftId]);
        Audit::log('ROSTER_PUBLISHED', "Shift {$shiftId} published to staff by BM", $userId, $s['branch_id']);
        echo json_encode(['success' => true, 'message' => 'Shift published successfully.']);
        exit;
    } elseif ($branchId > 0 && !empty($dateFrom) && !empty($dateTo)) {
        Rbac::assert_branch_access($branchId);

        $update = $pdo->prepare("
            UPDATE shifts 
            SET status = 'PUBLISHED', updated_by = ?, updated_at = NOW() 
            WHERE branch_id = ? AND status = 'APPROVED' AND shift_date BETWEEN ? AND ?
        ");
        $update->execute([$userId, $branchId, $dateFrom, $dateTo]);
        $count = $update->rowCount();

        if ($count === 0) {
            http_response_code(400);
            exit(json_encode(['error' => 'No approved shifts found to publish for this week.']));
        }

        Audit::log('ROSTER_PUBLISHED', "BM published {$count} shift(s) for Branch {$branchId} ({$dateFrom} to {$dateTo})", $userId, $branchId);

        echo json_encode(['success' => true, 'message' => "Published {$count} shift(s) to staff ESS portal successfully."]);
        exit;
    } else {
        http_response_code(400);
        exit(json_encode(['error' => 'Shift ID or Branch and Date Range are required.']));
    }

} elseif ($action === 'reject') {
    // Permitted for BM (Role 6) on PENDING_BM or BA (Role 4) on PENDING_BA (or Admin)
    $branchId = (int)($input['branch_id'] ?? ($user['branch_id'] ?? 0));
    $dateFrom = trim((string)($input['date_from'] ?? ''));
    $dateTo = trim((string)($input['date_to'] ?? ''));
    $reason = trim((string)($input['reason'] ?? ''));

    if (!$branchId || empty($dateFrom) || empty($dateTo)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Branch ID and Date Range are required.']));
    }
    if (empty($reason)) {
        http_response_code(400);
        exit(json_encode(['error' => 'A rejection reason is required.']));
    }
    Rbac::assert_branch_access($branchId);

    $targetStatus = '';
    if ($userRole === 6 || in_array($userRoleName, ['BM', 'Branch Manager'], true)) {
        $targetStatus = 'PENDING_BM';
    } elseif ($userRole === 4 || in_array($userRoleName, ['BA', 'Branch Accountant'], true)) {
        $targetStatus = 'PENDING_BA';
    } elseif ($userRole === 1) {
        $targetStatus = 'ANY';
    } else {
        http_response_code(403);
        exit(json_encode(['error' => 'Only Branch Manager or Branch Accountant can reject schedules.']));
    }

    if ($targetStatus === 'ANY') {
        $update = $pdo->prepare("
            UPDATE shifts 
            SET status = 'REJECTED', rejection_reason = ?, updated_by = ?, updated_at = NOW() 
            WHERE branch_id = ? AND status IN ('PENDING_BM', 'PENDING_BA') AND shift_date BETWEEN ? AND ?
        ");
        $update->execute([$reason, $userId, $branchId, $dateFrom, $dateTo]);
    } else {
        $update = $pdo->prepare("
            UPDATE shifts 
            SET status = 'REJECTED', rejection_reason = ?, updated_by = ?, updated_at = NOW() 
            WHERE branch_id = ? AND status = ? AND shift_date BETWEEN ? AND ?
        ");
        $update->execute([$reason, $userId, $branchId, $targetStatus, $dateFrom, $dateTo]);
    }

    $count = $update->rowCount();
    if ($count === 0) {
        http_response_code(400);
        exit(json_encode(['error' => "No shifts in review found to reject for this week."]));
    }

    Audit::log('ROSTER_REJECTED', "Roster rejected: {$reason} for {$count} shift(s) (Branch {$branchId})", $userId, $branchId);

    echo json_encode([
        'success' => true,
        'message' => "Schedule rejected and returned to Branch HR with feedback."
    ]);
    exit;

} elseif ($action === 'delete') {
    Rbac::require_permission('schedule.manage');

    $shiftId = (int)($input['shift_id'] ?? 0);
    if (!$shiftId) {
        http_response_code(400);
        exit(json_encode(['error' => 'Shift ID is required.']));
    }

    $stmt = $pdo->prepare("SELECT branch_id, status FROM shifts WHERE id = ?");
    $stmt->execute([$shiftId]);
    $s = $stmt->fetch();
    if (!$s) {
        http_response_code(404);
        exit(json_encode(['error' => 'Shift not found.']));
    }
    Rbac::assert_branch_access($s['branch_id']);

    if (in_array($s['status'], ['PENDING_BM', 'PENDING_BA', 'APPROVED'], true)) {
        http_response_code(400);
        exit(json_encode(['error' => "Cannot delete shift while in {$s['status']} review. Please reject first."]));
    }

    $del = $pdo->prepare("DELETE FROM shifts WHERE id = ?");
    $del->execute([$shiftId]);

    echo json_encode(['success' => true, 'message' => 'Shift deleted successfully.']);
    exit;

} else {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid action specified.']));
}
