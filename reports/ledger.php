<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$api      = new ApiClient();
$errors   = [];
$ledger   = null;
$tenant   = null;

// Tenant lookup (restrict to own record if role=tenant)
$user = current_user();
if ($user['role'] === 'tenant') {
    $tr = $api->get("tenants?user_id={$user['id']}");
    $tenant = ($tr['data'][0] ?? null);
    $tenantId = $tenant ? (int)$tenant['id'] : 0;
} else {
    $tenantId = int_param('tenant_id');
    if ($tenantId) {
        $tr = $api->get("tenants/$tenantId");
        $tenant = $tr['data'] ?? null;
    }
}

$dateFrom = str_param('date_from') ?: date('Y-01-01');
$dateTo   = str_param('date_to')   ?: date('Y-m-d');

if ($tenantId) {
    $res    = $api->get("reports/ledger?tenant_id=$tenantId&date_from=$dateFrom&date_to=$dateTo");
    $ledger = $res['data'] ?? null;
    if (!$ledger) $errors[] = $res['message'] ?? 'Could not load ledger.';
}

// Tenants list for selector (non-tenant roles)
$tenants = [];
if ($user['role'] !== 'tenant') {
    $tr2     = $api->get('tenants?per_page=200&status=active');
    $tenants = $tr2['data'] ?? [];
}

$page_title = 'Tenant Ledger';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/reports/index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
        <h5 class="fw-bold mb-0"><i class="bi bi-journal-text me-2 text-primary"></i>Tenant Ledger</h5>
    </div>
    <?php if ($ledger): ?>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>
    <?php endif; ?>
</div>

<?php if ($errors): ?><div class="alert alert-danger small"><?= e($errors[0]) ?></div><?php endif; ?>

<!-- Filter form -->
<?php if ($user['role'] !== 'tenant'): ?>
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold small mb-1">Tenant</label>
                <select name="tenant_id" class="form-select form-select-sm" required>
                    <option value="">— select tenant —</option>
                    <?php foreach ($tenants as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $tenantId === (int)$t['id'] ? 'selected':'' ?>>
                        <?= e($t['first_name'] . ' ' . $t['last_name']) ?>
                        <?php if (!empty($t['unit_number'])): ?>(<?= e($t['unit_number']) ?>)<?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small mb-1">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small mb-1">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i>Load</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($ledger): ?>
<!-- Summary KPIs -->
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="text-muted small">Total Charged</div>
            <div class="fs-5 fw-bold text-danger"><?= money($ledger['total_debits']) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="text-muted small">Total Paid</div>
            <div class="fs-5 fw-bold text-success"><?= money($ledger['total_credits']) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="text-muted small">Closing Balance</div>
            <?php $cb = $ledger['closing_balance']; ?>
            <div class="fs-5 fw-bold <?= $cb > 0 ? 'text-danger' : 'text-success' ?>"><?= money($cb) ?></div>
            <small class="text-muted"><?= $cb > 0 ? 'amount owed' : ($cb < 0 ? 'credit' : 'settled') ?></small>
        </div>
    </div>
</div>

<!-- Ledger table -->
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold small">
            <i class="bi bi-person me-1 text-primary"></i>
            <?= e($tenant['first_name'] . ' ' . $tenant['last_name']) ?>
            &mdash; <?= fmt_date($ledger['date_from']) ?> to <?= fmt_date($ledger['date_to']) ?>
        </span>
        <span class="small text-muted"><?= count($ledger['entries']) ?> entries</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Reference</th>
                    <th>Description</th>
                    <th class="text-end text-danger">Debit</th>
                    <th class="text-end text-success">Credit</th>
                    <th class="text-end">Balance</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($ledger['entries']): foreach ($ledger['entries'] as $entry): ?>
            <tr class="<?= $entry['entry_type'] === 'debit' ? '' : 'table-success table-sm' ?>">
                <td class="small"><?= fmt_date($entry['entry_date']) ?></td>
                <td class="small"><code><?= e($entry['reference'] ?? '—') ?></code></td>
                <td class="small"><?= e($entry['description']) ?></td>
                <td class="text-end small <?= $entry['entry_type']==='debit' ? 'fw-semibold text-danger' : 'text-muted' ?>">
                    <?= $entry['entry_type']==='debit' ? money($entry['amount']) : '—' ?>
                </td>
                <td class="text-end small <?= $entry['entry_type']==='credit' ? 'fw-semibold text-success' : 'text-muted' ?>">
                    <?= $entry['entry_type']==='credit' ? money($entry['amount']) : '—' ?>
                </td>
                <td class="text-end small fw-semibold <?= $entry['running_balance'] > 0 ? 'text-danger' : 'text-success' ?>">
                    <?= money(abs($entry['running_balance'])) ?>
                    <?= $entry['running_balance'] > 0 ? '<span class="text-muted fw-normal">Dr</span>' : ($entry['running_balance'] < 0 ? '<span class="text-muted fw-normal">Cr</span>' : '') ?>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No transactions in this period.</td></tr>
            <?php endif; ?>
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="3">Totals</td>
                    <td class="text-end text-danger"><?= money($ledger['total_debits']) ?></td>
                    <td class="text-end text-success"><?= money($ledger['total_credits']) ?></td>
                    <td class="text-end <?= $ledger['closing_balance'] > 0 ? 'text-danger' : 'text-success' ?>"><?= money(abs($ledger['closing_balance'])) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php elseif (!$tenantId && $user['role'] !== 'tenant'): ?>
<div class="text-center text-muted py-5">
    <i class="bi bi-journal-text fs-1 d-block mb-2 opacity-25"></i>
    Select a tenant above to view their ledger.
</div>
<?php endif; ?>
<?php include BASE_PATH . '/includes/footer.php'; ?>
