<?php
// Configuration Example - Copy this to config.php
return [
    'app' => [
        'env' => 'local', // local, staging, production
        'base_url' => 'http://fika.test',
        'timezone' => 'Asia/Manila',
        'session_timeout' => 3600,
    ],
    
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'hrms', // Unified database
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    
    'security' => [
        'app_key' => 'CHANGE_ME_TO_A_RANDOM_SECURE_STRING',
    ],
    
    'paymongo' => [
        'public_key' => 'pk_test_...',
        'secret_key' => 'sk_test_...',
        'webhook_secret' => 'whsec_...',
    ]
];
