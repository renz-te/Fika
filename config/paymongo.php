<?php
// PayMongo Configuration
// Fail-closed: secrets come only from .env / environment. No fallcoded secrets.

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
            if (getenv($k) === false || getenv($k) === '') {
                putenv("{$k}={$v}");
                $_ENV[$k] = $v;
                $_SERVER[$k] = $v;
            }
        }
    }
}

$publicKey     = getenv('PAYMONGO_PUBLIC_KEY');
$secretKey     = getenv('PAYMONGO_SECRET_KEY');
$webhookSecret = getenv('PAYMONGO_WEBHOOK_SECRET');
$isTestEnv     = getenv('PAYMONGO_IS_TEST');

return [
    'public_key'        => (!empty($publicKey) && is_string($publicKey)) ? $publicKey : null,
    'secret_key'        => (!empty($secretKey) && is_string($secretKey)) ? $secretKey : null,
    'webhook_secret'    => (!empty($webhookSecret) && is_string($webhookSecret)) ? $webhookSecret : null,
    'is_test'           => ($isTestEnv === 'true' || $isTestEnv === '1'),
    'supported_methods' => ['gcash', 'paymaya', 'card'],
];
