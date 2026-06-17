<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$api = new ApiClient();
$id  = int_param('id');
if (!$id) { redirect(BASE_URL . '/payments/index.php'); }

// Handle reverse action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'reverse') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/payments/view.php?id=' . $id); }
    $res = $api->post("payments/$id/reverse", []);
    set_flash(!empty($res['success']) ? 'success' : 'error', $res['message'] ?? 'Action failed.');
    redirect(BASE_URL . '/payments/view.php?id=' . $id);
}

$res = $api->get("payments/$id");
$pay = $res['data'] ?? null;
if (!$pay) { set_flash('error', 'Payment not found.'); redirect(BASE_URL . '/payments/index.php'); }

// Decrypt phone if set
if (!empty($pay['tenant_phone'])) {
    $pay['tenant_phone'] = $pay['tenant_phone']; // already decrypted by API
}

$page_title = 'Receipt — ' . ($pay['payment_ref'] ?? $id);
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3 gap-2">
    <a href="<?= BASE_URL ?>/payments/index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0 flex-grow-1">Payment Receipt</h5>
    <?= payment_badge($pay['status'] ?? 'completed') ?>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary d-print-none"><i class="bi bi-printer me-1"></i>Print</button>
    <?php if (($pay['status'] ?? '') === 'completed' && is_manager()): ?>
    <form method="POST" class="d-print-none" style="display:inline"
          onsubmit="return confirm('Reverse this payment? This will update the linked invoice balance.')">
        <?= csrf_field() ?><input type="hidden" name="action" value="reverse">
        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-arrow-counterclockwise me-1"></i>Reverse</button>
    </form>
    <?php endif; ?>
</div>

<?php flash_messages(); ?>

<div class="card shadow-sm" style="max-width:600px;margin:auto" id="receiptCard">
    <div class="card-body p-4">

        <!-- Header -->
        <div class="row mb-3">
            <div class="col-7">
                <div class="fw-bold fs-6"><i class="bi bi-building-fill text-warning me-1"></i><?= e(get_setting('company_name', APP_FULL_NAME)) ?></div>
                <div class="text-muted small"><?= e(get_setting('company_address')) ?></div>
                <div class="text-muted small"><?= e(get_setting('company_phone')) ?></div>
                <div class="text-muted small"><?= e(get_setting('company_email')) ?></div>
            </div>
            <div class="col-5 text-end">
                <div class="fw-bold text-uppercase small text-muted">Payment Receipt</div>
                <div class="fs-6 fw-bold font-monospace"><?= e($pay['payment_ref'] ?? '—') ?></div>
                <div class="small text-muted"><?= fmt_date($pay['payment_date'], 'd F Y') ?></div>
                <div class="mt-1"><?= payment_badge($pay['status'] ?? 'completed') ?></div>
            </div>
        </div>

        <hr class="my-2">

        <!-- Billed to + Property -->
        <div class="row mb-3">
            <div class="col-6">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Received From</div>
                <div class="fw-semibold"><?= e($pay['tenant_name'] ?? '—') ?></div>
                <?php if (!empty($pay['tenant_phone'])): ?>
                <div class="small text-muted"><?= e($pay['tenant_phone']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-6">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Property / Unit</div>
                <div class="fw-semibold"><?= e($pay['property_name'] ?? '—') ?></div>
                <div class="small text-muted">Unit <?= e($pay['unit_number'] ?? '—') ?></div>
            </div>
        </div>

        <!-- Amount -->
        <div class="bg-success bg-opacity-10 rounded p-3 mb-3 d-flex justify-content-between align-items-center">
            <div>
                <div class="text-muted small">Amount Paid</div>
                <div class="text-success fw-semibold small"><?= ucfirst(str_replace('_',' ',$pay['payment_type'] ?? '')) ?></div>
            </div>
            <div class="fs-3 fw-bold <?= ($pay['status'] ?? '') === 'reversed' ? 'text-muted text-decoration-line-through' : 'text-success' ?>">
                <?= money($pay['amount']) ?>
            </div>
        </div>

        <!-- Details -->
        <dl class="row small mb-2">
            <dt class="col-5 text-muted">Payment Method</dt>
            <dd class="col-7"><?= ucfirst(str_replace('_',' ',$pay['payment_method'] ?? '')) ?></dd>

            <?php if (!empty($pay['mpesa_transaction_id'])): ?>
            <dt class="col-5 text-muted">M-Pesa Code</dt>
            <dd class="col-7"><code><?= e($pay['mpesa_transaction_id']) ?></code></dd>
            <?php endif; ?>

            <?php if (!empty($pay['mpesa_receipt'])): ?>
            <dt class="col-5 text-muted">M-Pesa Receipt</dt>
            <dd class="col-7"><code><?= e($pay['mpesa_receipt']) ?></code></dd>
            <?php endif; ?>

            <?php if (!empty($pay['cheque_number'])): ?>
            <dt class="col-5 text-muted">Cheque / Ref No.</dt>
            <dd class="col-7"><code><?= e($pay['cheque_number']) ?></code></dd>
            <?php endif; ?>

            <?php if (!empty($pay['invoice_number'])): ?>
            <dt class="col-5 text-muted">Invoice</dt>
            <dd class="col-7">
                <a href="<?= BASE_URL ?>/invoices/view.php?id=<?= $pay['invoice_id'] ?>" class="text-decoration-none d-print-none">
                    <code><?= e($pay['invoice_number']) ?></code>
                </a>
                <span class="d-none d-print-inline"><code><?= e($pay['invoice_number']) ?></code></span>
                <?php if (!empty($pay['invoice_total'])): ?>
                <small class="text-muted ms-1">(total: <?= money($pay['invoice_total']) ?>)</small>
                <?php endif; ?>
            </dd>
            <?php endif; ?>

            <?php if (!empty($pay['notes'])): ?>
            <dt class="col-5 text-muted">Notes</dt>
            <dd class="col-7"><?= e($pay['notes']) ?></dd>
            <?php endif; ?>
        </dl>

        <hr class="my-2">
        <p class="text-center text-muted small mb-0">
            Generated <?= date('d M Y H:i') ?> &middot; <?= APP_NAME ?>
            <?php if (!empty(get_setting('company_kra_pin'))): ?>
            &middot; KRA PIN: <?= e(get_setting('company_kra_pin')) ?>
            <?php endif; ?>
        </p>
        <?php if (!empty(get_setting('payment_terms'))): ?>
        <p class="text-center text-muted" style="font-size:.7rem"><?= e(get_setting('payment_terms')) ?></p>
        <?php endif; ?>
    </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
