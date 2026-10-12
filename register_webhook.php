<?php
$secretKey = 'sk_test_DMPoHmYv73Y4wB5xMM62f9q2';
$webhookUrl = 'https://expire-goggles-calzone.ngrok-free.dev/Fika/Fika/cafe_pos/api/payment_webhook.php';

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
curl_close($ch);

echo "Response:\n";
$decoded = json_decode($response, true);
print_r($decoded);
