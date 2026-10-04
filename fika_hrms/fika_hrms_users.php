<?php
require_once __DIR__ . '/../app/bootstrap.php';
Auth::requireLogin();
Rbac::require_permission('users.view');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fika HRMS - Users</title>
</head>
<body>
    <h1>System Users</h1>
    <p>Welcome, <?= e(Auth::user()['id'] ?? 'Unknown User') ?>. You have permission to view users.</p>
</body>
</html>
