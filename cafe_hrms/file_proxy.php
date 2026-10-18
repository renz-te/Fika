<?php
require_once __DIR__ . '/init.php';

// Check access
if (empty($_SESSION['user'])) {
    http_response_code(401);
    die('Unauthorized: Please log in.');
}

// Only HR and Admins should access applicant/employee files
$allowed = [ROLE_SUPER_ADMIN, ROLE_HR_MANAGER, ROLE_CENTRAL_HR, ROLE_BRANCH_MANAGER];
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
if (str_contains($fileParam, '..') || str_contains($fileParam, '\\') || str_contains($fileParam, "\0")) {
    http_response_code(400);
    die('Bad Request: Invalid file path.');
}

$fileParam = ltrim($fileParam, '/');
$baseUploadDir = realpath(__DIR__ . '/uploads');
$filePath = __DIR__ . '/uploads/' . $fileParam;
$realTarget = realpath($filePath);

if ($baseUploadDir === false || $realTarget === false || !str_starts_with($realTarget, $baseUploadDir . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    die('Not Found.');
}

if (!is_file($realTarget)) {
    http_response_code(404);
    die('Not Found.');
}

// Allowed extensions whitelist
$allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
$ext = strtolower(pathinfo($realTarget, PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExts, true)) {
    http_response_code(403);
    die('Forbidden: File type not permitted.');
}

// Get MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $filePath);
finfo_close($finfo);

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);

// Force PDFs as attachments to prevent inline HTML/JS execution
if ($mime === 'application/pdf') {
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
} else {
    header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
}

header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
