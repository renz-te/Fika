<?php
require_once __DIR__ . '/../init.php';

// Only Admin and Head Barista can manage the menu
if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!in_array($_SESSION['user']['role'], ['Super Admin', 'Admin', 'Head Barista'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Connect to POS database
require_once __DIR__ . '/../../cafe_pos/database.php';
$posPdo = $pdo;

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $type = $_GET['type'] ?? 'products';
        
        if ($type === 'products') {
            $stmt = $posPdo->query("
                SELECT p.*, 
                IFNULL((SELECT SUM(r.quantity * i.unit_cost) 
                        FROM recipes r 
                        JOIN inventory i ON r.inventory_id = i.id 
                        WHERE r.product_id = p.id), 0) AS calculated_cogs
                FROM products p 
                ORDER BY p.category, p.name
            ");
            $products = $stmt->fetchAll();
            foreach ($products as &$prod) {
                if (!empty($prod['image_path'])) {
                    $prod['image_url'] = '../' . ltrim($prod['image_path'], '/');
                } else {
                    $prod['image_url'] = null;
                }
            }
            echo json_encode(['success' => true, 'data' => $products]);
        } elseif ($type === 'modifiers') {
            $stmt = $posPdo->query("SELECT * FROM modifiers ORDER BY modifier_group, name");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } else {
            throw new Exception("Invalid type requested.");
        }
    } elseif ($method === 'POST') {
        $rawInput = json_decode(file_get_contents('php://input'), true);
        if (is_array($rawInput)) {
            $input = array_merge($_POST, $rawInput);
        } else {
            $input = $_POST;
        }

        // CSRF Verification for state changes
        $csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verify_csrf_token($csrfToken)) {
            throw new Exception("CSRF verification failed. Please refresh and try again.");
        }
        
        $type = $input['type'] ?? '';
        $action = $input['action'] ?? '';
        
        if (!$type || !$action) {
            throw new Exception("Missing type or action.");
        }

        $uploadDir = dirname(__DIR__, 2) . '/uploads/menu/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Helper to safely delete an existing image file from uploads/menu/
        $deleteOldImageFile = function($relPath) use ($uploadDir) {
            if (empty($relPath)) return;
            $filename = basename($relPath);
            $fullPath = $uploadDir . $filename;
            if (is_file($fullPath) && file_exists($fullPath)) {
                @unlink($fullPath);
            }
        };

        // Helper to validate and save uploaded image
        $handleUploadedImage = function($itemId) use ($uploadDir) {
            if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
                return null;
            }
            
            $file = $_FILES['image'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("File upload failed with error code: " . $file['error']);
            }

            // Max file size: 2 MB (2,097,152 bytes)
            if ($file['size'] > 2 * 1024 * 1024) {
                throw new Exception("Uploaded file exceeds maximum limit of 2 MB.");
            }

            // Strict MIME type check using finfo
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];

            if (!isset($allowedMimes[$mimeType])) {
                throw new Exception("Invalid file type ($mimeType). Only JPG, PNG, and WebP images are allowed.");
            }

            $ext = $allowedMimes[$mimeType];
            $randomHash = bin2hex(random_bytes(6));
            $filename = "{$itemId}_{$randomHash}.{$ext}";
            $destination = $uploadDir . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new Exception("Failed to save uploaded image to destination directory.");
            }

            return 'uploads/menu/' . $filename;
        };
        
        if ($type === 'products') {
            $name = trim($input['name'] ?? '');
            $category = trim($input['category'] ?? '');
            $price = isset($input['price']) ? (float)$input['price'] : 0.00;
            $imageAlt = isset($input['image_alt']) ? mb_substr(trim($input['image_alt']), 0, 160) : null;
            if ($imageAlt === '') $imageAlt = null;

            if ($action === 'create') {
                if ($name === '' || $category === '') {
                    throw new Exception("Product name and category are required.");
                }

                $allowed = isset($input['allowed_modifier_groups']) ? 
                    (is_array($input['allowed_modifier_groups']) ? implode(',', $input['allowed_modifier_groups']) : (string)$input['allowed_modifier_groups']) 
                    : '';

                $posPdo->beginTransaction();
                $stmt = $posPdo->prepare("INSERT INTO products (name, category, price, allowed_modifier_groups, image_path, image_alt) VALUES (?, ?, ?, ?, NULL, ?)");
                $stmt->execute([$name, $category, $price, $allowed, $imageAlt]);
                $newId = $posPdo->lastInsertId();

                $uploadedPath = $handleUploadedImage($newId);
                if ($uploadedPath !== null) {
                    $upStmt = $posPdo->prepare("UPDATE products SET image_path = ? WHERE id = ?");
                    $upStmt->execute([$uploadedPath, $newId]);
                }
                $posPdo->commit();

                echo json_encode(['success' => true, 'id' => $newId]);

            } elseif ($action === 'update') {
                $id = (int)($input['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception("Invalid product ID.");
                }

                $allowed = isset($input['allowed_modifier_groups']) ? 
                    (is_array($input['allowed_modifier_groups']) ? implode(',', $input['allowed_modifier_groups']) : (string)$input['allowed_modifier_groups']) 
                    : '';

                // Fetch existing product image details
                $oldStmt = $posPdo->prepare("SELECT image_path, image_alt FROM products WHERE id = ?");
                $oldStmt->execute([$id]);
                $oldData = $oldStmt->fetch();
                $currentImagePath = $oldData['image_path'] ?? null;

                $deleteImageFlag = !empty($input['delete_image']) && $input['delete_image'] === '1';
                $uploadedPath = $handleUploadedImage($id);

                $newImagePath = $currentImagePath;
                $newImageAlt = $imageAlt;

                if ($uploadedPath !== null) {
                    // New image uploaded, delete previous
                    $deleteOldImageFile($currentImagePath);
                    $newImagePath = $uploadedPath;
                } elseif ($deleteImageFlag) {
                    // Explicit delete requested
                    $deleteOldImageFile($currentImagePath);
                    $newImagePath = null;
                    $newImageAlt = null;
                }

                $stmt = $posPdo->prepare("UPDATE products SET name = ?, category = ?, price = ?, allowed_modifier_groups = ?, image_path = ?, image_alt = ? WHERE id = ?");
                $stmt->execute([$name, $category, $price, $allowed, $newImagePath, $newImageAlt, $id]);

                echo json_encode(['success' => true]);

            } elseif ($action === 'delete') {
                $id = (int)($input['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception("Invalid product ID.");
                }

                // Delete image file when product is deleted
                $oldStmt = $posPdo->prepare("SELECT image_path FROM products WHERE id = ?");
                $oldStmt->execute([$id]);
                $oldPath = $oldStmt->fetchColumn();
                $deleteOldImageFile($oldPath);
                
                $stmt = $posPdo->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true]);
            }
        } elseif ($type === 'modifiers') {
            $name = trim($input['name'] ?? '');
            $group = trim($input['modifier_group'] ?? '');
            $adj = isset($input['price_adjustment']) ? (float)$input['price_adjustment'] : 0.00;

            if ($action === 'create') {
                $stmt = $posPdo->prepare("INSERT INTO modifiers (name, price_adjustment, modifier_group) VALUES (?, ?, ?)");
                $stmt->execute([$name, $adj, $group]);
                echo json_encode(['success' => true, 'id' => $posPdo->lastInsertId()]);
            } elseif ($action === 'update') {
                $id = (int)($input['id'] ?? 0);
                $stmt = $posPdo->prepare("UPDATE modifiers SET name = ?, price_adjustment = ?, modifier_group = ? WHERE id = ?");
                $stmt->execute([$name, $adj, $group, $id]);
                echo json_encode(['success' => true]);
            } elseif ($action === 'delete') {
                $id = (int)($input['id'] ?? 0);
                $stmt = $posPdo->prepare("DELETE FROM modifiers WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true]);
            }
        } else {
            throw new Exception("Invalid type provided.");
        }
    }
} catch (Exception $e) {
    if (isset($posPdo) && $posPdo->inTransaction()) {
        $posPdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
