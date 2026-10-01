<?php
require 'init.php';
$cols = $pdo->query('DESCRIBE applicants')->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);
