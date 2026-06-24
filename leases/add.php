<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api = new ApiClient();
$errors = [];

$pre_unit_id   = int_param('unit_id');
$pre_tenant_id = int_param('tenant_id');

// Fetch data for selects
$units_res = $api->get('units', ['status' => 'available', 'per_page' => 500]);
$units     = $units_res['data'] ?? [];
$ten_res   = $api->get('tenants', ['status' => 'active', 'per_page' => 500]);
$tenants   = $ten_res['data'] ?? [];
$tpl_res   = $api->get('lease-templates');
$templates = $tpl_res['data'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/leases/add'); }

    $unit_id       = int_param('unit_id', 0, 'post');
    $tenant_id     = int_param('tenant_id', 0, 'post');
    $start_date    = post('start_date');
    $end_date      = post('end_date');
    $monthly_rent  = (float)post('monthly_rent');
    $deposit_amt   = (float)post('deposit_amount');
    $deposit_paid  = (float)post('deposit_paid');
    $payment_day   = max(1, min(28, (int)post('payment_day') ?: 1));
    $grace_days    = max(0, (int)post('grace_period_days') ?: 5);
    $penalty_rate  = (float)post('penalty_rate');
    $notice_days   = max(0, (int)post('notice_period_days') ?: 30);
    $lease_type    = post('lease_type') ?: 'fixed-term';
    $template_id   = int_param('template_id', 0, 'post') ?: null;
    $esc_type      = post('escalation_type') ?: 'none';
    $esc_rate      = (float)post('escalation_rate');
    $esc_freq      = post('escalation_frequency') ?: 'annually';
    $terms         = post('terms');

    if (!$unit_id)            $errors[] = 'Unit is required.';
    if (!$tenant_id)          $errors[] = 'Tenant is required.';
    if (!$start_date)         $errors[] = 'Start date is required.';
    if (!$end_date)           $errors[] = 'End date is required.';
    if ($monthly_rent <= 0)   $errors[] = 'Rent amount must be greater than 0.';
    if ($end_date && $start_date && $end_date <= $start_date) $errors[] = 'End date must be after start date.';
    if ($esc_type !== 'none' && $esc_rate <= 0) $errors[] = 'Escalation rate must be greater than 0 when escalation is enabled.';

    if (!$errors) {
        $payload = array_filter([
            'unit_id'              => $unit_id,
            'tenant_id'            => $tenant_id,
            'start_date'           => $start_date,
            'end_date'             => $end_date,
            'monthly_rent'         => $monthly_rent,
            'deposit_amount'       => $deposit_amt,
            'payment_day'          => $payment_day,
            'grace_period_days'    => $grace_days,
            'penalty_rate'         => $penalty_rate,
            'notice_period_days'   => $notice_days,
            'lease_type'           => $lease_type,
            'template_id'          => $template_id,
            'escalation_type'      => $esc_type,
            'escalation_rate'      => $esc_type !== 'none' ? $esc_rate : null,
            'escalation_frequency' => $esc_type !== 'none' ? $esc_freq : null,
            'terms'                => $terms ?: null,
        ], fn($v) => $v !== null && $v !== '' && $v !== 0.0);

        $res = $api->post('leases', $payload);
        if (!empty($res['success'])) {
            $lease_id = $res['data']['id'] ?? 0;
            if ($deposit_paid > 0 && $lease_id) {
                $api->post('payments', [
                    'lease_id'       => $lease_id,
                    'amount'         => $deposit_paid,
                    'payment_date'   => date('Y-m-d'),
                    'payment_method' => 'cash',
                    'payment_type'   => 'deposit',
                    'notes'          => 'Deposit on lease creation',
                ]);
            }
            set_flash('success', 'Lease ' . ($res['data']['lease_number'] ?? '') . ' created.');
            redirect(BASE_URL . '/leases/view?id=' . $lease_id);
        }
        $errors[] = $res['message'] ?? 'Failed to create lease.';
    }
}

$cur_sym  = get_setting('currency_symbol', CURRENCY_SYMBOL);
$page_title = 'New Lease';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3">
    <a href="<?= BASE_URL ?>/leases/index" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0">Create New Lease</h5>
    <?php if ($templates): ?>
    <div class="ms-auto">
        <select id="templateSelect" class="form-select form-select-sm" style="width:220px">
            <option value="">Load from template…</option>
            <?php foreach ($templates as $t): ?>
            <option value="<?= $t['id'] ?>"
                    data-type="<?= e($t['lease_type']) ?>"><?= e($t['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
</div>
<?php if ($errors): ?><div class="alert alert-danger small"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form method="POST" id="leaseForm">
<?= csrf_field() ?>
<input type="hidden" name="template_id" id="templateIdInput" value="<?= e(post('template_id')) ?>">

<!-- ── Parties ──────────────────────────────────────────────── -->
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold small"><i class="bi bi-people me-1 text-primary"></i>Parties</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Unit *</label>
                <select name="unit_id" class="form-select" id="unitSelect" required onchange="fillUnitAmounts(this)">
                    <option value="">— Select Available Unit —</option>
                    <?php foreach ($units as $u): ?>
                    <option value="<?= $u['id'] ?>"
                            data-rent="<?= $u['rent_amount'] ?>"
                            data-deposit="<?= $u['deposit_amount'] ?? 0 ?>"
                            <?= ($pre_unit_id==$u['id'] || int_param('unit_id',0,'post')==$u['id']) ? 'selected':'' ?>>
                        <?= e($u['property_name'] ?? '') ?> / <?= e($u['unit_number']) ?>
                        (<?= strtoupper($u['unit_type'] ?? '') ?>) — <?= $cur_sym ?> <?= number_format($u['rent_amount'], 2) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Tenant *</label>
                <select name="tenant_id" class="form-select" required>
                    <option value="">— Select Tenant —</option>
                    <?php foreach ($tenants as $t): ?>
                    <option value="<?= $t['id'] ?>"
                        <?= ($pre_tenant_id==$t['id'] || int_param('tenant_id',0,'post')==$t['id']) ? 'selected':'' ?>>
                        <?= e($t['full_name'] ?? ($t['first_name'].' '.$t['last_name'])) ?>
                        <?php if (!empty($t['phone'])): ?> — <?= e($t['phone']) ?><?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- ── Dates & Type ─────────────────────────────────────────── -->
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold small"><i class="bi bi-calendar-range me-1 text-primary"></i>Lease Period & Type</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Start Date *</label>
                <input type="date" name="start_date" class="form-control" value="<?= e(post('start_date') ?: date('Y-m-01')) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">End Date *</label>
                <input type="date" name="end_date" class="form-control" value="<?= e(post('end_date') ?: date('Y-m-d', strtotime('+1 year -1 day'))) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Lease Type</label>
                <select name="lease_type" id="leaseType" class="form-select">
                    <?php foreach (['fixed-term','periodic','commercial','furnished'] as $lt): ?>
                    <option value="<?= $lt ?>" <?= (post('lease_type') ?: 'fixed-term') === $lt ? 'selected' : '' ?>><?= ucfirst(str_replace('-',' ',$lt)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Notice Period (days)</label>
                <input type="number" name="notice_period_days" class="form-control" value="<?= e(post('notice_period_days') ?: 30) ?>" min="0" max="180">
            </div>
        </div>
    </div>
</div>

<!-- ── Financials ───────────────────────────────────────────── -->
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold small"><i class="bi bi-cash-coin me-1 text-success"></i>Financials</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Monthly Rent (<?= $cur_sym ?>) *</label>
                <input type="number" step="0.01" name="monthly_rent" id="rentAmount" class="form-control" value="<?= e(post('monthly_rent')) ?>" required min="1">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Deposit Amount (<?= $cur_sym ?>)</label>
                <input type="number" step="0.01" name="deposit_amount" id="depositAmount" class="form-control" value="<?= e(post('deposit_amount')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Deposit Paid Now (<?= $cur_sym ?>)</label>
                <input type="number" step="0.01" name="deposit_paid" class="form-control" value="<?= e(post('deposit_paid') ?: '0') ?>" min="0">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Rent Due (day of month)</label>
                <input type="number" name="payment_day" class="form-control" value="<?= e(post('payment_day') ?: 1) ?>" min="1" max="28">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Grace Period (days)</label>
                <input type="number" name="grace_period_days" class="form-control" value="<?= e(post('grace_period_days') ?: 5) ?>" min="0" max="30">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Penalty Rate (%)</label>
                <input type="number" step="0.01" name="penalty_rate" class="form-control" value="<?= e(post('penalty_rate') ?: '0') ?>" min="0" max="100">
            </div>
        </div>
    </div>
</div>

<!-- ── Rent Escalation ──────────────────────────────────────── -->
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold small"><i class="bi bi-graph-up-arrow me-1 text-warning"></i>Rent Escalation Rules</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Escalation Type</label>
                <select name="escalation_type" id="escType" class="form-select" onchange="toggleEscalation(this.value)">
                    <option value="none" <?= (post('escalation_type') ?: 'none') === 'none' ? 'selected':'' ?>>None (no automatic increases)</option>
                    <option value="percentage" <?= post('escalation_type') === 'percentage' ? 'selected':'' ?>>Percentage (e.g. 5%)</option>
                    <option value="fixed" <?= post('escalation_type') === 'fixed' ? 'selected':'' ?>>Fixed Amount (e.g. <?= $cur_sym ?> 500)</option>
                </select>
            </div>
            <div class="col-md-4 esc-fields" style="<?= (post('escalation_type') && post('escalation_type') !== 'none') ? '' : 'opacity:0.4;pointer-events:none' ?>">
                <label class="form-label fw-semibold" id="escRateLabel">Escalation Rate</label>
                <div class="input-group">
                    <input type="number" step="0.01" name="escalation_rate" class="form-control" value="<?= e(post('escalation_rate') ?: '0') ?>" min="0">
                    <span class="input-group-text" id="escRateSuffix"><?= post('escalation_type') === 'fixed' ? $cur_sym : '%' ?></span>
                </div>
            </div>
            <div class="col-md-4 esc-fields" style="<?= (post('escalation_type') && post('escalation_type') !== 'none') ? '' : 'opacity:0.4;pointer-events:none' ?>">
                <label class="form-label fw-semibold">Escalation Frequency</label>
                <select name="escalation_frequency" class="form-select">
                    <option value="annually"   <?= (post('escalation_frequency') ?: 'annually') === 'annually'   ? 'selected':'' ?>>Annually</option>
                    <option value="biannually" <?= post('escalation_frequency') === 'biannually' ? 'selected':'' ?>>Every 6 Months</option>
                    <option value="quarterly"  <?= post('escalation_frequency') === 'quarterly'  ? 'selected':'' ?>>Quarterly</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- ── Terms ────────────────────────────────────────────────── -->
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold small"><i class="bi bi-journal-text me-1 text-primary"></i>Lease Terms</span>
        <small class="text-muted">Template placeholders are auto-substituted when a template is loaded</small>
    </div>
    <div class="card-body">
        <textarea name="terms" id="termsBody" class="form-control font-monospace" rows="12"
                  placeholder="Paste or type lease terms here, or load a template above…"><?= e(post('terms')) ?></textarea>
    </div>
</div>

<div class="d-flex gap-2 mb-4">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Create Lease</button>
    <a href="<?= BASE_URL ?>/leases/index" class="btn btn-outline-secondary">Cancel</a>
</div>
</form>

<script>
function fillUnitAmounts(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('rentAmount').value    = opt.dataset.rent    || '';
    document.getElementById('depositAmount').value = opt.dataset.deposit || '';
}

function toggleEscalation(val) {
    const fields  = document.querySelectorAll('.esc-fields');
    const suffix  = document.getElementById('escRateSuffix');
    const label   = document.getElementById('escRateLabel');
    const enabled = val !== 'none';
    fields.forEach(f => { f.style.opacity = enabled ? '1' : '0.4'; f.style.pointerEvents = enabled ? '' : 'none'; });
    if (suffix) suffix.textContent = val === 'fixed' ? '<?= $cur_sym ?>' : '%';
    if (label)  label.textContent  = val === 'fixed' ? 'Fixed Amount per Period' : 'Percentage per Period';
}

<?php if ($templates): ?>
document.getElementById('templateSelect').addEventListener('change', function() {
    const id = this.value;
    if (!id) return;
    fetch(`<?= rtrim(env('APP_URL',''), '/') ?>/api/v1/lease-templates/${id}`, {
        headers: { 'Authorization': 'Bearer <?= $_SESSION['api_token'] ?? '' ?>' }
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success || !res.data) return;
        const tpl = res.data;
        // Set lease type
        const ltSel = document.getElementById('leaseType');
        if (ltSel) ltSel.value = tpl.lease_type;
        // Substitute placeholders and fill terms
        document.getElementById('termsBody').value = substitutePlaceholders(tpl.body);
        document.getElementById('templateIdInput').value = id;
    });
});

function substitutePlaceholders(body) {
    const unitSel   = document.getElementById('unitSelect');
    const unitOpt   = unitSel ? unitSel.options[unitSel.selectedIndex] : null;
    const tenantSel = document.querySelector('[name="tenant_id"]');
    const tenantOpt = tenantSel ? tenantSel.options[tenantSel.selectedIndex] : null;

    const map = {
        '{{UNIT_NUMBER}}':      unitOpt  ? unitOpt.text.split('(')[0].trim().split('/')[1]?.trim() || '' : '',
        '{{PROPERTY_NAME}}':    unitOpt  ? unitOpt.text.split('/')[0].trim() : '',
        '{{TENANT_NAME}}':      tenantOpt ? tenantOpt.text.split('—')[0].trim() : '',
        '{{MONTHLY_RENT}}':     document.querySelector('[name="monthly_rent"]')?.value || '',
        '{{DEPOSIT_AMOUNT}}':   document.querySelector('[name="deposit_amount"]')?.value || '',
        '{{START_DATE}}':       document.querySelector('[name="start_date"]')?.value || '',
        '{{END_DATE}}':         document.querySelector('[name="end_date"]')?.value || '',
        '{{PAYMENT_DAY}}':      document.querySelector('[name="payment_day"]')?.value || '',
        '{{NOTICE_PERIOD_DAYS}}': document.querySelector('[name="notice_period_days"]')?.value || '',
        '{{GRACE_PERIOD_DAYS}}':  document.querySelector('[name="grace_period_days"]')?.value || '',
        '{{PENALTY_RATE}}':       document.querySelector('[name="penalty_rate"]')?.value || '',
        '{{LEASE_TYPE}}':         document.getElementById('leaseType')?.options[document.getElementById('leaseType').selectedIndex]?.text || '',
        '{{TODAY}}':              new Date().toLocaleDateString('en-KE'),
    };
    return Object.entries(map).reduce((b, [k, v]) => b.replaceAll(k, v), body);
}
<?php endif; ?>
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
