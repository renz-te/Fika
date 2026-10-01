<?php
require_once __DIR__ . '/init.php';
require_login();
$user = current_user();

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($token)) {
        $name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $status = 'Applied';
        if ($name && $email && $position) {
            $stmt = $pdo->prepare('INSERT INTO applicants (full_name, email, position_applied, status, created_at) VALUES (?, ?, ?, ?, NOW())');
            $stmt->execute([$name, $email, $position, $status]);
            $message = 'Applicant registered successfully.';
            log_activity($pdo, $_SESSION['user']['id'], 'add_applicant', "Added applicant {$name}");
        }
    }
}

$action = $_GET['action'] ?? null;
$applicantId = $_GET['id'] ?? null;
if ($action && $applicantId && in_array($action, ['interview', 'exam', 'final', 'hire', 'reject'], true)) {
    $statusMap = [
        'interview' => 'Interview',
        'exam' => 'Exam',
        'final' => 'Final Interview',
        'hire' => 'Hired',
        'reject' => 'Rejected',
    ];
    $stmt = $pdo->prepare('UPDATE applicants SET status = ? WHERE id = ?');
    $stmt->execute([$statusMap[$action], $applicantId]);
    $message = 'Applicant status updated.';
    log_activity($pdo, $_SESSION['user']['id'], 'update_applicant', "Applicant {$applicantId} status {$statusMap[$action]}");
}

$applicants = $pdo->query('SELECT id, first_name, last_name, CONCAT(first_name, " ", last_name) AS full_name, email, phone, position_applied, stage, created_at, resume FROM applicants ORDER BY created_at DESC')->fetchAll();
?>
