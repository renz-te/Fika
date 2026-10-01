<?php
$pdoPos = new PDO('mysql:host=127.0.0.1;dbname=cafe_pos;charset=utf8mb4', 'root', '');
$stmt = $pdoPos->query('DESCRIBE orders');
if($stmt) print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
