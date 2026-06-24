<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'landlord', 'accountant');

$api = new ApiClient();
$id  = int_param('id');
if (!$id) { redirect(BASE_URL . '/tenants/index'); }

$date_from = get_param('date_from', date('Y-m-01', strtotime('-3 months')));
$date_to   = get_param('date_to',   date('Y-m-d'));

$res    = $api->get("tenants/$id");
$tenant = $res['data'] ?? null;
if (!$tenant) { set_flash('error', 'Tenant not found.'); redirect(BASE_URL . '/tenants/index'); }
$full_name = $tenant['full_name'] ?? trim(($tenant['first_name'] ?? '') . ' ' . ($tenant['last_name'] ?? ''));

$stmt_res   = $api->get("tenants/$id/statement", ['date_from' => $date_from, 'date_to' => $date_to]);
$stmt       = $stmt_res['data'] ?? [];
$invoices   = $stmt['invoices']  ?? [];
$payments   = $stmt['payments']  ?? [];
$summary    = $stmt['summary']   ?? [];

$page_title = 'Statement — ' . $full_name;
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3 gap-2">
    <a href="<?= BASE_URL ?>/tenants/view?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0 flex-grow-1">Statement — <?= e($full_name) ?></h5>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>
</div>

<form method="GET" class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($date_from) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($date_to) ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-primary">Generate</button>
            </div>
        </div>
    </div>
</form>

<?php if (!empty($summary)): ?>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center py-2">
            <div class="fs-5 fw-bold text-primary"><?= money($summary['total_invoiced'] ?? 0) ?></div>
            <div class="small text-muted">Total Invoiced</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center py-2">
            <div class="fs-5 fw-bold text-success"><?= money($summary['total_paid'] ?? 0) ?></div>
            <div class="small text-muted">Total Paid</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center py-2">
            <div class="fs-5 fw-bold text-danger"><?= money($summary['balance'] ?? 0) ?></div>
            <div class="small text-muted">Balance Due</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center py-2">
            <div class="fs-5 fw-bold text-info"><?= count($invoices) ?></div>
            <div class="small text-muted">Invoices</div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-md-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-receipt me-1 text-info"></i>Invoices</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Invoice #</th><th>Period</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if ($invoices): foreach ($invoices as $inv):
                        $bal = (float)($inv['balance'] ?? ((float)($inv['total_amount'] ?? 0) - (float)($inv['amount_paid'] ?? 0)));
                    ?>
                        <tr>
                            <td><a href="<?= BASE_URL ?>/invoices/view?id=<?= $inv['id'] ?>"><code class="small"><?= e($inv['invoice_number']) ?></code></a></td>
                            <td class="small"><?= !empty($inv['period_month']) ? month_name($inv['period_month']) . ' ' . $inv['period_year'] : fmt_date($inv['invoice_date'] ?? '') ?></td>
                            <td><?= money($inv['total_amount'] ?? 0) ?></td>
                            <td class="text-success"><?= money($inv['amount_paid'] ?? 0) ?></td>
                            <td class="<?= $bal > 0 ? 'text-danger fw-semibold' : 'text-success' ?>"><?= money($bal) ?></td>
                            <td><?= invoice_badge($inv['status']) ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">No invoices for this period.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-cash-coin me-1 text-success"></i>Payments</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Reference</th><th>Method</th><th>Date</th><th>Amount</th></tr></thead>
                    <tbody>
                    <?php if ($payments): foreach ($payments as $pay): ?>
                        <tr>
                            <td><code class="small"><?= e($pay['payment_ref'] ?? '—') ?></code></td>
                            <td class="small"><?= ucfirst($pay['payment_method'] ?? '') ?></td>
                            <td class="small"><?= fmt_date($pay['payment_date'] ?? '') ?></td>
                            <td class="fw-semibold text-success"><?= money($pay['amount'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No payments for this period.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
