<?php
require_once __DIR__ . '/init.php';
$token = $_GET['token'] ?? null;
$message = 'Verification link is invalid.';
if ($token) {
    $stmt = $pdo->prepare('SELECT ev.user_id FROM email_verifications ev WHERE ev.token = ? ORDER BY ev.created_at DESC LIMIT 1');
    $stmt->execute([$token]);
    $verification = $stmt->fetch();
    if ($verification) {
        $stmt = $pdo->prepare('UPDATE users SET verified = 1 WHERE id = ?');
        $stmt->execute([$verification['user_id']]);
        $stmt = $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ?');
        $stmt->execute([$verification['user_id']]);
        $message = 'Your email has been verified. You can now sign in.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Café HRMS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
<div class="min-vh-100 d-flex align-items-center justify-content-center p-3">
    <div class="card shadow-sm w-100" style="max-width:420px;">
        <div class="card-body p-4 text-center">
            <h3 class="mb-3">Email Verification</h3>
            <p><?php echo h($message); ?></p>
            <a href="login" class="btn btn-brown">Go to Login</a>
        </div>
    </div>
</div>
</body>
</html>
