<?php
// PayMongo Configuration (Test Keys Only - Environment Protected)
// Ensure actual production secrets are provided via environment variables.

return [
    'public_key'     => getenv('PAYMONGO_PUBLIC_KEY') ?: (defined('PAYMONGO_PUBLIC_KEY') ? PAYMONGO_PUBLIC_KEY : 'pk_test_xxyz1234'),
    'secret_key'     => getenv('PAYMONGO_SECRET_KEY') ?: (defined('PAYMONGO_SECRET_KEY') ? PAYMONGO_SECRET_KEY : 'sk_test_xxyz1234'),
    'webhook_secret' => getenv('PAYMONGO_WEBHOOK_SECRET') ?: (defined('PAYMONGO_WEBHOOK_SECRET') ? PAYMONGO_WEBHOOK_SECRET : 'whk_test_xxyz1234'),
    'is_test'        => true,
    'supported_methods' => ['gcash', 'paymaya', 'card'],
];
