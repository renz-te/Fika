<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('branches.manage');

global $pdo;

$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true) ?? [];
$inputData = array_merge($_POST, $jsonInput);
$action = trim($inputData['action'] ?? '');

try {
    if ($action === 'save') {
        $branchId = (int)($inputData['branch_id'] ?? 0);
        $name = trim($inputData['name'] ?? '');
        $address = trim($inputData['address'] ?? '');
        $contactNumber = trim($inputData['contact_number'] ?? '');

        if ($name === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Branch Name is required.']);
            exit;
        }

        // Uniqueness check across active/non-deleted branches
        $dupStmt = $pdo->prepare("
            SELECT id FROM branches 
            WHERE LOWER(name) = LOWER(?) AND id != ? AND deleted_at IS NULL 
            LIMIT 1
        ");
        $dupStmt->execute([$name, $branchId]);
        if ($dupStmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'A branch with this name already exists.']);
            exit;
        }

        if ($branchId > 0) {
            // Verify existence
            $existStmt = $pdo->prepare("SELECT id, name, status FROM branches WHERE id = ? LIMIT 1");
            $existStmt->execute([$branchId]);
            $existing = $existStmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                http_response_code(404);
                echo json_encode(['error' => 'Branch not found.']);
                exit;
            }

            $updStmt = $pdo->prepare("
                UPDATE branches 
                SET name = ?, address = ?, contact_number = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $updStmt->execute([$name, $address ?: null, $contactNumber ?: null, $branchId]);

            Audit::log('BRANCH_UPDATED', "Updated Branch: {$name} (ID: {$branchId})");

            echo json_encode([
                'success' => true,
                'message' => 'Branch updated successfully.',
                'branch' => [
                    'id' => $branchId,
                    'name' => $name,
                    'address' => $address,
                    'contact_number' => $contactNumber,
                    'status' => $existing['status']
                ]
            ]);
            exit;
        } else {
            // Create new branch
            $insStmt = $pdo->prepare("
                INSERT INTO branches (name, address, contact_number, status, created_at, updated_at) 
                VALUES (?, ?, ?, 'Active', NOW(), NOW())
            ");
            $insStmt->execute([$name, $address ?: null, $contactNumber ?: null]);
            $newId = (int)$pdo->lastInsertId();

            Audit::log('BRANCH_CREATED', "Created Branch: {$name} (ID: {$newId})");

            echo json_encode([
                'success' => true,
                'message' => 'Branch created successfully.',
                'branch' => [
                    'id' => $newId,
                    'name' => $name,
                    'address' => $address,
                    'contact_number' => $contactNumber,
                    'status' => 'Active'
                ]
            ]);
            exit;
        }
    } elseif ($action === 'toggle_status') {
        $branchId = (int)($inputData['branch_id'] ?? 0);
        if ($branchId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid Branch ID is required.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, name, status FROM branches WHERE id = ? LIMIT 1");
        $stmt->execute([$branchId]);
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$branch) {
            http_response_code(404);
            echo json_encode(['error' => 'Branch not found.']);
            exit;
        }

        // Toggle status between Active and Inactive (Soft toggle, never hard delete)
        $newStatus = ($branch['status'] === 'Active') ? 'Inactive' : 'Active';
        $upd = $pdo->prepare("UPDATE branches SET status = ?, updated_at = NOW() WHERE id = ?");
        $upd->execute([$newStatus, $branchId]);

        $exactLog = ($newStatus === 'Active') ? "Activated Branch: {$branch['name']}" : "Deactivated Branch: {$branch['name']}";
        Audit::log('BRANCH_STATUS_TOGGLED', $exactLog);

        echo json_encode([
            'success' => true,
            'message' => $exactLog,
            'new_status' => $newStatus,
            'branch_id' => $branchId
        ]);
        exit;
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action.']);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected database error occurred.']);
    exit;
}
