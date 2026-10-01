<?php
require 'c:\laragon\www\cafe_hrms\init.php';
$stmt = $pdo->query("SELECT * FROM roles");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
