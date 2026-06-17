<?php
require_once __DIR__ . '/../../config/config.php';
require_role('admin', 'manager');

$api = new ApiClient();

$templates  = $api->get('message-templates')['data'] ?? [];
$properties = $api->get('properties', ['per_page' => 100])['data'] ?? [];

$page_title = 'New Broadcast';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3 gap-2">
    <a href="<?= BASE_URL ?>/notifications/broadcasts.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h5 class="fw-bold mb-0"><i class="bi bi-broadcast me-2 text-primary"></i>New Broadcast</h5>
</div>

<?= flash_html() ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold py-2">Broadcast Details</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" placeholder="e.g. December Rent Reminder" maxlength="200">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Channel <span class="text-danger">*</span></label>
                    <select class="form-select" id="channel" onchange="toggleSubject()">
                        <option value="sms">SMS</option>
                        <option value="email">Email</option>
                        <option value="both">Both (SMS + Email)</option>
                    </select>
                </div>

                <div class="mb-3 d-none" id="subjectRow">
                    <label class="form-label fw-semibold">Email Subject</label>
                    <input type="text" class="form-control" id="subject" placeholder="Email subject line" maxlength="255">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Template <small class="text-muted">(optional)</small></label>
                    <select class="form-select" id="templateId" onchange="applyTemplate()">
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

                <div class="mb-3">
                    <label class="form-label fw-semibold">Message <span class="text-danger">*</span></label>
                    <textarea class="form-control font-monospace" id="message" rows="7" placeholder="Your broadcast message..."></textarea>
                    <small class="text-muted"><span id="msgLen">0</span> characters</small>
                </div>

                <div id="createResult" class="mb-3 d-none"></div>

                <div class="d-flex gap-2">
                    <button class="btn btn-primary" onclick="createBroadcast(false)">
                        <i class="bi bi-save me-1"></i>Save as Draft
                    </button>
                    <button class="btn btn-success" onclick="createBroadcast(true)">
                        <i class="bi bi-send me-1"></i>Save & Send Now
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <!-- Recipient filter -->
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold py-2"><i class="bi bi-funnel me-1"></i>Recipient Filter</div>
            <div class="card-body">
                <p class="text-muted small">Leave all filters blank to send to all active tenants.</p>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Property</label>
                    <select class="form-select form-select-sm" id="filterProperty">
                        <option value="">All Properties</option>
                        <?php foreach ($properties as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="filterOverdue">
                    <label class="form-check-label small" for="filterOverdue">
                        Only tenants with overdue payments
                    </label>
                </div>

                <hr class="my-2">

                <div class="mb-0">
                    <label class="form-label small fw-semibold">Scheduled At <small class="text-muted">(optional)</small></label>
                    <input type="datetime-local" class="form-control form-control-sm" id="scheduledAt">
                    <small class="text-muted">Leave blank to send immediately when you click "Send".</small>
                </div>
            </div>
        </div>

        <!-- Variable reference -->
        <div class="card shadow-sm">
            <div class="card-header fw-semibold py-2 small"><i class="bi bi-braces me-1"></i>Template Variables</div>
            <div class="card-body p-2">
                <div class="row row-cols-2 g-1" style="font-size:.75rem">
                    <?php foreach (['TENANT_NAME','UNIT_NUMBER','PROPERTY_NAME','COMPANY_NAME'] as $v): ?>
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

document.getElementById('message').addEventListener('input', () => {
    document.getElementById('msgLen').textContent = document.getElementById('message').value.length;
});

function toggleSubject() {
    const ch = document.getElementById('channel').value;
    document.getElementById('subjectRow').classList.toggle('d-none', ch === 'sms');
}

function applyTemplate() {
    const sel = document.getElementById('templateId');
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    document.getElementById('message').value = opt.dataset.body || '';
    document.getElementById('subject').value = opt.dataset.subject || '';
    if (opt.dataset.channel && opt.dataset.channel !== 'both') {
        document.getElementById('channel').value = opt.dataset.channel;
    }
    toggleSubject();
    document.getElementById('msgLen').textContent = document.getElementById('message').value.length;
}

function insertVar(v) {
    const ta = document.getElementById('message');
    const s = ta.selectionStart, e = ta.selectionEnd;
    ta.value = ta.value.slice(0, s) + v + ta.value.slice(e);
    ta.selectionStart = ta.selectionEnd = s + v.length;
    ta.focus();
    document.getElementById('msgLen').textContent = ta.value.length;
}

async function createBroadcast(sendNow) {
    const title   = document.getElementById('title').value.trim();
    const channel = document.getElementById('channel').value;
    const message = document.getElementById('message').value.trim();
    const subject = document.getElementById('subject').value.trim();
    const tplId   = document.getElementById('templateId').value;
    const sched   = document.getElementById('scheduledAt').value;

    if (!title)   { showResult('error', 'Title is required.'); return; }
    if (!message) { showResult('error', 'Message is required.'); return; }
    if (channel !== 'sms' && !subject) { showResult('error', 'Subject is required for email.'); return; }

    const filter = {};
    const propId = document.getElementById('filterProperty').value;
    if (propId) filter.property_id = parseInt(propId);
    if (document.getElementById('filterOverdue').checked) filter.has_overdue = true;

    const payload = {
        title, channel, message,
        subject:          subject || null,
        template_id:      tplId   ? parseInt(tplId) : null,
        recipient_filter: Object.keys(filter).length ? filter : null,
        scheduled_at:     sched   || null,
    };

    const btns = document.querySelectorAll('button[onclick^="createBroadcast"]');
    btns.forEach(b => b.disabled = true);

    try {
        const r1 = await fetch(API_BASE + '/broadcasts', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + TOKEN },
            body: JSON.stringify(payload),
        });
        const res1 = await r1.json();
        if (!res1.success) { showResult('error', res1.message || 'Create failed.'); btns.forEach(b => b.disabled = false); return; }

        const broadcastId = res1.data?.id;

        if (sendNow && broadcastId) {
            const r2 = await fetch(API_BASE + '/broadcasts/' + broadcastId + '/send', {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + TOKEN },
            });
            const res2 = await r2.json();
            if (res2.success) {
                showResult('success', `Broadcast sent! ${res2.data?.sent ?? 0}/${res2.data?.total ?? 0} messages delivered. Redirecting...`);
            } else {
                showResult('warning', 'Broadcast created but send failed: ' + (res2.message || 'Unknown error.'));
            }
        } else {
            showResult('success', 'Broadcast saved as draft. Redirecting...');
        }

        setTimeout(() => { window.location.href = '<?= BASE_URL ?>/notifications/broadcasts.php'; }, 2000);
    } catch (e) {
        showResult('error', e.message);
        btns.forEach(b => b.disabled = false);
    }
}

function showResult(type, msg) {
    const el = document.getElementById('createResult');
    const map = { success: 'success', error: 'danger', warning: 'warning' };
    el.className = 'mb-3 alert alert-' + (map[type] || 'info') + ' py-2 small';
    el.textContent = msg;
    el.classList.remove('d-none');
}
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
