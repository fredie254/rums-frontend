<?php
/**
 * RUMS - M-Pesa STK Push Callback (thin proxy)
 * Receives Safaricom webhook and forwards to the REST API.
 *
 * To remove this proxy entirely, change the Safaricom callback URL to:
 *   https://api-rums.nexusiot.xyz/api/v1/mpesa/callback
 */
require_once __DIR__ . '/../config/config.php';

$raw = file_get_contents('php://input');
error_log('[M-Pesa Callback Proxy] ' . date('Y-m-d H:i:s') . ' | ' . $raw);

header('Content-Type: application/json');

if (empty($raw)) {
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Empty body']);
    exit;
}

// Forward raw body to the API's public callback endpoint
$ch = curl_init(API_URL . '/api/v1/mpesa/callback');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $raw,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response = curl_exec($ch);
$err      = curl_error($ch);
curl_close($ch);

if ($err) {
    error_log('[M-Pesa Callback Proxy Error] cURL: ' . $err);
}

// Always acknowledge Safaricom
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
