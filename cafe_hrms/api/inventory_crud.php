<?php
require_once __DIR__ . '/../../cafe_pos/database.php';

header('Content-Type: application/json');

session_start();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
if (!$input && !empty($_POST)) {
    $input = $_POST;
}

$posPdo = $pdo; // Map pdo to posPdo just in case

try {
    if ($method === 'GET') {
        $type = $_GET['type'] ?? '';
        
        if ($type === 'inventory') {
            $branchId = $_GET['branch_id'] ?? 1; // Default to branch 1 if not provided
            $stmt = $pdo->prepare("
                SELECT i.*, COALESCE(s.stock, 0) as stock, COALESCE(s.low_stock_level, 0) as low_stock_level 
                FROM inventory i 
                LEFT JOIN inventory_stock s ON i.id = s.inventory_id AND s.branch_id = ? 
                ORDER BY i.item_name ASC
            ");
            $stmt->execute([$branchId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } elseif ($type === 'recipes') {
            $productId = $_GET['product_id'] ?? null;
            $modifierId = $_GET['modifier_id'] ?? null;
            
            if ($productId) {
                $stmt = $pdo->prepare("
                    SELECT r.*, i.item_name, i.unit, i.unit_cost 
                    FROM recipes r 
                    JOIN inventory i ON r.inventory_id = i.id 
                    WHERE r.product_id = ?
                ");
                $stmt->execute([$productId]);
            } elseif ($modifierId) {
                $stmt = $pdo->prepare("
                    SELECT r.*, i.item_name, i.unit, i.unit_cost 
                    FROM recipes r 
                    JOIN inventory i ON r.inventory_id = i.id 
                    WHERE r.modifier_id = ?
                ");
                $stmt->execute([$modifierId]);
            } else {
                throw new Exception("Missing product_id or modifier_id");
            }
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        }
    } elseif ($method === 'POST') {
        $type = $input['type'] ?? '';
        $action = $input['action'] ?? '';
        
        if ($type === 'inventory') {
            if ($action === 'create' || $action === 'update') {
                if (!isset($input['unit_cost']) || (float)$input['unit_cost'] <= 0) {
                    throw new Exception("Unit cost must be greater than zero.");
                }
            }

            if ($action === 'create') {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("INSERT INTO inventory (item_name, unit, unit_cost) VALUES (?, ?, ?)");
                    $stmt->execute([$input['item_name'], $input['unit'], $input['unit_cost']]);
                    $newId = $pdo->lastInsertId();

                    $branchId = $input['branch_id'] ?? 1;
                    $stmtStock = $pdo->prepare("INSERT INTO inventory_stock (inventory_id, branch_id, stock, low_stock_level) VALUES (?, ?, ?, ?)");
                    $stmtStock->execute([$newId, $branchId, $input['stock'], $input['low_stock_level']]);
                    
                    $pdo->commit();
                    echo json_encode(['success' => true, 'id' => $newId]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            } elseif ($action === 'update') {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("UPDATE inventory SET item_name = ?, unit = ?, unit_cost = ? WHERE id = ?");
                    $stmt->execute([$input['item_name'], $input['unit'], $input['unit_cost'], $input['id']]);

                    $branchId = $input['branch_id'] ?? 1;
                    $stmtStock = $pdo->prepare("
                        INSERT INTO inventory_stock (inventory_id, branch_id, stock, low_stock_level) 
                        VALUES (?, ?, ?, ?) 
                        ON DUPLICATE KEY UPDATE stock = VALUES(stock), low_stock_level = VALUES(low_stock_level)
                    ");
                    $stmtStock->execute([$input['id'], $branchId, $input['stock'], $input['low_stock_level']]);
                    
                    $pdo->commit();
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            } elseif ($action === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
                $stmt->execute([$input['id']]);
                echo json_encode(['success' => true]);
            } elseif ($action === 'manual_adjust') {
                $pdo->beginTransaction();
                try {
                    $role = $_SESSION['user']['role'] ?? '';
                    $isSuperAdmin = in_array($role, ['Super Admin', 'Admin']);
                    $status = $isSuperAdmin ? 'Approved' : 'Pending';
                    $remarks = $input['remarks'] ?? null;
                    
                    // Deduct stock only if approved immediately
                    if ($status === 'Approved') {
                        $stmtUpdate = $pdo->prepare("UPDATE inventory_stock SET stock = stock - ? WHERE inventory_id = ? AND branch_id = ?");
                        $stmtUpdate->execute([$input['quantity'], $input['id'], $input['branch_id']]);

                        $stmtItem = $pdo->prepare("SELECT item_name, unit_cost FROM inventory WHERE id = ?");
                        $stmtItem->execute([$input['id']]);
                        $itemDetails = $stmtItem->fetch();
                        $calculatedCost = $input['quantity'] * $itemDetails['unit_cost'];

                        $stmtInsert = $pdo->prepare("INSERT INTO inventory_transactions (inventory_id, branch_id, item_name, type, quantity, cost, status, logged_by) VALUES (?, ?, ?, 'Write-off', ?, ?, 'Completed', ?)");
                        $stmtInsert->execute([$input['id'], $input['branch_id'], $itemDetails['item_name'], $input['quantity'], $calculatedCost, $_SESSION['user']['id']]);

                        // Check stock level
                        $stmtCheck = $pdo->prepare("SELECT stock, low_stock_level FROM inventory_stock WHERE inventory_id = ? AND branch_id = ?");
                        $stmtCheck->execute([$input['id'], $input['branch_id']]);
                        $stockData = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                        if ($stockData && $stockData['stock'] <= $stockData['low_stock_level']) {
                            $reqQty = $stockData['low_stock_level'] > 0 ? $stockData['low_stock_level'] * 3 : 50;
                            $stmtPR = $pdo->prepare("INSERT INTO purchase_requests (branch_id, inventory_id, requested_by, item_name, quantity, estimated_cost) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmtPR->execute([$input['branch_id'], $input['id'], $_SESSION['user']['id'], $itemDetails['item_name'], $reqQty, $reqQty * $itemDetails['unit_cost']]);
                        }
                    }

                    $pdo->commit();
                    $_SESSION['success_msg'] = "Stock adjustment logged successfully.";
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            } elseif ($action === 'get_pending_adjustments') {
                $branchId = $_GET['branch_id'] ?? ($input['branch_id'] ?? 1);
                $stmt = $pdo->prepare("
                    SELECT t.*, i.item_name, i.unit 
                    FROM inventory_transactions t 
                    JOIN inventory i ON t.inventory_id = i.id 
                    WHERE t.status = 'Pending' AND t.branch_id = ? 
                    ORDER BY t.transaction_date ASC
                ");
                $stmt->execute([$branchId]);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            } elseif ($action === 'approve_adjustment') {
                $pdo->beginTransaction();
                try {
                    // Get transaction details
                    $stmt = $pdo->prepare("SELECT * FROM inventory_transactions WHERE id = ?");
                    $stmt->execute([$input['id']]);
                    $transaction = $stmt->fetch();
                    
                    if ($transaction && $transaction['status'] === 'Pending') {
                        // Fetch unit cost
                        $stmtCost = $pdo->prepare("SELECT unit_cost FROM inventory WHERE id = ?");
                        $stmtCost->execute([$transaction['inventory_id']]);
                        $unitCost = $stmtCost->fetchColumn();
                        $financialImpact = $transaction['quantity'] * $unitCost;

                        // Deduct stock
                        $stmtUpdate = $pdo->prepare("UPDATE inventory_stock SET stock = stock - ? WHERE inventory_id = ? AND branch_id = ?");
                        $stmtUpdate->execute([$transaction['quantity'], $transaction['inventory_id'], $transaction['branch_id']]);
                        
                        // Update status and financial impact
                        $stmtStatus = $pdo->prepare("UPDATE inventory_transactions SET status = 'Approved', financial_impact = ? WHERE id = ?");
                        $stmtStatus->execute([$financialImpact, $input['id']]);

                        // Check stock level
                        $stmtCheck = $pdo->prepare("SELECT stock, low_stock_level FROM inventory_stock WHERE inventory_id = ? AND branch_id = ?");
                        $stmtCheck->execute([$transaction['inventory_id'], $transaction['branch_id']]);
                        $stockData = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                        if ($stockData && $stockData['stock'] <= $stockData['low_stock_level']) {
                            $reqQty = $stockData['low_stock_level'] > 0 ? $stockData['low_stock_level'] * 3 : 50;
                            // Need item details for purchase_requests
                            $stmtItemDetails = $pdo->prepare("SELECT item_name, unit_cost FROM inventory WHERE id = ?");
                            $stmtItemDetails->execute([$transaction['inventory_id']]);
                            $itemDetailsForPR = $stmtItemDetails->fetch();
                            
                            $stmtPR = $pdo->prepare("INSERT INTO purchase_requests (branch_id, inventory_id, requested_by, item_name, quantity, estimated_cost) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmtPR->execute([$transaction['branch_id'], $transaction['inventory_id'], $_SESSION['user']['id'], $itemDetailsForPR['item_name'], $reqQty, $reqQty * $itemDetailsForPR['unit_cost']]);
                        }
                    }
                    
                    $pdo->commit();
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            } elseif ($action === 'reject_adjustment') {
                $stmt = $pdo->prepare("UPDATE inventory_transactions SET status = 'Rejected' WHERE id = ?");
                $stmt->execute([$input['id']]);
                echo json_encode(['success' => true]);
            }
        } elseif ($type === 'recipes') {
            if ($action === 'create') {
                $productId = $input['product_id'] ?? null;
                $modifierId = $input['modifier_id'] ?? null;
                $inventoryId = $input['inventory_id'];
                $quantity = $input['quantity'];

                if ($productId) {
                    $stmt = $pdo->prepare("SELECT id FROM recipes WHERE product_id = ? AND inventory_id = ?");
                    $stmt->execute([$productId, $inventoryId]);
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM recipes WHERE modifier_id = ? AND inventory_id = ?");
                    $stmt->execute([$modifierId, $inventoryId]);
                }

                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ingredient already mapped. Please edit the existing quantity.']);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO recipes (product_id, modifier_id, inventory_id, quantity) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$productId, $modifierId, $inventoryId, $quantity]);
                    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'updated' => false]);
                }
            } elseif ($action === 'update') {
                $stmt = $pdo->prepare("UPDATE recipes SET quantity = ? WHERE id = ?");
                $stmt->execute([$input['quantity'], $input['id']]);
                echo json_encode(['success' => true]);
            } elseif ($action === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM recipes WHERE id = ?");
                $stmt->execute([$input['id']]);
                echo json_encode(['success' => true]);
            }
        }
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
