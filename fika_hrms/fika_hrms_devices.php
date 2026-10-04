<?php
require_once __DIR__ . '/../app/bootstrap.php';
Auth::requireLogin();
Rbac::require_permission('branches.manage'); // Or whatever permission covers devices
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fika HRMS - Devices</title>
</head>
<body>
    <h1>Registered Devices</h1>
    <p>Device management page placeholder.</p>
</body>
</html>
