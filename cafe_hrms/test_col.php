<?php
require_once __DIR__ . '/init.php';

try {
    $stmt = $pdo->query("SELECT item_name FROM inventory_transactions LIMIT 1");
    echo "item_name exists in inventory_transactions.\n";
} catch (PDOException $e) {
    echo "Error in inventory_transactions: " . $e->getMessage() . "\n";
}

try {
    $stmt = $pdo->query("SELECT item_name FROM purchase_requests LIMIT 1");
    echo "item_name exists in purchase_requests.\n";
} catch (PDOException $e) {
    echo "Error in purchase_requests: " . $e->getMessage() . "\n";
}
