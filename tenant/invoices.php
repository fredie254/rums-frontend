<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$user = current_user();
if ($user['role'] !== 'tenant') { redirect(BASE_URL . '/dashboard/index'); }

$api    = new ApiClient();
$page   = max(1, int_param('page'));
$status = get_param('status');

$query = array_filter([
    'page'     => $page,
    'per_page' => ROWS_PER_PAGE,
    'status'   => $status ?: null,
], fn($v) => $v !== null);

$res      = $api->get('invoices', $query);
$invoices = $res['data'] ?? [];
$meta     = $res['meta'] ?? [];
$total    = $meta['total'] ?? 0;
$pg       = [
    'total'       => $total,
    'per_page'    => $meta['per_page']     ?? ROWS_PER_PAGE,
    'page'        => $meta['current_page'] ?? 1,
    'total_pages' => $meta['total_pages']  ?? 1,
    'offset'      => (($meta['current_page'] ?? 1) - 1) * ($meta['per_page'] ?? ROWS_PER_PAGE),
];

// Summary totals
$all_res   = $api->get('invoices', ['per_page' => 200]);
$all_inv   = $all_res['data'] ?? [];
$total_due = array_sum(array_map(fn($i) => max(0, (float)$i['total_amount'] - (float)$i['amount_paid']), $all_inv));
$total_paid= array_sum(array_column($all_inv, 'amount_paid'));

$page_title = 'My Invoices';
include BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-4">
    <div>
        <h5 class="fw-bold mb-1"><i class="bi bi-receipt me-2 text-primary"></i>My Invoices</h5>
        <small class="text-muted">All invoices for your tenancy</small>
    </div>
    <a href="<?= BASE_URL ?>/tenant/dashboard" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Dashboard
    </a>
</div>

<!-- Summary cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="kpi-card <?= $total_due > 0 ? 'kpi-red' : 'kpi-green' ?>">
            <div class="kpi-icon"><i class="bi bi-<?= $total_due > 0 ? 'exclamation-circle' : 'check-circle' ?>"></i></div>
            <div class="kpi-value" style="font-size:1rem"><?= money($total_due) ?></div>
            <div class="kpi-label">Outstanding</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
            <div class="kpi-value" style="font-size:1rem"><?= money($total_paid) ?></div>
            <div class="kpi-label">Total Paid</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-receipt"></i></div>
            <div class="kpi-value"><?= count($all_inv) ?></div>
            <div class="kpi-label">Total Invoices</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div>
            <div class="kpi-value"><?= count(array_filter($all_inv, fn($i) => $i['status'] === 'overdue')) ?></div>
            <div class="kpi-label">Overdue</div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="d-flex gap-2 mb-3 flex-wrap">
    <?php
    $statuses = ['' => 'All', 'unpaid' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid', 'overdue' => 'Overdue'];
    foreach ($statuses as $sv => $sl):
    ?>
    <a href="?status=<?= $sv ?>" class="btn btn-sm <?= $status === $sv ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $sl ?></a>
    <?php endforeach; ?>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Invoice #</th>
                    <th>Period</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Balance</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($invoices): foreach ($invoices as $inv):
                $bal = (float)$inv['total_amount'] - (float)$inv['amount_paid'];
            ?>
            <tr>
                <td><code class="small"><?= e($inv['invoice_number']) ?></code></td>
                <td class="small"><?= !empty($inv['period_month']) ? month_name($inv['period_month']) . ' ' . $inv['period_year'] : fmt_date($inv['invoice_date']) ?></td>
                <td class="text-end"><?= money($inv['total_amount']) ?></td>
                <td class="text-end text-success"><?= money($inv['amount_paid']) ?></td>
                <td class="text-end <?= $bal > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= money($bal) ?></td>
                <td class="small <?= $inv['status'] === 'overdue' ? 'text-danger' : '' ?>"><?= fmt_date($inv['due_date']) ?></td>
                <td><?= invoice_badge($inv['status']) ?></td>
                <td>
                    <a href="<?= BASE_URL ?>/invoices/view?id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2">
                        <i class="bi bi-eye"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="8" class="text-center text-muted py-5">
                <i class="bi bi-receipt fs-2 d-block mb-2 opacity-25"></i>
                No invoices<?= $status ? ' with status "' . e($status) . '"' : '' ?> found.
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total > $pg['per_page']): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Showing <?= count($invoices) ?> of <?= $total ?></small>
        <?= pagination_links($pg, BASE_URL . '/tenant/invoices?' . http_build_query(array_filter(['status' => $status]))) ?>
    </div>
    <?php endif; ?>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
