<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
$user = Auth::user();
global $pdo;

try {
    Csrf::requireValid();
    Rbac::require_permission('inventory.adjust');

    $productId = (int)($_POST['product_id'] ?? $_POST['id'] ?? 0);
    if ($productId <= 0) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'error' => 'Valid product ID is required.']));
    }

    $checkStmt = $pdo->prepare("SELECT id, name, image_path FROM products WHERE id = ? AND deleted_at IS NULL");
    $checkStmt->execute([$productId]);
    $product = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'error' => 'Product not found.']));
    }

    $uploadedPath = null;
    $file = $_FILES['product_image'] ?? $_FILES['image'] ?? null;
    if ($file && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK) {
        $destinationDir = APP_ROOT . '/public/uploads/menu';
        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        $filename = Upload::handle($file, $destinationDir);
        if ($filename === null) {
            http_response_code(400);
            exit(json_encode(['success' => false, 'error' => 'Invalid image. Must be JPEG, PNG, or WebP under 5MB.']));
        }

        $uploadedPath = '/public/uploads/menu/' . $filename;

        // Clean up previous image if it was in public/uploads/menu
        if (!empty($product['image_path'])) {
            $oldPath = APP_ROOT . '/' . ltrim($product['image_path'], '/');
            if (is_file($oldPath) && strpos(ltrim($product['image_path'], '/'), 'public/uploads/menu/') === 0) {
                @unlink($oldPath);
            }
        }

        $updateStmt = $pdo->prepare("UPDATE products SET image_path = ?, updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$uploadedPath, $productId]);

        Audit::log('PRODUCT_IMAGE_UPLOADED', "Saved image for product #{$productId} ({$product['name']}): {$uploadedPath}", $user['id']);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Recipe and product details saved successfully.',
        'image_path' => $uploadedPath ?? $product['image_path']
    ]);
    exit;

} catch (\Throwable $e) {
    error_log("Recipe Save API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
