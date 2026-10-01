<?php
require_once __DIR__ . '/init.php';
$stmt = $pdo->query("DESCRIBE interviews");
$interviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "INTERVIEWS SCHEMA:\n";
print_r($interviews);
