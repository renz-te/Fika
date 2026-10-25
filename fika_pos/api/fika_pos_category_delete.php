<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();

// Parse input (supports JSON and form-data)
$rawInput = json_decode(file_get_contents('php://input'), true);
if (is_array($rawInput)) {
    $input = array_merge($_POST, $rawInput);
    if (!empty($rawInput['csrf_token']) && empty($_POST['csrf_token'])) {
        $_POST['csrf_token'] = $rawInput['csrf_token'];
    }
} else {
    $input = $_POST;
}

Csrf::requireValid();
Rbac::require_permission('pos.menu.manage');

$id = isset($input['id']) ? (int)$input['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid category ID is required.']);
    exit;
}

global $pdo;

try {
    // Strict query check for linked active products
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category = (SELECT name FROM categories WHERE id = ?) AND deleted_at IS NULL");
    $checkStmt->execute([$id]);
    $linkedCount = (int)$checkStmt->fetchColumn();

    if ($linkedCount > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete category: linked to active products.']);
        exit;
    }

    $delStmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $delStmt->execute([$id]);

    echo json_encode(['success' => true]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete category.']);
    exit;
}
