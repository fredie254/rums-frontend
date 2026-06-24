<?php
require_once __DIR__ . '/../../config/config.php';
require_role('admin', 'manager');

$api = new ApiClient();
$errors = [];
$input  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $input = [
            'name'      => trim($_POST['name']      ?? ''),
            'category'  => trim($_POST['category']  ?? ''),
            'channel'   => trim($_POST['channel']   ?? ''),
            'subject'   => trim($_POST['subject']   ?? '') ?: null,
            'body'      => trim($_POST['body']       ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $res = $api->post('message-templates', $input);
        if ($res['success'] ?? false) {
            set_flash('success', 'Template created successfully.');
            redirect(BASE_URL . '/notifications/templates');
        } else {
            $errors[] = $res['message'] ?? 'Failed to create template.';
        }
    }
}

$page_title = 'New Message Template';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3 gap-2">
    <a href="<?= BASE_URL ?>/notifications/templates" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h5 class="fw-bold mb-0"><i class="bi bi-file-plus me-2 text-primary"></i>New Message Template</h5>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold py-2">Template Details</div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?= e($input['name'] ?? '') ?>" required maxlength="150" placeholder="e.g. Payment Reminder SMS">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select" required>
                                <option value="">— Select —</option>
                                <?php foreach (['payment', 'lease', 'maintenance', 'broadcast', 'general'] as $c): ?>
                                <option value="<?= $c ?>" <?= ($input['category'] ?? '') === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Channel <span class="text-danger">*</span></label>
                            <select name="channel" class="form-select" required onchange="toggleSubjectField(this.value)">
                                <option value="">— Select —</option>
                                <?php foreach (['sms', 'email', 'both'] as $c): ?>
                                <option value="<?= $c ?>" <?= ($input['channel'] ?? '') === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3" id="subjectField" style="display:none">
                        <label class="form-label fw-semibold">Email Subject</label>
                        <input type="text" name="subject" class="form-control" value="<?= e($input['subject'] ?? '') ?>" maxlength="255" placeholder="Email subject line with {{PLACEHOLDERS}}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Body <span class="text-danger">*</span></label>
                        <textarea name="body" class="form-control font-monospace" rows="9" required
                                  id="bodyField" oninput="updateCounter()"><?= e($input['body'] ?? '') ?></textarea>
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted" id="smsCounter"></small>
                            <small class="text-muted"><span id="charCount">0</span> chars</small>
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1"
                               <?= ($input['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Active (available for use)</label>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Create Template
                        </button>
                        <a href="<?= BASE_URL ?>/notifications/templates" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold py-2 small"><i class="bi bi-braces me-1"></i>Available Variables</div>
            <div class="card-body p-2">
                <p class="text-muted small mb-2">Click to insert at cursor position.</p>
                <div class="row row-cols-1 g-1" style="font-size:.8rem">
                    <?php
                    $vars = [
                        'TENANT_NAME'    => 'Full tenant name',
                        'UNIT_NUMBER'    => 'Unit/house number',
                        'PROPERTY_NAME'  => 'Property name',
                        'MONTHLY_RENT'   => 'Monthly rent amount',
                        'AMOUNT_DUE'     => 'Amount due on invoice',
                        'DUE_DATE'       => 'Invoice due date',
                        'INVOICE_NUMBER' => 'Invoice reference number',
                        'PAYMENT_REF'    => 'Payment reference',
                        'PAYMENT_DATE'   => 'Date payment was made',
                        'LEASE_NUMBER'   => 'Lease reference number',
                        'START_DATE'     => 'Lease start date',
                        'END_DATE'       => 'Lease end date',
                        'DAYS_REMAINING' => 'Days until lease expires',
                        'PAYMENT_DAY'    => 'Day of month rent is due',
                        'STATUS'         => 'Status (maintenance, etc.)',
                        'COMPANY_NAME'   => 'Your company name',
                    ];
                    foreach ($vars as $v => $desc): ?>
                    <div class="col">
                        <code class="text-primary" style="cursor:pointer" title="<?= e($desc) ?>"
                              onclick="insertVar('{{<?= $v ?>}}')">{{<?= $v ?>}}</code>
                        <span class="text-muted" style="font-size:.7rem"> — <?= e($desc) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleSubjectField(val) {
    document.getElementById('subjectField').style.display = (val === 'email' || val === 'both') ? '' : 'none';
    updateCounter();
}
function updateCounter() {
    const sel = document.querySelector('select[name="channel"]');
    const ch  = sel ? sel.value : '';
    const txt = document.getElementById('bodyField').value;
    document.getElementById('charCount').textContent = txt.length;
    if (ch === 'sms' || ch === '') {
        const parts = Math.ceil(txt.length / 160) || 1;
        document.getElementById('smsCounter').textContent = parts + ' SMS part' + (parts > 1 ? 's' : '');
    } else {
        document.getElementById('smsCounter').textContent = '';
    }
}
function insertVar(v) {
    const ta = document.getElementById('bodyField');
    const s = ta.selectionStart, e = ta.selectionEnd;
    ta.value = ta.value.slice(0, s) + v + ta.value.slice(e);
    ta.selectionStart = ta.selectionEnd = s + v.length;
    ta.focus();
    updateCounter();
}
// Init
const chanSel = document.querySelector('select[name="channel"]');
if (chanSel && chanSel.value) toggleSubjectField(chanSel.value);
updateCounter();
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
