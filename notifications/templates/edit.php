<?php
require_once __DIR__ . '/../../config/config.php';
require_role('admin', 'manager');

$api = new ApiClient();
$id  = int_param('id');
if (!$id) { set_flash('error', 'Invalid template.'); redirect(BASE_URL . '/notifications/templates.php'); }

$res = $api->get("message-templates/$id");
if (empty($res['data'])) { set_flash('error', 'Template not found.'); redirect(BASE_URL . '/notifications/templates.php'); }

$tpl    = $res['data'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $payload = [
            'name'      => trim($_POST['name']      ?? ''),
            'category'  => trim($_POST['category']  ?? ''),
            'channel'   => trim($_POST['channel']   ?? ''),
            'subject'   => trim($_POST['subject']   ?? '') ?: null,
            'body'      => trim($_POST['body']       ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $res2 = $api->put("message-templates/$id", $payload);
        if ($res2['success'] ?? false) {
            set_flash('success', 'Template updated.');
            redirect(BASE_URL . '/notifications/templates.php');
        } else {
            $errors[] = $res2['message'] ?? 'Update failed.';
            $tpl = array_merge($tpl, $payload); // keep user input
        }
    }
}

$page_title = 'Edit Template — ' . e($tpl['name']);
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3 gap-2">
    <a href="<?= BASE_URL ?>/notifications/templates.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h5 class="fw-bold mb-0"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Template</h5>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold py-2">Template: <?= e($tpl['name']) ?></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?= e($tpl['name']) ?>" required maxlength="150">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select" required>
                                <?php foreach (['payment', 'lease', 'maintenance', 'broadcast', 'general'] as $c): ?>
                                <option value="<?= $c ?>" <?= $tpl['category'] === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Channel <span class="text-danger">*</span></label>
                            <select name="channel" class="form-select" required onchange="toggleSubjectField(this.value)">
                                <?php foreach (['sms', 'email', 'both'] as $c): ?>
                                <option value="<?= $c ?>" <?= $tpl['channel'] === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3" id="subjectField" style="<?= in_array($tpl['channel'], ['email', 'both']) ? '' : 'display:none' ?>">
                        <label class="form-label fw-semibold">Email Subject</label>
                        <input type="text" name="subject" class="form-control" value="<?= e($tpl['subject'] ?? '') ?>" maxlength="255">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Body <span class="text-danger">*</span></label>
                        <textarea name="body" class="form-control font-monospace" rows="9" required
                                  id="bodyField" oninput="updateCounter()"><?= e($tpl['body']) ?></textarea>
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted" id="smsCounter"></small>
                            <small class="text-muted"><span id="charCount">0</span> chars</small>
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1"
                               <?= $tpl['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Save Changes
                        </button>
                        <a href="<?= BASE_URL ?>/notifications/templates.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold py-2 small"><i class="bi bi-braces me-1"></i>Available Variables</div>
            <div class="card-body p-2">
                <div class="row row-cols-1 g-1" style="font-size:.8rem">
                    <?php
                    $vars = ['TENANT_NAME','UNIT_NUMBER','PROPERTY_NAME','MONTHLY_RENT','AMOUNT_DUE','DUE_DATE','INVOICE_NUMBER','PAYMENT_REF','PAYMENT_DATE','LEASE_NUMBER','START_DATE','END_DATE','DAYS_REMAINING','PAYMENT_DAY','STATUS','COMPANY_NAME'];
                    foreach ($vars as $v): ?>
                    <div class="col">
                        <code class="text-primary" style="cursor:pointer" onclick="insertVar('{{<?= $v ?>}}')">{{<?= $v ?>}}</code>
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
}
function updateCounter() {
    const sel = document.querySelector('select[name="channel"]');
    const ch  = sel ? sel.value : '';
    const txt = document.getElementById('bodyField').value;
    document.getElementById('charCount').textContent = txt.length;
    if (ch !== 'email') {
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
updateCounter();
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
