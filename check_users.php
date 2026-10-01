<?php
require 'c:\laragon\www\cafe_hrms\init.php';
$stmt = $pdo->query("DESCRIBE users");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
