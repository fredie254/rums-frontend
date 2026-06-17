<?php
/**
 * RUMS - M-Pesa STK Push endpoint (thin proxy)
 * Forwards request to POST /api/v1/mpesa/stk-push
 * Returns JSON
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}
if (!verify_csrf()) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$phone      = clean($_POST['phone']      ?? '');
$amount     = (float)($_POST['amount']   ?? 0);
$lease_id   = (int)($_POST['lease_id']   ?? 0);
$invoice_id = (int)($_POST['invoice_id'] ?? 0) ?: null;
$account    = clean($_POST['account']    ?? 'RENT');

if (!$phone || $amount <= 0 || !$lease_id) {
    echo json_encode(['success' => false, 'message' => 'Phone, amount, and lease are required.']);
    exit;
}

$res = (new ApiClient())->post('mpesa/stk-push', [
    'phone'      => $phone,
    'amount'     => $amount,
    'lease_id'   => $lease_id,
    'invoice_id' => $invoice_id,
    'account'    => $account,
]);

if (!empty($res['success'])) {
    echo json_encode([
        'success'             => true,
        'message'             => $res['message'] ?? 'STK Push sent. Enter your M-Pesa PIN.',
        'checkout_request_id' => $res['data']['checkout_request_id'] ?? '',
        'payment_id'          => $res['data']['payment_id'] ?? null,
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $res['message'] ?? 'STK Push failed.',
    ]);
}
