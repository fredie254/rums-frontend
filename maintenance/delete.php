<?php
/**
 * AJAX delete handler for maintenance work orders (admin / manager only)
 * POST /maintenance/delete  — JSON body {id: int}
 */
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

header('Content-Type: application/json; charset=utf-8');

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$id   = (int)($body['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid work order ID.']);
    exit;
}

$res = (new ApiClient())->delete("maintenance/$id");

if (!empty($res['success'])) {
    echo json_encode(['success' => true, 'message' => $res['message'] ?? 'Work order deleted.']);
} else {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $res['message'] ?? 'Failed to delete work order.']);
}
