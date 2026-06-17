<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api      = new ApiClient();
$status   = get_param('status');
$search   = get_param('search');
$month    = int_param('month');
$year     = int_param('year') ?: (int)date('Y');
$lease_id = int_param('lease_id');
$page     = max(1, int_param('page'));

$query = array_filter([
    'search'   => $search,
    'status'   => $status,
    'month'    => $month ?: null,
    'year'     => $year,
    'lease_id' => $lease_id ?: null,
    'page'     => $page,
    'per_page' => ROWS_PER_PAGE,
], fn($v) => $v !== null && $v !== '');

$res      = $api->get('invoices', $query);
$invoices = $res['data'] ?? [];
$meta     = $res['meta'] ?? ['total' => 0, 'total_pages' => 1, 'current_page' => 1, 'per_page' => ROWS_PER_PAGE];
$total    = $meta['total'] ?? 0;
$pg       = ['total' => $total, 'per_page' => $meta['per_page'], 'page' => $meta['current_page'], 'total_pages' => $meta['total_pages'], 'offset' => ($meta['current_page'] - 1) * $meta['per_page']];

$page_title = 'Invoices';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-receipt me-2 text-info"></i>Invoices</h5>
    <?php if (is_manager()): ?>
    <a href="<?= BASE_URL ?>/invoices/generate.php" class="btn btn-info btn-sm text-white"><i class="bi bi-plus-circle me-1"></i>Generate Invoices</a>
    <?php endif; ?>
</div>
<div class="card shadow-sm mb-3"><div class="card-body py-2">
    <form method="GET" class="row g-2">
        <div class="col-md-3"><input type="text" name="search" class="form-control form-control-sm" placeholder="Invoice#, tenant..." value="<?= e($search) ?>"></div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm">
                <option value="">All Status</option>
                <?php foreach (['draft','sent','paid','partial','overdue','cancelled'] as $s): ?><option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1"><select name="month" class="form-select form-select-sm"><option value="">Month</option><?php for ($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $month==$m?'selected':'' ?>><?= month_name($m) ?></option><?php endfor; ?></select></div>
        <div class="col-md-1"><input type="number" name="year" class="form-control form-control-sm" value="<?= $year ?>" placeholder="Year"></div>
        <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Filter</button><a href="<?= BASE_URL ?>/invoices/index.php" class="btn btn-sm btn-outline-secondary ms-1">Reset</a></div>
    </form>
</div></div>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>#</th><th>Invoice #</th><th>Tenant</th><th>Property/Unit</th><th>Period</th><th>Total</th><th>Paid</th><th>Balance</th><th>Due Date</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if ($invoices): $sn = $pg['offset']+1; foreach ($invoices as $inv): $balance = $inv['total_amount'] - $inv['amount_paid']; ?>
                <tr>
                    <td><?= $sn++ ?></td>
                    <td><a href="<?= BASE_URL ?>/invoices/view.php?id=<?= $inv['id'] ?>"><code><?= e($inv['invoice_number']) ?></code></a></td>
                    <td><?= e($inv['tenant_name'] ?? '—') ?></td>
                    <td><?= e($inv['property_name'] ?? '') ?>/<?= e($inv['unit_number'] ?? '') ?></td>
                    <td><?= month_name($inv['period_month']) ?> <?= $inv['period_year'] ?></td>
                    <td><?= money($inv['total_amount']) ?></td>
                    <td class="text-success"><?= money($inv['amount_paid']) ?></td>
                    <td class="<?= $balance > 0 ? 'text-danger fw-semibold' : 'text-success' ?>"><?= money($balance) ?></td>
                    <td><?= fmt_date($inv['due_date']) ?></td>
                    <td><?= invoice_badge($inv['status']) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/invoices/view.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-1"><i class="bi bi-eye"></i></a>
                        <?php if (in_array($inv['status'], ['sent','partial','overdue']) && is_manager()): ?>
                        <a href="<?= BASE_URL ?>/payments/add.php?lease_id=<?= $inv['lease_id'] ?>&invoice_id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-success py-0 px-1" title="Record Payment"><i class="bi bi-cash"></i></a>
                        <?php endif; ?>
                        <?php if (!in_array($inv['status'], ['paid','cancelled','voided']) && is_accountant()): ?>
                        <a href="<?= BASE_URL ?>/invoices/view.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-danger py-0 px-1" title="Void Invoice"><i class="bi bi-slash-circle"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="11" class="text-center text-muted py-4">No invoices found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between"><small class="text-muted"><?= count($invoices) ?> of <?= $total ?></small><?= pagination_links($pg, BASE_URL . '/invoices/index.php?' . http_build_query(['search'=>$search,'status'=>$status,'month'=>$month,'year'=>$year])) ?></div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
