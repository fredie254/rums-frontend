<?php
/**
 * AJAX settings save endpoint
 * POST /settings/save.php  — JSON body, returns JSON
 */
require_once __DIR__ . '/../config/config.php';
require_role('admin');

header('Content-Type: application/json; charset=utf-8');

// Validate CSRF
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body) || empty($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Empty or invalid request body.']);
    exit;
}

// Sanitise each value
$payload = [];
foreach ($body as $k => $v) {
    $payload[trim((string)$k)] = trim((string)$v);
}

$res = (new ApiClient())->put('settings', $payload);

if (!empty($res['success'])) {
    echo json_encode(['success' => true, 'message' => 'Settings saved successfully.']);
} else {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $res['message'] ?? 'Failed to save settings.']);
}
