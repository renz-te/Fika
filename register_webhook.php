<?php
/**
 * Register PayMongo Webhook Endpoint
 * Reads configuration from environment variables or CLI arguments.
 */

declare(strict_types=1);

// Optional root .env loader if variables not set in environment
$envFile = __DIR__ . '/.env';
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

// 1. Read Secret Key & Webhook URL from environment variables or CLI arguments
$secretKey = getenv('PAYMONGO_SECRET_KEY') ?: ($_ENV['PAYMONGO_SECRET_KEY'] ?? '');
$webhookUrl = getenv('WEBHOOK_URL') ?: ($_ENV['WEBHOOK_URL'] ?? '');

// CLI arguments fallback: php register_webhook.php <webhook_url> <secret_key>
if (isset($argv[1]) && !empty($argv[1])) {
    $webhookUrl = trim($argv[1]);
}
if (isset($argv[2]) && !empty($argv[2])) {
    $secretKey = trim($argv[2]);
}

if (empty($secretKey)) {
    fwrite(STDERR, "Error: Missing PAYMONGO_SECRET_KEY. Please provide via environment variable PAYMONGO_SECRET_KEY or as second CLI argument.\n");
    exit(1);
}

if (empty($webhookUrl)) {
    fwrite(STDERR, "Error: Missing WEBHOOK_URL. Please provide via environment variable WEBHOOK_URL or as first CLI argument.\n");
    exit(1);
}

$ch = curl_init('https://api.paymongo.com/v1/webhooks');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_USERPWD, $secretKey . ':');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'data' => [
        'attributes' => [
            'events' => ['payment.paid', 'payment.failed'],
            'url' => $webhookUrl
        ]
    ]
]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    fwrite(STDERR, "cURL Error: {$curlErr}\n");
    exit(1);
}

echo "Response (HTTP {$httpCode}):\n";
$decoded = json_decode((string)$response, true);
// Redact any sensitive secret if returned in response
if (isset($decoded['data']['attributes']['secret_key'])) {
    $decoded['data']['attributes']['secret_key'] = '***REDACTED***';
}
print_r($decoded);
