<?php
require 'init.php';
$stmt = $pdo->query("SELECT * FROM branches");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
