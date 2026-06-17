<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'accountant');

$api = new ApiClient();

$dateFrom = str_param('date_from') ?: date('Y-m-01');
$dateTo   = str_param('date_to')   ?: date('Y-m-d');
$method   = get_param('method');
$type     = get_param('type');
$status   = get_param('status');
$tenantId = int_param('tenant_id');
$propId   = int_param('property_id');
$page     = max(1, int_param('page'));

$query = array_filter([
    'date_from'   => $dateFrom,
    'date_to'     => $dateTo,
    'method'      => $method   ?: null,
    'type'        => $type     ?: null,
    'status'      => $status   ?: null,
    'tenant_id'   => $tenantId ?: null,
    'property_id' => $propId   ?: null,
    'page'        => $page,
    'per_page'    => ROWS_PER_PAGE,
], fn($v) => $v !== null && $v !== '');

$res      = $api->get('payments', $query);
$payments = $res['data'] ?? [];
$meta     = $res['meta'] ?? [];
$total    = $meta['total'] ?? 0;
$pg       = [
    'total'       => $total,
    'per_page'    => $meta['per_page'] ?? ROWS_PER_PAGE,
    'page'        => $meta['current_page'] ?? 1,
    'total_pages' => $meta['total_pages'] ?? 1,
    'offset'      => (($meta['current_page'] ?? 1) - 1) * ($meta['per_page'] ?? ROWS_PER_PAGE),
];

// Summary KPIs for selected period
$sumRes  = $api->get('payments/summary', ['date_from' => $dateFrom, 'date_to' => $dateTo, 'property_id' => $propId ?: null]);
$summary = $sumRes['data'] ?? [];

$props = $api->get('properties?per_page=100')['data'] ?? [];

$baseQuery = http_build_query(array_filter([
    'date_from'   => $dateFrom,
    'date_to'     => $dateTo,
    'method'      => $method,
    'type'        => $type,
    'status'      => $status,
    'tenant_id'   => $tenantId ?: null,
    'property_id' => $propId   ?: null,
], fn($v) => $v !== null && $v !== ''));

$exportUrl = env('APP_URL', BASE_URL) . '/api/v1/payments/export?' . $baseQuery;

$page_title = 'Payments';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-cash-coin me-2 text-success"></i>Payments</h5>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/payments/reconcile.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-bank me-1"></i>Reconcile</a>
        <a href="<?= BASE_URL ?>/payments/mpesa_transactions.php" class="btn btn-sm btn-outline-success"><i class="bi bi-phone me-1"></i>M-Pesa Log</a>
        <?php if (is_manager()): ?>
        <a href="<?= BASE_URL ?>/payments/add.php" class="btn btn-sm btn-success"><i class="bi bi-plus-circle me-1"></i>Record Payment</a>
        <?php endif; ?>
    </div>
</div>

<!-- KPI cards -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="text-muted small">Total Collected</div>
            <div class="fs-5 fw-bold text-success"><?= money($summary['total'] ?? 0) ?></div>
            <small class="text-muted"><?= $total ?> transactions</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3" style="background:#d1fae5">
            <div class="text-muted small"><i class="bi bi-phone text-success me-1"></i>M-Pesa</div>
            <div class="fs-6 fw-bold"><?= money($summary['mpesa_total'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3" style="background:#dbeafe">
            <div class="text-muted small"><i class="bi bi-bank text-primary me-1"></i>Bank Transfer</div>
            <div class="fs-6 fw-bold"><?= money($summary['bank_total'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3" style="background:#fef9c3">
            <div class="text-muted small"><i class="bi bi-cash text-warning me-1"></i>Cash</div>
            <div class="fs-6 fw-bold"><?= money($summary['cash_total'] ?? 0) ?></div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label fw-semibold small mb-1">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label fw-semibold small mb-1">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label fw-semibold small mb-1">Method</label>
                <select name="method" class="form-select form-select-sm">
                    <option value="">All Methods</option>
                    <?php foreach (['cash','mpesa','bank_transfer','bank','cheque','card','other'] as $m): ?>
                    <option value="<?= $m ?>" <?= $method===$m?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$m)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label fw-semibold small mb-1">Type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <?php foreach (['rent','deposit','deposit_refund','utility','penalty','maintenance','other'] as $t): ?>
                    <option value="<?= $t ?>" <?= $type===$t?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$t)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label fw-semibold small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <?php foreach (['completed','pending','reversed','failed'] as $st): ?>
                    <option value="<?= $st ?>" <?= $status===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (is_admin()): ?>
            <div class="col-6 col-md-2">
                <label class="form-label fw-semibold small mb-1">Property</label>
                <select name="property_id" class="form-select form-select-sm">
                    <option value="">All Properties</option>
                    <?php foreach ($props as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $propId==$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-auto d-flex gap-1">
                <button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>/payments/index.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                <a href="<?= $exportUrl ?>" class="btn btn-sm btn-outline-success" target="_blank"><i class="bi bi-download me-1"></i>CSV</a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Reference</th>
                    <th>Tenant</th>
                    <th>Unit</th>
                    <th class="text-end">Amount</th>
                    <th>Type</th>
                    <th>Method</th>
                    <th>M-Pesa</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Invoice</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($payments): $sn = $pg['offset'] + 1; foreach ($payments as $p): ?>
            <tr class="<?= ($p['status'] ?? '') === 'reversed' ? 'text-muted text-decoration-line-through' : '' ?>">
                <td><?= $sn++ ?></td>
                <td><code class="small"><?= e($p['payment_ref'] ?? '—') ?></code></td>
                <td class="small"><?= e($p['tenant_name'] ?? '—') ?></td>
                <td class="small text-muted"><?= e($p['property_name'] ?? '') ?>/<?= e($p['unit_number'] ?? '') ?></td>
                <td class="text-end fw-semibold <?= ($p['status'] ?? '') === 'completed' ? 'text-success' : '' ?>"><?= money($p['amount']) ?></td>
                <td><span class="badge bg-light text-dark border small"><?= ucfirst(str_replace('_',' ',$p['payment_type'] ?? '')) ?></span></td>
                <td class="small"><?= ucfirst(str_replace('_',' ',$p['payment_method'] ?? '')) ?></td>
                <td><?= !empty($p['mpesa_transaction_id']) ? '<code class="small">' . e($p['mpesa_transaction_id']) . '</code>' : '<span class="text-muted small">—</span>' ?></td>
                <td class="small"><?= fmt_date($p['payment_date']) ?></td>
                <td><?= payment_badge($p['status'] ?? 'completed') ?></td>
                <td class="small">
                    <?php if (!empty($p['invoice_number'])): ?>
                    <a href="<?= BASE_URL ?>/invoices/view.php?id=<?= $p['invoice_id'] ?>" class="text-decoration-none"><code><?= e($p['invoice_number']) ?></code></a>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td>
                    <a href="<?= BASE_URL ?>/payments/view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-1" title="Receipt"><i class="bi bi-receipt"></i></a>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="12" class="text-center text-muted py-4">No payments found for this period.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted"><?= count($payments) ?> of <?= $total ?> total</small>
        <?= pagination_links($pg, BASE_URL . '/payments/index.php?' . $baseQuery) ?>
    </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
