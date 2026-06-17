<?php
require_once __DIR__ . '/../config/config.php';

// Already logged in
if (is_logged_in()) {
    redirect(BASE_URL . '/dashboard/index.php');
}

// No pending MFA session
if (empty($_SESSION['mfa_pending_token'])) {
    set_flash('error', 'No MFA session found. Please log in.');
    redirect(BASE_URL . '/index.php');
}

$error        = '';
$pendingToken = $_SESSION['mfa_pending_token'];
$email        = $_SESSION['mfa_pending_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $code   = trim(preg_replace('/\s+/', '', $_POST['code'] ?? ''));
        $result = mfa_login($pendingToken, $code);
        if ($result === true) {
            redirect(BASE_URL . '/dashboard/index.php');
        } else {
            $error = is_string($result) ? $result : 'Invalid code. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication — <?= APP_FULL_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .code-input {
            font-size: 2rem;
            letter-spacing: .5rem;
            text-align: center;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }
        .timer-ring { transition: stroke-dashoffset 1s linear; }
    </style>
</head>
<body class="login-body">
<div class="login-wrapper">
    <div class="login-card shadow-lg">
        <!-- Icon -->
        <div class="text-center mb-4">
            <div class="mb-3">
                <i class="bi bi-shield-lock-fill text-warning" style="font-size:3rem"></i>
            </div>
            <h4 class="fw-bold mb-1">Two-Factor Authentication</h4>
            <p class="text-muted small mb-0">
                Enter the 6-digit code from your authenticator app
                <?php if ($email): ?>
                for <strong><?= e($email) ?></strong>
                <?php endif; ?>
            </p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2">
            <i class="bi bi-exclamation-triangle me-1"></i><?= e($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="mfaForm">
            <?= csrf_field() ?>

            <div class="mb-4">
                <input type="text" name="code" id="codeInput"
                       class="form-control code-input"
                       inputmode="numeric" pattern="[0-9 ]*"
                       maxlength="7" placeholder="000000"
                       autocomplete="one-time-code"
                       autofocus required>
            </div>

            <!-- Countdown timer -->
            <div class="d-flex align-items-center justify-content-center mb-3 gap-2">
                <svg width="28" height="28" viewBox="0 0 28 28">
                    <circle cx="14" cy="14" r="12" fill="none" stroke="#dee2e6" stroke-width="3"/>
                    <circle id="timerArc" cx="14" cy="14" r="12" fill="none"
                            stroke="#0d6efd" stroke-width="3"
                            stroke-dasharray="75.4" stroke-dashoffset="0"
                            stroke-linecap="round"
                            transform="rotate(-90 14 14)"
                            class="timer-ring"/>
                </svg>
                <span class="text-muted small">Code refreshes in <strong id="timerSec">30</strong>s</span>
            </div>

            <button type="submit" class="btn btn-warning w-100 fw-bold py-2" id="submitBtn">
                <i class="bi bi-shield-check me-2"></i>Verify
            </button>
        </form>

        <hr class="my-3">

        <details class="text-center">
            <summary class="text-muted small" style="cursor:pointer">
                <i class="bi bi-key me-1"></i>Use a backup code instead
            </summary>
            <div class="mt-2">
                <p class="text-muted" style="font-size:.8rem">Enter one of your 8-character backup codes.</p>
            </div>
        </details>

        <div class="text-center mt-3">
            <a href="<?= BASE_URL ?>/index.php" class="text-muted small">
                <i class="bi bi-arrow-left me-1"></i>Back to Login
            </a>
        </div>

        <div class="text-center mt-4 text-muted small">
            &copy; <?= date('Y') ?> <?= APP_NAME ?> &mdash; v<?= APP_VERSION ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── TOTP countdown timer ──────────────────────────────────────
(function () {
    const arc = document.getElementById('timerArc');
    const sec = document.getElementById('timerSec');
    const PERIOD = 30;
    const CIRCUMFERENCE = 75.4;

    function tick() {
        const now    = Math.floor(Date.now() / 1000);
        const remain = PERIOD - (now % PERIOD);
        const offset = CIRCUMFERENCE * (1 - remain / PERIOD);
        arc.style.strokeDashoffset = offset;
        sec.textContent = remain;

        // Warn when < 5 seconds left
        arc.style.stroke = remain <= 5 ? '#dc3545' : '#0d6efd';
    }

    tick();
    setInterval(tick, 1000);
})();

// ── Auto-format and submit ─────────────────────────────────────
const input = document.getElementById('codeInput');
input.addEventListener('input', function () {
    // Strip non-digits
    this.value = this.value.replace(/[^0-9]/g, '');
    // Auto-submit when 6 digits entered
    if (this.value.length === 6) {
        document.getElementById('submitBtn').click();
    }
});

// Disable submit to prevent double-submit
document.getElementById('mfaForm').addEventListener('submit', function () {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verifying…';
});
</script>
</body>
</html>
