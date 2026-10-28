<?php

class InventoryDeduction {

    /**
     * Compute total ingredient requirements for an items payload (products + modifiers)
     * Returns an array: [ inventory_id => [ 'inventory_id' => ..., 'total_qty' => ..., 'item_name' => ..., 'unit_cost' => ... ] ]
     */
    public static function calculateRequiredIngredients(array $items): array {
        global $pdo;

        $productRecipeStmt = $pdo->prepare("
            SELECT r.inventory_id, r.quantity_required, ii.item_name, ii.unit_cost
            FROM recipes r
            JOIN inventory_items ii ON ii.id = r.inventory_id
            WHERE r.product_id = ? AND ii.deleted_at IS NULL
        ");

        $modifierRecipeStmt = $pdo->prepare("
            SELECT r.inventory_id, r.quantity_required, ii.item_name, ii.unit_cost
            FROM recipes r
            JOIN inventory_items ii ON ii.id = r.inventory_id
            WHERE r.modifier_id = ? AND ii.deleted_at IS NULL
        ");

        $requirements = [];

        foreach ($items as $item) {
            $prodId = (int)($item['id'] ?? $item['product_id'] ?? 0);
            $qty = (int)($item['qty'] ?? $item['quantity'] ?? 1);
            if ($prodId <= 0 || $qty <= 0) continue;

            // Product base recipe
            $productRecipeStmt->execute([$prodId]);
            $prodIngredients = $productRecipeStmt->fetchAll();

            foreach ($prodIngredients as $ing) {
                $invId = (int)$ing['inventory_id'];
                $amount = (float)$ing['quantity_required'] * $qty;
                if (!isset($requirements[$invId])) {
                    $requirements[$invId] = [
                        'inventory_id' => $invId,
                        'total_qty' => 0.0,
                        'item_name' => $ing['item_name'],
                        'unit_cost' => (float)$ing['unit_cost']
                    ];
                }
                $requirements[$invId]['total_qty'] += $amount;
            }

            // Modifier recipes
            if (!empty($item['modifiers']) && is_array($item['modifiers'])) {
                foreach ($item['modifiers'] as $modId) {
                    $modId = (int)$modId;
                    if ($modId <= 0) continue;

                    $modifierRecipeStmt->execute([$modId]);
                    $modIngredients = $modifierRecipeStmt->fetchAll();

                    foreach ($modIngredients as $ing) {
                        $invId = (int)$ing['inventory_id'];
                        $amount = (float)$ing['quantity_required'] * $qty;
                        if (!isset($requirements[$invId])) {
                            $requirements[$invId] = [
                                'inventory_id' => $invId,
                                'total_qty' => 0.0,
                                'item_name' => $ing['item_name'],
                                'unit_cost' => (float)$ing['unit_cost']
                            ];
                        }
                        $requirements[$invId]['total_qty'] += $amount;
                    }
                }
            }
        }

        return $requirements;
    }

    /**
     * Pre-check that branch has sufficient stock for all requirements
     * Locks rows with FOR UPDATE to prevent race conditions
     */
    public static function checkStockAvailability(int $branchId, array $requirements): void {
        global $pdo;

        $stockStmt = $pdo->prepare("
            SELECT current_stock 
            FROM inventory_stock 
            WHERE branch_id = ? AND inventory_id = ? 
            FOR UPDATE
        ");

        foreach ($requirements as $invId => $req) {
            $stockStmt->execute([$branchId, $invId]);
            $stockRow = $stockStmt->fetch();
            $available = $stockRow ? (float)$stockRow['current_stock'] : 0.0;

            if ($available < $req['total_qty']) {
                throw new \Exception("Insufficient stock for {$req['item_name']}. Required: {$req['total_qty']}, Available: {$available}");
            }
        }
    }

    /**
     * Deduct inventory for an order idempotently with race condition protection.
     * Constraint 2: Attempts INSERT into order_ledger_deductions FIRST.
     * Returns true if deducted or already deducted; throws Exception on failure.
     */
    public static function deductOrder(mixed $orderId, mixed $branchId = null, ?int $userId = null, string $orderNumber = ''): bool {
        global $pdo;

        // Support both deductOrder($pdo, $orderId, $branchId, $userId) and deductOrder($orderId, $branchId, $userId, $orderNumber)
        if ($orderId instanceof \PDO) {
            $pdo = $orderId;
            $orderId = (int)$branchId;
            $branchId = (int)$userId;
            $userId = !empty($orderNumber) && is_numeric($orderNumber) ? (int)$orderNumber : 1;
            $orderNumber = '';
        } else {
            $orderId = (int)$orderId;
            $branchId = (int)$branchId;
        }

        $userId = $userId ?: 1;

        if (empty($orderNumber)) {
            $ordStmt = $pdo->prepare("SELECT order_number FROM orders WHERE id = ?");
            $ordStmt->execute([$orderId]);
            $orderNumber = (string)($ordStmt->fetchColumn() ?: ('ORD-' . $orderId));
        }

        // 1. Race condition protection: Attempt INSERT FIRST
        try {
            $deductStmt = $pdo->prepare("
                INSERT INTO order_ledger_deductions (order_id, branch_id, deducted_at)
                VALUES (?, ?, NOW())
            ");
            $deductStmt->execute([$orderId, $branchId]);
        } catch (\PDOException $e) {
            // Error 23000 / 1062 = Duplicate entry for key uq_order_deductions_order_id
            if ($e->getCode() == 23000 || ($e->errorInfo[1] ?? 0) == 1062) {
                // Silently skip deduction and return true
                return true;
            }
            throw $e;
        }

        // 2. Fetch order items and their modifiers from DB
        $itemsStmt = $pdo->prepare("SELECT id, product_id, quantity FROM order_items WHERE order_id = ?");
        $itemsStmt->execute([$orderId]);
        $orderItems = $itemsStmt->fetchAll();

        $modStmt = $pdo->prepare("SELECT modifier_id FROM order_item_modifiers WHERE order_item_id = ?");

        $itemsPayload = [];
        foreach ($orderItems as $oi) {
            $modStmt->execute([$oi['id']]);
            $modIds = $modStmt->fetchAll(PDO::FETCH_COLUMN);

            $itemsPayload[] = [
                'product_id' => $oi['product_id'],
                'qty' => $oi['quantity'],
                'modifiers' => $modIds
            ];
        }

        $requirements = self::calculateRequiredIngredients($itemsPayload);
        if (empty($requirements)) {
            return true; // No recipe mapped, nothing to deduct
        }

        // 3. Pre-check stock with FOR UPDATE
        self::checkStockAvailability($branchId, $requirements);

        // 4. Subtract from inventory_stock & append to inventory_ledger
        $stockUpdate = $pdo->prepare("
            UPDATE inventory_stock 
            SET current_stock = current_stock - ? 
            WHERE branch_id = ? AND inventory_id = ?
        ");

        $ledgerInsert = $pdo->prepare("
            INSERT INTO inventory_ledger (
                branch_id, inventory_id, type, quantity, unit_cost_snapshot, user_id, order_id, remarks, created_at
            ) VALUES (?, ?, 'SALE', ?, ?, ?, ?, ?, NOW())
        ");

        foreach ($requirements as $invId => $req) {
            $usedQty = $req['total_qty'];
            $stockUpdate->execute([$usedQty, $branchId, $invId]);

            // Negative quantity for deduction
            $ledgerInsert->execute([
                $branchId,
                $invId,
                -$usedQty,
                $req['unit_cost'],
                $userId,
                $orderId,
                "Order #{$orderNumber} sale deduction"
            ]);
        }

        return true;
    }

    /**
     * Restore stock when an order is voided or refunded.
     * Restores BOTH product and modifier ingredients.
     */
    public static function restoreOrder(int $orderId, int $branchId, ?int $userId = null, string $orderNumber = ''): bool {
        global $pdo;

        $userId = $userId ?: 1;

        // 1. Check if order was ever deducted
        $checkStmt = $pdo->prepare("SELECT id FROM order_ledger_deductions WHERE order_id = ? FOR UPDATE");
        $checkStmt->execute([$orderId]);
        $deductionRecord = $checkStmt->fetch();

        if (!$deductionRecord) {
            // Never deducted (or already restored), nothing to restore
            return true;
        }

        // 2. Fetch order items and their modifiers
        $itemsStmt = $pdo->prepare("SELECT id, product_id, quantity FROM order_items WHERE order_id = ?");
        $itemsStmt->execute([$orderId]);
        $orderItems = $itemsStmt->fetchAll();

        $modStmt = $pdo->prepare("SELECT modifier_id FROM order_item_modifiers WHERE order_item_id = ?");

        $itemsPayload = [];
        foreach ($orderItems as $oi) {
            $modStmt->execute([$oi['id']]);
            $modIds = $modStmt->fetchAll(PDO::FETCH_COLUMN);

            $itemsPayload[] = [
                'product_id' => $oi['product_id'],
                'qty' => $oi['quantity'],
                'modifiers' => $modIds
            ];
        }

        $requirements = self::calculateRequiredIngredients($itemsPayload);

        // 3. Delete from order_ledger_deductions to prevent double-restoration
        $delStmt = $pdo->prepare("DELETE FROM order_ledger_deductions WHERE order_id = ?");
        $delStmt->execute([$orderId]);

        if (empty($requirements)) {
            return true;
        }

        // 4. Restore stock in inventory_stock & write VOID_RESTOCK to inventory_ledger
        $stockRestore = $pdo->prepare("
            UPDATE inventory_stock 
            SET current_stock = current_stock + ? 
            WHERE branch_id = ? AND inventory_id = ?
        ");

        $ledgerInsert = $pdo->prepare("
            INSERT INTO inventory_ledger (
                branch_id, inventory_id, type, quantity, unit_cost_snapshot, user_id, order_id, remarks, created_at
            ) VALUES (?, ?, 'VOID_RESTOCK', ?, ?, ?, ?, ?, NOW())
        ");

        foreach ($requirements as $invId => $req) {
            $restoredQty = $req['total_qty'];
            $stockRestore->execute([$restoredQty, $branchId, $invId]);

            // Positive quantity for restoration
            $ledgerInsert->execute([
                $branchId,
                $invId,
                $restoredQty,
                $req['unit_cost'],
                $userId,
                $orderId,
                "Order #{$orderNumber} void/refund stock restoration"
            ]);
        }

        return true;
    }
}
