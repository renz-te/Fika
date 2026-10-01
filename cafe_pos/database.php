<?php
$host = '127.0.0.1';
$db   = 'cafe_pos';
$user = 'root'; // default Laragon MySQL user
$pass = '';     // default Laragon MySQL password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // If database doesn't exist, try connecting without db name to create it
    if ($e->getCode() == 1049) {
        try {
            $pdo_init = new PDO("mysql:host=$host;charset=$charset", $user, $pass, $options);
            $pdo_init->exec("CREATE DATABASE IF NOT EXISTS `$db`");
            $pdo_init->exec("USE `$db`");
            
            // Read and execute schema file if it exists
            $schema_file = __DIR__ . '/schema.sql';
            if (file_exists($schema_file)) {
                $sql = file_get_contents($schema_file);
                $pdo_init->exec($sql);
            }
            
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e_init) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(["error" => "Database Connection Failed: " . $e_init->getMessage()]);
            exit;
        }
    } else {
        header('Content-Type: application/json', true, 500);
        echo json_encode(["error" => "Database Connection Failed: " . $e->getMessage()]);
        exit;
    }
}
?>
