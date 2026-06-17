<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$api    = new ApiClient();
$user   = current_user();
$errors = [];
$tab    = get_param('tab', 'mfa');

// ── Load MFA status ───────────────────────────────────────────
$mfaStatus = $api->get('auth/mfa/status')['data'] ?? ['enabled' => false, 'backup_codes_left' => 0];
$mfaEnabled = (bool)($mfaStatus['enabled'] ?? false);

// ── Load active sessions (tokens) ────────────────────────────
$tokensRes = $api->get('auth/me');
$activeToken = $tokensRes['data']['token'] ?? [];

// ── Handle POST actions ───────────────────────────────────────
$flash = '';
$setupData = null; // holds secret/qr_uri/backup_codes after setup

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $action = $_POST['_action'] ?? '';

        // ── Initiate MFA setup ────────────────────────────────
        if ($action === 'mfa_setup') {
            $res = $api->post('auth/mfa/setup');
            if ($res['success'] ?? false) {
                $setupData = $res['data'];
                $tab       = 'mfa';
            } else {
                $errors[] = $res['message'] ?? 'Setup failed.';
            }

        // ── Confirm MFA (verify first code) ──────────────────
        } elseif ($action === 'mfa_confirm') {
            $code = trim($_POST['code'] ?? '');
            $res  = $api->post('auth/mfa/confirm', ['code' => $code]);
            if ($res['success'] ?? false) {
                set_flash('success', 'Two-factor authentication enabled.');
                redirect(BASE_URL . '/profile/security.php?tab=mfa');
            } else {
                $errors[] = $res['message'] ?? 'Invalid code.';
                // Re-show setup data from session
                $setupData = [
                    'secret'       => $_POST['_secret']    ?? '',
                    'qr_uri'       => $_POST['_qr_uri']    ?? '',
                    'backup_codes' => json_decode($_POST['_backup_codes'] ?? '[]', true),
                ];
                $tab = 'mfa';
            }

        // ── Disable MFA ───────────────────────────────────────
        } elseif ($action === 'mfa_disable') {
            $pw  = $_POST['password'] ?? '';
            $res = $api->post('auth/mfa/disable', ['password' => $pw]);
            if ($res['success'] ?? false) {
                set_flash('success', 'Two-factor authentication disabled.');
                redirect(BASE_URL . '/profile/security.php?tab=mfa');
            } else {
                $errors[] = $res['message'] ?? 'Failed to disable MFA.';
            }

        // ── Regenerate backup codes ───────────────────────────
        } elseif ($action === 'regen_backup') {
            $code = trim($_POST['code'] ?? '');
            $res  = $api->post('auth/mfa/backup-codes/regenerate', ['code' => $code]);
            if ($res['success'] ?? false) {
                $setupData = ['backup_codes' => $res['data']['backup_codes'] ?? [], 'regen_only' => true];
                $flash     = 'Backup codes regenerated. Save the new codes now.';
                $tab       = 'mfa';
            } else {
                $errors[] = $res['message'] ?? 'Regeneration failed.';
            }

        // ── Change password ───────────────────────────────────
        } elseif ($action === 'change_password') {
            $current = $_POST['current_password'] ?? '';
            $new     = $_POST['new_password']     ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if ($new !== $confirm) {
                $errors[] = 'New passwords do not match.';
            } elseif (strlen($new) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            } else {
                $res = $api->post('auth/change-password', [
                    'current_password' => $current,
                    'new_password'     => $new,
                ]);
                if ($res['success'] ?? false) {
                    set_flash('success', 'Password changed successfully.');
                    redirect(BASE_URL . '/profile/security.php?tab=password');
                } else {
                    $errors[] = $res['message'] ?? 'Password change failed.';
                }
            }
            $tab = 'password';
        }
    }
}

// Refresh MFA status after actions
$mfaStatus  = $api->get('auth/mfa/status')['data'] ?? ['enabled' => false, 'backup_codes_left' => 0];
$mfaEnabled = (bool)($mfaStatus['enabled'] ?? false);

$page_title = 'Security Settings';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3 gap-2">
    <a href="<?= BASE_URL ?>/dashboard/index.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h5 class="fw-bold mb-0"><i class="bi bi-shield-lock me-2 text-primary"></i>Security Settings</h5>
</div>

<?= flash_html() ?>
<?php if ($flash): ?>
<div class="alert alert-success"><?= e($flash) ?></div>
<?php endif; ?>
<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'mfa' ? 'active' : '' ?>" href="?tab=mfa">
            <i class="bi bi-phone me-1"></i>Authenticator (MFA)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'password' ? 'active' : '' ?>" href="?tab=password">
            <i class="bi bi-key me-1"></i>Change Password
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'sessions' ? 'active' : '' ?>" href="?tab=sessions">
            <i class="bi bi-device-hdd me-1"></i>Active Session
        </a>
    </li>
</ul>

<!-- ── MFA Tab ──────────────────────────────────────────────── -->
<?php if ($tab === 'mfa'): ?>
<div class="row g-3">
    <div class="col-lg-7">

        <!-- Status card -->
        <div class="card shadow-sm mb-3">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="fs-2 <?= $mfaEnabled ? 'text-success' : 'text-secondary' ?>">
                    <i class="bi bi-<?= $mfaEnabled ? 'shield-fill-check' : 'shield' ?>"></i>
                </div>
                <div>
                    <div class="fw-semibold">
                        Two-Factor Authentication is
                        <span class="badge bg-<?= $mfaEnabled ? 'success' : 'secondary' ?> ms-1">
                            <?= $mfaEnabled ? 'Enabled' : 'Disabled' ?>
                        </span>
                    </div>
                    <?php if ($mfaEnabled): ?>
                    <small class="text-muted">
                        Enabled <?= $mfaStatus['enabled_at'] ? fmt_date($mfaStatus['enabled_at'], 'd M Y') : '' ?> &mdash;
                        <?= (int)($mfaStatus['backup_codes_left'] ?? 0) ?> backup codes remaining
                    </small>
                    <?php else: ?>
                    <small class="text-muted">Protect your account with an authenticator app.</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($setupData && !($setupData['regen_only'] ?? false)): ?>
        <!-- ── Setup wizard ── -->
        <div class="card shadow-sm border-primary mb-3">
            <div class="card-header fw-semibold py-2 bg-primary text-white">
                <i class="bi bi-qr-code me-1"></i>Step 1 — Scan QR Code
            </div>
            <div class="card-body">
                <p class="text-muted small">Scan this QR code with Google Authenticator, Authy, or any TOTP app. If you can't scan, enter the secret manually.</p>

                <!-- QR code rendered client-side -->
                <div class="text-center mb-3">
                    <div id="qrContainer" class="d-inline-block p-2 border rounded bg-white"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-muted">Manual Entry Secret</label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control font-monospace" id="mfaSecret"
                               value="<?= e($setupData['secret'] ?? '') ?>" readonly>
                        <button class="btn btn-outline-secondary" onclick="copySecret()" type="button">
                            <i class="bi bi-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Backup codes -->
        <?php if (!empty($setupData['backup_codes'])): ?>
        <div class="card shadow-sm border-warning mb-3">
            <div class="card-header fw-semibold py-2 bg-warning">
                <i class="bi bi-key me-1"></i>Step 2 — Save Backup Codes
            </div>
            <div class="card-body">
                <p class="text-muted small">Store these 8 one-time codes somewhere safe. Each can be used once if you lose your phone.</p>
                <div class="row g-2 mb-3">
                    <?php foreach ($setupData['backup_codes'] as $bc): ?>
                    <div class="col-6 col-md-3">
                        <code class="d-block text-center border rounded p-1 bg-light" style="font-size:.85rem;letter-spacing:.15rem">
                            <?= e($bc) ?>
                        </code>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn-sm btn-outline-secondary" onclick="copyBackupCodes()" type="button">
                    <i class="bi bi-clipboard me-1"></i>Copy All
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Confirm form -->
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold py-2">
                <i class="bi bi-shield-check me-1"></i>Step 3 — Confirm with Code
            </div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="mfa_confirm">
                    <input type="hidden" name="_secret" value="<?= e($setupData['secret'] ?? '') ?>">
                    <input type="hidden" name="_qr_uri" value="<?= e($setupData['qr_uri'] ?? '') ?>">
                    <input type="hidden" name="_backup_codes" value="<?= e(json_encode($setupData['backup_codes'] ?? [])) ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Enter the 6-digit code from your app</label>
                        <input type="text" name="code" class="form-control"
                               inputmode="numeric" pattern="[0-9]*" maxlength="6"
                               placeholder="000000" autocomplete="one-time-code" autofocus required>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i>Enable MFA
                    </button>
                </form>
            </div>
        </div>

        <?php elseif ($setupData && ($setupData['regen_only'] ?? false)): ?>
        <!-- Regenerated backup codes display -->
        <div class="card shadow-sm border-warning mb-3">
            <div class="card-header fw-semibold py-2 bg-warning">
                <i class="bi bi-key me-1"></i>New Backup Codes — Save Now
            </div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <?php foreach ($setupData['backup_codes'] as $bc): ?>
                    <div class="col-6 col-md-3">
                        <code class="d-block text-center border rounded p-1 bg-light" style="font-size:.85rem;letter-spacing:.15rem">
                            <?= e($bc) ?>
                        </code>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php elseif (!$mfaEnabled): ?>
        <!-- Enable button -->
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="mfa_setup">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-shield-plus me-1"></i>Set Up Two-Factor Authentication
            </button>
        </form>

        <?php else: ?>
        <!-- Enabled — show disable + regen options -->
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold py-2 small">Regenerate Backup Codes</div>
                    <div class="card-body">
                        <p class="text-muted small">Enter your current TOTP code to regenerate backup codes. Old codes will be invalidated.</p>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_action" value="regen_backup">
                            <div class="input-group input-group-sm mb-2">
                                <input type="text" name="code" class="form-control" placeholder="TOTP code or password"
                                       inputmode="numeric" maxlength="10" required>
                                <button type="submit" class="btn btn-outline-primary">Regenerate</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm h-100 border-danger">
                    <div class="card-header fw-semibold py-2 small text-danger">Disable MFA</div>
                    <div class="card-body">
                        <p class="text-muted small">Enter your account password to turn off two-factor authentication.</p>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_action" value="mfa_disable">
                            <div class="input-group input-group-sm mb-2">
                                <input type="password" name="password" class="form-control" placeholder="Your password" required>
                                <button type="submit" class="btn btn-outline-danger">Disable</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right info column -->
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold py-2 small"><i class="bi bi-info-circle me-1"></i>About MFA</div>
            <div class="card-body small text-muted">
                <ul class="mb-2">
                    <li>Use <strong>Google Authenticator</strong>, <strong>Authy</strong>, or <strong>Microsoft Authenticator</strong></li>
                    <li>Codes refresh every <strong>30 seconds</strong></li>
                    <li>Works even when offline</li>
                    <li>8 single-use backup codes are provided if you lose your phone</li>
                </ul>
                <hr class="my-2">
                <strong>Compatible apps:</strong>
                <div class="mt-1">
                    Google Authenticator, Authy, Microsoft Authenticator, Bitwarden, 1Password, LastPass
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Change Password Tab ───────────────────────────────────── -->
<?php elseif ($tab === 'password'): ?>
<div class="row">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold py-2">Change Password</div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="change_password">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Password</label>
                        <input type="password" name="new_password" class="form-control" minlength="8" required
                               id="newPw" oninput="checkStrength(this.value)">
                        <div class="progress mt-1" style="height:4px">
                            <div id="pwStrengthBar" class="progress-bar" style="width:0%"></div>
                        </div>
                        <div id="pwStrengthLabel" class="form-text"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required
                               oninput="checkMatch()">
                        <div id="matchMsg" class="form-text"></div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-key me-1"></i>Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header py-2 small fw-semibold"><i class="bi bi-info-circle me-1"></i>Password Policy</div>
            <div class="card-body small text-muted">
                <ul class="mb-0">
                    <li>Minimum <strong>8 characters</strong></li>
                    <li>Use a mix of letters, numbers, and symbols</li>
                    <li>Avoid dictionary words or personal info</li>
                    <li>Use a password manager for unique passwords</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- ── Sessions Tab ──────────────────────────────────────────── -->
<?php elseif ($tab === 'sessions'): ?>
<div class="card shadow-sm">
    <div class="card-header fw-semibold py-2">Current Session</div>
    <div class="card-body">
        <?php if ($activeToken): ?>
        <dl class="row mb-0" style="font-size:.875rem">
            <dt class="col-sm-3 text-muted">Token Name</dt>
            <dd class="col-sm-9"><?= e($activeToken['name'] ?? '—') ?></dd>

            <dt class="col-sm-3 text-muted">Scopes</dt>
            <dd class="col-sm-9" style="font-size:.75rem;word-break:break-all">
                <?= e($activeToken['scopes'] ?? '—') ?>
            </dd>

            <dt class="col-sm-3 text-muted">Last Used</dt>
            <dd class="col-sm-9"><?= fmt_date($activeToken['last_used'] ?? '', 'd M Y, H:i') ?></dd>

            <dt class="col-sm-3 text-muted">Expires</dt>
            <dd class="col-sm-9">
                <?= $activeToken['expires_at'] ? fmt_date($activeToken['expires_at'], 'd M Y') : 'Never' ?>
            </dd>
        </dl>
        <?php else: ?>
        <p class="text-muted mb-0">No session information available.</p>
        <?php endif; ?>
    </div>
    <div class="card-footer bg-transparent">
        <form method="post" action="<?= BASE_URL ?>/auth/logout.php">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-box-arrow-right me-1"></i>Log Out All Sessions
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
<?php if ($setupData && !($setupData['regen_only'] ?? false) && !empty($setupData['qr_uri'])): ?>
// ── QR Code rendering (qrcode-generator via CDN) ──────────────
(function() {
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js';
    script.onload = function() {
        const qr = qrcode(0, 'M');
        qr.addData(<?= json_encode($setupData['qr_uri']) ?>);
        qr.make();
        document.getElementById('qrContainer').innerHTML = qr.createImgTag(4, 8);
    };
    document.head.appendChild(script);
})();

function copySecret() {
    navigator.clipboard.writeText(document.getElementById('mfaSecret').value)
        .then(() => alert('Secret copied to clipboard.'));
}

function copyBackupCodes() {
    const codes = <?= json_encode($setupData['backup_codes'] ?? []) ?>;
    navigator.clipboard.writeText(codes.join('\n'))
        .then(() => alert('Backup codes copied to clipboard.'));
}
<?php endif; ?>

// ── Password strength meter ───────────────────────────────────
function checkStrength(pw) {
    let score = 0;
    if (pw.length >= 8)  score++;
    if (pw.length >= 12) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    const bar   = document.getElementById('pwStrengthBar');
    const label = document.getElementById('pwStrengthLabel');
    const levels = [
        ['0%',  'bg-secondary', ''],
        ['25%', 'bg-danger',    'Weak'],
        ['50%', 'bg-warning',   'Fair'],
        ['75%', 'bg-info',      'Good'],
        ['90%', 'bg-primary',   'Strong'],
        ['100%','bg-success',   'Very Strong'],
    ];
    const [w, cls, txt] = levels[Math.min(score, 5)];
    bar.style.width = w;
    bar.className   = 'progress-bar ' + cls;
    label.textContent = txt;
}

function checkMatch() {
    const pw1 = document.getElementById('newPw')?.value;
    const pw2 = event.target.value;
    const msg = document.getElementById('matchMsg');
    if (!pw2) { msg.textContent = ''; return; }
    if (pw1 === pw2) {
        msg.textContent = '✓ Passwords match';
        msg.className   = 'form-text text-success';
    } else {
        msg.textContent = '✗ Passwords do not match';
        msg.className   = 'form-text text-danger';
    }
}
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
