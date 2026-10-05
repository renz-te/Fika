<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
Csrf::requireValid();

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    Rbac::require_permission('hr.employee.edit');
} else {
    Rbac::require_permission('hr.employee.create');
}

$employeeCode = trim($_POST['employee_code'] ?? '');
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$branchId = (int) ($_POST['branch_id'] ?? 0);
$position = trim($_POST['position'] ?? '');
$department = trim($_POST['department'] ?? '');
$employmentType = trim($_POST['employment_type'] ?? 'REGULAR');
$status = trim($_POST['status'] ?? 'ACTIVE');
$dateHired = trim($_POST['date_hired'] ?? '');
$basicSalaryRaw = trim($_POST['basic_salary'] ?? '0');
$pin = trim($_POST['pin'] ?? '');

$bankNo = trim($_POST['bank_no'] ?? '');
$tin = trim($_POST['tin'] ?? '');
$sss = trim($_POST['sss'] ?? '');
$philhealth = trim($_POST['philhealth'] ?? '');
$pagibig = trim($_POST['pagibig'] ?? '');

// Validation
$errors = [];
if ($employeeCode === '') $errors[] = "Employee code is required.";
if ($firstName === '') $errors[] = "First name is required.";
if ($lastName === '') $errors[] = "Last name is required.";
if ($branchId <= 0) $errors[] = "Valid branch is required.";
if ($dateHired !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateHired)) $errors[] = "Invalid date hired format.";
$basicSalaryCentavos = Money::toCentavos($basicSalaryRaw);
if ($basicSalaryCentavos <= 0) $errors[] = "Basic salary must be greater than 0.";
if ($pin !== '' && !preg_match('/^\d{4,6}$/', $pin)) $errors[] = "PIN must be exactly 4 to 6 digits.";
if (!in_array($employmentType, ['REGULAR', 'PROBATIONARY', 'PART_TIME'])) $errors[] = "Invalid employment type.";
if (!in_array($status, ['ACTIVE', 'INACTIVE', 'SEPARATED'])) $errors[] = "Invalid status.";

// Ensure user has permission for the selected branch (even for creation)
Rbac::assert_branch_access($branchId);

if (!empty($errors)) {
    http_response_code(400);
    exit(json_encode(["error" => implode(" ", $errors)]));
}

global $pdo;

try {
    $pdo->beginTransaction();
    
    // Check Code Uniqueness
    $stmt = $pdo->prepare("SELECT id FROM employees WHERE employee_code = ? AND id != ? AND deleted_at IS NULL");
    $stmt->execute([$employeeCode, $id]);
    if ($stmt->fetch()) {
        throw new Exception("Employee code is already in use.");
    }
    
    $existing = null;
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? FOR UPDATE");
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            throw new Exception("Employee not found.");
        }
        // Ensure they have access to the employee's CURRENT branch
        Rbac::assert_branch_access($existing['branch_id']);
    }
    
    // Process Encrypted Fields (If empty or contains '****', ignore update to prevent wiping)
    $updateFields = [];
    $params = [];
    $auditChanges = [];
    
    $checkSensitive = function($fieldName, $dbField, $newValue) use (&$updateFields, &$params, &$auditChanges, $existing) {
        if ($newValue === '' || str_contains($newValue, '****')) {
            // Keep existing (do not add to update list)
            return;
        }
        $encValue = Crypto::encrypt($newValue);
        if (!$existing || $existing[$dbField] !== $encValue) {
            $updateFields[] = "`$dbField` = ?";
            $params[] = $encValue;
            $auditChanges[] = $fieldName; // Only log the name
        }
    };
    
    $checkSensitive('Bank Account', 'bank_no_enc', $bankNo);
    $checkSensitive('TIN', 'tin_enc', $tin);
    $checkSensitive('SSS', 'sss_no_enc', $sss);
    $checkSensitive('PhilHealth', 'philhealth_no_enc', $philhealth);
    $checkSensitive('PagIBIG', 'pagibig_no_enc', $pagibig);
    
    // Standard Fields
    $standardMap = [
        'employee_code' => ['Code', $employeeCode],
        'first_name' => ['First Name', $firstName],
        'last_name' => ['Last Name', $lastName],
        'branch_id' => ['Branch', $branchId],
        'position' => ['Position', $position],
        'department' => ['Department', $department],
        'employment_type' => ['Type', $employmentType],
        'status' => ['Status', $status],
        'date_hired' => ['Date Hired', $dateHired ?: null],
        'basic_salary' => ['Basic Salary', Money::toDecimal($basicSalaryCentavos)],
    ];
    
    foreach ($standardMap as $dbField => $meta) {
        $name = $meta[0];
        $val = $meta[1];
        
        if (!$existing || (string)$existing[$dbField] !== (string)$val) {
            $updateFields[] = "`$dbField` = ?";
            $params[] = $val;
            $auditChanges[] = $name;
        }
    }
    
    if ($pin !== '') {
        $updateFields[] = "`pin_hash` = ?";
        $params[] = password_hash($pin, PASSWORD_DEFAULT);
        $auditChanges[] = 'PIN';
    }
    
    if ($id > 0) {
        if (!empty($updateFields)) {
            $updateFields[] = "`updated_at` = NOW()";
            $sql = "UPDATE employees SET " . implode(", ", $updateFields) . " WHERE id = ?";
            $params[] = $id;
            $pdo->prepare($sql)->execute($params);
            
            if (!empty($auditChanges)) {
                Audit::log('EMPLOYEE_UPDATED', "Updated Employee #{$id}. Changed fields: " . implode(", ", $auditChanges));
            }
        }
        $employeeId = $id;
    } else {
        $cols = [];
        $vals = [];
        foreach ($updateFields as $idx => $upd) {
            $colName = explode("=", $upd)[0];
            $cols[] = trim($colName);
            $vals[] = "?";
        }
        $cols[] = "`created_at`";
        $vals[] = "NOW()";
        
        $sql = "INSERT INTO employees (" . implode(", ", $cols) . ") VALUES (" . implode(", ", $vals) . ")";
        $pdo->prepare($sql)->execute($params);
        $employeeId = $pdo->lastInsertId();
        
        Audit::log('EMPLOYEE_CREATED', "Created Employee #{$employeeId} (Code: {$employeeCode})");
    }
    
    $pdo->commit();
    echo json_encode(["success" => true, "id" => $employeeId, "message" => "Employee saved successfully."]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    exit(json_encode(["error" => $e->getMessage()]));
}
