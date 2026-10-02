<?php
require_once __DIR__ . '/init.php';

// Check access
if (empty($_SESSION['user'])) {
    http_response_code(403);
    die('Forbidden: Please log in.');
}

// Only HR and Admins should access applicant/employee files, wait maybe Employee themselves?
// Assuming 'Super Admin', 'Admin', 'HR Manager'
$allowed = ['Super Admin', 'Admin', 'HR Manager'];
if (!in_array($_SESSION['user']['role'], $allowed)) {
    http_response_code(403);
    die('Forbidden: Insufficient privileges.');
}

$fileParam = $_GET['file'] ?? '';
if (empty($fileParam)) {
    http_response_code(400);
    die('Bad Request: File not specified.');
}

// Security: Prevent directory traversal
$fileParam = str_replace(['..', '\\', "\0"], '', $fileParam);
// Ensure path starts with uploads/ (we strip leading slashes if any)
$fileParam = ltrim($fileParam, '/');
$filePath = __DIR__ . '/uploads/' . $fileParam;

if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    die('Not Found.');
}

// Get MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $filePath);
finfo_close($finfo);

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
