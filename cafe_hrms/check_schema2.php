<?php
require_once __DIR__ . '/init.php';

$stmt = $pdo->query("DESCRIBE purchase_requests");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($columns);
