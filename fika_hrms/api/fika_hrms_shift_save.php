<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
Rbac::require_permission('schedule.manage');

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

if ($action === 'create') {
    $branchId = (int)($input['branch_id'] ?? ($user['branch_id'] ?? 0));
    $employeeId = (int)($input['employee_id'] ?? 0);
    $shiftDate = trim((string)($input['shift_date'] ?? ''));
    $startTime = trim((string)($input['start_time'] ?? ''));
    $endTime = trim((string)($input['end_time'] ?? ''));
    $shiftType = trim((string)($input['shift_type'] ?? 'Regular'));
    $status = strtoupper(trim((string)($input['status'] ?? 'DRAFT')));

    if (!$branchId || !$employeeId || empty($shiftDate) || empty($startTime) || empty($endTime)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Branch, Employee, Date, and Times are required.']));
    }

    if (!in_array($status, ['DRAFT', 'PUBLISHED'])) {
        $status = 'DRAFT';
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
    // Check leave_requests for an APPROVED leave overlapping shift_date
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
        'message' => 'Shift created successfully.',
        'shift_id' => $shiftId
    ]);
    exit;

} elseif ($action === 'edit') {
    $shiftId = (int)($input['shift_id'] ?? 0);
    $employeeId = (int)($input['employee_id'] ?? 0);
    $shiftDate = trim((string)($input['shift_date'] ?? ''));
    $startTime = trim((string)($input['start_time'] ?? ''));
    $endTime = trim((string)($input['end_time'] ?? ''));
    $shiftType = trim((string)($input['shift_type'] ?? 'Regular'));
    $status = strtoupper(trim((string)($input['status'] ?? 'DRAFT')));
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

    // Constraint 5: Once published, any edit requires a mandatory reason and is audited via Audit::log('SHIFT_EDITED')
    if ($existing['status'] === 'PUBLISHED') {
        if (empty($reason)) {
            http_response_code(400);
            exit(json_encode(['error' => 'A reason is required when editing a published shift.']));
        }
        Audit::log('SHIFT_EDITED', "Shift {$shiftId} edited: {$reason}", $userId);
    }

    $updateStmt = $pdo->prepare("
        UPDATE shifts 
        SET employee_id = ?, shift_date = ?, start_time = ?, end_time = ?, shift_type = ?, status = ?, edit_reason = ?, updated_by = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$targetEmpId, $targetDate, $targetStart, $targetEnd, $shiftType, $status, $reason ?: $existing['edit_reason'], $userId, $shiftId]);

    echo json_encode([
        'success' => true,
        'message' => 'Shift updated successfully.'
    ]);
    exit;

} elseif ($action === 'publish') {
    $shiftId = (int)($input['shift_id'] ?? 0);
    $branchId = (int)($input['branch_id'] ?? 0);
    $dateFrom = trim((string)($input['date_from'] ?? ''));
    $dateTo = trim((string)($input['date_to'] ?? ''));

    if ($shiftId > 0) {
        $stmt = $pdo->prepare("SELECT branch_id FROM shifts WHERE id = ?");
        $stmt->execute([$shiftId]);
        $s = $stmt->fetch();
        if (!$s) {
            http_response_code(404);
            exit(json_encode(['error' => 'Shift not found.']));
        }
        Rbac::assert_branch_access($s['branch_id']);

        $update = $pdo->prepare("UPDATE shifts SET status = 'PUBLISHED', updated_by = ?, updated_at = NOW() WHERE id = ?");
        $update->execute([$userId, $shiftId]);
        echo json_encode(['success' => true, 'message' => 'Shift published successfully.']);
        exit;
    } elseif ($branchId > 0 && !empty($dateFrom) && !empty($dateTo)) {
        Rbac::assert_branch_access($branchId);

        $update = $pdo->prepare("
            UPDATE shifts 
            SET status = 'PUBLISHED', updated_by = ?, updated_at = NOW() 
            WHERE branch_id = ? AND status = 'DRAFT' AND shift_date BETWEEN ? AND ?
        ");
        $update->execute([$userId, $branchId, $dateFrom, $dateTo]);
        $count = $update->rowCount();

        echo json_encode(['success' => true, 'message' => "Published {$count} shift(s) successfully."]);
        exit;
    } else {
        http_response_code(400);
        exit(json_encode(['error' => 'Shift ID or Branch and Date Range are required.']));
    }

} elseif ($action === 'delete') {
    $shiftId = (int)($input['shift_id'] ?? 0);
    if (!$shiftId) {
        http_response_code(400);
        exit(json_encode(['error' => 'Shift ID is required.']));
    }

    $stmt = $pdo->prepare("SELECT branch_id FROM shifts WHERE id = ?");
    $stmt->execute([$shiftId]);
    $s = $stmt->fetch();
    if (!$s) {
        http_response_code(404);
        exit(json_encode(['error' => 'Shift not found.']));
    }
    Rbac::assert_branch_access($s['branch_id']);

    $del = $pdo->prepare("DELETE FROM shifts WHERE id = ?");
    $del->execute([$shiftId]);

    echo json_encode(['success' => true, 'message' => 'Shift deleted successfully.']);
    exit;

} else {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid action specified.']));
}
