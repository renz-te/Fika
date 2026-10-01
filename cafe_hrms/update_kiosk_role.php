<?php
require_once __DIR__ . '/init.php';

$stmt = $pdo->prepare("INSERT INTO roles (name) VALUES ('Clock')");
$stmt->execute();
$clockRoleId = $pdo->lastInsertId();

$stmt = $pdo->prepare("UPDATE users SET role_id = ? WHERE id = 163");
$stmt->execute([$clockRoleId]);

echo "Created Clock role ($clockRoleId) and updated user 163.\n";
