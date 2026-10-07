<?php
$pdo = new PDO('mysql:host=localhost;dbname=fika_unified', 'root', '');
foreach (glob('migrations/*.sql') as $file) {
    $sql = file_get_contents($file);
    try {
        $pdo->exec($sql);
        echo "Ran $file\n";
    } catch (Exception $e) {
        echo "Error in $file: " . $e->getMessage() . "\n";
    }
}
