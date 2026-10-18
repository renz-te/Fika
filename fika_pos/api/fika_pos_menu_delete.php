<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();

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
    exit(json_encode(["error" => "Invalid product ID."]));
}

$user = Auth::user();
$targetBranchId = !empty($input['branch_id']) ? (int)$input['branch_id'] : null;
if ($targetBranchId !== null) {
    Rbac::assert_branch_access($targetBranchId);
} elseif ($user['branch_id'] !== null) {
    Rbac::assert_branch_access((int)$user['branch_id']);
}

global $pdo;

try {
    $stmt = $pdo->prepare("SELECT id, name, image_path FROM products WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $prod = $stmt->fetch();
    if (!$prod) {
        throw new Exception("Product not found or already deleted.");
    }

    $pdo->beginTransaction();

    // Soft delete product (Constraint 3)
    $delStmt = $pdo->prepare("UPDATE products SET deleted_at = NOW(), is_active = 0 WHERE id = ?");
    $delStmt->execute([$id]);

    // Deactivate from all branch_products
    $delBpStmt = $pdo->prepare("UPDATE branch_products SET is_active = 0 WHERE product_id = ?");
    $delBpStmt->execute([$id]);

    Audit::log('PRODUCT_DELETED', "Soft deleted product #{$id} ('{$prod['name']}')");

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
