<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$api  = new ApiClient();
$id   = int_param('id');
$csrf = get_param('csrf');

if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/users/index');
}

if (!$id || $id == $_SESSION['user_id']) {
    set_flash('error', 'Cannot modify this user.'); redirect(BASE_URL . '/users/index');
}

$res  = $api->get("users/$id");
$user = $res['data'] ?? null;
if (!$user) { set_flash('error', 'User not found.'); redirect(BASE_URL . '/users/index'); }

$new_status = ($user['status'] ?? '') === 'active' ? 'inactive' : 'active';
$api->patch("users/$id/status", ['status' => $new_status]);

set_flash('success', 'User status updated to ' . $new_status . '.');
redirect(BASE_URL . '/users/index');
