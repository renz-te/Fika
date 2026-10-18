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

$productId = isset($input['product_id']) ? (int)$input['product_id'] : 0;
if ($productId <= 0) {
    http_response_code(400);
    exit(json_encode(["error" => "Invalid product ID."]));
}

$user = Auth::user();
$targetBranchId = isset($input['branch_id']) && $input['branch_id'] !== '' ? (int)$input['branch_id'] : null;

if ($targetBranchId !== null) {
    Rbac::assert_branch_access($targetBranchId);
} elseif ($user['branch_id'] !== null) {
    $targetBranchId = (int)$user['branch_id'];
    Rbac::assert_branch_access($targetBranchId);
}

global $pdo;

try {
    // Check product exists
    $prodStmt = $pdo->prepare("SELECT id, is_active FROM products WHERE id = ? AND deleted_at IS NULL");
    $prodStmt->execute([$productId]);
    $prod = $prodStmt->fetch();
    if (!$prod) {
        throw new Exception("Product not found.");
    }

    if ($targetBranchId !== null) {
        // Branch-specific availability toggle
        $bpStmt = $pdo->prepare("SELECT is_active FROM branch_products WHERE branch_id = ? AND product_id = ?");
        $bpStmt->execute([$targetBranchId, $productId]);
        $current = $bpStmt->fetch();

        $desiredStatus = isset($input['is_active']) 
            ? ((int)$input['is_active'] ? 1 : 0)
            : ($current ? ($current['is_active'] ? 0 : 1) : 0);

        $upsertStmt = $pdo->prepare("
            INSERT INTO branch_products (branch_id, product_id, is_active)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE is_active = VALUES(is_active)
        ");
        $upsertStmt->execute([$targetBranchId, $productId, $desiredStatus]);

        echo json_encode([
            'success' => true,
            'product_id' => $productId,
            'branch_id' => $targetBranchId,
            'is_active' => $desiredStatus
        ]);

    } else {
        // Global availability toggle (Admin only when no branch specified)
        $desiredStatus = isset($input['is_active'])
            ? ((int)$input['is_active'] ? 1 : 0)
            : ($prod['is_active'] ? 0 : 1);

        $updateStmt = $pdo->prepare("UPDATE products SET is_active = ?, updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$desiredStatus, $productId]);

        echo json_encode([
            'success' => true,
            'product_id' => $productId,
            'is_active' => $desiredStatus
        ]);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
