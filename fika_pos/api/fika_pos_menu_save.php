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
$name = trim($input['name'] ?? '');
$category = trim($input['category'] ?? '');
$priceRaw = $input['price'] ?? null;
$imageAlt = isset($input['image_alt']) ? mb_substr(trim($input['image_alt']), 0, 160) : null;
if ($imageAlt === '') $imageAlt = null;
$deleteImage = !empty($input['delete_image']) && ($input['delete_image'] === '1' || $input['delete_image'] === 1 || $input['delete_image'] === true);
$targetBranchId = !empty($input['branch_id']) ? (int)$input['branch_id'] : null;

// Branch Scope check for BM
$user = Auth::user();
if ($targetBranchId !== null) {
    Rbac::assert_branch_access($targetBranchId);
} elseif ($user['branch_id'] !== null) {
    // If user is a branch-bound manager and no branch was specified, scope to their own branch
    $targetBranchId = (int)$user['branch_id'];
    Rbac::assert_branch_access($targetBranchId);
}

// Validation
$errors = [];
if ($name === '') $errors[] = "Product name is required.";
if ($category === '') $errors[] = "Category is required.";
if ($priceRaw === null || $priceRaw === '') $errors[] = "Price is required.";

$priceCentavos = Money::toCentavos($priceRaw);
if ($priceCentavos < 0) {
    $errors[] = "Price cannot be negative.";
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(["error" => implode(" ", $errors)]);
    exit;
}

global $pdo;

try {
    $pdo->beginTransaction();

    // 1. Ensure Category exists
    $catStmt = $pdo->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
    $catStmt->execute([$category]);

    // 2. Handle Image Upload
    $uploadDir = APP_ROOT . '/uploads/menu/';
    $uploadedFilename = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadedFilename = Upload::handle($_FILES['image'], $uploadDir);
        if ($uploadedFilename === null) {
            throw new Exception("Invalid image upload. Must be a valid JPEG or PNG file under 5MB.");
        }
    }

    $existing = null;
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND deleted_at IS NULL FOR UPDATE");
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            throw new Exception("Product not found.");
        }

        // Determine image path
        $newImagePath = $existing['image_path'];
        $newImageAlt = $imageAlt ?? $existing['image_alt'];

        if ($uploadedFilename !== null) {
            // Delete old image file if exists
            if (!empty($existing['image_path'])) {
                $oldFullPath = APP_ROOT . '/' . ltrim($existing['image_path'], '/');
                if (is_file($oldFullPath)) {
                    @unlink($oldFullPath);
                }
            }
            $newImagePath = 'uploads/menu/' . $uploadedFilename;
        } elseif ($deleteImage) {
            if (!empty($existing['image_path'])) {
                $oldFullPath = APP_ROOT . '/' . ltrim($existing['image_path'], '/');
                if (is_file($oldFullPath)) {
                    @unlink($oldFullPath);
                }
            }
            $newImagePath = null;
            $newImageAlt = null;
        }

        // Check for Price Change
        $oldPriceCentavos = (int)$existing['price_cents'];
        $priceChanged = ($oldPriceCentavos !== $priceCentavos);

        $updateStmt = $pdo->prepare("
            UPDATE products 
            SET name = ?, category = ?, price = ?, price_cents = ?, image_path = ?, image_alt = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([
            $name,
            $category,
            Money::toDecimal($priceCentavos),
            $priceCentavos,
            $newImagePath,
            $newImageAlt,
            $id
        ]);

        if ($priceChanged) {
            Audit::log(
                'PRODUCT_PRICE_CHANGED',
                "Changed price for product #{$id} ('{$name}') from " . Money::format($oldPriceCentavos) . " to " . Money::format($priceCentavos)
            );
        }

        $productId = $id;

    } else {
        // Create new Product
        $newImagePath = $uploadedFilename !== null ? 'uploads/menu/' . $uploadedFilename : null;

        $insertStmt = $pdo->prepare("
            INSERT INTO products (name, category, price, price_cents, image_path, image_alt, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");
        $insertStmt->execute([
            $name,
            $category,
            Money::toDecimal($priceCentavos),
            $priceCentavos,
            $newImagePath,
            $imageAlt
        ]);
        $productId = (int)$pdo->lastInsertId();

        // Constraint 1: Automatically insert rows into branch_products for ALL active branches, setting is_active = 1
        $branchesStmt = $pdo->query("SELECT id FROM branches");
        $branches = $branchesStmt->fetchAll(PDO::FETCH_COLUMN);

        $bpStmt = $pdo->prepare("
            INSERT INTO branch_products (branch_id, product_id, is_active)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE is_active = 1
        ");
        foreach ($branches as $bId) {
            $bpStmt->execute([$bId, $productId]);
        }

        // Audit Log initial price
        Audit::log(
            'PRODUCT_PRICE_CHANGED',
            "Initial price for new product #{$productId} ('{$name}') set to " . Money::format($priceCentavos)
        );
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'id' => $productId,
        'name' => $name,
        'price_cents' => $priceCentavos,
        'price_formatted' => Money::format($priceCentavos)
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
