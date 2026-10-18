<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
$user = Auth::user();
global $pdo;

$rawInput = json_decode(file_get_contents('php://input'), true) ?? [];
$input = is_array($rawInput) ? array_merge($_POST, $rawInput) : $_POST;
$action = $input['action'] ?? $_GET['action'] ?? 'list';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' || in_array($action, ['list', 'cost_report'], true)) {
        Rbac::require_permission('inventory.view');

        if ($action === 'cost_report') {
            // Fetch all active products with ingredients and calculated COGS
            $prodStmt = $pdo->query("
                SELECT id, name, category, price 
                FROM products 
                WHERE is_active = 1 AND deleted_at IS NULL 
                ORDER BY category ASC, name ASC
            ");
            $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

            $recStmt = $pdo->prepare("
                SELECT r.id as recipe_id, r.inventory_id, r.quantity_required, 
                       ii.item_name, ii.unit, ii.unit_cost
                FROM recipes r
                JOIN inventory_items ii ON ii.id = r.inventory_id
                WHERE r.product_id = ? AND ii.deleted_at IS NULL
                ORDER BY ii.item_name ASC
            ");

            $report = [];
            foreach ($products as $prod) {
                $recStmt->execute([$prod['id']]);
                $ingredients = $recStmt->fetchAll(PDO::FETCH_ASSOC);

                $cogs = 0.00;
                foreach ($ingredients as &$ing) {
                    $qty = (float)$ing['quantity_required'];
                    $unitCost = (float)$ing['unit_cost'];
                    $subtotal = $qty * $unitCost;
                    $ing['subtotal_cost'] = round($subtotal, 2);
                    $cogs += $subtotal;
                }
                unset($ing);

                $retailPrice = (float)$prod['price'];
                $profitMargin = $retailPrice > 0 ? (($retailPrice - $cogs) / $retailPrice) * 100 : 0;

                $report[] = [
                    'product_id' => (int)$prod['id'],
                    'product_name' => $prod['name'],
                    'category' => $prod['category'],
                    'price' => $retailPrice,
                    'cogs' => round($cogs, 2),
                    'profit_margin_pct' => round($profitMargin, 1),
                    'ingredients' => $ingredients
                ];
            }

            echo json_encode(['success' => true, 'data' => $report]);
            exit;
        }

        // Action: list
        // 1. Products with mapped recipes
        $prodStmt = $pdo->query("
            SELECT id, name, category, price 
            FROM products 
            WHERE is_active = 1 AND deleted_at IS NULL 
            ORDER BY name ASC
        ");
        $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

        $prodRecipesStmt = $pdo->prepare("
            SELECT r.id, r.inventory_id, r.quantity_required, ii.item_name, ii.unit, ii.unit_cost
            FROM recipes r
            JOIN inventory_items ii ON ii.id = r.inventory_id
            WHERE r.product_id = ? AND ii.deleted_at IS NULL
            ORDER BY ii.item_name ASC
        ");

        foreach ($products as &$p) {
            $prodRecipesStmt->execute([$p['id']]);
            $p['ingredients'] = $prodRecipesStmt->fetchAll(PDO::FETCH_ASSOC);
            $totalCost = 0.0;
            foreach ($p['ingredients'] as &$ing) {
                $ing['quantity'] = (float)$ing['quantity_required'];
                $ing['unit_cost'] = (float)$ing['unit_cost'];
                $ing['subtotal_cost'] = round($ing['quantity'] * $ing['unit_cost'], 2);
                $totalCost += $ing['subtotal_cost'];
            }
            unset($ing);
            $p['cogs'] = round($totalCost, 2);
        }
        unset($p);

        // 2. Modifiers with mapped recipes
        $modStmt = $pdo->query("
            SELECT id, name, price 
            FROM modifiers 
            WHERE is_active = 1 AND deleted_at IS NULL 
            ORDER BY name ASC
        ");
        $modifiers = $modStmt->fetchAll(PDO::FETCH_ASSOC);

        $modRecipesStmt = $pdo->prepare("
            SELECT r.id, r.inventory_id, r.quantity_required, ii.item_name, ii.unit, ii.unit_cost
            FROM recipes r
            JOIN inventory_items ii ON ii.id = r.inventory_id
            WHERE r.modifier_id = ? AND ii.deleted_at IS NULL
            ORDER BY ii.item_name ASC
        ");

        foreach ($modifiers as &$m) {
            $modRecipesStmt->execute([$m['id']]);
            $m['ingredients'] = $modRecipesStmt->fetchAll(PDO::FETCH_ASSOC);
            $totalCost = 0.0;
            foreach ($m['ingredients'] as &$ing) {
                $ing['quantity'] = (float)$ing['quantity_required'];
                $ing['unit_cost'] = (float)$ing['unit_cost'];
                $ing['subtotal_cost'] = round($ing['quantity'] * $ing['unit_cost'], 2);
                $totalCost += $ing['subtotal_cost'];
            }
            unset($ing);
            $m['cogs'] = round($totalCost, 2);
        }
        unset($m);

        // 3. Inventory items for dropdown
        $invStmt = $pdo->query("
            SELECT id, item_name, unit, unit_cost 
            FROM inventory_items 
            WHERE deleted_at IS NULL 
            ORDER BY item_name ASC
        ");
        $inventoryItems = $invStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'products' => $products,
            'modifiers' => $modifiers,
            'inventory_items' => $inventoryItems
        ]);
        exit;
    }

    // Mutating actions require valid CSRF & inventory.adjust permission
    Csrf::requireValid();
    Rbac::require_permission('inventory.adjust');

    if ($action === 'save_ingredient') {
        $type = trim((string)($input['type'] ?? 'product')); // 'product' or 'modifier'
        $targetId = (int)($input['target_id'] ?? $input['product_id'] ?? $input['modifier_id'] ?? 0);
        $inventoryId = (int)($input['inventory_id'] ?? 0);
        $quantity = (float)($input['quantity'] ?? $input['quantity_required'] ?? 0);

        if ($targetId <= 0 || $inventoryId <= 0 || $quantity <= 0) {
            http_response_code(400);
            exit(json_encode(['success' => false, 'error' => 'Target ID, inventory item, and quantity > 0 are required.']));
        }

        // Validate inventory item exists
        $checkInv = $pdo->prepare("SELECT item_name FROM inventory_items WHERE id = ? AND deleted_at IS NULL");
        $checkInv->execute([$inventoryId]);
        $invItem = $checkInv->fetch();
        if (!$invItem) {
            http_response_code(404);
            exit(json_encode(['success' => false, 'error' => 'Inventory item not found or deactivated.']));
        }

        $productId = ($type === 'product') ? $targetId : null;
        $modifierId = ($type === 'modifier') ? $targetId : null;

        if ($type === 'product') {
            $stmt = $pdo->prepare("
                INSERT INTO recipes (product_id, modifier_id, inventory_id, quantity_required)
                VALUES (?, NULL, ?, ?)
                ON DUPLICATE KEY UPDATE quantity_required = VALUES(quantity_required)
            ");
            $stmt->execute([$productId, $inventoryId, $quantity]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO recipes (product_id, modifier_id, inventory_id, quantity_required)
                VALUES (NULL, ?, ?, ?)
                ON DUPLICATE KEY UPDATE quantity_required = VALUES(quantity_required)
            ");
            $stmt->execute([$modifierId, $inventoryId, $quantity]);
        }

        Audit::log('RECIPE_INGREDIENT_SAVED', "Saved recipe for {$type} #{$targetId}: item #{$inventoryId} x {$quantity}", $user['id']);

        echo json_encode([
            'success' => true,
            'message' => 'Recipe ingredient saved successfully.'
        ]);
        exit;

    } elseif ($action === 'delete_ingredient') {
        $recipeId = (int)($input['id'] ?? 0);
        if ($recipeId <= 0) {
            http_response_code(400);
            exit(json_encode(['success' => false, 'error' => 'Valid recipe ID required.']));
        }

        $stmt = $pdo->prepare("DELETE FROM recipes WHERE id = ?");
        $stmt->execute([$recipeId]);

        Audit::log('RECIPE_INGREDIENT_DELETED', "Deleted recipe ingredient row #{$recipeId}", $user['id']);

        echo json_encode(['success' => true, 'message' => 'Recipe ingredient removed.']);
        exit;

    } else {
        http_response_code(400);
        exit(json_encode(['success' => false, 'error' => 'Invalid action.']));
    }

} catch (\Throwable $e) {
    error_log("Recipe API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
