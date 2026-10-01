<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=cafe_pos;charset=utf8mb4', 'root', '');
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "cafe_pos tables:\n";
print_r($tables);

foreach ($tables as $table) {
    $stmt = $pdo->query("DESCRIBE $table");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('branch_id', $cols)) {
        echo "$table has branch_id\n";
    }
}
