<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'accountant');

$api    = new ApiClient();
$propId = int_param('property_id');

$qs   = $propId ? "?property_id=$propId" : '';
$res  = $api->get("reports/deposits$qs");
$data = $res['data'] ?? null;

$props = $api->get('properties?per_page=100')['data'] ?? [];

$page_title = 'Deposit Management';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/reports/index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
        <h5 class="fw-bold mb-0"><i class="bi bi-safe me-2 text-primary"></i>Deposit Management</h5>
    </div>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>
</div>

<!-- Filter -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold small mb-1">Property</label>
                <select name="property_id" class="form-select form-select-sm">
                    <option value="">All Properties</option>
                    <?php foreach ($props as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $propId == $p['id'] ? 'selected':'' ?>><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

<?php if ($data): ?>
<?php $summary = $data['summary']; $rows = $data['rows']; ?>

<!-- KPI cards -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="text-muted small">Expected</div>
            <div class="fs-6 fw-bold"><?= money($summary['total_expected']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="text-muted small">Collected</div>
            <div class="fs-6 fw-bold text-success"><?= money($summary['total_collected']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="text-muted small">Still Held</div>
            <div class="fs-6 fw-bold text-primary"><?= money($summary['total_held']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="text-muted small">Refunded</div>
            <div class="fs-6 fw-bold text-warning"><?= money($summary['total_refunded']) ?></div>
        </div>
    </div>
</div>
<?php if ($summary['total_outstanding'] > 0): ?>
<div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-1"></i>
    <strong><?= money($summary['total_outstanding']) ?></strong> in deposits is outstanding (expected but not yet collected).
</div>
<?php endif; ?>

<!-- Detail table -->
<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold small"><?= count($rows) ?> Lease(s)</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Tenant</th>
                    <th>Unit</th>
                    <th>Property</th>
                    <th>Lease</th>
                    <th class="text-end">Expected</th>
                    <th class="text-end text-success">Collected</th>
                    <th class="text-end text-warning">Refunded</th>
                    <th class="text-end text-primary">Held</th>
                    <th class="text-end text-danger">Outstanding</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rows): foreach ($rows as $row): ?>
            <tr class="<?= $row['deposit_outstanding'] > 0 ? 'table-warning' : '' ?>">
                <td class="small fw-semibold"><?= e($row['tenant_name']) ?></td>
                <td class="small"><?= e($row['unit_number']) ?></td>
                <td class="small"><?= e($row['property_name']) ?></td>
                <td class="small"><code><?= e($row['lease_number']) ?></code></td>
                <td class="text-end small"><?= money($row['expected_deposit']) ?></td>
                <td class="text-end small text-success"><?= money($row['paid_deposit']) ?></td>
                <td class="text-end small text-warning"><?= money($row['refunded_deposit']) ?></td>
                <td class="text-end small text-primary fw-semibold"><?= money($row['deposit_balance']) ?></td>
                <td class="text-end small <?= $row['deposit_outstanding'] > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>">
                    <?= $row['deposit_outstanding'] > 0 ? money($row['deposit_outstanding']) : '—' ?>
                </td>
                <td>
                    <?php if ($row['lease_status'] === 'active'): ?>
                    <span class="badge bg-success">Active</span>
                    <?php elseif ($row['lease_status'] === 'terminated'): ?>
                    <span class="badge bg-secondary">Terminated</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="<?= BASE_URL ?>/leases/view.php?id=<?= $row['lease_id'] ?>"
                           class="btn btn-sm btn-outline-secondary py-0 px-1" title="View Lease"><i class="bi bi-eye"></i></a>
                        <?php if ($row['deposit_balance'] > 0 && $row['lease_status'] === 'terminated'): ?>
                        <a href="<?= BASE_URL ?>/payments/add.php?lease_id=<?= $row['lease_id'] ?>&payment_type=deposit_refund"
                           class="btn btn-sm btn-outline-warning py-0 px-1" title="Record Refund"><i class="bi bi-arrow-return-left"></i></a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="11" class="text-center text-muted py-4">No deposit records found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif ($res): ?>
<div class="alert alert-warning small"><?= e($res['message'] ?? 'No data returned.') ?></div>
<?php endif; ?>
<?php include BASE_PATH . '/includes/footer.php'; ?>
