<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api = new ApiClient();

// Fetch tenants and templates for selects
$tenants   = $api->get('tenants', ['per_page' => 500, 'status' => 'active'])['data'] ?? [];
$templates = $api->get('message-templates')['data'] ?? [];

$page_title = 'Compose Message';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3 gap-2">
    <a href="<?= BASE_URL ?>/notifications/index.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h5 class="fw-bold mb-0"><i class="bi bi-send me-2 text-primary"></i>Compose Message</h5>
</div>

<?= flash_html() ?>

<div class="row g-3">
    <!-- Compose Form -->
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold py-2">New Message</div>
            <div class="card-body">
                <!-- Channel toggle -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Channel</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="channel" id="chSms" value="sms" checked onchange="toggleChannel()">
                            <label class="form-check-label" for="chSms"><i class="bi bi-chat-text me-1"></i>SMS</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="channel" id="chEmail" value="email" onchange="toggleChannel()">
                            <label class="form-check-label" for="chEmail"><i class="bi bi-envelope me-1"></i>Email</label>
                        </div>
                    </div>
                </div>

                <!-- Recipient -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Recipient</label>
                    <div class="d-flex gap-2">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="recip_type" id="rtTenant" value="tenant" checked onchange="toggleRecipType()">
                            <label class="form-check-label" for="rtTenant">Select Tenant</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="recip_type" id="rtManual" value="manual" onchange="toggleRecipType()">
                            <label class="form-check-label" for="rtManual">Enter Manually</label>
                        </div>
                    </div>
                </div>

                <div id="tenantSelect" class="mb-3">
                    <select class="form-select" id="tenantId" onchange="onTenantChange()">
                        <option value="">— Select tenant —</option>
                        <?php foreach ($tenants as $t): ?>
                        <option value="<?= $t['id'] ?>"
                                data-phone="<?= e($t['phone'] ?? '') ?>"
                                data-email="<?= e($t['email'] ?? '') ?>">
                            <?= e($t['first_name'] . ' ' . $t['last_name']) ?>
                            <?= $t['unit_number'] ? '— ' . e($t['unit_number']) : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="manualRecipient" class="mb-3 d-none">
                    <input type="text" class="form-control" id="recipientValue" placeholder="Phone (+2547xxx) or Email address">
                </div>

                <!-- Template selector -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Template <small class="text-muted">(optional)</small></label>
                    <select class="form-select" id="templateSelect" onchange="applyTemplate()">
                        <option value="">— No template —</option>
                        <?php foreach ($templates as $tpl): ?>
                        <option value="<?= $tpl['id'] ?>"
                                data-channel="<?= e($tpl['channel']) ?>"
                                data-subject="<?= e($tpl['subject'] ?? '') ?>"
                                data-body="<?= e($tpl['body']) ?>">
                            <?= e($tpl['name']) ?> (<?= e($tpl['channel']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Email subject (only for email) -->
                <div id="subjectRow" class="mb-3 d-none">
                    <label class="form-label fw-semibold">Subject</label>
                    <input type="text" class="form-control" id="emailSubject" placeholder="Email subject line" maxlength="255">
                </div>

                <!-- Message body -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Message</label>
                    <textarea class="form-control font-monospace" id="messageBody" rows="6" placeholder="Type your message..."></textarea>
                    <div class="d-flex justify-content-between mt-1">
                        <small class="text-muted" id="smsCounter"></small>
                        <small class="text-muted" id="charCount">0 chars</small>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button class="btn btn-primary" onclick="sendMessage()">
                        <i class="bi bi-send me-1"></i>Send
                    </button>
                    <button class="btn btn-outline-secondary" onclick="clearForm()">Clear</button>
                </div>

                <!-- Result alert -->
                <div id="sendResult" class="mt-3 d-none"></div>
            </div>
        </div>
    </div>

    <!-- Reminders panel -->
    <div class="col-lg-5">
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold py-2"><i class="bi bi-alarm me-2 text-warning"></i>Batch Reminders</div>
            <div class="card-body">
                <p class="text-muted small">Send templated reminders to multiple tenants at once.</p>

                <!-- Payment reminders -->
                <div class="border rounded p-3 mb-3">
                    <h6 class="fw-semibold mb-2"><i class="bi bi-cash-coin me-1 text-success"></i>Payment Reminders</h6>
                    <p class="text-muted small mb-2">Send to tenants with invoices due in the next N days.</p>
                    <div class="input-group input-group-sm mb-2">
                        <span class="input-group-text">Due within</span>
                        <input type="number" class="form-control" id="dueDays" value="3" min="1" max="30">
                        <span class="input-group-text">days</span>
                    </div>
                    <button class="btn btn-sm btn-warning w-100" onclick="runBatch('payment')">
                        <i class="bi bi-send me-1"></i>Send Payment Reminders
                    </button>
                    <div id="payResult" class="mt-2 d-none"></div>
                </div>

                <!-- Lease reminders -->
                <div class="border rounded p-3">
                    <h6 class="fw-semibold mb-2"><i class="bi bi-file-earmark-text me-1 text-info"></i>Lease Expiry Reminders</h6>
                    <p class="text-muted small mb-2">Send to tenants whose lease expires within N days.</p>
                    <div class="input-group input-group-sm mb-2">
                        <span class="input-group-text">Expiring in</span>
                        <input type="number" class="form-control" id="expiryDays" value="30" min="1" max="180">
                        <span class="input-group-text">days</span>
                    </div>
                    <button class="btn btn-sm btn-info w-100" onclick="runBatch('lease')">
                        <i class="bi bi-send me-1"></i>Send Lease Reminders
                    </button>
                    <div id="leaseResult" class="mt-2 d-none"></div>
                </div>
            </div>
        </div>

        <!-- Variable reference -->
        <div class="card shadow-sm">
            <div class="card-header fw-semibold py-2 small"><i class="bi bi-braces me-1"></i>Template Variables</div>
            <div class="card-body p-2">
                <div class="row row-cols-2 g-1" style="font-size:.75rem">
                    <?php foreach (['TENANT_NAME','UNIT_NUMBER','PROPERTY_NAME','MONTHLY_RENT','AMOUNT_DUE','DUE_DATE','INVOICE_NUMBER','PAYMENT_REF','PAYMENT_DATE','LEASE_NUMBER','START_DATE','END_DATE','DAYS_REMAINING','PAYMENT_DAY','COMPANY_NAME','STATUS'] as $v): ?>
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
const API_BASE = '<?= rtrim(env('APP_URL', BASE_URL), '/') ?>/api/v1';
const TOKEN    = '<?= e($_SESSION['api_token'] ?? '') ?>';

function toggleChannel() {
    const ch = document.querySelector('input[name="channel"]:checked').value;
    document.getElementById('subjectRow').classList.toggle('d-none', ch !== 'email');
    updateSmsCounter();
}
function toggleRecipType() {
    const t = document.querySelector('input[name="recip_type"]:checked').value;
    document.getElementById('tenantSelect').classList.toggle('d-none', t !== 'tenant');
    document.getElementById('manualRecipient').classList.toggle('d-none', t === 'tenant');
}
function onTenantChange() {
    const sel = document.getElementById('tenantId');
    const opt = sel.options[sel.selectedIndex];
    // Pre-fill recipient based on channel
    const ch = document.querySelector('input[name="channel"]:checked').value;
}
function applyTemplate() {
    const sel = document.getElementById('templateSelect');
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    document.getElementById('messageBody').value   = opt.dataset.body || '';
    document.getElementById('emailSubject').value  = opt.dataset.subject || '';
    const ch = opt.dataset.channel;
    if (ch === 'sms')   document.getElementById('chSms').checked   = true;
    if (ch === 'email') document.getElementById('chEmail').checked  = true;
    toggleChannel();
    updateSmsCounter();
}
function updateSmsCounter() {
    const ch  = document.querySelector('input[name="channel"]:checked').value;
    const txt = document.getElementById('messageBody').value;
    document.getElementById('charCount').textContent = txt.length + ' chars';
    if (ch === 'sms') {
        const parts = Math.ceil(txt.length / 160) || 1;
        document.getElementById('smsCounter').textContent = parts + ' SMS part' + (parts > 1 ? 's' : '');
    } else {
        document.getElementById('smsCounter').textContent = '';
    }
}
document.getElementById('messageBody').addEventListener('input', updateSmsCounter);

function insertVar(v) {
    const ta = document.getElementById('messageBody');
    const s = ta.selectionStart, e = ta.selectionEnd;
    ta.value = ta.value.slice(0, s) + v + ta.value.slice(e);
    ta.selectionStart = ta.selectionEnd = s + v.length;
    ta.focus();
    updateSmsCounter();
}

function clearForm() {
    document.getElementById('tenantId').value      = '';
    document.getElementById('messageBody').value   = '';
    document.getElementById('emailSubject').value  = '';
    document.getElementById('templateSelect').value = '';
    document.getElementById('sendResult').classList.add('d-none');
    updateSmsCounter();
}

async function sendMessage() {
    const ch = document.querySelector('input[name="channel"]:checked').value;
    const rt = document.querySelector('input[name="recip_type"]:checked').value;
    const body = document.getElementById('messageBody').value.trim();
    const subj = document.getElementById('emailSubject').value.trim();
    const tplId = document.getElementById('templateSelect').value;

    if (!body) { showResult('sendResult', 'error', 'Message body is required.'); return; }

    let recipient = '';
    let tenantId  = null;

    if (rt === 'tenant') {
        const sel = document.getElementById('tenantId');
        tenantId  = sel.value ? parseInt(sel.value) : null;
        const opt = sel.options[sel.selectedIndex];
        recipient = ch === 'sms' ? (opt.dataset.phone || '') : (opt.dataset.email || '');
        if (!tenantId) { showResult('sendResult', 'error', 'Please select a tenant.'); return; }
        if (!recipient) { showResult('sendResult', 'error', 'Tenant has no ' + ch + ' contact.'); return; }
    } else {
        recipient = document.getElementById('recipientValue').value.trim();
        if (!recipient) { showResult('sendResult', 'error', 'Please enter a recipient.'); return; }
    }

    const btn = document.querySelector('button[onclick="sendMessage()"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending...';

    try {
        let payload, endpoint;
        if (ch === 'sms') {
            endpoint = 'notifications/send-sms';
            payload  = { phone: recipient, message: body, tenant_id: tenantId, template_id: tplId ? parseInt(tplId) : null };
        } else {
            if (!subj) { showResult('sendResult', 'error', 'Subject is required for email.'); btn.disabled = false; btn.innerHTML = '<i class="bi bi-send me-1"></i>Send'; return; }
            endpoint = 'notifications/send-email';
            payload  = { email: recipient, subject: subj, html_body: body.replace(/\n/g, '<br>'), tenant_id: tenantId, template_id: tplId ? parseInt(tplId) : null };
        }

        const res = await apiPost(endpoint, payload);
        if (res.success) {
            showResult('sendResult', 'success', ch.toUpperCase() + ' sent successfully to ' + recipient + '!');
            clearForm();
        } else {
            showResult('sendResult', 'error', res.message || 'Send failed.');
        }
    } catch (e) {
        showResult('sendResult', 'error', 'Request failed: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-1"></i>Send';
    }
}

async function runBatch(type) {
    const resultId = type === 'payment' ? 'payResult' : 'leaseResult';
    const btn = document.querySelector(`button[onclick="runBatch('${type}')"]`);
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending...';

    const payload = type === 'payment'
        ? { due_days:    parseInt(document.getElementById('dueDays').value) || 3 }
        : { expiry_days: parseInt(document.getElementById('expiryDays').value) || 30 };

    const endpoint = type === 'payment' ? 'notifications/payment-reminders' : 'notifications/lease-reminders';

    try {
        const res = await apiPost(endpoint, payload);
        if (res.success) {
            const d = res.data || {};
            showResult(resultId, 'success', `Sent: ${d.sent || 0} | Failed: ${d.failed || 0} | Skipped: ${d.skipped || 0}`);
        } else {
            showResult(resultId, 'error', res.message || 'Failed.');
        }
    } catch (e) {
        showResult(resultId, 'error', e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = type === 'payment'
            ? '<i class="bi bi-send me-1"></i>Send Payment Reminders'
            : '<i class="bi bi-send me-1"></i>Send Lease Reminders';
    }
}

async function apiPost(endpoint, payload) {
    const r = await fetch(API_BASE + '/' + endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + TOKEN },
        body: JSON.stringify(payload),
    });
    return r.json();
}

function showResult(id, type, msg) {
    const el = document.getElementById(id);
    el.className = 'mt-2 alert alert-' + (type === 'success' ? 'success' : 'danger') + ' py-2 small';
    el.textContent = msg;
    el.classList.remove('d-none');
    setTimeout(() => el.classList.add('d-none'), 8000);
}
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
