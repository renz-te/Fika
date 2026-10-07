<?php
$pdo = new PDO('mysql:host=localhost', 'root', '');
print_r($pdo->query('SHOW DATABASES')->fetchAll());
