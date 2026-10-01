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
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
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
            if ($action === 'create') {
                $stmt = $posPdo->prepare("INSERT INTO products (name, category, price, allowed_modifier_groups) VALUES (?, ?, ?, ?)");
                $allowed = isset($input['allowed_modifier_groups']) ? implode(',', $input['allowed_modifier_groups']) : '';
                $stmt->execute([$input['name'], $input['category'], $input['price'], $allowed]);
                echo json_encode(['success' => true, 'id' => $posPdo->lastInsertId()]);
            } elseif ($action === 'update') {
                $stmt = $posPdo->prepare("UPDATE products SET name = ?, category = ?, price = ?, allowed_modifier_groups = ? WHERE id = ?");
                $allowed = isset($input['allowed_modifier_groups']) ? implode(',', $input['allowed_modifier_groups']) : '';
                $stmt->execute([$input['name'], $input['category'], $input['price'], $allowed, $input['id']]);
                echo json_encode(['success' => true]);
            } elseif ($action === 'delete') {
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
