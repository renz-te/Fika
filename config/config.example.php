<?php
// Example Configuration
return [
    'app' => [
        'env' => 'local', 
        'base_url' => 'http://fika.test',
        'timezone' => 'Asia/Manila',
        'session_timeout' => 3600,
    ],
    
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'fika_unified', 
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    
    'security' => [
        'app_key' => 'local_dev_key_super_secret',
    ],
    
    'paymongo' => [
        'public_key' => getenv('PAYMONGO_PUBLIC_KEY') ?: '',
        'secret_key' => getenv('PAYMONGO_SECRET_KEY') ?: '',
        'webhook_secret' => getenv('PAYMONGO_WEBHOOK_SECRET') ?: '',
    ]
];
