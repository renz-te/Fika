<?php
require_once __DIR__ . '/init.php';
$stmt = $pdo->prepare("SELECT id, name FROM roles");
$stmt->execute();
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($roles as $r) {
    echo $r['id'] . " => " . $r['name'] . "\n";
}
