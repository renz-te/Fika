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

$name = trim($input['name'] ?? '');

if ($name === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Category name is required.']);
    exit;
}

if (mb_strlen($name) > 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Category name must not exceed 100 characters.']);
    exit;
}

global $pdo;

try {
    // Check for duplicate category name (case-insensitive)
    $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $stmt->execute([$name]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Category already exists.']);
        exit;
    }

    $ins = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
    $ins->execute([$name]);
    $newId = (int)$pdo->lastInsertId();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'category' => [
            'id' => $newId,
            'name' => $name
        ]
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save category.']);
    exit;
}
