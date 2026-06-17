<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$api = new ApiClient();
$id  = int_param('id');

// Handle void action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'void') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/invoices/view.php?id=' . $id); }
    $res = $api->post("invoices/$id/void", []);
    set_flash(!empty($res['success']) ? 'success' : 'error', $res['message'] ?? 'Action failed.');
    redirect(BASE_URL . '/invoices/view.php?id=' . $id);
}

// Handle apply-penalty action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'apply_penalty') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/invoices/view.php?id=' . $id); }
    $res = $api->post("invoices/$id/apply-penalty", []);
    set_flash(!empty($res['success']) ? 'success' : 'error', $res['message'] ?? 'Action failed.');
    redirect(BASE_URL . '/invoices/view.php?id=' . $id);
}

$res = $api->get("invoices/$id");
$inv = $res['data'] ?? null;
if (!$inv) { set_flash('error', 'Invoice not found.'); redirect(BASE_URL . '/invoices/index.php'); }

$payments = $inv['payments'] ?? [];
$balance  = $inv['total_amount'] - $inv['amount_paid'];

$page_title = 'Invoice ' . $inv['invoice_number'];
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3 gap-2">
    <a href="<?= BASE_URL ?>/invoices/index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0 flex-grow-1">Invoice <?= e($inv['invoice_number']) ?></h5>
    <?= invoice_badge($inv['status']) ?>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>
    <?php if (in_array($inv['status'],['sent','partial','overdue']) && is_manager()): ?>
    <a href="<?= BASE_URL ?>/payments/add.php?lease_id=<?= $inv['lease_id'] ?>&invoice_id=<?= $id ?>" class="btn btn-sm btn-success"><i class="bi bi-cash me-1"></i>Record Payment</a>
    <?php endif; ?>
    <?php if (!in_array($inv['status'], ['paid','cancelled','voided']) && is_manager()): ?>
    <form method="POST" style="display:inline" onsubmit="return confirm('Void this invoice? This cannot be undone.')">
        <?= csrf_field() ?><input type="hidden" name="action" value="void">
        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-slash-circle me-1"></i>Void</button>
    </form>
    <?php endif; ?>
    <?php if (in_array($inv['status'], ['unpaid','partial','overdue']) && is_manager()): ?>
    <form method="POST" style="display:inline" onsubmit="return confirm('Apply late penalty to this invoice?')">
        <?= csrf_field() ?><input type="hidden" name="action" value="apply_penalty">
        <button type="submit" class="btn btn-sm btn-outline-warning"><i class="bi bi-exclamation-triangle me-1"></i>Apply Penalty</button>
    </form>
    <?php endif; ?>
</div>

<div class="card shadow-sm" style="max-width:640px;margin:auto">
    <div class="card-body p-4">
        <div class="row mb-3">
            <div class="col-6">
                <h5 class="fw-bold mb-0"><i class="bi bi-building-fill text-warning me-1"></i><?= e(get_setting('company_name', APP_FULL_NAME)) ?></h5>
                <p class="text-muted small mb-0"><?= e(get_setting('company_address')) ?></p>
                <p class="text-muted small mb-0"><?= e(get_setting('company_phone')) ?></p>
            </div>
            <div class="col-6 text-end">
                <h5 class="fw-bold">INVOICE</h5>
                <p class="mb-0 small"><strong>#:</strong> <?= e($inv['invoice_number']) ?></p>
                <?php if (!empty($inv['period_month'])): ?><p class="mb-0 small"><strong>Period:</strong> <?= month_name($inv['period_month']) ?> <?= $inv['period_year'] ?></p><?php endif; ?>
                <p class="mb-0 small"><strong>Due:</strong> <?= fmt_date($inv['due_date']) ?></p>
            </div>
        </div>
        <hr>
        <div class="row mb-3">
            <div class="col-6">
                <p class="text-muted small mb-1">BILLED TO:</p>
                <p class="fw-bold mb-0"><?= e($inv['tenant_name'] ?? '—') ?></p>
                <p class="small mb-0"><?= e($inv['tenant_phone'] ?? '') ?></p>
                <p class="small mb-0"><?= e($inv['tenant_email'] ?? '') ?></p>
            </div>
            <div class="col-6">
                <p class="text-muted small mb-1">PROPERTY:</p>
                <p class="fw-bold mb-0"><?= e($inv['property_name'] ?? '—') ?></p>
                <p class="small mb-0">Unit: <?= e($inv['unit_number'] ?? '—') ?></p>
            </div>
        </div>
        <table class="table table-sm border">
            <thead class="table-dark"><tr><th>Description</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
                <tr><td>Monthly Rent</td><td class="text-end"><?= money($inv['rent_amount'] ?? 0) ?></td></tr>
                <?php if (!empty($inv['utility_amount']) && $inv['utility_amount'] > 0): ?><tr><td>Utilities</td><td class="text-end"><?= money($inv['utility_amount']) ?></td></tr><?php endif; ?>
                <?php if (!empty($inv['discount_amount']) && $inv['discount_amount'] > 0): ?><tr><td class="text-success">Discount</td><td class="text-end text-success">-<?= money($inv['discount_amount']) ?></td></tr><?php endif; ?>
                <?php if (!empty($inv['penalty_amount']) && $inv['penalty_amount'] > 0): ?><tr><td class="text-danger">Late Payment Penalty</td><td class="text-end text-danger"><?= money($inv['penalty_amount']) ?></td></tr><?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold"><td>Total</td><td class="text-end"><?= money($inv['total_amount']) ?></td></tr>
                <tr class="text-success"><td>Amount Paid</td><td class="text-end"><?= money($inv['amount_paid']) ?></td></tr>
                <tr class="<?= $balance > 0 ? 'text-danger fw-bold' : 'text-success fw-bold' ?>">
                    <td>Balance Due</td><td class="text-end"><?= money($balance) ?></td>
                </tr>
            </tfoot>
        </table>

        <?php if ($payments): ?>
        <h6 class="fw-semibold mt-3">Payment History</h6>
        <table class="table table-sm small">
            <thead class="table-light"><tr><th>Reference</th><th>Method</th><th>Date</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $pay): ?>
                <tr>
                    <td><code><?= e($pay['payment_ref'] ?? $pay['id']) ?></code></td>
                    <td><?= ucfirst(str_replace('_',' ',$pay['payment_method'] ?? '')) ?></td>
                    <td><?= fmt_date($pay['payment_date']) ?></td>
                    <td class="text-end"><?= money($pay['amount']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <p class="text-center text-muted small mt-3 mb-0">Thank you for your payment! | <?= APP_NAME ?></p>
    </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
