<?php
require_once __DIR__ . '/../../app/bootstrap.php';

// 1. Enforce Authentication
Auth::requireLogin();

// 2. Enforce Permission Check
if (!Rbac::can('hr.employee.view')) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Forbidden: hr.employee.view permission required.']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid applicant ID.']);
    exit;
}

global $pdo;
$stmt = $pdo->prepare("SELECT id, first_name, last_name, resume, resume_path FROM applicants WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$applicant = $stmt->fetch();

if (!$applicant) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Applicant not found.']);
    exit;
}

$filename = $applicant['resume_path'] ?: $applicant['resume'];
if (!$filename) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No resume file associated with applicant.']);
    exit;
}

// Storage path constraint outside web root
$safeFilename = basename($filename);
$storageDir = APP_ROOT . '/storage/resumes';
$filePath = $storageDir . '/' . $safeFilename;

if (!file_exists($filePath)) {
    $fallbackPath = APP_ROOT . '/app/storage/resumes/' . $safeFilename;
    if (file_exists($fallbackPath)) {
        $filePath = $fallbackPath;
    } else {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'File not found on storage server.']);
        exit;
    }
}

// Safely proxy serve the PDF file
Upload::serve($filePath, 'application/pdf');
