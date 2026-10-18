<?php
/**
 * Fika Rebuild Core - Finance Expenses & Budgets API
 * Handles operational expenses, maker-checker approvals, voiding, receipt uploads, and monthly budgets.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

// Require authentication
Auth::requireLogin();
$user = $_SESSION['user'];
$userId = (int)$user['id'];
$userBranchId = isset($user['branch_id']) ? (int)$user['branch_id'] : 1;
$isAdminOrGa = in_array($user['role_name'] ?? '', ['ADMIN', 'Super Admin', 'GA', 'Global Accountant'], true) 
               || in_array((int)($user['role_id'] ?? 0), [1, 3], true);

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Read input (support JSON body or multipart/form-data for uploads)
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
        // CATEGORIES
        // =================================================================
        case 'list_categories': {
            $stmt = $pdo->query("SELECT id, name FROM expense_categories ORDER BY name ASC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            exit;
        }

        // =================================================================
        // EXPENSES LIST
        // =================================================================
        case 'list_expenses': {
            Rbac::require_permission('finance.view');

            $branchId = !empty($_GET['branch_id']) ? (int)$_GET['branch_id'] : ($isAdminOrGa ? null : $userBranchId);
            $status = trim($_GET['status'] ?? '');
            $month = trim($_GET['month'] ?? ''); // YYYY-MM

            $sql = "
                SELECT e.*, ec.name AS category_name, b.name AS branch_name,
                       u1.username AS creator_username, u2.username AS approver_username
                FROM expenses e
                JOIN expense_categories ec ON e.category_id = ec.id
                JOIN branches b ON e.branch_id = b.id
                LEFT JOIN users u1 ON e.created_by = u1.id
                LEFT JOIN users u2 ON e.approved_by = u2.id
                WHERE 1=1
            ";
            $params = [];

            if ($branchId !== null) {
                $sql .= " AND e.branch_id = ?";
                $params[] = $branchId;
            }

            if (!empty($status)) {
                $sql .= " AND e.status = ?";
                $params[] = $status;
            }

            if (!empty($month)) {
                $sql .= " AND DATE_FORMAT(e.expense_date, '%Y-%m') = ?";
                $params[] = $month;
            }

            $sql .= " ORDER BY e.expense_date DESC, e.id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            exit;
        }

        // =================================================================
        // BUDGET SUMMARY (Constraint 2: strictly APPROVED expenses)
        // =================================================================
        case 'get_budget_summary': {
            Rbac::require_permission('finance.view');

            $branchId = !empty($_GET['branch_id']) ? (int)$_GET['branch_id'] : $userBranchId;
            $budgetMonth = trim($_GET['budget_month'] ?? date('Y-m'));

            if (!$isAdminOrGa && $branchId !== $userBranchId) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: Cannot access budget for another branch']);
                exit;
            }

            if (!preg_match('/^\d{4}-\d{2}$/', $budgetMonth)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid budget month format. Must be YYYY-MM.']);
                exit;
            }

            // 1. Fetch allocated budget in cents
            $bStmt = $pdo->prepare("
                SELECT allocated_amount_cents 
                FROM branch_budgets 
                WHERE branch_id = ? AND budget_month = ?
            ");
            $bStmt->execute([$branchId, $budgetMonth]);
            $allocatedCents = (int)($bStmt->fetchColumn() ?: 0);

            // 2. Compute total spent strictly for APPROVED expenses (Constraint 2)
            $spentStmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount_cents), 0) 
                FROM expenses 
                WHERE branch_id = ? 
                  AND status = 'APPROVED' 
                  AND DATE_FORMAT(expense_date, '%Y-%m') = ?
            ");
            $spentStmt->execute([$branchId, $budgetMonth]);
            $spentCents = (int)$spentStmt->fetchColumn();

            $remainingCents = $allocatedCents - $spentCents;
            $utilizationPercent = $allocatedCents > 0 ? round(($spentCents / $allocatedCents) * 100, 2) : 0.00;

            echo json_encode([
                'success' => true,
                'data' => [
                    'branch_id' => $branchId,
                    'budget_month' => $budgetMonth,
                    'allocated_amount_cents' => $allocatedCents,
                    'spent_cents' => $spentCents,
                    'remaining_cents' => $remainingCents,
                    'utilization_percent' => $utilizationPercent
                ]
            ]);
            exit;
        }

        // =================================================================
        // SAVE EXPENSE (Constraint 1: Dynamic Threshold, Constraint 3: Upload)
        // =================================================================
        case 'save_expense': {
            Csrf::requireValid();
            Rbac::require_permission('finance.expense.create');

            $branchId = !empty($data['branch_id']) ? (int)$data['branch_id'] : $userBranchId;
            if (!$isAdminOrGa && $branchId !== $userBranchId) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: Cannot create expenses for another branch']);
                exit;
            }

            $categoryId = (int)($data['category_id'] ?? 0);
            $payee = trim($data['payee'] ?? '');
            $expenseDate = trim($data['expense_date'] ?? date('Y-m-d'));
            $notes = trim($data['notes'] ?? '');

            // Amount can be provided as amount_cents or amount in pesos
            if (isset($data['amount_cents'])) {
                $amountCents = (int)$data['amount_cents'];
            } elseif (isset($data['amount'])) {
                $amountCents = (int)round((float)str_replace(',', '', (string)$data['amount']) * 100);
            } else {
                $amountCents = 0;
            }

            if ($categoryId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Valid expense category is required']);
                exit;
            }

            if (empty($payee)) {
                http_response_code(400);
                echo json_encode(['error' => 'Payee is required']);
                exit;
            }

            if ($amountCents <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Expense amount must be greater than zero']);
                exit;
            }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expenseDate)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid expense date format. Must be YYYY-MM-DD.']);
                exit;
            }

            // Verify category exists
            $catStmt = $pdo->prepare("SELECT id FROM expense_categories WHERE id = ?");
            $catStmt->execute([$categoryId]);
            if (!$catStmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Category does not exist']);
                exit;
            }

            // Constraint 3: Secure receipt file upload via Upload::handle()
            $receiptPath = null;
            if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadDir = APP_ROOT . '/storage/receipts';
                $savedFile = Upload::handle($_FILES['receipt'], $uploadDir);
                if ($savedFile === null) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid receipt file upload. Allowed formats: JPG, PNG, PDF (max 5MB).']);
                    exit;
                }
                $receiptPath = 'storage/receipts/' . $savedFile;
            }

            // Constraint 1: Dynamic Approval Threshold from settings table
            $settingStmt = $pdo->prepare("
                SELECT setting_value 
                FROM settings 
                WHERE setting_key = 'finance_expense_approval_threshold_cents'
            ");
            $settingStmt->execute();
            $thresholdVal = $settingStmt->fetchColumn();
            $thresholdCents = $thresholdVal !== false ? (int)$thresholdVal : 100000;

            if ($amountCents <= $thresholdCents) {
                $status = 'APPROVED';
                $approvedBy = $userId;
            } else {
                $status = 'DRAFT';
                $approvedBy = null;
            }

            $stmt = $pdo->prepare("
                INSERT INTO expenses (
                    branch_id, category_id, amount_cents, expense_date, payee, notes,
                    receipt_path, status, created_by, approved_by, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $branchId,
                $categoryId,
                $amountCents,
                $expenseDate,
                $payee,
                $notes,
                $receiptPath,
                $status,
                $userId,
                $approvedBy
            ]);
            $expenseId = (int)$pdo->lastInsertId();

            Audit::log('EXPENSE_CREATED', json_encode([
                'expense_id' => $expenseId,
                'branch_id' => $branchId,
                'amount_cents' => $amountCents,
                'status' => $status,
                'payee' => $payee,
                'auto_approved' => ($status === 'APPROVED')
            ]));

            echo json_encode([
                'success' => true,
                'expense_id' => $expenseId,
                'status' => $status,
                'amount_cents' => $amountCents,
                'auto_approved' => ($status === 'APPROVED')
            ]);
            exit;
        }

        // =================================================================
        // APPROVE EXPENSE (Maker-Checker enforced)
        // =================================================================
        case 'approve_expense': {
            Csrf::requireValid();

            $expenseId = (int)($data['expense_id'] ?? 0);
            if (!$expenseId) {
                http_response_code(400);
                echo json_encode(['error' => 'Expense ID is required']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ?");
            $stmt->execute([$expenseId]);
            $expense = $stmt->fetch();

            if (!$expense) {
                http_response_code(404);
                echo json_encode(['error' => 'Expense not found']);
                exit;
            }

            // Cross-branch check
            if (!$isAdminOrGa && (int)$expense['branch_id'] !== $userBranchId) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: Cannot approve expenses for another branch']);
                exit;
            }

            // Maker-Checker: Creator cannot approve their own expense
            if ((int)$expense['created_by'] === $userId) {
                http_response_code(403);
                echo json_encode(['error' => 'Maker-Checker violation: Creator cannot approve their own expense']);
                exit;
            }

            // Approver must have finance.expense.approve permission
            Rbac::require_permission('finance.expense.approve');

            if ($expense['status'] !== 'DRAFT') {
                http_response_code(400);
                echo json_encode(['error' => 'Expense is not in DRAFT status (current status: ' . $expense['status'] . ')']);
                exit;
            }

            $update = $pdo->prepare("
                UPDATE expenses 
                SET status = 'APPROVED', approved_by = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $update->execute([$userId, $expenseId]);

            Audit::log('EXPENSE_APPROVED', json_encode([
                'expense_id' => $expenseId,
                'approved_by' => $userId,
                'amount_cents' => (int)$expense['amount_cents']
            ]));

            echo json_encode(['success' => true, 'message' => 'Expense approved successfully']);
            exit;
        }

        // =================================================================
        // VOID EXPENSE (Requires void_reason, does not delete row)
        // =================================================================
        case 'void_expense': {
            Csrf::requireValid();
            Rbac::require_permission('finance.expense.approve');

            $expenseId = (int)($data['expense_id'] ?? 0);
            $voidReason = trim($data['void_reason'] ?? '');

            if (!$expenseId) {
                http_response_code(400);
                echo json_encode(['error' => 'Expense ID is required']);
                exit;
            }

            if (empty($voidReason)) {
                http_response_code(400);
                echo json_encode(['error' => 'Void reason is required']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ?");
            $stmt->execute([$expenseId]);
            $expense = $stmt->fetch();

            if (!$expense) {
                http_response_code(404);
                echo json_encode(['error' => 'Expense not found']);
                exit;
            }

            // Cross-branch check
            if (!$isAdminOrGa && (int)$expense['branch_id'] !== $userBranchId) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: Cannot void expenses for another branch']);
                exit;
            }

            if ($expense['status'] === 'VOIDED') {
                http_response_code(400);
                echo json_encode(['error' => 'Expense is already voided']);
                exit;
            }

            $update = $pdo->prepare("
                UPDATE expenses 
                SET status = 'VOIDED', void_reason = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $update->execute([$voidReason, $expenseId]);

            Audit::log('EXPENSE_VOIDED', json_encode([
                'expense_id' => $expenseId,
                'voided_by' => $userId,
                'void_reason' => $voidReason,
                'amount_cents' => (int)$expense['amount_cents']
            ]));

            echo json_encode(['success' => true, 'message' => 'Expense voided successfully']);
            exit;
        }

        // =================================================================
        // SAVE BUDGET (Constraint 4: finance.budget.manage permission required)
        // =================================================================
        case 'save_budget': {
            Csrf::requireValid();
            Rbac::require_permission('finance.budget.manage');

            $branchId = (int)($data['branch_id'] ?? 0);
            $budgetMonth = trim($data['budget_month'] ?? '');

            // Support allocated_amount_cents or allocated_labor_budget / amount
            if (isset($data['allocated_amount_cents'])) {
                $amountCents = (int)$data['allocated_amount_cents'];
            } elseif (isset($data['allocated_amount'])) {
                $amountCents = (int)round((float)str_replace(',', '', (string)$data['allocated_amount']) * 100);
            } else {
                $amountCents = 0;
            }

            if (!$branchId) {
                http_response_code(400);
                echo json_encode(['error' => 'Branch ID is required']);
                exit;
            }

            if (!preg_match('/^\d{4}-\d{2}$/', $budgetMonth)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid budget month format. Must be YYYY-MM.']);
                exit;
            }

            if ($amountCents < 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Allocated budget cannot be negative']);
                exit;
            }

            // Verify branch exists
            $bCheck = $pdo->prepare("SELECT id FROM branches WHERE id = ?");
            $bCheck->execute([$branchId]);
            if (!$bCheck->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Branch does not exist']);
                exit;
            }

            $upsert = $pdo->prepare("
                INSERT INTO branch_budgets (
                    branch_id, budget_month, allocated_amount_cents, created_by, created_at, updated_at
                ) VALUES (?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                    allocated_amount_cents = VALUES(allocated_amount_cents),
                    updated_at = NOW()
            ");
            $upsert->execute([$branchId, $budgetMonth, $amountCents, $userId]);

            Audit::log('BUDGET_SAVED', json_encode([
                'branch_id' => $branchId,
                'budget_month' => $budgetMonth,
                'allocated_amount_cents' => $amountCents,
                'created_by' => $userId
            ]));

            echo json_encode([
                'success' => true,
                'message' => 'Budget saved successfully',
                'allocated_amount_cents' => $amountCents
            ]);
            exit;
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
