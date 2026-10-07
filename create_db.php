<?php
$pdo = new PDO('mysql:host=localhost', 'root', '');
$pdo->exec("CREATE DATABASE IF NOT EXISTS fika_unified");
echo "Done";
