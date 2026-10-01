<?php
require_once __DIR__ . '/../init.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicant_id = (int)($_POST['applicant_id'] ?? 0);
    $new_stage = $_POST['new_stage'] ?? '';
    
    if (!$applicant_id || empty($new_stage)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
        exit;
    }

    // Backend State Lock
    $is_forward = in_array($new_stage, ['Final Interview', 'Hireable']);
    if ($is_forward) {
        $chkStmt = $pdo->prepare("SELECT COUNT(*) FROM interviews WHERE applicant_id = ? AND (status = 'Scheduled' OR (status = 'Completed' AND id NOT IN (SELECT interview_id FROM interview_scorecards)))");
        $chkStmt->execute([$applicant_id]);
        if ($chkStmt->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Progression blocked. Candidate requires scorecard submission via the Interview Workspace.']);
            exit;
        }
    }
    
    if ($new_stage === 'Rejected') {
        $stmt = $pdo->prepare('UPDATE applicants SET stage = ?, rejection_reason = ? WHERE id = ?');
        $stmt->execute([$new_stage, 'Rejected via drag and drop', $applicant_id]);
        
        $stmtAWOL = $pdo->prepare("UPDATE interviews SET status = 'Cancelled' WHERE applicant_id = ? AND status = 'Scheduled'");
        $stmtAWOL->execute([$applicant_id]);
    } else {
        $stmt = $pdo->prepare('UPDATE applicants SET stage = ? WHERE id = ?');
        $stmt->execute([$new_stage, $applicant_id]);
    }
    
    // Log the stage change
    $user_name = $_SESSION['user']['name'] ?? 'System';
    $logStmt = $pdo->prepare('INSERT INTO applicant_logs (applicant_id, user_name, action) VALUES (?, ?, ?)');
    $logStmt->execute([$applicant_id, $user_name, "Moved to $new_stage via Kanban Drag-and-Drop"]);
    
    echo json_encode(['status' => 'success']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
exit;
