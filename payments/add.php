<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api = new ApiClient();
$errors = [];
$pre_lease_id = int_param('lease_id');

// Active leases for dropdown
$leases_res = $api->get('leases', ['status' => 'active', 'per_page' => 500]);
$leases     = $leases_res['data'] ?? [];

// Pending invoices (sent + partial + overdue — three calls since API filters by single status)
$inv1     = $api->get('invoices', ['status' => 'sent',    'per_page' => 500]);
$inv2     = $api->get('invoices', ['status' => 'partial', 'per_page' => 500]);
$inv3     = $api->get('invoices', ['status' => 'overdue', 'per_page' => 500]);
$invoices = array_merge($inv1['data'] ?? [], $inv2['data'] ?? [], $inv3['data'] ?? []);
usort($invoices, fn($a, $b) => strcmp($a['due_date'] ?? '', $b['due_date'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/payments/add.php'); }

    $lease_id   = int_param('lease_id', 0, 'post');
    $invoice_id = int_param('invoice_id', 0, 'post') ?: null;
    $amount     = (float)post('amount');
    $pay_date   = post('payment_date') ?: date('Y-m-d');
    $pay_type   = post('payment_type');
    $pay_method = post('payment_method');
    $mpesa_code = trim(post('mpesa_transaction_id'));
    $cheque_no  = post('cheque_number');
    $notes      = post('notes');

    if (!$lease_id)   $errors[] = 'Lease is required.';
    if ($amount <= 0) $errors[] = 'Amount must be greater than 0.';
    if (!$pay_date)   $errors[] = 'Payment date is required.';

    if (!$errors) {
        $payload = array_filter([
            'lease_id'           => $lease_id,
            'invoice_id'         => $invoice_id,
            'amount'             => $amount,
            'payment_date'       => $pay_date,
            'payment_type'       => $pay_type,
            'payment_method'     => $pay_method,
            'mpesa_transaction_id' => $mpesa_code ?: null,
            'cheque_number'      => $cheque_no ?: null,
            'notes'              => $notes ?: null,
        ], fn($v) => $v !== null && $v !== '');

        $res = $api->post('payments', $payload);
        if (!empty($res['success'])) {
            $pay_id = $res['data']['id'] ?? 0;
            $ref    = $res['data']['payment_ref'] ?? '';
            set_flash('success', "Payment $ref recorded successfully.");
            redirect(BASE_URL . '/payments/view.php?id=' . $pay_id);
        }
        $errors[] = $res['message'] ?? 'Failed to record payment.';
    }
}

$page_title = 'Record Payment';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3">
    <a href="<?= BASE_URL ?>/payments/index.php" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0">Record Payment</h5>
</div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="card shadow-sm"><div class="card-body">
    <form method="POST">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Lease (Tenant / Unit) *</label>
                <select name="lease_id" class="form-select" required>
                    <option value="">— Select Active Lease —</option>
                    <?php foreach ($leases as $l): ?>
                    <option value="<?= $l['id'] ?>" <?= ($pre_lease_id == $l['id'] || int_param('lease_id',0,'post') == $l['id']) ? 'selected' : '' ?>>
                        <?= e($l['tenant_name'] ?? '') ?> — <?= e($l['property_name'] ?? '') ?>/<?= e($l['unit_number'] ?? '') ?> [<?= e($l['lease_number']) ?>]
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Link to Invoice (optional)</label>
                <select name="invoice_id" class="form-select" id="invoiceSelect">
                    <option value="">— None —</option>
                    <?php foreach ($invoices as $inv): ?>
                    <option value="<?= $inv['id'] ?>" data-balance="<?= $inv['balance'] ?? ($inv['total_amount'] - $inv['amount_paid']) ?>">
                        <?= e($inv['tenant_name'] ?? '') ?> — <?= e($inv['invoice_number']) ?> (<?= e($inv['unit_number'] ?? '') ?>) <?= get_setting('currency_symbol', CURRENCY_SYMBOL) ?> <?= number_format($inv['balance'] ?? ($inv['total_amount'] - $inv['amount_paid']), 2) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Amount (<?= get_setting('currency_symbol', CURRENCY_SYMBOL) ?>) *</label>
                <input type="number" step="0.01" name="amount" id="payAmount" class="form-control" value="<?= e(post('amount')) ?>" required min="1">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Payment Date *</label>
                <input type="date" name="payment_date" class="form-control" value="<?= e(post('payment_date') ?: date('Y-m-d')) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Payment Type</label>
                <select name="payment_type" class="form-select">
                    <?php foreach (['rent','deposit','water','electricity','maintenance','penalty','other'] as $pt): ?>
                    <option value="<?= $pt ?>" <?= post('payment_type')===$pt?'selected':'' ?>><?= ucfirst($pt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Payment Method</label>
                <select name="payment_method" class="form-select" id="payMethod" onchange="toggleMpesa(this.value)">
                    <?php foreach (['cash','mpesa','bank_transfer','cheque','card'] as $pm): ?>
                    <option value="<?= $pm ?>" <?= post('payment_method')===$pm?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$pm)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4" id="mpesaField" style="display:none">
                <label class="form-label fw-semibold">M-Pesa Transaction Code</label>
                <input type="text" name="mpesa_transaction_id" class="form-control" value="<?= e(post('mpesa_transaction_id')) ?>" placeholder="e.g. QHX7K12MNP" style="text-transform:uppercase">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Cheque / Reference No.</label>
                <input type="text" name="cheque_number" class="form-control" value="<?= e(post('cheque_number')) ?>">
            </div>
            <div class="col-12"><label class="form-label fw-semibold">Notes</label><textarea name="notes" class="form-control" rows="2"><?= e(post('notes')) ?></textarea></div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Record Payment</button>
                <a href="<?= BASE_URL ?>/payments/index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </form>
</div></div>
<script>
function toggleMpesa(method) {
    document.getElementById('mpesaField').style.display = method === 'mpesa' ? 'block' : 'none';
}
toggleMpesa(document.getElementById('payMethod').value);
document.getElementById('invoiceSelect').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (opt.dataset.balance) document.getElementById('payAmount').value = opt.dataset.balance;
});
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
