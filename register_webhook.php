<?php
// register_webhook.php — CLI only. Reads secrets from the environment.

require_once __DIR__ . '/config/config.php';

$secretKey  = getenv('PAYMONGO_SECRET_KEY');
$webhookUrl = getenv('WEBHOOK_URL');

if (empty($secretKey)) {
    exit("Error: PAYMONGO_SECRET_KEY is not set.\n");
}
if (empty($webhookUrl)) {
    exit("Error: WEBHOOK_URL is not set.\n");
}

$ch = curl_init('https://api.paymongo.com/v1/webhooks');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_USERPWD, $secretKey . ':');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'data' => [
        'attributes' => [
            'events' => ['payment.paid', 'payment.failed'],
            'url'    => $webhookUrl,
        ],
    ],
]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Response:\n";
print_r(json_decode($response, true));

echo ($httpCode >= 200 && $httpCode < 300)
    ? "Webhook registered successfully.\n"
    : "Failed to register webhook. HTTP $httpCode\n";
