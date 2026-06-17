<?php
require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    redirect(BASE_URL . '/dashboard/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email    = post('email');
        $password = $_POST['password'] ?? '';
        if (empty($email) || empty($password)) {
            $error = 'Email and password are required.';
        } elseif (login_user($email, $password)) {
            redirect(BASE_URL . '/dashboard/index.php');
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

$err_param = get_param('err');
if ($err_param === 'suspended') $error = 'Your account has been suspended. Contact admin.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= APP_FULL_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="login-body">
<div class="login-wrapper">
    <div class="login-card shadow-lg">
        <!-- Logo -->
        <div class="login-logo text-center mb-4">
            <div class="login-icon mb-3">
                <i class="bi bi-building-fill text-warning" style="font-size:3rem"></i>
            </div>
            <h3 class="fw-bold text-dark mb-0"><?= APP_NAME ?></h3>
            <p class="text-muted small"><?= APP_FULL_NAME ?></p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-sm py-2">
            <i class="bi bi-exclamation-triangle me-1"></i><?= e($error) ?>
        </div>
        <?php endif; ?>

        <?= flash_html() ?>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label fw-semibold">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control"
                           value="<?= e(post('email')) ?>"
                           placeholder="admin@rums.co.ke" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" id="loginPassword" class="form-control" placeholder="••••••••" required>
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePwd()">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-warning w-100 fw-bold py-2">
                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
            </button>
        </form>

        <div class="text-center mt-4 text-muted small">
            &copy; <?= date('Y') ?> <?= APP_NAME ?> &mdash; v<?= APP_VERSION ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePwd() {
    const p = document.getElementById('loginPassword');
    const e = document.getElementById('eyeIcon');
    p.type = p.type === 'password' ? 'text' : 'password';
    e.className = p.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
</script>
</body>
</html>
