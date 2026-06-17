<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$api    = new ApiClient();
$user   = current_user();
$errors = [];

// ── Load current consent status ───────────────────────────────
$consentsRes  = $api->get('gdpr/consents');
$consentRows  = $consentsRes['data']['data'] ?? [];
// Key by type for quick lookup
$consentMap = [];
foreach ($consentRows as $c) {
    $consentMap[$c['consent_type']] = $c;
}

// ── Load deletion request status ─────────────────────────────
$delRes    = $api->get('gdpr/deletion/status');
$delReq    = ($delRes['success'] ?? false) ? ($delRes['data'] ?? null) : null;

// ── Admin: load pending deletion requests ─────────────────────
$pendingDeletions = [];
if (is_admin()) {
    $pRes             = $api->get('gdpr/deletion/requests', ['status' => 'pending', 'per_page' => 50]);
    $pendingDeletions = $pRes['data'] ?? [];
}

// ── Handle POST actions ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $action = $_POST['_action'] ?? '';

        // ── Record / withdraw consent ─────────────────────────
        if ($action === 'update_consent') {
            foreach (['terms', 'privacy', 'marketing'] as $type) {
                if (isset($_POST['consent_' . $type])) {
                    $given = (bool)$_POST['consent_' . $type];
                    $api->post('gdpr/consent', [
                        'consent_type' => $type,
                        'consented'    => $given,
                    ]);
                }
            }
            set_flash('success', 'Consent preferences updated.');
            redirect(BASE_URL . '/gdpr/index.php');

        // ── Request data export ───────────────────────────────
        } elseif ($action === 'export_request') {
            $res = $api->post('gdpr/export/request');
            if ($res['success'] ?? false) {
                $token = $res['data']['token'] ?? '';
                // Redirect immediately to download
                $dlUrl = rtrim(env('APP_URL', BASE_URL), '/') . '/api/v1/gdpr/export/download'
                       . '?token=' . urlencode($token)
                       . '&api_token=' . urlencode($_SESSION['api_token'] ?? '');
                redirect($dlUrl);
            } else {
                $errors[] = $res['message'] ?? 'Export failed.';
            }

        // ── Submit deletion request ───────────────────────────
        } elseif ($action === 'deletion_request') {
            $reason = trim($_POST['reason'] ?? '') ?: null;
            $res    = $api->post('gdpr/deletion/request', ['reason' => $reason]);
            if ($res['success'] ?? false) {
                set_flash('success', 'Deletion request submitted. An administrator will review it within 30 days.');
                redirect(BASE_URL . '/gdpr/index.php');
            } else {
                $errors[] = $res['message'] ?? 'Could not submit request.';
            }

        // ── Admin: approve/reject deletion request ────────────
        } elseif ($action === 'process_deletion' && is_admin()) {
            $reqId  = (int)($_POST['request_id'] ?? 0);
            $act    = $_POST['decision'] ?? '';
            $notes  = trim($_POST['admin_notes'] ?? '') ?: null;
            $res    = $api->post('gdpr/deletion/' . $reqId . '/process', [
                'action' => $act,
                'notes'  => $notes,
            ]);
            if ($res['success'] ?? false) {
                set_flash('success', $act === 'approve' ? 'User data anonymized.' : 'Request rejected.');
                redirect(BASE_URL . '/gdpr/index.php');
            } else {
                $errors[] = $res['message'] ?? 'Processing failed.';
            }
        }
    }
}

$page_title = 'Privacy & Data';
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex align-items-center mb-3 gap-2">
    <a href="<?= BASE_URL ?>/dashboard/index.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h5 class="fw-bold mb-0"><i class="bi bi-person-lock me-2 text-primary"></i>Privacy &amp; Data Management</h5>
</div>

<?= flash_html() ?>
<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">

    <!-- ── Left column ────────────────────────────────────── -->
    <div class="col-lg-8">

        <!-- What We Store -->
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold py-2">
                <i class="bi bi-database me-1 text-primary"></i>Data We Hold About You
            </div>
            <div class="card-body small">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <ul class="mb-0 text-muted">
                            <li>Name, email address, phone number</li>
                            <li>National ID / passport (encrypted at rest)</li>
                            <li>Lease agreements and terms</li>
                            <li>Payment history and invoices</li>
                        </ul>
                    </div>
                    <div class="col-sm-6">
                        <ul class="mb-0 text-muted">
                            <li>Maintenance requests and history</li>
                            <li>Documents you have uploaded</li>
                            <li>Login activity and audit logs</li>
                            <li>In-app notifications</li>
                        </ul>
                    </div>
                </div>
                <hr class="my-2">
                <p class="text-muted mb-0" style="font-size:.8rem">
                    All sensitive fields (national IDs, contact numbers) are encrypted at rest using AES-256-GCM.
                    Your data is never sold to third parties.
                </p>
            </div>
        </div>

        <!-- Consent Management -->
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold py-2">
                <i class="bi bi-check2-square me-1 text-success"></i>Consent Preferences
            </div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="update_consent">

                    <?php
                    $consentDefs = [
                        'terms'     => ['Required', 'Terms of Service', 'Acceptance of terms is required to use the system.', true],
                        'privacy'   => ['Required', 'Privacy Policy', 'Consent to data processing as described in our privacy policy.', true],
                        'marketing' => ['Optional', 'Marketing Communications', 'Occasional updates about new features and announcements.', false],
                    ];
                    foreach ($consentDefs as $type => [$badge, $label, $desc, $required]):
                        $current = $consentMap[$type]['consented'] ?? null;
                        $isOn    = $current === null ? ($required ? true : false) : (bool)$current;
                    ?>
                    <div class="d-flex align-items-center justify-content-between mb-3 py-2 border-bottom">
                        <div>
                            <span class="fw-semibold"><?= $label ?></span>
                            <span class="badge bg-<?= $required ? 'secondary' : 'light text-dark border' ?> ms-1 small"><?= $badge ?></span>
                            <div class="text-muted small"><?= $desc ?></div>
                            <?php if ($current !== null): ?>
                            <div class="text-muted" style="font-size:.75rem">
                                Last updated: <?= fmt_date($consentMap[$type]['created_at'] ?? '', 'd M Y') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="form-check form-switch ms-3">
                            <input class="form-check-input" type="checkbox"
                                   name="consent_<?= $type ?>"
                                   value="1"
                                   <?= $isOn ? 'checked' : '' ?>
                                   <?= $required ? 'disabled' : '' ?>>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-save me-1"></i>Save Preferences
                    </button>
                </form>
            </div>
        </div>

        <!-- Download Your Data -->
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold py-2">
                <i class="bi bi-download me-1 text-info"></i>Download Your Data
                <span class="badge bg-info ms-1">GDPR Art. 20</span>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Download a complete copy of all personal data we hold about you as a JSON file.
                    The download link is valid for <strong>1 hour</strong>.
                </p>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="export_request">
                    <button type="submit" class="btn btn-info text-white">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i>Generate &amp; Download My Data
                    </button>
                </form>
            </div>
        </div>

        <!-- Right to Erasure -->
        <div class="card shadow-sm border-danger mb-3">
            <div class="card-header fw-semibold py-2 text-danger">
                <i class="bi bi-person-dash me-1"></i>Right to Erasure
                <span class="badge bg-danger ms-1">GDPR Art. 17</span>
            </div>
            <div class="card-body">
                <?php if ($delReq && in_array($delReq['status'], ['pending', 'processing'])): ?>
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-clock me-1"></i>
                    You have an open deletion request (submitted <?= fmt_date($delReq['requested_at'], 'd M Y') ?>).
                    Status: <strong><?= ucfirst($delReq['status']) ?></strong>
                </div>
                <?php elseif ($delReq && $delReq['status'] === 'completed'): ?>
                <div class="alert alert-success mb-0">
                    <i class="bi bi-check-circle me-1"></i>
                    Your deletion request was processed on <?= fmt_date($delReq['processed_at'], 'd M Y') ?>.
                </div>
                <?php else: ?>
                <p class="text-muted small mb-3">
                    Request permanent deletion of your personal data. Note that financial records and audit logs
                    may be retained for legal compliance. Your account will be anonymized rather than fully deleted.
                </p>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="deletion_request">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Reason (optional)</label>
                        <textarea name="reason" class="form-control form-control-sm" rows="2"
                                  placeholder="Why are you requesting deletion?"></textarea>
                    </div>
                    <button type="submit" class="btn btn-outline-danger btn-sm"
                            onclick="return confirm('Submit a data deletion request? Your account will be reviewed by an administrator.')">
                        <i class="bi bi-trash me-1"></i>Submit Deletion Request
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /col-lg-8 -->

    <!-- ── Right column ───────────────────────────────────── -->
    <div class="col-lg-4">

        <!-- Security link -->
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="fw-semibold mb-1"><i class="bi bi-shield-lock me-1 text-primary"></i>Account Security</div>
                <p class="text-muted small mb-2">Manage two-factor authentication and change your password.</p>
                <a href="<?= BASE_URL ?>/profile/security.php" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-gear me-1"></i>Security Settings
                </a>
            </div>
        </div>

        <!-- Legal info -->
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold py-2 small"><i class="bi bi-balance-scale me-1"></i>Your Rights</div>
            <div class="card-body small text-muted">
                <ul class="mb-0">
                    <li><strong>Access</strong> — Request a copy of your data (Art. 15)</li>
                    <li><strong>Rectification</strong> — Correct inaccurate data (Art. 16)</li>
                    <li><strong>Erasure</strong> — Request deletion (Art. 17)</li>
                    <li><strong>Portability</strong> — Download your data (Art. 20)</li>
                    <li><strong>Objection</strong> — Object to processing (Art. 21)</li>
                </ul>
            </div>
        </div>

        <!-- Retention -->
        <div class="card shadow-sm">
            <div class="card-header fw-semibold py-2 small"><i class="bi bi-calendar-check me-1"></i>Data Retention</div>
            <div class="card-body small text-muted">
                <ul class="mb-0">
                    <li>Account data: retained while active</li>
                    <li>Financial records: 7 years (legal)</li>
                    <li>Audit logs: 2 years</li>
                    <li>Login history: 90 days</li>
                    <li>Documents: as per tenant agreement</li>
                </ul>
            </div>
        </div>
    </div>

</div>

<!-- ── Admin: Pending Deletion Requests ─────────────────────── -->
<?php if (is_admin() && $pendingDeletions): ?>
<div class="card shadow-sm mt-3 border-warning">
    <div class="card-header fw-semibold py-2">
        <i class="bi bi-person-x me-1 text-warning"></i>Pending Deletion Requests
        <span class="badge bg-warning text-dark ms-1"><?= count($pendingDeletions) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Email</th>
                    <th>Reason</th>
                    <th>Requested</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingDeletions as $dr): ?>
                <tr>
                    <td><?= $dr['id'] ?></td>
                    <td><?= e($dr['user_name']) ?></td>
                    <td class="text-muted"><?= e($dr['user_email']) ?></td>
                    <td class="text-muted" style="max-width:200px">
                        <?= e(mb_strimwidth($dr['reason'] ?? '—', 0, 60, '…')) ?>
                    </td>
                    <td><?= fmt_date($dr['requested_at'], 'd M Y') ?></td>
                    <td>
                        <button class="btn btn-xs btn-sm btn-outline-success py-0 px-1 me-1"
                                onclick="processRequest(<?= $dr['id'] ?>, 'approve')"
                                title="Approve — Anonymize user">
                            <i class="bi bi-check-lg"></i>
                        </button>
                        <button class="btn btn-xs btn-sm btn-outline-danger py-0 px-1"
                                onclick="processRequest(<?= $dr['id'] ?>, 'reject')"
                                title="Reject">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Hidden process form -->
<form method="post" id="processForm" style="display:none">
    <?= csrf_field() ?>
    <input type="hidden" name="_action"    value="process_deletion">
    <input type="hidden" name="request_id" id="processId">
    <input type="hidden" name="decision"   id="processDecision">
    <input type="hidden" name="admin_notes" id="processNotes">
</form>
<?php endif; ?>

<script>
function processRequest(id, action) {
    const label = action === 'approve' ? 'anonymize this user\'s data' : 'reject this request';
    const notes = action === 'approve'
        ? prompt('Optional admin notes (e.g., retention reason):')
        : prompt('Reason for rejection (required):', '');

    if (action === 'reject' && notes === null) return; // cancelled
    if (!confirm('Are you sure you want to ' + label + '?')) return;

    document.getElementById('processId').value       = id;
    document.getElementById('processDecision').value = action;
    document.getElementById('processNotes').value    = notes || '';
    document.getElementById('processForm').submit();
}
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
