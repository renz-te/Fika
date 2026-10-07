<?php
$pdo = new PDO('mysql:host=localhost;dbname=fika_unified', 'root', '');
$pdo->exec("INSERT INTO users (id, username, password, role_id) VALUES (1, 'admin', '...', 1), (2, 'user2', '...', 1), (3, 'user3', '...', 1) ON DUPLICATE KEY UPDATE id=id;");
echo 'Users inserted';
