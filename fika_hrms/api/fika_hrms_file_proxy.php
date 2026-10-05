<?php
require_once __DIR__ . '/../../app/bootstrap.php';

Auth::requireLogin();
Rbac::require_permission('hr.employee.view');

$filename = $_GET['file'] ?? '';
if (empty($filename)) {
    http_response_code(400);
    die("File parameter is required.");
}

// Basic path traversal prevention
$filename = basename($filename);

global $pdo;

$stmt = $pdo->prepare("
    SELECT ed.file_path, e.branch_id 
    FROM employee_documents ed
    JOIN employees e ON e.id = ed.employee_id
    WHERE ed.file_path = ?
");
$stmt->execute([$filename]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    die("File not found or unauthorized.");
}

// Enforce branch scope! A manager from Branch A cannot view documents of Branch B employees
Rbac::assert_branch_access($doc['branch_id']);

$storageDir = __DIR__ . '/../../app/storage/documents';
$absolutePath = $storageDir . '/' . $doc['file_path'];

if (!file_exists($absolutePath)) {
    http_response_code(404);
    die("File not found on disk.");
}

// Determine MIME
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $absolutePath);
finfo_close($finfo);

Upload::serve($absolutePath, $mime);
