<?php
require 'c:\laragon\www\cafe_hrms\init.php';
$stmt = $pdo->query("SELECT u.id, u.name, u.username, r.name as role_name, u.employee_id FROM users u JOIN roles r ON u.role_id = r.id WHERE u.employee_id IS NULL");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
