<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$api    = new ApiClient();
$user   = current_user();
$errors = [];
$tab    = get_param('tab', 'security');

// ── Load MFA status ───────────────────────────────────────────
$mfaStatus      = $api->get('auth/mfa/status')['data'] ?? [];
$mfaEnabled     = (bool)($mfaStatus['enabled'] ?? false);
$backupCodesLeft = (int)($mfaStatus['backup_codes_left'] ?? 0);

// ── Setup wizard state (persisted across the confirm step) ────
$setupData = null; // ['secret', 'qr_uri', 'backup_codes']

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $action = $_POST['_action'] ?? '';

        // ── Start MFA setup ───────────────────────────────────
        if ($action === 'mfa_enable') {
            $res = $api->post('auth/mfa/setup');
            if ($res['success'] ?? false) {
                $setupData = $res['data'];
            } else {
                $errors[] = $res['message'] ?? 'Could not start MFA setup.';
            }

        // ── Confirm first TOTP code ───────────────────────────
        } elseif ($action === 'mfa_confirm') {
            $code = trim($_POST['code'] ?? '');
            $res  = $api->post('auth/mfa/confirm', ['code' => $code]);
            if ($res['success'] ?? false) {
                set_flash('success', 'Two-factor authentication is now enabled.');
                redirect(BASE_URL . '/settings/account?tab=security');
            } else {
                $errors[] = $res['message'] ?? 'Invalid code — please try again.';
                // Re-populate setup data so user can retry without rescanning
                $setupData = [
                    'secret'       => $_POST['_secret']        ?? '',
                    'qr_uri'       => $_POST['_qr_uri']        ?? '',
                    'backup_codes' => json_decode($_POST['_backup_codes'] ?? '[]', true) ?: [],
                ];
            }

        // ── Disable MFA ───────────────────────────────────────
        } elseif ($action === 'mfa_disable') {
            $pw  = $_POST['password'] ?? '';
            $res = $api->post('auth/mfa/disable', ['password' => $pw]);
            if ($res['success'] ?? false) {
                set_flash('success', 'Two-factor authentication disabled.');
                redirect(BASE_URL . '/settings/account?tab=security');
            } else {
                $errors[] = $res['message'] ?? 'Incorrect password.';
            }

        // ── Regenerate backup codes ───────────────────────────
        } elseif ($action === 'regen_backup') {
            $code = trim($_POST['regen_code'] ?? '');
            $res  = $api->post('auth/mfa/backup-codes/regenerate', ['code' => $code]);
            if ($res['success'] ?? false) {
                $setupData = ['backup_codes' => $res['data']['backup_codes'] ?? [], '_regen' => true];
                set_flash('success', 'Backup codes regenerated — save them now.');
            } else {
                $errors[] = $res['message'] ?? 'Regeneration failed.';
            }

        // ── Change password ───────────────────────────────────
        } elseif ($action === 'change_password') {
            $tab     = 'password';
            $current = $_POST['current_password'] ?? '';
            $new     = $_POST['new_password']     ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            if ($new !== $confirm) {
                $errors[] = 'New passwords do not match.';
            } elseif (strlen($new) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            } else {
                $res = $api->post('auth/change-password', [
                    'current_password' => $current,
                    'new_password'     => $new,
                ]);
                if ($res['success'] ?? false) {
                    set_flash('success', 'Password changed successfully.');
                    redirect(BASE_URL . '/settings/account?tab=password');
                } else {
                    $errors[] = $res['message'] ?? 'Password change failed.';
                }
            }

        // ── Update profile ────────────────────────────────────
        } elseif ($action === 'update_profile') {
            $tab  = 'profile';
            $data = [];
            if (!empty($_POST['name']))  $data['name']  = trim($_POST['name']);
            if (isset($_POST['phone'])) $data['phone'] = trim($_POST['phone']) ?: null;
            if ($data) {
                $res = $api->patch('auth/profile', $data);
                if ($res['success'] ?? false) {
                    // Refresh session name
                    if (!empty($data['name'])) {
                        $_SESSION['user_name'] = $data['name'];
                        if (isset($_SESSION['user_data'])) $_SESSION['user_data']['name'] = $data['name'];
                    }
                    set_flash('success', 'Profile updated.');
                    redirect(BASE_URL . '/settings/account?tab=profile');
                } else {
                    $errors[] = $res['message'] ?? 'Update failed.';
                }
            }
        }
    }
}

// Refresh MFA status after any action
$mfaStatus      = $api->get('auth/mfa/status')['data'] ?? [];
$mfaEnabled     = (bool)($mfaStatus['enabled'] ?? false);
$backupCodesLeft = (int)($mfaStatus['backup_codes_left'] ?? 0);

$page_title = 'Account Settings';
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex align-items-center mb-3 gap-2">
    <h5 class="fw-bold mb-0"><i class="bi bi-person-gear me-2 text-primary"></i>Account Settings</h5>
</div>

<?= flash_html() ?>
<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?>
    <li><?= e($err) ?></li>
<?php endforeach; ?></ul></div>
<?php endif; ?>

<!-- Tab nav -->
<ul class="nav nav-tabs mb-0" id="accountTabs">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'security' ? 'active' : '' ?>"
           href="?tab=security">
            <i class="bi bi-shield-lock me-1"></i>Security
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'password' ? 'active' : '' ?>"
           href="?tab=password">
            <i class="bi bi-key me-1"></i>Password
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'profile' ? 'active' : '' ?>"
           href="?tab=profile">
            <i class="bi bi-person me-1"></i>Profile
        </a>
    </li>
</ul>

<div class="card shadow-sm" style="border-radius:0 0 .5rem .5rem">
<div class="card-body p-3 p-md-4">

<!-- ══════════════════════════════════════════════
     SECURITY TAB — MFA toggle
══════════════════════════════════════════════ -->
<?php if ($tab === 'security'): ?>

<div class="row g-4">
    <div class="col-lg-7">

        <!-- MFA toggle row -->
        <div class="d-flex align-items-start justify-content-between gap-3 mb-4 pb-3 border-bottom">
            <div>
                <div class="fw-semibold d-flex align-items-center gap-2">
                    <i class="bi bi-phone fs-5 text-<?= $mfaEnabled ? 'success' : 'secondary' ?>"></i>
                    Two-Factor Authentication (2FA)
                    <span class="badge bg-<?= $mfaEnabled ? 'success' : 'secondary' ?>">
                        <?= $mfaEnabled ? 'Enabled' : 'Disabled' ?>
                    </span>
                    <span class="badge bg-light text-muted border" style="font-weight:400">Optional</span>
                </div>
                <p class="text-muted small mb-0 mt-1">
                    <?php if ($mfaEnabled): ?>
                    Enabled <?= $mfaStatus['enabled_at'] ? fmt_date($mfaStatus['enabled_at'], 'd M Y') : '' ?>.
                    <?= $backupCodesLeft ?> backup <?= $backupCodesLeft === 1 ? 'code' : 'codes' ?> remaining.
                    <?php else: ?>
                    Off by default. Enable for an extra layer of login security using an authenticator app.
                    <?php endif; ?>
                </p>
            </div>
            <!-- Toggle button -->
            <?php if ($mfaEnabled): ?>
            <button class="btn btn-sm btn-outline-danger flex-shrink-0"
                    data-bs-toggle="modal" data-bs-target="#disableMfaModal">
                <i class="bi bi-toggle-on me-1"></i>Disable
            </button>
            <?php elseif (!$setupData): ?>
            <form method="post" class="flex-shrink-0">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="mfa_enable">
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-toggle-off me-1"></i>Enable
                </button>
            </form>
            <?php endif; ?>
        </div>

        <?php if ($setupData && !($setupData['_regen'] ?? false)): ?>
        <!-- ── Setup wizard ─────────────────────────────────── -->
        <div class="alert alert-info d-flex gap-2 small mb-3">
            <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
            <div>Scan the QR code with your authenticator app (Google Authenticator, Authy, etc.),
            then enter the 6-digit code to confirm.</div>
        </div>

        <!-- QR code -->
        <div class="text-center mb-3">
            <div id="qrBox" class="d-inline-block p-2 border rounded bg-white shadow-sm"></div>
        </div>

        <!-- Manual secret -->
        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted">Can't scan? Enter manually</label>
            <div class="input-group input-group-sm">
                <input type="text" class="form-control font-monospace" id="mfaSecretInput"
                       value="<?= e($setupData['secret'] ?? '') ?>" readonly>
                <button class="btn btn-outline-secondary" type="button" onclick="copyField('mfaSecretInput')">
                    <i class="bi bi-copy"></i>
                </button>
            </div>
        </div>

        <!-- Backup codes -->
        <?php if (!empty($setupData['backup_codes'])): ?>
        <div class="card border-warning mb-3">
            <div class="card-header py-2 small fw-semibold bg-warning bg-opacity-25">
                <i class="bi bi-key me-1"></i>Save your backup codes
            </div>
            <div class="card-body py-2">
                <p class="text-muted small mb-2">Use these if you lose access to your phone. Each code works once.</p>
                <div class="row g-1 mb-2">
                    <?php foreach ($setupData['backup_codes'] as $bc): ?>
                    <div class="col-6 col-sm-3">
                        <code class="d-block text-center border rounded px-1 py-1 bg-light small"
                              style="letter-spacing:.1rem"><?= e($bc) ?></code>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-xs btn-sm btn-outline-secondary py-0"
                        onclick="copyBackupCodes(<?= e(json_encode($setupData['backup_codes'])) ?>)">
                    <i class="bi bi-clipboard me-1"></i>Copy all
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Confirm form -->
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="_action"        value="mfa_confirm">
            <input type="hidden" name="_secret"        value="<?= e($setupData['secret'] ?? '') ?>">
            <input type="hidden" name="_qr_uri"        value="<?= e($setupData['qr_uri'] ?? '') ?>">
            <input type="hidden" name="_backup_codes"  value="<?= e(json_encode($setupData['backup_codes'] ?? [])) ?>">
            <div class="mb-3">
                <label class="form-label fw-semibold">Enter the 6-digit code from your app</label>
                <div class="input-group" style="max-width:220px">
                    <input type="text" name="code" class="form-control text-center fw-bold"
                           style="letter-spacing:.4rem;font-size:1.1rem"
                           inputmode="numeric" pattern="[0-9]*" maxlength="6"
                           placeholder="000000" autocomplete="one-time-code" autofocus required>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg me-1"></i>Confirm &amp; Enable
                </button>
                <a href="?tab=security" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>

        <?php elseif ($mfaEnabled): ?>
        <!-- ── Backup codes management (MFA is on) ─────────── -->
        <div class="card border-0 bg-light mb-3">
            <div class="card-body py-3">
                <div class="fw-semibold small mb-1">Backup Codes</div>
                <div class="text-muted small mb-2">
                    <?= $backupCodesLeft ?> of 8 codes remaining.
                    <?php if ($backupCodesLeft <= 2): ?>
                    <span class="text-danger fw-semibold">Running low — regenerate soon.</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($setupData['_regen']) && !empty($setupData['backup_codes'])): ?>
                <!-- Show freshly regenerated codes -->
                <div class="row g-1 mb-2">
                    <?php foreach ($setupData['backup_codes'] as $bc): ?>
                    <div class="col-6 col-sm-3">
                        <code class="d-block text-center border rounded px-1 py-1 bg-white small"
                              style="letter-spacing:.1rem"><?= e($bc) ?></code>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <form method="post" class="d-flex align-items-center gap-2 mt-1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="regen_backup">
                    <input type="text" name="regen_code" class="form-control form-control-sm"
                           placeholder="TOTP code or password" style="max-width:200px" required
                           inputmode="numeric" maxlength="10">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Regenerate</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <div class="col-lg-5">
        <div class="card border-0 bg-light">
            <div class="card-body small text-muted">
                <div class="fw-semibold text-dark mb-2"><i class="bi bi-info-circle me-1"></i>About 2FA</div>
                <ul class="mb-3">
                    <li>Requires a code from your phone at each login</li>
                    <li>Works offline — no SMS needed</li>
                    <li>Compatible with Google Authenticator, Authy, Microsoft Authenticator, Bitwarden</li>
                    <li>8 single-use backup codes provided in case you lose your phone</li>
                </ul>
                <div class="fw-semibold text-dark mb-1">How to set up</div>
                <ol class="mb-0">
                    <li>Click <strong>Turn On</strong></li>
                    <li>Scan the QR code in your app</li>
                    <li>Enter the 6-digit code to confirm</li>
                    <li>Save your backup codes somewhere safe</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Disable MFA modal -->
<?php if ($mfaEnabled): ?>
<div class="modal fade" id="disableMfaModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="bi bi-shield-x me-1 text-danger"></i>Turn Off 2FA</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Enter your account password to disable two-factor authentication.</p>
                <form method="post" id="disableMfaForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="mfa_disable">
                    <div class="mb-3">
                        <input type="password" name="password" class="form-control"
                               placeholder="Your password" required autofocus>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger btn-sm">Disable 2FA</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════
     PASSWORD TAB
══════════════════════════════════════════════ -->
<?php elseif ($tab === 'password'): ?>
<div class="row">
    <div class="col-lg-5">
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="change_password">
            <div class="mb-3">
                <label class="form-label fw-semibold">Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">New Password</label>
                <input type="password" name="new_password" id="newPwInput" class="form-control"
                       minlength="8" required oninput="checkStrength(this.value)">
                <div class="progress mt-1" style="height:4px">
                    <div id="pwBar" class="progress-bar" style="width:0%"></div>
                </div>
                <div id="pwLabel" class="form-text"></div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required
                       oninput="checkMatch(event)">
                <div id="matchMsg" class="form-text"></div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-key me-1"></i>Change Password
            </button>
        </form>
    </div>
    <div class="col-lg-4 offset-lg-1 mt-4 mt-lg-0">
        <div class="card border-0 bg-light">
            <div class="card-body small text-muted">
                <div class="fw-semibold text-dark mb-2">Password requirements</div>
                <ul class="mb-0">
                    <li>Minimum 8 characters</li>
                    <li>Mix of uppercase, numbers, symbols</li>
                    <li>Avoid passwords you use elsewhere</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     PROFILE TAB
══════════════════════════════════════════════ -->
<?php elseif ($tab === 'profile'): ?>
<div class="row">
    <div class="col-lg-5">
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="update_profile">
            <div class="mb-3">
                <label class="form-label fw-semibold">Full Name</label>
                <input type="text" name="name" class="form-control"
                       value="<?= e($user['name'] ?? '') ?>" maxlength="100" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Phone</label>
                <input type="text" name="phone" class="form-control"
                       value="<?= e($user['phone'] ?? '') ?>" maxlength="20" placeholder="+254 700 000 000">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Email</label>
                <input type="email" class="form-control bg-light"
                       value="<?= e($user['email'] ?? '') ?>" disabled>
                <div class="form-text">Contact an admin to change your email address.</div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Role</label>
                <input type="text" class="form-control bg-light"
                       value="<?= ucfirst($user['role'] ?? '') ?>" disabled>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i>Save Profile
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

</div><!-- .card-body -->
</div><!-- .card -->

<script>
<?php if ($setupData && !($setupData['_regen'] ?? false) && !empty($setupData['qr_uri'])): ?>
// ── Render QR code ────────────────────────────────────────────
(function () {
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js';
    s.onload = function () {
        const qr = qrcode(0, 'M');
        qr.addData(<?= json_encode($setupData['qr_uri']) ?>);
        qr.make();
        const box = document.getElementById('qrBox');
        if (box) box.innerHTML = qr.createImgTag(4, 8);
    };
    document.head.appendChild(s);
})();
<?php endif; ?>

function copyField(id) {
    const el = document.getElementById(id);
    if (!el) return;
    navigator.clipboard.writeText(el.value).then(function () {
        const btn = el.nextElementSibling;
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check text-success"></i>';
        setTimeout(function () { btn.innerHTML = orig; }, 1500);
    });
}

function copyBackupCodes(codes) {
    navigator.clipboard.writeText(codes.join('\n')).then(function () {
        alert('Backup codes copied.');
    });
}

function checkStrength(pw) {
    let s = 0;
    if (pw.length >= 8)            s++;
    if (pw.length >= 12)           s++;
    if (/[A-Z]/.test(pw))          s++;
    if (/[0-9]/.test(pw))          s++;
    if (/[^A-Za-z0-9]/.test(pw))   s++;
    const levels = [
        ['0%',   'bg-secondary', ''],
        ['25%',  'bg-danger',    'Weak'],
        ['50%',  'bg-warning',   'Fair'],
        ['75%',  'bg-info',      'Good'],
        ['90%',  'bg-primary',   'Strong'],
        ['100%', 'bg-success',   'Very strong'],
    ];
    const [w, cls, txt] = levels[Math.min(s, 5)];
    const bar = document.getElementById('pwBar');
    if (bar) { bar.style.width = w; bar.className = 'progress-bar ' + cls; }
    const lbl = document.getElementById('pwLabel');
    if (lbl) lbl.textContent = txt;
}

function checkMatch(e) {
    const pw1 = document.getElementById('newPwInput')?.value;
    const pw2 = e.target.value;
    const msg = document.getElementById('matchMsg');
    if (!msg || !pw2) return;
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
