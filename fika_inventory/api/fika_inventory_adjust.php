<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
$user = Auth::user();

$rawInput = json_decode(file_get_contents('php://input'), true) ?? [];
$input = is_array($rawInput) ? array_merge($_POST, $rawInput) : $_POST;
$action = $input['action'] ?? $_GET['action'] ?? 'submit';

global $pdo;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' || in_array($action, ['list', 'pending_list'], true)) {
        Rbac::require_permission('inventory.view');
        
        $branchId = (int)($input['branch_id'] ?? $_GET['branch_id'] ?? ($user['branch_id'] ?? 1));
        if (!empty($user['branch_id']) && (int)$user['branch_id'] !== $branchId) {
            http_response_code(403);
            exit(json_encode(['success' => false, 'error' => 'Forbidden: wrong branch.']));
        }

        if ($action === 'pending_list') {
            $stmt = $pdo->prepare("
                SELECT a.*, i.item_name, i.unit, u.username as maker_username 
                FROM inventory_adjustments a
                JOIN inventory_items i ON i.id = a.inventory_id
                JOIN users u ON u.id = a.created_by
                WHERE a.branch_id = ? AND a.status = 'PENDING'
                ORDER BY a.created_at ASC
            ");
            $stmt->execute([$branchId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            exit;
        }

        // Action: list
        $stmt = $pdo->prepare("
            SELECT 
                i.id, i.item_name, i.unit, i.unit_cost, i.deleted_at,
                COALESCE(s.low_stock_threshold, 10.00) AS low_stock_threshold,
                COALESCE(SUM(l.quantity), 0) AS current_stock
            FROM inventory_items i
            LEFT JOIN inventory_ledger l ON l.inventory_id = i.id AND l.branch_id = ?
            LEFT JOIN inventory_stock s ON s.inventory_id = i.id AND s.branch_id = ?
            WHERE i.deleted_at IS NULL
            GROUP BY i.id, i.item_name, i.unit, i.unit_cost, i.deleted_at, s.low_stock_threshold
            ORDER BY i.item_name ASC
        ");
        $stmt->execute([$branchId, $branchId]);
        $items = $stmt->fetchAll();

        foreach ($items as &$item) {
            $item['current_stock'] = (float)$item['current_stock'];
            $item['low_stock_threshold'] = (float)$item['low_stock_threshold'];
            $item['is_low_stock'] = ($item['current_stock'] <= $item['low_stock_threshold']);
        }
        unset($item);

        echo json_encode(['success' => true, 'data' => $items]);
        exit;
    }

    // All mutating actions require valid CSRF
    Csrf::requireValid();

    if ($action === 'submit') {
        Rbac::require_permission('inventory.adjust');

        $branchId = (int)($input['branch_id'] ?? ($user['branch_id'] ?? 0));
        $inventoryId = (int)($input['inventory_id'] ?? 0);
        $quantity = (float)($input['quantity'] ?? 0);
        $reason = trim((string)($input['reason'] ?? ''));

        if ($branchId <= 0 || $inventoryId <= 0 || $quantity == 0 || $reason === '') {
            http_response_code(400);
            exit(json_encode(['success' => false, 'error' => 'Branch, item, non-zero quantity, and reason are required.']));
        }

        if (!empty($user['branch_id']) && (int)$user['branch_id'] !== $branchId) {
            http_response_code(403);
            exit(json_encode(['success' => false, 'error' => 'Forbidden: wrong branch.']));
        }

        $pdo->beginTransaction();

        // 1. Fetch item details
        $stmtItem = $pdo->prepare("SELECT id, item_name, unit, unit_cost FROM inventory_items WHERE id = ? AND deleted_at IS NULL");
        $stmtItem->execute([$inventoryId]);
        $item = $stmtItem->fetch();

        if (!$item) {
            $pdo->rollBack();
            http_response_code(404);
            exit(json_encode(['success' => false, 'error' => 'Inventory item not found or deactivated.']));
        }

        // 2. Compute current stock strictly from inventory_ledger
        $stmtStock = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM inventory_ledger WHERE branch_id = ? AND inventory_id = ?");
        $stmtStock->execute([$branchId, $inventoryId]);
        $currentStock = (float)$stmtStock->fetchColumn();
        $newStock = $currentStock + $quantity;

        // 3. Negative stock validation
        if ($newStock < 0) {
            $stmtAllowNeg = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'inventory_allow_negative'");
            $allowNegVal = $stmtAllowNeg ? $stmtAllowNeg->fetchColumn() : '0';
            $allowNegative = ($allowNegVal === '1' || strtolower((string)$allowNegVal) === 'true');

            if (!$allowNegative) {
                $pdo->rollBack();
                http_response_code(400);
                exit(json_encode(['success' => false, 'error' => 'Negative stock is not permitted.']));
            }
        }

        // 4. Financial impact in centavos (Constraint 1: unit_cost is decimal, converted via Money::toCentavos)
        $unitCostCents = Money::toCentavos($item['unit_cost']);
        $totalCostCents = (int)round(abs($quantity) * $unitCostCents);

        // Fetch threshold setting
        $stmtThresh = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'inventory_approval_threshold_cents'");
        $thresholdCents = $stmtThresh ? (int)$stmtThresh->fetchColumn() : 50000;
        if ($thresholdCents <= 0) {
            $thresholdCents = 50000;
        }

        $isAboveThreshold = ($totalCostCents > $thresholdCents);

        if ($isAboveThreshold) {
            // High value: hold in PENDING Maker-Checker queue
            $stmtAdj = $pdo->prepare("
                INSERT INTO inventory_adjustments (
                    branch_id, inventory_id, quantity, unit_cost_snapshot, total_cost_cents, reason, status, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'PENDING', ?, NOW())
            ");
            $stmtAdj->execute([
                $branchId,
                $inventoryId,
                $quantity,
                $item['unit_cost'],
                $totalCostCents,
                $reason,
                $user['id']
            ]);
            $adjId = (int)$pdo->lastInsertId();

            Audit::log('INVENTORY_ADJUSTMENT_SUBMITTED', "Pending adjustment #{$adjId} of {$quantity} for {$item['item_name']} (Total: " . Money::format($totalCostCents) . ")", $user['id'], $branchId);
            $pdo->commit();

            echo json_encode([
                'success' => true,
                'status' => 'PENDING',
                'adjustment_id' => $adjId,
                'message' => 'Adjustment exceeds approval threshold and is pending approval.'
            ]);
            exit;
        }

        // Auto-approved (Constraint 2: audit row created with status=APPROVED and approved_by=created_by)
        $stmtAdj = $pdo->prepare("
            INSERT INTO inventory_adjustments (
                branch_id, inventory_id, quantity, unit_cost_snapshot, total_cost_cents, reason, status, created_by, approved_by, approved_at, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'APPROVED', ?, ?, NOW(), NOW())
        ");
        $stmtAdj->execute([
            $branchId,
            $inventoryId,
            $quantity,
            $item['unit_cost'],
            $totalCostCents,
            $reason,
            $user['id'],
            $user['id']
        ]);
        $adjId = (int)$pdo->lastInsertId();

        // Write row to inventory_ledger
        $stmtLedger = $pdo->prepare("
            INSERT INTO inventory_ledger (branch_id, inventory_id, type, quantity, unit_cost_snapshot, user_id, remarks, created_at)
            VALUES (?, ?, 'Adjustment', ?, ?, ?, ?, NOW())
        ");
        $stmtLedger->execute([
            $branchId,
            $inventoryId,
            $quantity,
            $item['unit_cost'],
            $user['id'],
            $reason
        ]);

        // Keep inventory_stock strictly synchronized with ledger sum
        $stmtSum = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM inventory_ledger WHERE branch_id = ? AND inventory_id = ?");
        $stmtSum->execute([$branchId, $inventoryId]);
        $exactLedgerSum = (float)$stmtSum->fetchColumn();

        $stmtStockSync = $pdo->prepare("
            INSERT INTO inventory_stock (branch_id, inventory_id, current_stock)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE current_stock = VALUES(current_stock)
        ");
        $stmtStockSync->execute([$branchId, $inventoryId, $exactLedgerSum]);

        Audit::log('INVENTORY_ADJUSTMENT_AUTO_APPROVED', "Auto-approved adjustment #{$adjId} of {$quantity} for {$item['item_name']}", $user['id'], $branchId);
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'status' => 'APPROVED',
            'adjustment_id' => $adjId,
            'new_stock' => $exactLedgerSum
        ]);
        exit;

    } elseif ($action === 'approve') {
        Rbac::require_permission('inventory.approve');

        $adjId = (int)($input['adjustment_id'] ?? 0);
        if ($adjId <= 0) {
            http_response_code(400);
            exit(json_encode(['success' => false, 'error' => 'Adjustment ID is required.']));
        }

        $pdo->beginTransaction();

        $stmtAdj = $pdo->prepare("SELECT * FROM inventory_adjustments WHERE id = ? FOR UPDATE");
        $stmtAdj->execute([$adjId]);
        $adj = $stmtAdj->fetch();

        if (!$adj) {
            $pdo->rollBack();
            http_response_code(404);
            exit(json_encode(['success' => false, 'error' => 'Adjustment not found.']));
        }

        if ($adj['status'] !== 'PENDING') {
            $pdo->rollBack();
            http_response_code(409);
            exit(json_encode(['success' => false, 'error' => 'Adjustment is not pending approval.']));
        }

        // Branch scoping check
        if (!empty($user['branch_id']) && (int)$user['branch_id'] !== (int)$adj['branch_id']) {
            $pdo->rollBack();
            http_response_code(403);
            exit(json_encode(['success' => false, 'error' => 'Forbidden: wrong branch.']));
        }

        // Maker-Checker enforcement: Maker cannot approve their own adjustment
        if ((int)$user['id'] === (int)$adj['created_by']) {
            $pdo->rollBack();
            http_response_code(403);
            exit(json_encode(['success' => false, 'error' => 'Maker cannot approve their own adjustment.']));
        }

        // Re-check negative stock against current ledger sum
        $stmtStock = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM inventory_ledger WHERE branch_id = ? AND inventory_id = ?");
        $stmtStock->execute([$adj['branch_id'], $adj['inventory_id']]);
        $currentStock = (float)$stmtStock->fetchColumn();
        $newStock = $currentStock + (float)$adj['quantity'];

        if ($newStock < 0) {
            $stmtAllowNeg = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'inventory_allow_negative'");
            $allowNegVal = $stmtAllowNeg ? $stmtAllowNeg->fetchColumn() : '0';
            $allowNegative = ($allowNegVal === '1' || strtolower((string)$allowNegVal) === 'true');

            if (!$allowNegative) {
                $pdo->rollBack();
                http_response_code(400);
                exit(json_encode(['success' => false, 'error' => 'Negative stock is not permitted.']));
            }
        }

        // Mark as APPROVED
        $upStmt = $pdo->prepare("UPDATE inventory_adjustments SET status = 'APPROVED', approved_by = ?, approved_at = NOW() WHERE id = ?");
        $upStmt->execute([$user['id'], $adjId]);

        // Write row to inventory_ledger
        $stmtLedger = $pdo->prepare("
            INSERT INTO inventory_ledger (branch_id, inventory_id, type, quantity, unit_cost_snapshot, user_id, remarks, created_at)
            VALUES (?, ?, 'Adjustment', ?, ?, ?, ?, NOW())
        ");
        $stmtLedger->execute([
            $adj['branch_id'],
            $adj['inventory_id'],
            $adj['quantity'],
            $adj['unit_cost_snapshot'],
            $user['id'],
            "Approved: " . $adj['reason']
        ]);

        // Keep inventory_stock strictly synchronized
        $stmtSum = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM inventory_ledger WHERE branch_id = ? AND inventory_id = ?");
        $stmtSum->execute([$adj['branch_id'], $adj['inventory_id']]);
        $exactLedgerSum = (float)$stmtSum->fetchColumn();

        $stmtStockSync = $pdo->prepare("
            INSERT INTO inventory_stock (branch_id, inventory_id, current_stock)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE current_stock = VALUES(current_stock)
        ");
        $stmtStockSync->execute([$adj['branch_id'], $adj['inventory_id'], $exactLedgerSum]);

        Audit::log('INVENTORY_ADJUSTMENT_APPROVED', "Adjustment #{$adjId} approved by User {$user['id']}", $user['id'], (int)$adj['branch_id']);
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'status' => 'APPROVED',
            'adjustment_id' => $adjId,
            'new_stock' => $exactLedgerSum
        ]);
        exit;

    } elseif ($action === 'reject') {
        Rbac::require_permission('inventory.approve');

        $adjId = (int)($input['adjustment_id'] ?? 0);
        if ($adjId <= 0) {
            http_response_code(400);
            exit(json_encode(['success' => false, 'error' => 'Adjustment ID is required.']));
        }

        $pdo->beginTransaction();

        $stmtAdj = $pdo->prepare("SELECT * FROM inventory_adjustments WHERE id = ? FOR UPDATE");
        $stmtAdj->execute([$adjId]);
        $adj = $stmtAdj->fetch();

        if (!$adj) {
            $pdo->rollBack();
            http_response_code(404);
            exit(json_encode(['success' => false, 'error' => 'Adjustment not found.']));
        }

        if ($adj['status'] !== 'PENDING') {
            $pdo->rollBack();
            http_response_code(409);
            exit(json_encode(['success' => false, 'error' => 'Adjustment is not pending approval.']));
        }

        if (!empty($user['branch_id']) && (int)$user['branch_id'] !== (int)$adj['branch_id']) {
            $pdo->rollBack();
            http_response_code(403);
            exit(json_encode(['success' => false, 'error' => 'Forbidden: wrong branch.']));
        }

        if ((int)$user['id'] === (int)$adj['created_by']) {
            $pdo->rollBack();
            http_response_code(403);
            exit(json_encode(['success' => false, 'error' => 'Maker cannot reject their own adjustment.']));
        }

        $upStmt = $pdo->prepare("UPDATE inventory_adjustments SET status = 'REJECTED', approved_by = ?, approved_at = NOW() WHERE id = ?");
        $upStmt->execute([$user['id'], $adjId]);

        Audit::log('INVENTORY_ADJUSTMENT_REJECTED', "Adjustment #{$adjId} rejected by User {$user['id']}", $user['id'], (int)$adj['branch_id']);
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'status' => 'REJECTED',
            'adjustment_id' => $adjId
        ]);
        exit;

    } elseif ($action === 'item_save') {
        Rbac::require_permission('inventory.adjust');

        $itemId = (int)($input['id'] ?? 0);
        $itemName = trim((string)($input['item_name'] ?? ''));
        $unit = trim((string)($input['unit'] ?? ''));
        $unitCostRaw = $input['unit_cost'] ?? null;
        $lowStockRaw = $input['low_stock_threshold'] ?? 10.00;
        $branchId = (int)($input['branch_id'] ?? ($user['branch_id'] ?? 1));

        if ($itemName === '' || $unit === '' || $unitCostRaw === null || (float)$unitCostRaw < 0) {
            http_response_code(400);
            exit(json_encode(['success' => false, 'error' => 'Item name, unit, and valid unit cost are required.']));
        }

        $unitCostDecimal = Money::toDecimal(Money::toCentavos($unitCostRaw));

        $pdo->beginTransaction();

        if ($itemId > 0) {
            $stmt = $pdo->prepare("UPDATE inventory_items SET item_name = ?, unit = ?, unit_cost = ? WHERE id = ?");
            $stmt->execute([$itemName, $unit, $unitCostDecimal, $itemId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO inventory_items (item_name, unit, unit_cost, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$itemName, $unit, $unitCostDecimal]);
            $itemId = (int)$pdo->lastInsertId();
        }

        // Update low_stock_threshold for branch
        if ($branchId > 0) {
            $stmtStock = $pdo->prepare("
                INSERT INTO inventory_stock (branch_id, inventory_id, current_stock, low_stock_threshold)
                VALUES (?, ?, 0.00, ?)
                ON DUPLICATE KEY UPDATE low_stock_threshold = VALUES(low_stock_threshold)
            ");
            $stmtStock->execute([$branchId, $itemId, (float)$lowStockRaw]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $itemId]);
        exit;

    } elseif ($action === 'item_deactivate') {
        Rbac::require_permission('inventory.adjust');

        $itemId = (int)($input['id'] ?? 0);
        if ($itemId <= 0) {
            http_response_code(400);
            exit(json_encode(['success' => false, 'error' => 'Valid item ID required.']));
        }

        $stmt = $pdo->prepare("UPDATE inventory_items SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$itemId]);

        Audit::log('INVENTORY_ITEM_DEACTIVATED', "Item ID {$itemId} soft-deactivated", $user['id']);
        echo json_encode(['success' => true]);
        exit;

    } else {
        http_response_code(400);
        exit(json_encode(['success' => false, 'error' => 'Invalid action.']));
    }

} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Inventory error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
