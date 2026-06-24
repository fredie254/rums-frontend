<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$api = new ApiClient();
$id  = int_param('id');
if (!$id) { redirect(BASE_URL . '/leases/index'); }

$res   = $api->get("leases/$id");
$lease = $res['data'] ?? null;
if (!$lease) { set_flash('error', 'Lease not found.'); redirect(BASE_URL . '/leases/index'); }

$invoices   = $lease['invoices']  ?? [];
$payments   = $lease['payments']  ?? [];
$documents  = $lease['documents'] ?? [];
$renewals   = $lease['renewals']  ?? [];
$total_paid = array_sum(array_column(array_filter($payments, fn($p) => ($p['status'] ?? '') === 'completed'), 'amount'));

$is_active  = ($lease['status'] === 'active');
$is_signed  = !empty($lease['signed_at']);

$page_title = 'Lease ' . $lease['lease_number'];
include BASE_PATH . '/includes/header.php';
?>
<!-- ── Header ─────────────────────────────────────────────── -->
<div class="d-flex align-items-center mb-3 gap-2 flex-wrap">
    <a href="<?= BASE_URL ?>/leases/index" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0 flex-grow-1">Lease — <code><?= e($lease['lease_number']) ?></code></h5>
    <?= lease_badge($lease['status']) ?>
    <?php if ($is_signed): ?>
        <span class="badge bg-success"><i class="bi bi-patch-check me-1"></i>Signed</span>
    <?php elseif ($is_active && is_manager()): ?>
        <span class="badge bg-warning text-dark"><i class="bi bi-pen me-1"></i>Unsigned</span>
    <?php endif; ?>

    <?php if ($is_active && is_manager()): ?>
    <a href="<?= BASE_URL ?>/payments/add?lease_id=<?= $id ?>" class="btn btn-sm btn-success"><i class="bi bi-cash me-1"></i>Payment</a>
    <a href="<?= BASE_URL ?>/invoices/generate?lease_id=<?= $id ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-receipt me-1"></i>Invoice</a>
    <a href="<?= BASE_URL ?>/leases/renew?id=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-clockwise me-1"></i>Renew</a>
    <a href="<?= BASE_URL ?>/leases/documents?lease_id=<?= $id ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-paperclip me-1"></i>Docs</a>
    <?php if (!$is_signed): ?>
    <button class="btn btn-sm btn-outline-success" onclick="markSigned(<?= $id ?>)"><i class="bi bi-patch-check me-1"></i>Mark Signed</button>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/leases/terminate?id=<?= $id ?>" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle me-1"></i>Terminate</a>
    <?php endif; ?>
</div>

<div class="row g-3">
    <!-- ── Left column ────────────────────────────────────── -->
    <div class="col-md-4">
        <!-- Lease Details -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-file-earmark-text me-1 text-primary"></i>Lease Details</div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-6 text-muted">Lease #</dt><dd class="col-6"><code><?= e($lease['lease_number']) ?></code></dd>
                    <dt class="col-6 text-muted">Type</dt><dd class="col-6"><?= ucfirst(str_replace('-',' ',$lease['lease_type'] ?? 'fixed-term')) ?></dd>
                    <dt class="col-6 text-muted">Property</dt><dd class="col-6"><?= e($lease['property_name'] ?? '—') ?></dd>
                    <dt class="col-6 text-muted">Unit</dt><dd class="col-6 fw-bold"><?= e($lease['unit_number'] ?? '—') ?> <small class="text-muted">(<?= strtoupper($lease['unit_type'] ?? '') ?>)</small></dd>
                    <dt class="col-6 text-muted">Start Date</dt><dd class="col-6"><?= fmt_date($lease['start_date']) ?></dd>
                    <dt class="col-6 text-muted">End Date</dt><dd class="col-6"><?= fmt_date($lease['end_date']) ?></dd>
                    <?php if (!empty($lease['days_remaining'])): ?>
                    <dt class="col-6 text-muted">Days Left</dt>
                    <dd class="col-6">
                        <?php $dr = (int)$lease['days_remaining']; ?>
                        <span class="badge bg-<?= $dr < 30 ? 'danger' : ($dr < 60 ? 'warning text-dark' : 'success') ?>">
                            <?= $dr ?> days
                        </span>
                    </dd>
                    <?php endif; ?>
                    <dt class="col-6 text-muted">Rent Due</dt><dd class="col-6">Day <?= $lease['payment_day'] ?? 1 ?> monthly</dd>
                    <dt class="col-6 text-muted">Monthly Rent</dt><dd class="col-6 fw-bold text-primary"><?= money($lease['monthly_rent'] ?? 0) ?></dd>
                    <dt class="col-6 text-muted">Deposit</dt><dd class="col-6"><?= money($lease['deposit_amount'] ?? 0) ?></dd>
                    <dt class="col-6 text-muted">Total Paid</dt><dd class="col-6 fw-bold text-success"><?= money($total_paid) ?></dd>
                    <?php if (($lease['notice_period_days'] ?? 30) !== 30): ?>
                    <dt class="col-6 text-muted">Notice Period</dt><dd class="col-6"><?= $lease['notice_period_days'] ?> days</dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <!-- Escalation -->
        <?php if (!empty($lease['escalation_type']) && $lease['escalation_type'] !== 'none'): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-graph-up-arrow me-1 text-warning"></i>Rent Escalation</div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-6 text-muted">Type</dt>
                    <dd class="col-6"><?= ucfirst($lease['escalation_type']) ?></dd>
                    <dt class="col-6 text-muted">Rate</dt>
                    <dd class="col-6">
                        <?= $lease['escalation_type'] === 'fixed'
                            ? money($lease['escalation_rate'] ?? 0)
                            : number_format($lease['escalation_rate'] ?? 0, 2) . '%' ?>
                    </dd>
                    <dt class="col-6 text-muted">Frequency</dt>
                    <dd class="col-6"><?= ucfirst($lease['escalation_frequency'] ?? 'annually') ?></dd>
                    <?php if (!empty($lease['next_escalation_date'])): ?>
                    <dt class="col-6 text-muted">Next</dt>
                    <dd class="col-6"><?= fmt_date($lease['next_escalation_date']) ?></dd>
                    <?php endif; ?>
                </dl>
                <?php if ($is_active && is_manager()): ?>
                <button class="btn btn-sm btn-outline-warning w-100 mt-2" onclick="applyEscalation(<?= $id ?>)">
                    <i class="bi bi-arrow-up-circle me-1"></i>Apply Escalation Now
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tenant -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-person me-1 text-success"></i>Tenant</div>
            <div class="card-body small">
                <p class="fw-bold mb-1"><?= e($lease['tenant_name'] ?? '—') ?></p>
                <?php if (!empty($lease['tenant_email'])): ?><p class="mb-1"><i class="bi bi-envelope me-1 text-muted"></i><?= e($lease['tenant_email']) ?></p><?php endif; ?>
                <?php if (!empty($lease['tenant_phone'])): ?><p class="mb-1"><i class="bi bi-phone me-1 text-muted"></i><?= e($lease['tenant_phone']) ?></p><?php endif; ?>
                <?php if (!empty($lease['tenant_id'])): ?>
                <a href="<?= BASE_URL ?>/tenants/view?id=<?= $lease['tenant_id'] ?>" class="btn btn-sm btn-outline-primary w-100">View Profile</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Signature -->
        <?php if ($is_signed): ?>
        <div class="card shadow-sm mb-3 border-success">
            <div class="card-body small text-success">
                <i class="bi bi-patch-check-fill me-1"></i>
                Signed <?= fmt_date($lease['signed_at']) ?>
                <?php if (!empty($lease['signed_by_name'])): ?>by <?= e($lease['signed_by_name']) ?><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Documents -->
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold small"><i class="bi bi-paperclip me-1 text-secondary"></i>Documents</span>
                <?php if (is_manager()): ?>
                <a href="<?= BASE_URL ?>/leases/documents?lease_id=<?= $id ?>" class="btn btn-xs btn-sm btn-outline-secondary">Manage</a>
                <?php endif; ?>
            </div>
            <?php if ($documents): ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($documents as $doc): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center py-1 px-3 small">
                    <span>
                        <i class="bi bi-<?= str_contains($doc['mime_type'] ?? '', 'pdf') ? 'file-earmark-pdf text-danger' : 'file-earmark-image text-info' ?> me-1"></i>
                        <?= e($doc['original_name']) ?>
                        <span class="badge bg-light text-dark ms-1"><?= e($doc['document_type']) ?></span>
                    </span>
                    <a href="<?= BASE_URL ?>/uploads/<?= e($doc['file_path']) ?>" target="_blank" class="btn btn-xs btn-sm btn-outline-secondary py-0 px-1"><i class="bi bi-download"></i></a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <div class="card-body small text-muted text-center py-2">No documents attached.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Right column ───────────────────────────────────── -->
    <div class="col-md-8">
        <!-- Terms -->
        <?php if (!empty($lease['terms'])): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-journal-text me-1"></i>Terms & Notes</div>
            <div class="card-body small" style="white-space:pre-wrap"><?= e($lease['terms']) ?></div>
        </div>
        <?php endif; ?>

        <!-- Renewal History -->
        <?php if ($renewals): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-arrow-clockwise me-1 text-primary"></i>Renewal History</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Date</th><th>Old End</th><th>New End</th><th>Old Rent</th><th>New Rent</th><th>By</th></tr></thead>
                    <tbody>
                    <?php foreach ($renewals as $r): ?>
                    <tr>
                        <td><?= fmt_date($r['created_at']) ?></td>
                        <td><?= fmt_date($r['old_end_date']) ?></td>
                        <td class="fw-semibold"><?= fmt_date($r['new_end_date']) ?></td>
                        <td><?= money($r['old_monthly_rent']) ?></td>
                        <td class="text-primary fw-semibold"><?= money($r['new_monthly_rent']) ?></td>
                        <td><?= e($r['initiated_by_name'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Invoices -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold small"><i class="bi bi-receipt me-1 text-info"></i>Invoices</h6>
                <a href="<?= BASE_URL ?>/invoices/index?lease_id=<?= $id ?>" class="btn btn-xs btn-sm btn-outline-secondary">All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Invoice #</th><th>Period</th><th>Total</th><th>Paid</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php if ($invoices): foreach ($invoices as $inv): ?>
                        <tr>
                            <td><code><?= e($inv['invoice_number'] ?? '') ?></code></td>
                            <td><?= !empty($inv['invoice_date']) ? date('M Y', strtotime($inv['invoice_date'])) : '—' ?></td>
                            <td><?= money($inv['total_amount']) ?></td>
                            <td><?= money($inv['amount_paid']) ?></td>
                            <td><?= invoice_badge($inv['status']) ?></td>
                            <td><a href="<?= BASE_URL ?>/invoices/view?id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-1"><i class="bi bi-eye"></i></a></td>
                        </tr>
                    <?php endforeach; else: ?><tr><td colspan="6" class="text-center text-muted py-2">No invoices.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payments -->
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-cash-coin me-1 text-success"></i>Payments</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Reference</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if ($payments): foreach ($payments as $pay): ?>
                        <tr>
                            <td><code class="small"><?= e($pay['payment_ref'] ?? '—') ?></code></td>
                            <td class="fw-semibold"><?= money($pay['amount']) ?></td>
                            <td><?= ucfirst($pay['payment_method'] ?? '') ?></td>
                            <td><?= fmt_date($pay['payment_date']) ?></td>
                            <td><?= payment_badge($pay['status']) ?></td>
                        </tr>
                    <?php endforeach; else: ?><tr><td colspan="5" class="text-center text-muted py-2">No payments.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function markSigned(id) {
    if (!confirm('Mark this lease as signed?')) return;
    fetch(`<?= rtrim(env('APP_URL',''), '/') ?>/api/v1/leases/${id}/sign`, {
        method: 'POST',
        headers: { 'Authorization': 'Bearer <?= $_SESSION['api_token'] ?? '' ?>', 'Content-Type': 'application/json' },
        body: JSON.stringify({})
    })
    .then(r => r.json())
    .then(res => { if (res.success) location.reload(); else alert(res.message || 'Failed.'); });
}

function applyEscalation(id) {
    if (!confirm('Apply rent escalation now? This will increase the monthly rent immediately.')) return;
    fetch(`<?= rtrim(env('APP_URL',''), '/') ?>/api/v1/leases/${id}/apply-escalation`, {
        method: 'POST',
        headers: { 'Authorization': 'Bearer <?= $_SESSION['api_token'] ?? '' ?>', 'Content-Type': 'application/json' },
        body: JSON.stringify({})
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert(res.message || 'Escalation applied.');
            location.reload();
        } else {
            alert(res.message || 'Failed.');
        }
    });
}
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
