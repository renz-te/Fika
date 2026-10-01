<?php
error_reporting(0);
header('Content-Type: application/json');
require_once __DIR__ . '/../database.php';

try {
    $productsStmt = $pdo->query("SELECT * FROM products");
    $products = $productsStmt->fetchAll();

    $modifiersStmt = $pdo->query("SELECT * FROM modifiers");
    $modifiers = $modifiersStmt->fetchAll();

    $menu = [
        'products' => [],
        'modifiers' => $modifiers
    ];

    // Group products by category
    foreach ($products as $product) {
        $category = $product['category'];
        if (!isset($menu['products'][$category])) {
            $menu['products'][$category] = [];
        }
        $menu['products'][$category][] = $product;
    }

    echo json_encode($menu);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
