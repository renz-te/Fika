<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('hr.employee.edit');

$employeeId = (int) ($_POST['employee_id'] ?? 0);
$type = trim($_POST['type'] ?? '');
$user = Auth::user();

if (!$employeeId || empty($type) || empty($_FILES['document'])) {
    http_response_code(400);
    exit(json_encode(["error" => "Employee ID, Document Type, and a valid file are required."]));
}

global $pdo;

$stmt = $pdo->prepare("SELECT branch_id FROM employees WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$employeeId]);
$employee = $stmt->fetch();

if (!$employee) {
    http_response_code(404);
    exit(json_encode(["error" => "Employee not found."]));
}

Rbac::assert_branch_access($employee['branch_id']);

// Use absolute storage path outside the web root (assuming Fika is inside www, we put it in app/storage)
$storageDir = __DIR__ . '/../../app/storage/documents';

$safeFilename = Upload::handle($_FILES['document'], $storageDir);

if (!$safeFilename) {
    http_response_code(400);
    exit(json_encode(["error" => "Invalid file upload. Only JPG/PNG/PDF under 5MB allowed, and it must pass finfo MIME validation."]));
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("INSERT INTO employee_documents (employee_id, type, file_path, uploaded_by, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$employeeId, $type, $safeFilename, $user['id']]);
    
    Audit::log('EMPLOYEE_DOCUMENT_UPLOAD', "User {$user['id']} uploaded document '{$type}' for Employee {$employeeId}.");
    
    $pdo->commit();
    echo json_encode(["success" => true, "message" => "Document uploaded successfully.", "filename" => $safeFilename]);
} catch (Exception $e) {
    $pdo->rollBack();
    // Clean up file if db fails
    @unlink($storageDir . '/' . $safeFilename);
    http_response_code(500);
    exit(json_encode(["error" => "Database error: " . $e->getMessage()]));
}
