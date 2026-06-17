<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'landlord');

$api = new ApiClient();
$id  = int_param('id');
if (!$id) { redirect(BASE_URL . '/tenants/index.php'); }

$res    = $api->get("tenants/$id");
$tenant = $res['data'] ?? null;
if (!$tenant) { set_flash('error', 'Tenant not found.'); redirect(BASE_URL . '/tenants/index.php'); }

$full_name = $tenant['full_name'] ?? trim(($tenant['first_name'] ?? '') . ' ' . ($tenant['last_name'] ?? ''));

// Fetch leases, invoices, payments in parallel (sequential in PHP)
$leases_res  = $api->get('leases', ['tenant_id' => $id, 'status' => 'all', 'per_page' => 20]);
$leases      = $leases_res['data'] ?? [];
$inv_res     = $api->get('invoices', ['tenant_id' => $id, 'per_page' => 5]);
$invoices    = $inv_res['data'] ?? [];
$pay_res     = $api->get('payments', ['tenant_id' => $id, 'per_page' => 10]);
$payments    = $pay_res['data'] ?? [];

$page_title = 'Tenant — ' . $full_name;
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3 gap-2">
    <a href="<?= BASE_URL ?>/tenants/index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0 flex-grow-1"><?= e($full_name) ?></h5>
    <a href="<?= BASE_URL ?>/tenants/statement.php?id=<?= $id ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-file-earmark-bar-graph me-1"></i>Statement</a>
    <?php if (is_manager()): ?>
    <a href="<?= BASE_URL ?>/tenants/documents.php?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-folder2-open me-1"></i>Documents</a>
    <a href="<?= BASE_URL ?>/tenants/edit.php?id=<?= $id ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-pencil me-1"></i>Edit</a>
    <a href="<?= BASE_URL ?>/leases/add.php?tenant_id=<?= $id ?>" class="btn btn-sm btn-primary"><i class="bi bi-file-earmark-plus me-1"></i>New Lease</a>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card shadow-sm mb-3">
            <div class="card-body text-center">
                <div class="avatar-lg bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center fw-bold fs-3 mb-3">
                    <?= strtoupper(substr($full_name, 0, 1)) ?>
                </div>
                <h6 class="fw-bold mb-1"><?= e($full_name) ?></h6>
                <p class="text-muted small mb-1"><?= e($tenant['email']) ?></p>
                <p class="text-muted small mb-2"><?= e($tenant['phone']) ?></p>
                <?php
                $st = $tenant['status'] ?? '';
                $stClass = match($st) { 'active' => 'bg-success', 'blacklisted' => 'bg-danger', default => 'bg-secondary' };
                ?>
                <span class="badge <?= $stClass ?>"><?= ucfirst($st ?: 'inactive') ?></span>
            </div>
        </div>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-person-vcard me-1 text-primary"></i>Profile</div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted">ID Type</dt><dd class="col-7"><?= ucwords(str_replace('_', ' ', $tenant['id_type'] ?? 'national_id')) ?></dd>
                    <dt class="col-5 text-muted">ID Number</dt><dd class="col-7"><code><?= e($tenant['id_number'] ?? '—') ?></code></dd>
                    <dt class="col-5 text-muted">Date of Birth</dt><dd class="col-7"><?= !empty($tenant['dob']) ? fmt_date($tenant['dob']) : '—' ?></dd>
                    <dt class="col-5 text-muted">Gender</dt><dd class="col-7"><?= ucfirst($tenant['gender'] ?? '—') ?></dd>
                    <dt class="col-5 text-muted">Occupation</dt><dd class="col-7"><?= e($tenant['occupation'] ?? '—') ?></dd>
                    <dt class="col-5 text-muted">Employer</dt><dd class="col-7"><?= e($tenant['employer'] ?? '—') ?></dd>
                    <dt class="col-5 text-muted">Income</dt><dd class="col-7"><?= !empty($tenant['monthly_income']) ? money($tenant['monthly_income']) : '—' ?></dd>
                    <?php if (!empty($tenant['outstanding_balance'])): ?><dt class="col-5 text-muted">Outstanding</dt><dd class="col-7 text-danger fw-semibold"><?= money($tenant['outstanding_balance']) ?></dd><?php endif; ?>
                </dl>
            </div>
        </div>
        <?php if (!empty($tenant['emergency_contact_name'])): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-person-exclamation me-1 text-danger"></i>Emergency Contact</div>
            <div class="card-body small">
                <p class="fw-semibold mb-0"><?= e($tenant['emergency_contact_name']) ?></p>
                <p class="text-muted mb-0"><?= e($tenant['emergency_contact_phone'] ?? '') ?></p>
                <p class="text-muted mb-0"><?= e($tenant['emergency_contact_relation'] ?? '') ?></p>
            </div>
        </div>
        <?php endif; ?>
        <?php
        $kyc_res  = $api->get("tenants/$id/kyc-documents");
        $kyc_docs = $kyc_res['data'] ?? [];
        ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small d-flex align-items-center">
                <span class="flex-grow-1"><i class="bi bi-folder2-open me-1 text-primary"></i>KYC Documents</span>
                <span class="badge bg-primary-subtle text-primary"><?= count($kyc_docs) ?></span>
            </div>
            <?php if ($kyc_docs): ?>
            <ul class="list-group list-group-flush">
                <?php foreach (array_slice($kyc_docs, 0, 4) as $doc):
                    $ext   = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                    $icon  = $ext === 'pdf' ? 'bi-file-earmark-pdf text-danger' : 'bi-file-earmark-image text-primary';
                    $label = ucwords(str_replace('_', ' ', $doc['document_type']));
                ?>
                <li class="list-group-item d-flex align-items-center gap-2 py-2 small">
                    <i class="bi <?= $icon ?>"></i>
                    <span class="flex-grow-1 text-truncate"><?= e($label) ?></span>
                    <a href="<?= BASE_URL ?>/assets/uploads/<?= e($doc['file_path']) ?>" target="_blank" class="btn btn-xs btn-outline-secondary py-0 px-1"><i class="bi bi-eye"></i></a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <div class="card-body small text-muted text-center py-2">No documents.</div>
            <?php endif; ?>
            <?php if (is_manager()): ?>
            <div class="card-footer bg-white py-2">
                <a href="<?= BASE_URL ?>/tenants/documents.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-upload me-1"></i>Manage Documents
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-8">
        <!-- Leases -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-file-earmark-text me-1 text-primary"></i>Lease History</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Lease #</th><th>Property/Unit</th><th>Start</th><th>End</th><th>Rent</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if ($leases): foreach ($leases as $l): ?>
                        <tr>
                            <td><a href="<?= BASE_URL ?>/leases/view.php?id=<?= $l['id'] ?>"><code><?= e($l['lease_number']) ?></code></a></td>
                            <td><?= e($l['property_name'] ?? '') ?> / <?= e($l['unit_number'] ?? '') ?></td>
                            <td><?= fmt_date($l['start_date']) ?></td>
                            <td><?= fmt_date($l['end_date']) ?></td>
                            <td><?= money($l['monthly_rent'] ?? $l['rent_amount'] ?? 0) ?></td>
                            <td><?= lease_badge($l['status']) ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">No leases.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Invoices -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-receipt me-1 text-info"></i>Recent Invoices</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Invoice #</th><th>Unit</th><th>Period</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if ($invoices): foreach ($invoices as $inv): ?>
                        <tr>
                            <td><a href="<?= BASE_URL ?>/invoices/view.php?id=<?= $inv['id'] ?>"><code><?= e($inv['invoice_number']) ?></code></a></td>
                            <td><?= e($inv['unit_number'] ?? '—') ?></td>
                            <td><?= !empty($inv['period_month']) ? month_name($inv['period_month']) . ' ' . $inv['period_year'] : '—' ?></td>
                            <td><?= money($inv['total_amount']) ?></td>
                            <td><?= invoice_badge($inv['status']) ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-2">No invoices.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Payments -->
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-cash-coin me-1 text-success"></i>Payment History</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Reference</th><th>Unit</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if ($payments): foreach ($payments as $pay): ?>
                        <tr>
                            <td><code class="small"><?= e($pay['payment_ref'] ?? $pay['payment_reference'] ?? '—') ?></code></td>
                            <td><?= e($pay['unit_number'] ?? '—') ?></td>
                            <td class="fw-semibold"><?= money($pay['amount']) ?></td>
                            <td><?= ucfirst($pay['payment_method'] ?? '') ?></td>
                            <td><?= fmt_date($pay['payment_date']) ?></td>
                            <td><?= payment_badge($pay['status']) ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-2">No payments.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
