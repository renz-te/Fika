<?php
require_once __DIR__ . '/init.php';

if (!empty($_SESSION['user'])) {
    redirect('dashboard');
}
redirect('login');
