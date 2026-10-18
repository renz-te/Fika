<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();

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
$sessEmpId = (int)($user['employee_id'] ?? 0);

if ($action === 'drop') {
    // Requires schedule.claim
    Rbac::require_permission('schedule.claim');

    if (!$sessEmpId) {
        http_response_code(403);
        exit(json_encode(['error' => 'Account is not linked to an employee profile.']));
    }

    $shiftId = (int)($input['shift_id'] ?? 0);
    $dropReason = trim((string)($input['reason'] ?? ''));

    if (!$shiftId) {
        http_response_code(400);
        exit(json_encode(['error' => 'Shift ID is required.']));
    }

    $stmt = $pdo->prepare("SELECT * FROM shifts WHERE id = ?");
    $stmt->execute([$shiftId]);
    $shift = $stmt->fetch();
    if (!$shift) {
        http_response_code(404);
        exit(json_encode(['error' => 'Shift not found.']));
    }

    if ((int)$shift['employee_id'] !== $sessEmpId && !Rbac::can('schedule.manage')) {
        http_response_code(403);
        exit(json_encode(['error' => 'You can only drop your own assigned shift.']));
    }

    if ($shift['status'] !== 'PUBLISHED') {
        http_response_code(400);
        exit(json_encode(['error' => 'Only published shifts can be dropped.']));
    }

    // Check if already dropped and active
    $checkOpen = $pdo->prepare("SELECT id FROM open_shifts WHERE shift_id = ? AND status IN ('OPEN', 'PENDING_APPROVAL') LIMIT 1");
    $checkOpen->execute([$shiftId]);
    if ($checkOpen->fetch()) {
        http_response_code(400);
        exit(json_encode(['error' => 'This shift has already been dropped and is open.']));
    }

    // Constraint 3: Shift remains on Employee A in the shifts table, new row in open_shifts
    $insertStmt = $pdo->prepare("
        INSERT INTO open_shifts (shift_id, branch_id, original_employee_id, drop_reason, status, created_at)
        VALUES (?, ?, ?, ?, 'OPEN', NOW())
    ");
    $insertStmt->execute([$shiftId, $shift['branch_id'], $shift['employee_id'], $dropReason]);
    $openShiftId = (int)$pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Shift dropped successfully. It is now open for claiming.',
        'open_shift_id' => $openShiftId
    ]);
    exit;

} elseif ($action === 'claim') {
    Rbac::require_permission('schedule.claim');

    if (!$sessEmpId) {
        http_response_code(403);
        exit(json_encode(['error' => 'Account is not linked to an employee profile.']));
    }

    $openShiftId = (int)($input['open_shift_id'] ?? 0);
    if (!$openShiftId) {
        http_response_code(400);
        exit(json_encode(['error' => 'Open shift ID is required.']));
    }

    $stmt = $pdo->prepare("
        SELECT os.*, s.shift_date, s.start_time, s.end_time, s.branch_id as shift_branch, s.employee_id as current_shift_emp
        FROM open_shifts os
        JOIN shifts s ON os.shift_id = s.id
        WHERE os.id = ? AND os.status = 'OPEN'
    ");
    $stmt->execute([$openShiftId]);
    $openShift = $stmt->fetch();
    if (!$openShift) {
        http_response_code(400);
        exit(json_encode(['error' => 'This shift is no longer open for claiming.']));
    }

    if ((int)$openShift['original_employee_id'] === $sessEmpId) {
        http_response_code(400);
        exit(json_encode(['error' => 'You cannot claim your own dropped shift.']));
    }

    // Verify claimer belongs to same branch
    $empStmt = $pdo->prepare("SELECT branch_id FROM employees WHERE id = ? AND deleted_at IS NULL");
    $empStmt->execute([$sessEmpId]);
    $claimer = $empStmt->fetch();
    if (!$claimer || (int)$claimer['branch_id'] !== (int)$openShift['branch_id']) {
        http_response_code(403);
        exit(json_encode(['error' => 'You can only claim shifts within your assigned branch.']));
    }

    // Constraint 1: Leave Conflict Validation for Claimer
    $leaveStmt = $pdo->prepare("
        SELECT id FROM leave_requests 
        WHERE employee_id = ? 
          AND status = 'APPROVED'
          AND date_from <= ? AND date_to >= ?
        LIMIT 1
    ");
    $leaveStmt->execute([$sessEmpId, $openShift['shift_date'], $openShift['shift_date']]);
    if ($leaveStmt->fetch()) {
        http_response_code(400);
        exit(json_encode(['error' => 'Employee is on approved leave during this date.']));
    }

    // Constraint 4: Overlap Check on Claim
    $overlapStmt = $pdo->prepare("
        SELECT id FROM shifts 
        WHERE shift_date = ? 
          AND employee_id = ?
          AND (start_time < ? AND end_time > ?)
        LIMIT 1
    ");
    $overlapStmt->execute([$openShift['shift_date'], $sessEmpId, $openShift['end_time'], $openShift['start_time']]);
    if ($overlapStmt->fetch()) {
        http_response_code(400);
        exit(json_encode(['error' => 'Overlapping shift exists.']));
    }

    // Constraint 3: open_shifts.status becomes 'PENDING_APPROVAL' and claimed_by = Employee B ID. Shift is STILL on Employee A.
    $updateStmt = $pdo->prepare("
        UPDATE open_shifts 
        SET status = 'PENDING_APPROVAL', claimed_by = ?, updated_at = NOW() 
        WHERE id = ? AND status = 'OPEN'
    ");
    $updateStmt->execute([$sessEmpId, $openShiftId]);

    echo json_encode([
        'success' => true,
        'message' => 'Shift claimed successfully. Pending manager approval.'
    ]);
    exit;

} elseif ($action === 'decide') {
    Rbac::require_permission('schedule.manage');

    $openShiftId = (int)($input['open_shift_id'] ?? 0);
    $decision = strtoupper(trim((string)($input['decision'] ?? '')));

    if (!$openShiftId || !in_array($decision, ['APPROVE', 'REJECT'])) {
        http_response_code(400);
        exit(json_encode(['error' => 'Valid Open Shift ID and decision (APPROVE or REJECT) are required.']));
    }

    $stmt = $pdo->prepare("
        SELECT os.*, s.branch_id, s.shift_date, s.start_time, s.end_time 
        FROM open_shifts os
        JOIN shifts s ON os.shift_id = s.id
        WHERE os.id = ? AND os.status = 'PENDING_APPROVAL'
    ");
    $stmt->execute([$openShiftId]);
    $openShift = $stmt->fetch();
    if (!$openShift) {
        http_response_code(404);
        exit(json_encode(['error' => 'Pending claim request not found.']));
    }

    Rbac::assert_branch_access($openShift['branch_id']);

    $claimedBy = (int)$openShift['claimed_by'];

    if ($decision === 'APPROVE') {
        // Maker-Checker: A manager cannot approve their own claim if they were the claimer
        if ($sessEmpId && $sessEmpId === $claimedBy) {
            http_response_code(403);
            exit(json_encode(['error' => 'Conflict of interest: You cannot approve your own shift claim.']));
        }

        // Constraint 3: shifts.employee_id is updated to Employee B, and open_shifts.status becomes 'APPROVED'
        $pdo->beginTransaction();
        try {
            $shiftUpdate = $pdo->prepare("UPDATE shifts SET employee_id = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
            $shiftUpdate->execute([$claimedBy, $userId, $openShift['shift_id']]);

            $openUpdate = $pdo->prepare("UPDATE open_shifts SET status = 'APPROVED', approved_by = ?, approved_at = NOW() WHERE id = ?");
            $openUpdate->execute([$userId, $openShiftId]);

            Audit::log('SHIFT_CLAIM_APPROVED', "Manager {$userId} approved shift {$openShift['shift_id']} transfer to employee {$claimedBy}", $userId);

            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Shift claim approved and schedule updated.'
            ]);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            exit(json_encode(['error' => 'Failed to approve shift claim: ' . $e->getMessage()]));
        }

    } else { // REJECT
        // Constraint 3: open_shifts.status goes back to 'OPEN', and claimed_by is cleared to NULL
        $updateStmt = $pdo->prepare("
            UPDATE open_shifts 
            SET status = 'OPEN', claimed_by = NULL, updated_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$openShiftId]);

        Audit::log('SHIFT_CLAIM_REJECTED', "Manager {$userId} rejected shift {$openShift['shift_id']} claim by employee {$claimedBy}", $userId);

        echo json_encode([
            'success' => true,
            'message' => 'Shift claim rejected. Shift reopened for claiming.'
        ]);
        exit;
    }

} else {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid action specified.']));
}
