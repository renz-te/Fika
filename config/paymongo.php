<?php
// PayMongo Configuration
// Fail-closed: secrets come only from the environment. No fallbacks.

return [
    'public_key'        => getenv('PAYMONGO_PUBLIC_KEY')     ?: null,
    'secret_key'        => getenv('PAYMONGO_SECRET_KEY')     ?: null,
    'webhook_secret'    => getenv('PAYMONGO_WEBHOOK_SECRET') ?: null,
    'is_test'           => getenv('PAYMONGO_IS_TEST') === 'true',
    'supported_methods' => ['gcash', 'paymaya', 'card'],
];
