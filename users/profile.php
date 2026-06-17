<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$api    = new ApiClient();
$user   = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { set_flash('error','Invalid request.'); redirect(BASE_URL.'/users/profile.php'); }
    $name  = post('name');
    $phone = post('phone');

    if (!$name) $errors[] = 'Name required.';

    if (!$errors) {
        $res = $api->patch('auth/profile', array_filter([
            'name'  => $name,
            'phone' => $phone ?: null,
        ], fn($v) => $v !== null));

        if (!empty($res['success'])) {
            $_SESSION['user_name'] = $name;
            set_flash('success','Profile updated.');
            redirect(BASE_URL.'/users/profile.php');
        }
        $errors[] = $res['message'] ?? 'Failed to update profile.';
    }
}

$page_title = 'My Profile';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3"><h5 class="fw-bold mb-0"><i class="bi bi-person-circle me-2 text-primary"></i>My Profile</h5></div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="card shadow-sm" style="max-width:480px"><div class="card-body">
    <form method="POST">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-12"><label class="form-label fw-semibold">Full Name *</label><input type="text" name="name" class="form-control" value="<?= e(post('name') ?: $user['name']) ?>" required></div>
            <div class="col-12"><label class="form-label fw-semibold">Email (read-only)</label><input type="email" class="form-control bg-light" value="<?= e($user['email']) ?>" readonly></div>
            <div class="col-12"><label class="form-label fw-semibold">Phone</label><input type="tel" name="phone" class="form-control" value="<?= e(post('phone') ?: ($user['phone'] ?? '')) ?>"></div>
            <div class="col-12"><button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save</button></div>
        </div>
    </form>
</div></div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
