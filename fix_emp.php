<?php
$pdo = new PDO('mysql:host=localhost;dbname=fika_unified', 'root', '');
$sql = "
ALTER TABLE employees 
    CHANGE employee_id employee_code VARCHAR(20) UNIQUE DEFAULT NULL,
    MODIFY employment_type ENUM('REGULAR', 'CONTRACTUAL', 'PROBATIONARY', 'PART_TIME', 'INTERN') NOT NULL DEFAULT 'REGULAR',
    ADD COLUMN basic_salary DECIMAL(12,2) DEFAULT 0.00;
";
try {
    $pdo->exec($sql);
    echo "Fixed employees!\n";
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
}
