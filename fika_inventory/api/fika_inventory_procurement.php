<?php
/**
 * Fika Rebuild Core - Procurement API
 * Handles suppliers, purchase orders, approval workflow, and stock receiving.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

// Require authentication
Auth::requireLogin();
$user = $_SESSION['user'];
$userId = (int)$user['id'];
$userBranchId = isset($user['branch_id']) ? (int)$user['branch_id'] : 1;
$isAdmin = in_array($user['role_name'] ?? '', ['ADMIN', 'Super Admin'], true) || (int)($user['role_id'] ?? 0) === 1;

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Read JSON input if POST/PUT
$data = [];
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $decoded = json_decode($rawInput, true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }
    if (empty($data) && !empty($_POST)) {
        $data = $_POST;
    }
}

try {
    switch ($action) {
        // =================================================================
        // SUPPLIERS
        // =================================================================
        case 'list_suppliers': {
            Rbac::require_permission('procurement.view');

            if ($isAdmin) {
                $stmt = $pdo->prepare("
                    SELECT s.*, b.name AS branch_name
                    FROM suppliers s
                    LEFT JOIN branches b ON s.branch_id = b.id
                    WHERE s.deleted_at IS NULL
                    ORDER BY s.name ASC
                ");
                $stmt->execute();
            } else {
                $stmt = $pdo->prepare("
                    SELECT s.*, b.name AS branch_name
                    FROM suppliers s
                    LEFT JOIN branches b ON s.branch_id = b.id
                    WHERE s.deleted_at IS NULL AND (s.branch_id IS NULL OR s.branch_id = ?)
                    ORDER BY s.name ASC
                ");
                $stmt->execute([$userBranchId]);
            }

            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            exit;
        }

        case 'save_supplier': {
            Csrf::requireValid();
            Rbac::require_permission('procurement.create');

            $supplierId = !empty($data['id']) ? (int)$data['id'] : null;
            $name = trim($data['name'] ?? '');
            $contact = trim($data['contact'] ?? '');
            $address = trim($data['address'] ?? '');
            $branchId = !empty($data['branch_id']) ? (int)$data['branch_id'] : ($isAdmin ? null : $userBranchId);

            if (empty($name)) {
                http_response_code(400);
                echo json_encode(['error' => 'Supplier name is required']);
                exit;
            }

            if ($supplierId) {
                $stmt = $pdo->prepare("
                    UPDATE suppliers 
                    SET name = ?, contact = ?, address = ?, branch_id = ?, updated_at = NOW() 
                    WHERE id = ? AND deleted_at IS NULL
                ");
                $stmt->execute([$name, $contact, $address, $branchId, $supplierId]);
                Audit::log('SUPPLIER_UPDATED', json_encode(['supplier_id' => $supplierId, 'name' => $name]));
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO suppliers (name, contact, address, branch_id, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$name, $contact, $address, $branchId]);
                $supplierId = (int)$pdo->lastInsertId();
                Audit::log('SUPPLIER_CREATED', json_encode(['supplier_id' => $supplierId, 'name' => $name]));
            }

            echo json_encode(['success' => true, 'supplier_id' => $supplierId]);
            exit;
        }

        case 'deactivate_supplier': {
            Csrf::requireValid();
            Rbac::require_permission('procurement.create');

            $supplierId = (int)($data['id'] ?? 0);
            if (!$supplierId) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid supplier ID']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE suppliers SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$supplierId]);

            Audit::log('SUPPLIER_DEACTIVATED', json_encode(['supplier_id' => $supplierId]));
            echo json_encode(['success' => true]);
            exit;
        }

        // =================================================================
        // PURCHASE ORDERS
        // =================================================================
        case 'list_pos': {
            Rbac::require_permission('procurement.view');

            $status = trim($_GET['status'] ?? '');
            $sql = "
                SELECT po.*, s.name AS supplier_name, b.name AS branch_name,
                       u1.username AS creator_username, u2.username AS approver_username,
                       (SELECT COUNT(*) FROM purchase_order_items WHERE po_id = po.id) AS item_count,
                       (SELECT SUM(quantity_ordered) FROM purchase_order_items WHERE po_id = po.id) AS total_ordered_qty,
                       (SELECT SUM(quantity_received) FROM purchase_order_items WHERE po_id = po.id) AS total_received_qty
                FROM purchase_orders po
                JOIN suppliers s ON po.supplier_id = s.id
                JOIN branches b ON po.branch_id = b.id
                LEFT JOIN users u1 ON po.created_by = u1.id
                LEFT JOIN users u2 ON po.approved_by = u2.id
                WHERE 1=1
            ";
            $params = [];

            if (!$isAdmin) {
                $sql .= " AND po.branch_id = ?";
                $params[] = $userBranchId;
            }

            if (!empty($status)) {
                $sql .= " AND po.status = ?";
                $params[] = $status;
            }

            $sql .= " ORDER BY po.id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            exit;
        }

        case 'get_po': {
            Rbac::require_permission('procurement.view');

            $poId = (int)($_GET['id'] ?? 0);
            if (!$poId) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing purchase order ID']);
                exit;
            }

            $poStmt = $pdo->prepare("
                SELECT po.*, s.name AS supplier_name, s.contact AS supplier_contact, b.name AS branch_name,
                       u1.username AS creator_username, u2.username AS approver_username
                FROM purchase_orders po
                JOIN suppliers s ON po.supplier_id = s.id
                JOIN branches b ON po.branch_id = b.id
                LEFT JOIN users u1 ON po.created_by = u1.id
                LEFT JOIN users u2 ON po.approved_by = u2.id
                WHERE po.id = ?
            ");
            $poStmt->execute([$poId]);
            $po = $poStmt->fetch();

            if (!$po) {
                http_response_code(404);
                echo json_encode(['error' => 'Purchase order not found']);
                exit;
            }

            // Cross-branch check
            if (!$isAdmin && (int)$po['branch_id'] !== $userBranchId) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: Cannot access PO from another branch']);
                exit;
            }

            $itemsStmt = $pdo->prepare("
                SELECT poi.*, ii.item_name, ii.unit, ii.sku
                FROM purchase_order_items poi
                JOIN inventory_items ii ON poi.inventory_id = ii.id
                WHERE poi.po_id = ?
                ORDER BY poi.id ASC
            ");
            $itemsStmt->execute([$poId]);
            $items = $itemsStmt->fetchAll();

            $po['items'] = $items;

            echo json_encode(['success' => true, 'data' => $po]);
            exit;
        }

        case 'create_po': {
            Csrf::requireValid();
            Rbac::require_permission('procurement.create');

            $supplierId = (int)($data['supplier_id'] ?? 0);
            $items = $data['items'] ?? [];
            $branchId = !empty($data['branch_id']) ? (int)$data['branch_id'] : $userBranchId;

            if (!$isAdmin) {
                $branchId = $userBranchId; // Non-admin cannot create for other branch
            }

            if (!$supplierId) {
                http_response_code(400);
                echo json_encode(['error' => 'Supplier is required']);
                exit;
            }

            if (empty($items) || !is_array($items)) {
                http_response_code(400);
                echo json_encode(['error' => 'Purchase order must have at least one line item']);
                exit;
            }

            // Verify supplier exists
            $suppStmt = $pdo->prepare("SELECT id FROM suppliers WHERE id = ? AND deleted_at IS NULL");
            $suppStmt->execute([$supplierId]);
            if (!$suppStmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid or inactive supplier']);
                exit;
            }

            // Compute server-side total
            $totalCostCents = 0;
            $validatedItems = [];
            foreach ($items as $item) {
                $invId = (int)($item['inventory_id'] ?? 0);
                $qtyOrdered = (float)($item['quantity_ordered'] ?? 0);
                $unitCostCents = (int)($item['unit_cost_cents'] ?? 0);

                if ($invId <= 0 || $qtyOrdered <= 0 || $unitCostCents < 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid item details (inventory_id, quantity_ordered > 0, unit_cost_cents >= 0 required)']);
                    exit;
                }

                $totalCostCents += (int)round($qtyOrdered * $unitCostCents);
                $validatedItems[] = [
                    'inventory_id' => $invId,
                    'quantity_ordered' => $qtyOrdered,
                    'unit_cost_cents' => $unitCostCents
                ];
            }

            $pdo->beginTransaction();
            try {
                // PO Number generation with lock on today's sequence
                $today = date('Ymd');
                $prefix = sprintf('PO-%d-%s-', $branchId, $today);

                // Use SELECT ... FOR UPDATE to prevent race conditions on sequence
                $seqStmt = $pdo->prepare("
                    SELECT po_number 
                    FROM purchase_orders 
                    WHERE po_number LIKE ? 
                    ORDER BY id DESC 
                    LIMIT 1 
                    FOR UPDATE
                ");
                $seqStmt->execute([$prefix . '%']);
                $lastPo = $seqStmt->fetch();

                $nextSeq = 1;
                if ($lastPo && !empty($lastPo['po_number'])) {
                    $parts = explode('-', $lastPo['po_number']);
                    $lastNum = (int)end($parts);
                    $nextSeq = $lastNum + 1;
                }

                $poNumber = sprintf('PO-%d-%s-%05d', $branchId, $today, $nextSeq);

                $poInsert = $pdo->prepare("
                    INSERT INTO purchase_orders (
                        branch_id, supplier_id, po_number, status, total_cost_cents, created_by, created_at, updated_at
                    ) VALUES (?, ?, ?, 'DRAFT', ?, ?, NOW(), NOW())
                ");
                $poInsert->execute([$branchId, $supplierId, $poNumber, $totalCostCents, $userId]);
                $poId = (int)$pdo->lastInsertId();

                $itemInsert = $pdo->prepare("
                    INSERT INTO purchase_order_items (
                        po_id, inventory_id, quantity_ordered, quantity_received, unit_cost_cents
                    ) VALUES (?, ?, ?, 0.00, ?)
                ");
                foreach ($validatedItems as $vItem) {
                    $itemInsert->execute([
                        $poId,
                        $vItem['inventory_id'],
                        $vItem['quantity_ordered'],
                        $vItem['unit_cost_cents']
                    ]);
                }

                $pdo->commit();

                Audit::log('PO_CREATED', json_encode([
                    'po_id' => $poId,
                    'po_number' => $poNumber,
                    'branch_id' => $branchId,
                    'total_cost_cents' => $totalCostCents
                ]));

                echo json_encode([
                    'success' => true,
                    'po_id' => $poId,
                    'po_number' => $poNumber,
                    'total_cost_cents' => $totalCostCents
                ]);
                exit;

            } catch (\Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                http_response_code(500);
                echo json_encode(['error' => 'Database error while creating purchase order: ' . $e->getMessage()]);
                exit;
            }
        }

        case 'submit_po':
        case 'approve_po': {
            Csrf::requireValid();

            $poId = (int)($data['po_id'] ?? 0);
            if (!$poId) {
                http_response_code(400);
                echo json_encode(['error' => 'Purchase order ID is required']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ?");
            $stmt->execute([$poId]);
            $po = $stmt->fetch();

            if (!$po) {
                http_response_code(404);
                echo json_encode(['error' => 'Purchase order not found']);
                exit;
            }

            // Cross-branch check
            if (!$isAdmin && (int)$po['branch_id'] !== $userBranchId) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: Cannot approve PO from another branch']);
                exit;
            }

            // Maker-Checker enforcement: Creator cannot approve their own PO
            if ((int)$po['created_by'] === $userId) {
                http_response_code(403);
                echo json_encode(['error' => 'Maker-Checker violation: Creator cannot approve their own purchase order']);
                exit;
            }

            // Approver must have procurement.approve permission
            Rbac::require_permission('procurement.approve');

            if ($po['status'] !== 'DRAFT') {
                http_response_code(400);
                echo json_encode(['error' => 'Purchase order is not in DRAFT status (current status: ' . $po['status'] . ')']);
                exit;
            }

            // Recalculate server-side total
            $sumStmt = $pdo->prepare("
                SELECT SUM(ROUND(quantity_ordered * unit_cost_cents)) 
                FROM purchase_order_items 
                WHERE po_id = ?
            ");
            $sumStmt->execute([$poId]);
            $serverTotalCents = (int)$sumStmt->fetchColumn();

            $update = $pdo->prepare("
                UPDATE purchase_orders 
                SET status = 'APPROVED', approved_by = ?, total_cost_cents = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $update->execute([$userId, $serverTotalCents, $poId]);

            Audit::log('PO_APPROVED', json_encode([
                'po_id' => $poId,
                'po_number' => $po['po_number'],
                'approved_by' => $userId,
                'total_cost_cents' => $serverTotalCents
            ]));

            echo json_encode(['success' => true, 'message' => 'Purchase order approved successfully']);
            exit;
        }

        case 'receive_po': {
            Csrf::requireValid();
            Rbac::require_permission('procurement.receive');

            $poId = (int)($data['po_id'] ?? 0);
            $items = $data['items'] ?? [];
            $idempotencyKey = trim($data['idempotency_key'] ?? '');

            if (!$poId) {
                http_response_code(400);
                echo json_encode(['error' => 'Purchase order ID is required']);
                exit;
            }

            if (empty($items) || !is_array($items)) {
                http_response_code(400);
                echo json_encode(['error' => 'No items provided for receiving']);
                exit;
            }

            if (empty($idempotencyKey)) {
                $idempotencyKey = hash('sha256', $poId . ':' . json_encode($items) . ':' . microtime(true));
            }

            $poStmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ?");
            $poStmt->execute([$poId]);
            $po = $poStmt->fetch();

            if (!$po) {
                http_response_code(404);
                echo json_encode(['error' => 'Purchase order not found']);
                exit;
            }

            // Cross-branch check
            if (!$isAdmin && (int)$po['branch_id'] !== $userBranchId) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: Cannot receive PO from another branch']);
                exit;
            }

            // Must be in APPROVED or PARTIALLY_RECEIVED
            if (!in_array($po['status'], ['APPROVED', 'PARTIALLY_RECEIVED'], true)) {
                http_response_code(400);
                echo json_encode(['error' => 'Cannot receive items for purchase order with status ' . $po['status']]);
                exit;
            }

            $pdo->beginTransaction();

            // 1. Idempotency Check: Attempt INSERT into po_receipts FIRST
            try {
                $receiptInsert = $pdo->prepare("
                    INSERT INTO po_receipts (po_id, received_by, received_at, idempotency_key)
                    VALUES (?, ?, NOW(), ?)
                ");
                $receiptInsert->execute([$poId, $userId, $idempotencyKey]);
            } catch (\PDOException $e) {
                if ($e->getCode() == 23000 || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062)) {
                    // Duplicate idempotency key: silent no-op return 200
                    $pdo->rollBack();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Receipt already processed (idempotent)',
                        'po_id' => $poId
                    ]);
                    exit;
                }
                $pdo->rollBack();
                throw $e;
            }

            try {
                // Lock PO items for update
                $itemLock = $pdo->prepare("
                    SELECT * FROM purchase_order_items 
                    WHERE po_id = ? 
                    FOR UPDATE
                ");
                $itemLock->execute([$poId]);
                $dbItems = $itemLock->fetchAll();
                $dbItemsById = [];
                foreach ($dbItems as $dbi) {
                    $dbItemsById[(int)$dbi['id']] = $dbi;
                }

                // Statements for updates
                $updateItemStmt = $pdo->prepare("
                    UPDATE purchase_order_items 
                    SET quantity_received = quantity_received + ?, 
                        over_receipt_reason = COALESCE(?, over_receipt_reason)
                    WHERE id = ?
                ");

                $ledgerInsert = $pdo->prepare("
                    INSERT INTO inventory_ledger (
                        branch_id, inventory_id, type, quantity, unit_cost_snapshot, user_id, order_id, po_id, remarks, created_at
                    ) VALUES (?, ?, 'PURCHASE', ?, ?, ?, NULL, ?, ?, NOW())
                ");

                $stockUpdate = $pdo->prepare("
                    INSERT INTO inventory_stock (branch_id, inventory_id, current_stock, low_stock_threshold)
                    VALUES (?, ?, ?, 10.00)
                    ON DUPLICATE KEY UPDATE current_stock = current_stock + VALUES(current_stock)
                ");

                foreach ($items as $recvItem) {
                    $itemId = (int)($recvItem['item_id'] ?? 0);
                    $recvQty = (float)($recvItem['receive_qty'] ?? 0);
                    $reason = isset($recvItem['reason']) ? trim($recvItem['reason']) : '';

                    if (!isset($dbItemsById[$itemId])) {
                        $pdo->rollBack();
                        http_response_code(400);
                        echo json_encode(['error' => "Item ID $itemId does not belong to this purchase order"]);
                        exit;
                    }

                    if ($recvQty <= 0) {
                        continue; // Skip zero or negative receive inputs
                    }

                    $currentRecv = (float)$dbItemsById[$itemId]['quantity_received'];
                    $orderedQty = (float)$dbItemsById[$itemId]['quantity_ordered'];
                    $newRecvTotal = $currentRecv + $recvQty;

                    // Over-receiving constraint check
                    if ($newRecvTotal > $orderedQty) {
                        if (empty($reason)) {
                            $pdo->rollBack();
                            http_response_code(400);
                            echo json_encode(['error' => 'Over-receiving requires a reason']);
                            exit;
                        }
                    }

                    // 1. Update purchase_order_items
                    $updateItemStmt->execute([
                        $recvQty,
                        !empty($reason) ? $reason : null,
                        $itemId
                    ]);

                    // 2. Insert into inventory_ledger with type = 'PURCHASE' and po_id
                    $unitCostDecimal = $dbItemsById[$itemId]['unit_cost_cents'] / 100.0;
                    $remarks = "PO Received: " . $po['po_number'] . (!empty($reason) ? " (Reason: $reason)" : "");
                    $ledgerInsert->execute([
                        $po['branch_id'],
                        $dbItemsById[$itemId]['inventory_id'],
                        $recvQty,
                        $unitCostDecimal,
                        $userId,
                        $poId,
                        $remarks
                    ]);

                    // 3. Update inventory_stock
                    $stockUpdate->execute([
                        $po['branch_id'],
                        $dbItemsById[$itemId]['inventory_id'],
                        $recvQty
                    ]);
                }

                // Determine new status: fully received or partially received
                $checkStmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) AS total_items,
                        SUM(CASE WHEN quantity_received >= quantity_ordered THEN 1 ELSE 0 END) AS fully_received_items
                    FROM purchase_order_items 
                    WHERE po_id = ?
                ");
                $checkStmt->execute([$poId]);
                $summary = $checkStmt->fetch();

                $totalItems = (int)$summary['total_items'];
                $fullyReceived = (int)$summary['fully_received_items'];

                if ($fullyReceived >= $totalItems) {
                    $newStatus = 'RECEIVED';
                    $updatePo = $pdo->prepare("
                        UPDATE purchase_orders 
                        SET status = 'RECEIVED', received_at = NOW(), updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $updatePo->execute([$poId]);
                } else {
                    $newStatus = 'PARTIALLY_RECEIVED';
                    $updatePo = $pdo->prepare("
                        UPDATE purchase_orders 
                        SET status = 'PARTIALLY_RECEIVED', updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $updatePo->execute([$poId]);
                }

                $pdo->commit();

                Audit::log('PO_RECEIVED', json_encode([
                    'po_id' => $poId,
                    'po_number' => $po['po_number'],
                    'new_status' => $newStatus,
                    'received_by' => $userId,
                    'idempotency_key' => $idempotencyKey
                ]));

                echo json_encode([
                    'success' => true,
                    'po_id' => $poId,
                    'status' => $newStatus,
                    'message' => 'Items received successfully'
                ]);
                exit;

            } catch (\Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                http_response_code(500);
                echo json_encode(['error' => 'Database error while receiving items: ' . $e->getMessage()]);
                exit;
            }
        }

        default:
            http_response_code(400);
            echo json_encode(['error' => "Invalid action: '$action'"]);
            exit;
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    exit;
}
