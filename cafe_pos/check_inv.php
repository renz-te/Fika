<?php
$pdoPos = new PDO('mysql:host=127.0.0.1;dbname=cafe_pos;charset=utf8mb4', 'root', '');
$stmt = $pdoPos->query('SHOW CREATE TABLE inventory');
if($stmt) print_r($stmt->fetch(PDO::FETCH_ASSOC));
$stmt = $pdoPos->query('SHOW CREATE TABLE recipes');
if($stmt) print_r($stmt->fetch(PDO::FETCH_ASSOC));
