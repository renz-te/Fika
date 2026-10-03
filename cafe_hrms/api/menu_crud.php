<?php
require_once __DIR__ . '/../../Fika/cafe_hrms/init.php';

// Only Admin and Head Barista can manage the menu
if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['Super Admin', 'Admin', 'Head Barista'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Connect to POS database
try {
    $posPdo = new PDO("mysql:host=127.0.0.1;dbname=cafe_pos;charset=utf8mb4", "root", "");
    $posPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not connect to POS database: ' . $e->getMessage()]);
    exit;
}

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
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($products as &$prod) {
                if ($prod['image_path']) {
                    // Prepend the URL base for the frontend
                    $prod['image_url'] = '../cafe_pos/' . $prod['image_path'];
                } else {
                    $prod['image_url'] = null;
                }
            }
            echo json_encode(['success' => true, 'data' => $products]);
        } elseif ($type === 'modifiers') {
            $stmt = $posPdo->query("SELECT * FROM modifiers ORDER BY modifier_group, name");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } else {
            throw new Exception("Invalid type requested.");
        }
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;
        
        $type = $input['type'] ?? '';
        $action = $input['action'] ?? '';
        
        if (!$type || !$action) {
            throw new Exception("Missing type or action.");
        }
        
        if ($type === 'products') {
            // Function to process uploaded image
            $processImage = function() {
                if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                    return null;
                }
                $file = $_FILES['image'];
                $tmpPath = $file['tmp_name'];
                
                // Strict MIME validation
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $tmpPath);
                finfo_close($finfo);
                
                if (!in_array($mimeType, ['image/jpeg', 'image/png'])) {
                    throw new Exception("Invalid image format. Only JPG and PNG are allowed.");
                }

                // Load image via GD
                $sourceImage = null;
                if ($mimeType === 'image/jpeg') {
                    $sourceImage = imagecreatefromjpeg($tmpPath);
                } elseif ($mimeType === 'image/png') {
                    $sourceImage = imagecreatefrompng($tmpPath);
                }
                
                if (!$sourceImage) {
                    throw new Exception("Failed to process image.");
                }

                // Get original dimensions
                $origWidth = imagesx($sourceImage);
                $origHeight = imagesy($sourceImage);
                
                // Calculate new dimensions (max 400x400)
                $maxWidth = 400;
                $maxHeight = 400;
                
                $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
                $newWidth = $origWidth;
                $newHeight = $origHeight;
                
                if ($ratio < 1) {
                    $newWidth = (int)($origWidth * $ratio);
                    $newHeight = (int)($origHeight * $ratio);
                }

                // Create new image and resize
                $destImage = imagecreatetruecolor($newWidth, $newHeight);
                
                // Handle transparency for PNGs before conversion to WebP
                if ($mimeType === 'image/png') {
                    imagealphablending($destImage, false);
                    imagesavealpha($destImage, true);
                    $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
                    imagefilledrectangle($destImage, 0, 0, $newWidth, $newHeight, $transparent);
                }

                imagecopyresampled($destImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

                // Save as WebP
                $uploadDir = __DIR__ . '/../../../Fika/cafe_pos/assets/products/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $filename = 'prod_' . uniqid() . '.webp';
                $fullPath = $uploadDir . $filename;
                
                if (!imagewebp($destImage, $fullPath, 80)) {
                    throw new Exception("Failed to save WebP image.");
                }

                imagedestroy($sourceImage);
                imagedestroy($destImage);

                return 'assets/products/' . $filename;
            };

            if ($action === 'create') {
                $imagePath = $processImage();
                $stmt = $posPdo->prepare("INSERT INTO products (name, category, price, allowed_modifier_groups, image_path) VALUES (?, ?, ?, ?, ?)");
                $allowed = isset($input['allowed_modifier_groups']) ? implode(',', (array)$input['allowed_modifier_groups']) : '';
                $stmt->execute([$input['name'], $input['category'], $input['price'], $allowed, $imagePath]);
                echo json_encode(['success' => true, 'id' => $posPdo->lastInsertId()]);
            } elseif ($action === 'update') {
                $imagePath = $processImage();
                
                if ($imagePath) {
                    // First get old image to delete it if it exists
                    $oldStmt = $posPdo->prepare("SELECT image_path FROM products WHERE id = ?");
                    $oldStmt->execute([$input['id']]);
                    $oldImage = $oldStmt->fetchColumn();
                    if ($oldImage) {
                        $oldFullPath = __DIR__ . '/../../../Fika/cafe_pos/' . $oldImage;
                        if (file_exists($oldFullPath)) {
                            unlink($oldFullPath);
                        }
                    }
                    
                    $stmt = $posPdo->prepare("UPDATE products SET name = ?, category = ?, price = ?, allowed_modifier_groups = ?, image_path = ? WHERE id = ?");
                    $allowed = isset($input['allowed_modifier_groups']) ? implode(',', (array)$input['allowed_modifier_groups']) : '';
                    $stmt->execute([$input['name'], $input['category'], $input['price'], $allowed, $imagePath, $input['id']]);
                } else {
                    $stmt = $posPdo->prepare("UPDATE products SET name = ?, category = ?, price = ?, allowed_modifier_groups = ? WHERE id = ?");
                    $allowed = isset($input['allowed_modifier_groups']) ? implode(',', (array)$input['allowed_modifier_groups']) : '';
                    $stmt->execute([$input['name'], $input['category'], $input['price'], $allowed, $input['id']]);
                }
                
                echo json_encode(['success' => true]);
            } elseif ($action === 'delete') {
                // Delete image when product is deleted
                $oldStmt = $posPdo->prepare("SELECT image_path FROM products WHERE id = ?");
                $oldStmt->execute([$input['id']]);
                $oldImage = $oldStmt->fetchColumn();
                if ($oldImage) {
                    $oldFullPath = __DIR__ . '/../../../Fika/cafe_pos/' . $oldImage;
                    if (file_exists($oldFullPath)) {
                        unlink($oldFullPath);
                    }
                }
                
                $stmt = $posPdo->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$input['id']]);
                echo json_encode(['success' => true]);
            }
        } elseif ($type === 'modifiers') {
            if ($action === 'create') {
                $stmt = $posPdo->prepare("INSERT INTO modifiers (name, price_adjustment, modifier_group) VALUES (?, ?, ?)");
                $stmt->execute([$input['name'], $input['price_adjustment'], $input['modifier_group']]);
                echo json_encode(['success' => true, 'id' => $posPdo->lastInsertId()]);
            } elseif ($action === 'update') {
                $stmt = $posPdo->prepare("UPDATE modifiers SET name = ?, price_adjustment = ?, modifier_group = ? WHERE id = ?");
                $stmt->execute([$input['name'], $input['price_adjustment'], $input['modifier_group'], $input['id']]);
                echo json_encode(['success' => true]);
            } elseif ($action === 'delete') {
                $stmt = $posPdo->prepare("DELETE FROM modifiers WHERE id = ?");
                $stmt->execute([$input['id']]);
                echo json_encode(['success' => true]);
            }
        } else {
            throw new Exception("Invalid type provided.");
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
