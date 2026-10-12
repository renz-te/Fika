<?php
// PayMongo Configuration
// Relies purely on environment variables (or returns empty strings if not set).

// Optional local root .env loader if variables not set in server environment
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\n\r\0\x0B\"'");
            if (getenv($k) === false && !isset($_ENV[$k]) && !isset($_SERVER[$k])) {
                putenv("{$k}={$v}");
                $_ENV[$k] = $v;
                $_SERVER[$k] = $v;
            }
        }
    }
}

$publicKey = getenv('PAYMONGO_PUBLIC_KEY') ?: ($_ENV['PAYMONGO_PUBLIC_KEY'] ?? ($_SERVER['PAYMONGO_PUBLIC_KEY'] ?? ''));
$secretKey = getenv('PAYMONGO_SECRET_KEY') ?: ($_ENV['PAYMONGO_SECRET_KEY'] ?? ($_SERVER['PAYMONGO_SECRET_KEY'] ?? ''));
$webhookSecret = getenv('PAYMONGO_WEBHOOK_SECRET') ?: ($_ENV['PAYMONGO_WEBHOOK_SECRET'] ?? ($_SERVER['PAYMONGO_WEBHOOK_SECRET'] ?? ''));

return [
    'public_key'        => (string)$publicKey,
    'secret_key'        => (string)$secretKey,
    'webhook_secret'    => (string)$webhookSecret,
    'is_test'           => true,
    'supported_methods' => ['gcash', 'paymaya', 'card'],
];
