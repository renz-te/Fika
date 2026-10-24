<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('settings.manage');

$user = Auth::user();
$currentRoleId = (int)($user['role_id'] ?? 0);

// Determine branchId strictly from session / role
// Regardless of what is submitted in $input, BM is strictly locked to their branch_id
$branchId = null;
if ($currentRoleId === 6) {
    // Branch Manager: strictly force to their session branch_id
    $branchId = !empty($user['branch_id']) ? (int)$user['branch_id'] : null;
    if (!$branchId) {
        http_response_code(400);
        exit(json_encode(["error" => "Branch Manager must have an assigned branch to modify settings."]));
    }
} elseif ($currentRoleId === 1 || $currentRoleId === 3) {
    // Admin or GA -> Global Settings (branch_id = NULL)
    $branchId = null;
} else {
    $branchId = !empty($user['branch_id']) ? (int)$user['branch_id'] : null;
}

$input = json_decode(file_get_contents('php://input'), true);
$grace = (int) ($input['late_grace_minutes'] ?? 15);
$leaves = $input['leave_types'] ?? [];

global $pdo;

try {
    $pdo->beginTransaction();
    
    // Helper function to save setting row (handles branch-specific upsert & global null unique)
    $saveSetting = function(string $key, string $value) use ($pdo, $branchId, $user) {
        if ($branchId === null) {
            $check = $pdo->prepare("SELECT id FROM settings WHERE setting_key = ? AND branch_id IS NULL");
            $check->execute([$key]);
            $existingId = $check->fetchColumn();
            if ($existingId) {
                $upd = $pdo->prepare("UPDATE settings SET setting_value = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
                $upd->execute([$value, $user['id'], $existingId]);
            } else {
                $ins = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, branch_id, updated_by, updated_at) VALUES (?, ?, NULL, ?, NOW())");
                $ins->execute([$key, $value, $user['id']]);
            }
        } else {
            $upsert = $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value, branch_id, updated_by, updated_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()
            ");
            $upsert->execute([$key, $value, $branchId, $user['id']]);
        }
    };
    
    // Save late grace period
    $saveSetting('late_grace_minutes', (string)$grace);
    
    // Save Statutory Contributions
    $contribs = ['contrib_FULL_TIME', 'contrib_PART_TIME', 'contrib_CONTRACTUAL', 'contrib_INTERN'];
    foreach ($contribs as $c) {
        $val = !empty($input[$c]) ? '1' : '0';
        $saveSetting($c, $val);
    }

    // Save Receipt & BIR settings
    $receiptKeys = ['receipt_business_name', 'receipt_address', 'receipt_tin', 'receipt_footer', 'receipt_bir_registered', 'bir_registered'];
    foreach ($receiptKeys as $rk) {
        if (isset($input[$rk])) {
            $saveSetting($rk, (string)$input[$rk]);
        }
    }
    
    // Save leave types (Admin / Global scope only)
    if ($branchId === null && !empty($leaves)) {
        $leaveStmt = $pdo->prepare("UPDATE leave_types SET name = ?, is_paid = ?, annual_days = ?, min_service_months = ?, statutory = ? WHERE code = ?");
        foreach ($leaves as $code => $l) {
            $leaveStmt->execute([
                $l['name'],
                empty($l['is_paid']) ? 0 : 1,
                (int)$l['annual_days'],
                (int)$l['min_service_months'],
                empty($l['statutory']) ? 0 : 1,
                $code
            ]);
        }
    }
    
    $scopeDesc = ($branchId === null) ? "global settings" : "branch settings for branch {$branchId}";
    Audit::log('SETTINGS_UPDATED', "User {$user['id']} updated {$scopeDesc}.");
    
    $pdo->commit();
    echo json_encode(["success" => true, "message" => "Settings saved successfully."]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    exit(json_encode(["error" => $e->getMessage()]));
}
