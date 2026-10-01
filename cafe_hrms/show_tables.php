<?php
require_once __DIR__ . '/init.php';
$stmt = $pdo->query("SHOW TABLES");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
