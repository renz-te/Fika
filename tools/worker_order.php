<?php
// tools/worker_order.php
require_once __DIR__ . '/../app/bootstrap.php';
global $pdo;

$maxRetries = 5;
$retryDelay = 50000;

for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
    try {
        $pdo->beginTransaction();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');
        
        $stmt = $pdo->query("SELECT current_count, prefix, padding FROM doc_counters WHERE document_type = 'ORDER' FOR UPDATE");
        $counter = $stmt->fetch();
        
        if (!$counter) {
            $pdo->exec("INSERT INTO doc_counters (document_type, current_count, prefix, padding) VALUES ('ORDER', 1, 'ORD-', 6)");
            $orderNumber = 'ORD-000001';
        } else {
            $next = $counter['current_count'] + 1;
            $pdo->prepare("UPDATE doc_counters SET current_count = ? WHERE document_type = 'ORDER'")->execute([$next]);
            $orderNumber = $counter['prefix'] . str_pad((string)$next, (int)$counter['padding'], '0', STR_PAD_LEFT);
        }
        
        usleep(rand(1000, 10000));
        
        $pdo->prepare("INSERT INTO orders (order_number, branch_id, pos_session_id, cashier_id, order_status, payment_status, total_amount, final_amount, is_test) VALUES (?, 1, 1, 1, 'PENDING', 'UNPAID', 0, 0, 1)")
            ->execute([$orderNumber]);
            
        $pdo->commit();
        echo $orderNumber . "\n";
        exit(0);
    } catch (Exception $e) {
        $pdo->rollBack();
        if ($e->getCode() == 40001) { // Serialization failure / Deadlock
            usleep($retryDelay);
            continue;
        }
        echo "ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
}
echo "ERROR: Deadlock retries exhausted.\n";
