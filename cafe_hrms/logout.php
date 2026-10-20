<?php
require_once __DIR__ . '/init.php';
$_SESSION = [];
if (session_id()) {
    session_destroy();
}
header('Location: ../login.php');
exit;

