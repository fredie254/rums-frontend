<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$user = current_user();
if ($user['role'] !== 'tenant') { redirect(BASE_URL . '/dashboard/index'); }

$api  = new ApiClient();
$page = max(1, int_param('page'));

$res      = $api->get('payments', ['page' => $page, 'per_page' => ROWS_PER_PAGE]);
$payments = $res['data'] ?? [];
$meta     = $res['meta'] ?? [];
$total    = $meta['total'] ?? 0;
$pg       = [
    'total'       => $total,
    'per_page'    => $meta['per_page']     ?? ROWS_PER_PAGE,
    'page'        => $meta['current_page'] ?? 1,
    'total_pages' => $meta['total_pages']  ?? 1,
    'offset'      => (($meta['current_page'] ?? 1) - 1) * ($meta['per_page'] ?? ROWS_PER_PAGE),
];

// Active lease for Pay button
$lease_res = $api->get('leases', ['status' => 'active', 'per_page' => 1]);
$lease     = $lease_res['data'][0] ?? null;

// Summary
$total_amount = array_sum(array_map(
    fn($p) => $p['status'] === 'completed' ? (float)$p['amount'] : 0,
    $payments
));

$page_title = 'My Payments';
include BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-4">
    <div>
        <h5 class="fw-bold mb-1"><i class="bi bi-cash-coin me-2 text-success"></i>Payment History</h5>
        <small class="text-muted">All payments made on your account</small>
    </div>
    <div class="d-flex gap-2">
        <?php if ($lease): ?>
        <a href="<?= BASE_URL ?>/payments/mpesa_pay?lease_id=<?= $lease['id'] ?>" class="btn btn-success btn-sm">
            <i class="bi bi-phone me-1"></i>Pay via M-Pesa
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/tenant/dashboard" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Dashboard
        </a>
    </div>
</div>

<!-- Summary cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
            <div class="kpi-value" style="font-size:1rem"><?= money($total_amount) ?></div>
            <div class="kpi-label">Paid (this page)</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-receipt"></i></div>
            <div class="kpi-value"><?= $total ?></div>
            <div class="kpi-label">Total Transactions</div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Reference</th>
                    <th>Amount</th>
                    <th>Type</th>
                    <th>Method</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($payments): foreach ($payments as $p): ?>
            <tr>
                <td><code class="small"><?= e($p['payment_ref'] ?? '—') ?></code></td>
                <td class="fw-semibold <?= ($p['status'] ?? '') === 'completed' ? 'text-success' : '' ?>">
                    <?= money($p['amount']) ?>
                </td>
                <td class="small"><?= ucfirst($p['payment_type'] ?? '') ?></td>
                <td>
                    <span class="badge bg-light text-dark border">
                        <i class="bi bi-<?= $p['payment_method'] === 'mpesa' ? 'phone' : 'cash' ?> me-1"></i>
                        <?= ucfirst(str_replace('_', ' ', $p['payment_method'] ?? '')) ?>
                    </span>
                </td>
                <td class="small"><?= fmt_date($p['payment_date']) ?></td>
                <td><?= payment_badge($p['status'] ?? 'completed') ?></td>
                <td>
                    <a href="<?= BASE_URL ?>/payments/view?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2">
                        <i class="bi bi-eye"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="7" class="text-center text-muted py-5">
                <i class="bi bi-cash-coin fs-2 d-block mb-2 opacity-25"></i>
                No payment records found.
                <?php if ($lease): ?>
                <div class="mt-2"><a href="<?= BASE_URL ?>/payments/mpesa_pay?lease_id=<?= $lease['id'] ?>" class="btn btn-sm btn-success">Make your first payment</a></div>
                <?php endif; ?>
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total > $pg['per_page']): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Showing <?= count($payments) ?> of <?= $total ?></small>
        <?= pagination_links($pg, BASE_URL . '/tenant/payments') ?>
    </div>
    <?php endif; ?>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
