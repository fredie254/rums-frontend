<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'accountant');

$api        = new ApiClient();
$page_title = 'Payment Reconciliation';

/* ── filters ── */
$period   = get_param('period', date('Y-m'));
$status   = get_param('status', 'all');
$property = int_param('property_id', 0);

[$yr, $mo] = explode('-', $period);
$date_from = "$yr-$mo-01";
$date_to   = date('Y-m-t', strtotime($date_from));

/* ── Properties dropdown ── */
$prop_res   = $api->get('properties', ['per_page' => 500]);
$properties = $prop_res['data'] ?? [];

/* ── Fetch payments via API ── */
$query = array_filter([
    'date_from'   => $date_from,
    'date_to'     => $date_to,
    'property_id' => $property ?: null,
    'per_page'    => 500,
], fn($v) => $v !== null && $v !== '');

$pay_res  = $api->get('payments', $query);
$all_pays = $pay_res['data'] ?? [];

/* ── Apply status filter client-side ── */
$payments = match ($status) {
    'matched'   => array_filter($all_pays, fn($r) => !empty($r['invoice_id']) && ($r['invoice_status'] ?? '') === 'paid'),
    'unmatched' => array_filter($all_pays, fn($r) => empty($r['invoice_id'])),
    'partial'   => array_filter($all_pays, fn($r) => ($r['invoice_status'] ?? '') === 'partial'),
    default     => $all_pays,
};
$payments = array_values($payments);

/* ── Summary totals ── */
$total_payments  = array_sum(array_column($payments, 'amount'));
$matched_count   = count(array_filter($payments, fn($r) => !empty($r['invoice_id']) && ($r['invoice_status'] ?? '') === 'paid'));
$unmatched_count = count(array_filter($payments, fn($r) => empty($r['invoice_id'])));
$partial_count   = count(array_filter($payments, fn($r) => ($r['invoice_status'] ?? '') === 'partial'));

/* ── Unlinked M-Pesa transactions via API ── */
$mpesa_res    = $api->get('mpesa-transactions', [
    'status'     => 'completed',
    'no_payment' => '1',
    'date_from'  => $date_from,
    'date_to'    => $date_to,
    'per_page'   => 200,
]);
$unlinked_mpesa = $mpesa_res['data'] ?? [];

include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0"><i class="bi bi-arrow-left-right me-2 text-primary"></i>Payment Reconciliation</h5>
    <a href="<?= BASE_URL ?>/accountant/dashboard.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">Period</label>
                <input type="month" name="period" class="form-control form-control-sm" value="<?= e($period) ?>">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Property</label>
                <select name="property_id" class="form-select form-select-sm">
                    <option value="0">All Properties</option>
                    <?php foreach ($properties as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $property == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="all"       <?= $status === 'all'       ? 'selected' : '' ?>>All</option>
                    <option value="matched"   <?= $status === 'matched'   ? 'selected' : '' ?>>Fully Matched</option>
                    <option value="partial"   <?= $status === 'partial'   ? 'selected' : '' ?>>Partial</option>
                    <option value="unmatched" <?= $status === 'unmatched' ? 'selected' : '' ?>>Unmatched</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm">Filter</button>
                <a href="reconciliation.php" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- KPI Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm kpi-blue text-white">
            <div class="card-body py-3">
                <div class="small opacity-75">Total Collected</div>
                <div class="fs-5 fw-bold"><?= money($total_payments) ?></div>
                <div class="small opacity-75"><?= count($payments) ?> transactions</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm kpi-green text-white">
            <div class="card-body py-3">
                <div class="small opacity-75">Fully Matched</div>
                <div class="fs-5 fw-bold"><?= $matched_count ?></div>
                <div class="small opacity-75">invoices cleared</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm kpi-yellow text-white">
            <div class="card-body py-3">
                <div class="small opacity-75">Partial Payments</div>
                <div class="fs-5 fw-bold"><?= $partial_count ?></div>
                <div class="small opacity-75">invoices partially paid</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm kpi-red text-white">
            <div class="card-body py-3">
                <div class="small opacity-75">Unmatched</div>
                <div class="fs-5 fw-bold"><?= $unmatched_count ?></div>
                <div class="small opacity-75">no invoice linked</div>
            </div>
        </div>
    </div>
</div>

<!-- Payments Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Payments — <?= date('F Y', strtotime($date_from)) ?></span>
        <a href="<?= BASE_URL ?>/reports/financial.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-download me-1"></i>Export
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Ref</th><th>Date</th><th>Tenant</th><th>Unit</th><th>Method</th>
                        <th class="text-end">Amount</th><th>Invoice</th><th class="text-end">Inv. Amount</th>
                        <th>Status</th><th>Variance</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$payments): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">No payments found for the selected period.</td></tr>
                <?php else: ?>
                    <?php foreach ($payments as $row):
                        $inv_amt  = (float)($row['invoice_amount'] ?? 0);
                        $variance = $inv_amt > 0 ? ((float)$row['amount'] - $inv_amt) : null;
                        $var_class = $variance === null ? '' : ($variance < 0 ? 'text-danger' : ($variance > 0 ? 'text-warning' : 'text-success'));
                    ?>
                    <tr>
                        <td><a href="<?= BASE_URL ?>/payments/view.php?id=<?= $row['id'] ?>" class="text-decoration-none fw-semibold"><?= e($row['payment_ref'] ?? '—') ?></a></td>
                        <td><?= fmt_date($row['payment_date']) ?></td>
                        <td><?= e($row['tenant_name'] ?? '—') ?></td>
                        <td><?= e(!empty($row['unit_number']) ? ($row['property_name'] . ' / ' . $row['unit_number']) : '—') ?></td>
                        <td><span class="badge bg-secondary"><?= e(ucfirst($row['payment_method'] ?? '')) ?></span></td>
                        <td class="text-end fw-semibold"><?= money($row['amount']) ?></td>
                        <td>
                            <?php if (!empty($row['invoice_number'])): ?>
                            <a href="<?= BASE_URL ?>/invoices/view.php?id=<?= $row['invoice_id'] ?>"><?= e($row['invoice_number']) ?></a>
                            <?php else: ?>
                            <span class="text-danger small"><i class="bi bi-exclamation-circle"></i> Unlinked</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?= $inv_amt > 0 ? money($inv_amt) : '—' ?></td>
                        <td><?= !empty($row['invoice_status']) ? invoice_badge($row['invoice_status']) : '<span class="badge bg-secondary">—</span>' ?></td>
                        <td class="<?= $var_class ?> fw-semibold">
                            <?php if ($variance !== null): ?>
                                <?= $variance == 0 ? '<i class="bi bi-check-circle text-success"></i>' : money(abs($variance)) . ($variance < 0 ? ' short' : ' over') ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($unlinked_mpesa): ?>
<!-- Unlinked M-Pesa Transactions -->
<div class="card shadow-sm border-warning mb-4">
    <div class="card-header bg-warning-subtle py-2">
        <span class="fw-semibold text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1"></i>
        Unlinked M-Pesa Transactions (<?= count($unlinked_mpesa) ?>)</span>
        <small class="text-muted ms-2">Completed M-Pesa payments not yet linked to any payment record</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Transaction ID</th><th>From</th><th>Phone</th>
                        <th class="text-end">Amount</th><th>Date</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($unlinked_mpesa as $m): ?>
                <tr class="table-warning">
                    <td class="font-monospace small"><?= e($m['transaction_id'] ?? $m['mpesa_receipt'] ?? '—') ?></td>
                    <td><?= e(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?></td>
                    <td><?= e($m['msisdn'] ?? '—') ?></td>
                    <td class="text-end fw-semibold"><?= money($m['amount']) ?></td>
                    <td><?= fmt_date($m['created_at'], true) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/payments/add.php?mpesa_id=<?= $m['id'] ?>" class="btn btn-xs btn-warning">
                            <i class="bi bi-link-45deg me-1"></i>Link
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/footer.php'; ?>
