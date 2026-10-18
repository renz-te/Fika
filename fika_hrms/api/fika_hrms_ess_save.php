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

$sessEmpId = (int)($_SESSION['user']['employee_id'] ?? 0);
if (!$sessEmpId) {
    http_response_code(403);
    exit(json_encode(['error' => 'Account is not linked to an employee profile.']));
}

global $pdo;

$action = $input['action'] ?? '';

if ($action === 'update_profile') {
    // Constraint 3: Strict profile whitelist. If payload contains ANY forbidden field, reject with HTTP 400
    $forbiddenFields = [
        'salary',
        'hourly_rate',
        'basic_salary',
        'role_id',
        'role',
        'branch_id',
        'employee_code',
        'employment_type'
    ];

    foreach ($forbiddenFields as $forbidden) {
        if (array_key_exists($forbidden, $input)) {
            http_response_code(400);
            exit(json_encode([
                'error' => "Forbidden field in payload: {$forbidden}"
            ]));
        }
    }

    $phone = isset($input['phone']) ? trim((string)$input['phone']) : null;
    $address = isset($input['address']) ? trim((string)$input['address']) : null;
    $emergencyContact = isset($input['emergency_contact']) ? trim((string)$input['emergency_contact']) : null;
    $bankAccount = isset($input['bank_account']) ? trim((string)$input['bank_account']) : null;

    // Fetch existing employee record to keep unchanged values
    $stmt = $pdo->prepare("SELECT id, phone, address, emergency_contact, bank_no_enc FROM employees WHERE id = ?");
    $stmt->execute([$sessEmpId]);
    $emp = $stmt->fetch();
    if (!$emp) {
        http_response_code(404);
        exit(json_encode(['error' => 'Employee record not found.']));
    }

    $newPhone = $phone !== null ? $phone : $emp['phone'];
    $newAddress = $address !== null ? $address : $emp['address'];
    $newEmergency = $emergencyContact !== null ? $emergencyContact : $emp['emergency_contact'];
    
    // Constraint 5: Bank account encryption via Crypto::encrypt()
    if ($bankAccount !== null && $bankAccount !== '') {
        $newBankEnc = Crypto::encrypt($bankAccount);
    } else {
        $newBankEnc = $emp['bank_no_enc'];
    }

    $updateStmt = $pdo->prepare("
        UPDATE employees 
        SET phone = ?, address = ?, emergency_contact = ?, bank_no_enc = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $updateStmt->execute([$newPhone, $newAddress, $newEmergency, $newBankEnc, $sessEmpId]);

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully.'
    ]);
    exit;

} elseif ($action === 'request_leave') {
    $type = trim((string)($input['type'] ?? ''));
    $dateFrom = trim((string)($input['date_from'] ?? ''));
    $dateTo = trim((string)($input['date_to'] ?? ''));
    $reason = trim((string)($input['reason'] ?? ''));
    $daysInput = (float)($input['days'] ?? 0);

    if (empty($type) || empty($dateFrom) || empty($dateTo) || empty($reason)) {
        http_response_code(400);
        exit(json_encode(['error' => 'All leave fields (Type, Dates, Reason) are required.']));
    }

    $timeFrom = strtotime($dateFrom);
    $timeTo = strtotime($dateTo);
    if ($timeFrom === false || $timeTo === false || $timeFrom > $timeTo) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid leave dates. Start date cannot be after end date.']));
    }

    $days = $daysInput > 0 ? $daysInput : round(($timeTo - $timeFrom) / 86400 + 1, 2);

    // Constraint 4: Leave Overlap Check
    // check if (start_date <= new_end AND end_date >= new_start) for status IN ('PENDING', 'APPROVED')
    $overlapStmt = $pdo->prepare("
        SELECT id FROM leave_requests 
        WHERE employee_id = ? 
          AND status IN ('PENDING', 'APPROVED')
          AND (date_from <= ? AND date_to >= ?)
        LIMIT 1
    ");
    $overlapStmt->execute([$sessEmpId, $dateTo, $dateFrom]);
    if ($overlapStmt->fetch()) {
        http_response_code(400);
        exit(json_encode(['error' => 'Overlapping leave request exists.']));
    }

    // Constraint 6: Leave File Upload via Upload::handle()
    $medCertFile = null;
    if (!empty($_FILES['med_cert']) && $_FILES['med_cert']['error'] !== UPLOAD_ERR_NO_FILE) {
        $storageDir = __DIR__ . '/../../app/storage/leaves';
        $medCertFile = Upload::handle($_FILES['med_cert'], $storageDir);
        if (!$medCertFile) {
            http_response_code(400);
            exit(json_encode(['error' => 'Invalid medical certificate file. Only JPG, PNG, or PDF up to 5MB allowed.']));
        }
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO leave_requests (employee_id, type, date_from, date_to, days, reason, status, med_cert_path, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'PENDING', ?, NOW())
    ");
    $insertStmt->execute([$sessEmpId, $type, $dateFrom, $dateTo, $days, $reason, $medCertFile]);
    $leaveId = (int)$pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Leave request submitted successfully.',
        'leave_id' => $leaveId
    ]);
    exit;

} else {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid action specified.']));
}
