<?php
require_once __DIR__ . '/init.php';

try {
    $pdo->exec("ALTER TABLE interviews ADD COLUMN end_time TIME NULL AFTER interview_time");
    echo "Successfully added end_time to interviews table.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
