<?php
require_once __DIR__ . '/init.php';

$stmt = $pdo->query("DESCRIBE inventory_transactions");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($columns);
