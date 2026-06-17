<?php
require_once __DIR__ . '/../../config/config.php';
require_role('admin', 'manager');

$api    = new ApiClient();
$editId = int_param('id');
$isEdit = $editId > 0;
$errors = [];
$sched  = [];

if ($isEdit) {
    $res   = $api->get("report-schedules/$editId");
    $sched = $res['data'] ?? [];
    if (!$sched) { set_flash('error', 'Schedule not found.'); redirect(BASE_URL . '/reports/scheduled.php'); }
    $sched['recipients_raw'] = implode(', ', json_decode($sched['recipients'] ?? '[]', true) ?? []);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $rawRecips  = trim($_POST['recipients'] ?? '');
        $recipients = array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', $rawRecips))));
        $filtersRaw = trim($_POST['filters'] ?? '');
        $filters    = $filtersRaw ? (json_decode($filtersRaw, true) ?: null) : null;

        $payload = [
            'name'        => trim($_POST['name']      ?? ''),
            'report_type' => trim($_POST['report_type'] ?? ''),
            'format'      => trim($_POST['format']    ?? 'csv'),
            'frequency'   => trim($_POST['frequency'] ?? 'monthly'),
            'run_day'     => (int)($_POST['run_day']  ?? 1),
            'run_hour'    => (int)($_POST['run_hour'] ?? 7),
            'is_active'   => isset($_POST['is_active']) ? 1 : 0,
            'recipients'  => $recipients,
            'filters'     => $filters,
        ];

        if (!$payload['name'])        $errors[] = 'Name is required.';
        if (!$payload['report_type']) $errors[] = 'Report type is required.';
        if (empty($recipients))       $errors[] = 'At least one recipient email is required.';

        if (!$errors) {
            if ($isEdit) {
                $res = $api->put("report-schedules/$editId", $payload);
            } else {
                $res = $api->post('report-schedules', $payload);
            }

            if ($res['success'] ?? false) {
                set_flash('success', $isEdit ? 'Schedule updated.' : 'Schedule created.');
                redirect(BASE_URL . '/reports/scheduled.php');
            } else {
                $errors[] = $res['message'] ?? 'Save failed.';
            }
        }

        // Keep user input on error
        $sched = array_merge($sched, $payload, ['recipients_raw' => $rawRecips]);
    }
}

$reportTypes = [
    'financial'        => 'Financial Report',
    'occupancy'        => 'Occupancy Report',
    'rent_collection'  => 'Rent Collection',
    'arrears'          => 'Arrears Analysis',
    'tenant_analytics' => 'Tenant Analytics',
    'maintenance'      => 'Maintenance Report',
    'aging'            => 'AR Aging',
    'deposits'         => 'Deposit Management',
];

$page_title = $isEdit ? 'Edit Report Schedule' : 'New Report Schedule';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3 gap-2">
    <a href="<?= BASE_URL ?>/reports/scheduled.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h5 class="fw-bold mb-0">
        <i class="bi bi-calendar-plus me-2 text-primary"></i>
        <?= $isEdit ? 'Edit' : 'New' ?> Report Schedule
    </h5>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold py-2">Schedule Details</div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Schedule Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= e($sched['name'] ?? '') ?>" required maxlength="150" placeholder="e.g. Monthly Financial Report">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Report Type <span class="text-danger">*</span></label>
                            <select name="report_type" class="form-select" required>
                                <option value="">— Select —</option>
                                <?php foreach ($reportTypes as $val => $label): ?>
                                <option value="<?= $val ?>" <?= ($sched['report_type'] ?? '') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Format</label>
                            <select name="format" class="form-select">
                                <option value="csv" <?= ($sched['format'] ?? 'csv') === 'csv' ? 'selected' : '' ?>>CSV (Excel)</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Frequency</label>
                            <select name="frequency" class="form-select" id="freqSelect" onchange="updateDayLabel()">
                                <?php foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $v => $l): ?>
                                <option value="<?= $v ?>" <?= ($sched['frequency'] ?? 'monthly') === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4" id="runDayCol">
                            <label class="form-label fw-semibold" id="runDayLabel">Day of Month</label>
                            <input type="number" name="run_day" class="form-control" value="<?= (int)($sched['run_day'] ?? 1) ?>" min="1" max="28" id="runDayInput">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Time (24h)</label>
                            <div class="input-group">
                                <input type="number" name="run_hour" class="form-control" value="<?= (int)($sched['run_hour'] ?? 7) ?>" min="0" max="23">
                                <span class="input-group-text">:00</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Recipients <span class="text-danger">*</span></label>
                        <textarea name="recipients" class="form-control" rows="3"
                                  placeholder="email@example.com, another@example.com (comma or newline separated)"><?= e($sched['recipients_raw'] ?? '') ?></textarea>
                        <small class="text-muted">Separate multiple emails with commas or new lines.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Filters <small class="text-muted">(optional JSON)</small></label>
                        <input type="text" name="filters" class="form-control font-monospace" value="<?= e(is_array($sched['filters'] ?? null) ? json_encode($sched['filters']) : ($sched['filters'] ?? '')) ?>" placeholder='{"property_id": 1, "year": 2025}'>
                        <small class="text-muted">JSON object. E.g. <code>{"property_id":1}</code> for property-specific reports.</small>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1"
                               <?= ($sched['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Active (will run on schedule)</label>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Save Changes' : 'Create Schedule' ?>
                        </button>
                        <a href="<?= BASE_URL ?>/reports/scheduled.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold py-2 small"><i class="bi bi-info-circle me-1"></i>How Scheduled Reports Work</div>
            <div class="card-body small text-muted">
                <ul class="mb-0">
                    <li class="mb-1">Reports run at the specified day and hour (server time).</li>
                    <li class="mb-1">The generated CSV is emailed inline to all recipients.</li>
                    <li class="mb-1">Use "Run Now" on the schedule list to test delivery.</li>
                    <li class="mb-1">Filters narrow the report (e.g. <code>{"property_id":2}</code>).</li>
                    <li class="mb-1"><strong>Daily:</strong> Runs every day at specified hour.</li>
                    <li class="mb-1"><strong>Weekly:</strong> Day = 0 (Sun) to 6 (Sat).</li>
                    <li><strong>Monthly:</strong> Day = 1-28 of each month.</li>
                </ul>
                <hr class="my-2">
                <p class="mb-1 fw-semibold">Available filters per report:</p>
                <ul class="mb-0">
                    <li>Most reports: <code>property_id</code></li>
                    <li>Financial: <code>date_from</code>, <code>date_to</code></li>
                    <li>Rent Collection: <code>year</code>, <code>month</code></li>
                    <li>Arrears: <code>months</code> (lookback)</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function updateDayLabel() {
    const freq = document.getElementById('freqSelect').value;
    const col  = document.getElementById('runDayCol');
    const lbl  = document.getElementById('runDayLabel');
    const inp  = document.getElementById('runDayInput');
    if (freq === 'daily') {
        col.classList.add('d-none');
    } else {
        col.classList.remove('d-none');
        if (freq === 'weekly') {
            lbl.textContent = 'Day of Week (0=Sun…6=Sat)';
            inp.max = 6; inp.min = 0;
        } else {
            lbl.textContent = 'Day of Month';
            inp.max = 28; inp.min = 1;
        }
    }
}
updateDayLabel();
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
