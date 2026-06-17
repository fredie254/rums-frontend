<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$api    = new ApiClient();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { set_flash('error','Invalid request.'); redirect(BASE_URL.'/users/change_password.php'); }
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$current)          $errors[] = 'Current password is required.';
    if (strlen($new) < 8)   $errors[] = 'New password must be at least 8 characters.';
    if ($new !== $confirm)  $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $res = $api->post('auth/change-password', [
            'current_password' => $current,
            'new_password'     => $new,
        ]);

        if (!empty($res['success'])) {
            set_flash('success','Password changed successfully.');
            redirect(BASE_URL.'/dashboard/index.php');
        }
        $errors[] = $res['message'] ?? 'Failed to change password.';
    }
}

$page_title = 'Change Password';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3"><h5 class="fw-bold mb-0"><i class="bi bi-key me-2 text-primary"></i>Change Password</h5></div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="card shadow-sm" style="max-width:420px"><div class="card-body">
    <form method="POST">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-12"><label class="form-label fw-semibold">Current Password *</label><input type="password" name="current_password" class="form-control" required></div>
            <div class="col-12"><label class="form-label fw-semibold">New Password *</label><input type="password" name="new_password" class="form-control" minlength="8" required></div>
            <div class="col-12"><label class="form-label fw-semibold">Confirm New Password *</label><input type="password" name="confirm_password" class="form-control" required></div>
            <div class="col-12"><button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Change Password</button></div>
        </div>
    </form>
</div></div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
