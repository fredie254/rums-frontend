<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$api = new ApiClient();
$id  = int_param('id');
if (!$id) { redirect(BASE_URL . '/units/index.php'); }

$res  = $api->get("units/$id");
$unit = $res['data'] ?? null;
if (!$unit) { set_flash('error', 'Unit not found.'); redirect(BASE_URL . '/units/index.php'); }

// Active lease
$leaseRes = $api->get('leases', ['unit_id' => $id, 'status' => 'active', 'per_page' => 1]);
$lease    = $leaseRes['data'][0] ?? null;

// Recent payments — via active lease if available
$payments = [];
if ($lease) {
    $payRes   = $api->get('payments', ['lease_id' => $lease['id'], 'per_page' => 6]);
    $payments = $payRes['data'] ?? [];
}

// Recent maintenance
$maintRes     = $api->get('maintenance', ['unit_id' => $id, 'per_page' => 5]);
$maintenances = $maintRes['data'] ?? [];

$page_title = 'Unit ' . $unit['unit_number'];
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3 gap-2">
    <a href="<?= BASE_URL ?>/units/index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0 flex-grow-1">Unit <?= e($unit['unit_number']) ?> — <?= e($unit['property_name'] ?? '') ?></h5>
    <?= unit_badge($unit['status']) ?>
    <?php if (is_manager()): ?>
    <a href="<?= BASE_URL ?>/units/edit.php?id=<?= $unit['id'] ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-pencil me-1"></i>Edit</a>
    <?php if ($unit['status'] === 'available'): ?>
    <a href="<?= BASE_URL ?>/leases/add.php?unit_id=<?= $unit['id'] ?>" class="btn btn-sm btn-success"><i class="bi bi-person-check me-1"></i>Assign Tenant</a>
    <?php endif; ?>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1 text-primary"></i>Unit Details</div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted">Type</dt><dd class="col-7 fw-semibold"><?= strtoupper($unit['unit_type']) ?></dd>
                    <dt class="col-5 text-muted">Floor</dt><dd class="col-7"><?= $unit['floor'] ?: '—' ?></dd>
                    <dt class="col-5 text-muted">Bedrooms</dt><dd class="col-7"><?= $unit['bedrooms'] ?></dd>
                    <dt class="col-5 text-muted">Bathrooms</dt><dd class="col-7"><?= $unit['bathrooms'] ?></dd>
                    <dt class="col-5 text-muted">Size</dt><dd class="col-7"><?= $unit['size_sqft'] ? $unit['size_sqft'] . ' sq ft' : '—' ?></dd>
                    <dt class="col-5 text-muted">Monthly Rent</dt><dd class="col-7 fw-bold text-primary"><?= money($unit['rent_amount']) ?></dd>
                    <dt class="col-5 text-muted">Deposit</dt><dd class="col-7"><?= money($unit['deposit_amount']) ?></dd>
                    <dt class="col-5 text-muted">Water</dt><dd class="col-7"><?= $unit['water_included'] ? '<span class="badge bg-success">Included</span>' : '<span class="badge bg-light text-dark border">Extra</span>' ?></dd>
                    <dt class="col-5 text-muted">Electricity</dt><dd class="col-7"><?= $unit['electricity_included'] ? '<span class="badge bg-success">Included</span>' : '<span class="badge bg-light text-dark border">Extra</span>' ?></dd>
                    <?php if ($unit['amenities']): ?><dt class="col-5 text-muted">Amenities</dt><dd class="col-7"><?= e($unit['amenities']) ?></dd><?php endif; ?>
                </dl>
            </div>
        </div>
        <?php if ($lease): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-person-fill me-1 text-success"></i>Current Tenant</div>
            <div class="card-body small">
                <p class="fw-bold mb-1"><?= e($lease['tenant_name']) ?></p>
                <p class="text-muted mb-1"><i class="bi bi-phone me-1"></i><?= e($lease['tenant_phone'] ?? '') ?></p>
                <p class="mb-1">Lease: <?= fmt_date($lease['start_date']) ?> → <?= fmt_date($lease['end_date']) ?></p>
                <p class="mb-2"><?= lease_badge($lease['status']) ?></p>
                <a href="<?= BASE_URL ?>/leases/view.php?id=<?= $lease['id'] ?>" class="btn btn-sm btn-outline-primary w-100">View Lease</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="col-md-8">
        <!-- Payments -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-cash-coin me-1 text-success"></i>Recent Payments</h6>
                <?php if ($lease): ?>
                <a href="<?= BASE_URL ?>/payments/index.php?lease_id=<?= $lease['id'] ?>" class="btn btn-sm btn-outline-secondary btn-xs">All</a>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Ref</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if ($payments): foreach ($payments as $pay): ?>
                        <tr>
                            <td><code class="small"><?= e($pay['payment_ref']) ?></code></td>
                            <td class="fw-semibold"><?= money($pay['amount']) ?></td>
                            <td><?= ucfirst($pay['payment_method']) ?></td>
                            <td><?= fmt_date($pay['payment_date']) ?></td>
                            <td><?= payment_badge($pay['status']) ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No payments.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Maintenance -->
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-wrench me-1 text-warning"></i>Maintenance Requests</h6>
                <a href="<?= BASE_URL ?>/maintenance/add.php?unit_id=<?= $unit['id'] ?>" class="btn btn-sm btn-outline-warning btn-xs">+ New</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Title</th><th>Category</th><th>Priority</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php if ($maintenances): foreach ($maintenances as $m): ?>
                        <tr>
                            <td><a href="<?= BASE_URL ?>/maintenance/view.php?id=<?= $m['id'] ?>"><?= e($m['issue_title']) ?></a></td>
                            <td><?= ucfirst($m['category'] ?? '') ?></td>
                            <td><?= priority_badge($m['priority']) ?></td>
                            <td><?= maintenance_badge($m['status']) ?></td>
                            <td><?= fmt_date($m['created_at']) ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No maintenance requests.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
