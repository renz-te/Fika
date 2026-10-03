<?php
try {
    $posPdo = new PDO("mysql:host=127.0.0.1;dbname=cafe_pos;charset=utf8mb4", "root", "");
    $posPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $posPdo->exec("ALTER TABLE products ADD COLUMN image_path VARCHAR(255) DEFAULT NULL;");
    echo "Success!";
} catch (Exception $e) {
    echo $e->getMessage();
}
