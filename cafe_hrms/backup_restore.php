<?php
require_once __DIR__ . '/init.php';
require_login();
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    $token = $_POST['csrf_token'];
    if (verify_csrf_token($token) && isset($_FILES['restore_file'])) {
        $sql = file_get_contents($_FILES['restore_file']['tmp_name']);
        try {
            $statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
            foreach ($statements as $statement) {
                if ($statement !== '') {
                    $pdo->exec($statement);
                }
            }
            $message = 'Database restored successfully from uploaded file.';
        } catch (PDOException $ex) {
            $message = 'Restore failed: ' . $ex->getMessage();
        }
    }
}

if (isset($_GET['download']) && $_GET['download'] === 'sql') {
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="hrms_backup_' . date('Ymd_His') . '.sql"');
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $create = $pdo->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_ASSOC);
        echo "DROP TABLE IF EXISTS `{$table}`;\n";
        echo $create['Create Table'] . ";\n\n";
        $rows = $pdo->query('SELECT * FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $columns = array_map(function($col) { return "`{$col}`"; }, array_keys($row));
            $values = array_map(function($value) use ($pdo) {
                return $value === null ? 'NULL' : $pdo->quote($value);
            }, array_values($row));
            echo "INSERT INTO `{$table}` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
        }
        echo "\n";
    }
    exit;
}
?>
