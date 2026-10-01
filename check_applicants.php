<?php
require 'c:\laragon\www\cafe_hrms\init.php';
$stmt = $pdo->query("DESCRIBE applicants");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
