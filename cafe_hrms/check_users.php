<?php
require 'init.php';
echo "=== PAYROLL ===\n";
$cols = $pdo->query("DESCRIBE payroll")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) echo $c['Field'] . " | " . $c['Type'] . " | " . ($c['Null']==='YES'?'NULL':'NOT NULL') . " | " . ($c['Default']??'') . "\n";
echo "\n=== EMPLOYEES (branch_id + id only) ===\n";
$cols = $pdo->query("DESCRIBE employees")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    if (in_array($c['Field'], ['id','employee_id','branch_id','first_name','last_name','position'])) 
        echo $c['Field'] . " | " . $c['Type'] . "\n";
}
