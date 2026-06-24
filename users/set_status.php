<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$id     = int_param('id');
$status = get_param('status');
$csrf   = get_param('csrf');

if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/users/index');
}
if (!$id || $id == $_SESSION['user_id']) {
    set_flash('error', 'Cannot modify your own status.'); redirect(BASE_URL . '/users/index');
}
if (!in_array($status, ['active', 'inactive', 'suspended'], true)) {
    set_flash('error', 'Invalid status.'); redirect(BASE_URL . '/users/index');
}

$res = (new ApiClient())->patch("users/$id/status", ['status' => $status]);

if (!empty($res['success'])) {
    $labels = ['active' => 'activated', 'inactive' => 'deactivated', 'suspended' => 'suspended'];
    set_flash('success', 'User account ' . $labels[$status] . '.');
} else {
    set_flash('error', $res['message'] ?? 'Failed to update status.');
}

redirect(BASE_URL . '/users/index');
