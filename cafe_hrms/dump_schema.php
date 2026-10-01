<?php
require_once __DIR__ . '/init.php';
$stmt = $pdo->query("DESCRIBE applicants");
$applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "APPLICANTS SCHEMA:\n";
print_r($applicants);

$stmt = $pdo->query("DESCRIBE schedules");
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nSCHEDULES SCHEMA:\n";
print_r($schedules);
