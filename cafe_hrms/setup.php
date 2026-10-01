<?php
require_once __DIR__ . '/init.php';
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sqlFile = __DIR__ . '/db/hrms.sql';
    if (!file_exists($sqlFile)) {
        $message = 'Schema file not found.';
    } else {
        $sql = file_get_contents($sqlFile);
        $statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
        foreach ($statements as $statement) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $ex) {
                // continue on error for imports
            }
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users');
        try {
            $stmt->execute();
            $hasUsers = (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $ex) {
            $hasUsers = false;
        }
        if (!$hasUsers) {
            $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
            $roleStmt->execute(['Super Admin']);
            $roleId = $roleStmt->fetchColumn();
            $password = password_hash('Admin123!', PASSWORD_DEFAULT);
            $insert = $pdo->prepare('INSERT INTO users (name, username, email, password, role_id, verified, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())');
            $insert->execute(['System Admin', 'admin', 'admin@cafehrms.local', $password, $roleId]);
        }
        $message = 'Setup completed successfully. Default admin credentials: admin@cafehrms.local / Admin123!';
    }
}
?>
