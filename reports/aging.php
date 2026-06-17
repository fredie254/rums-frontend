<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'accountant');

$api    = new ApiClient();
$propId = int_param('property_id');

$qs  = $propId ? "?property_id=$propId" : '';
$res = $api->get("reports/aging$qs");
$data = $res['data'] ?? null;

$props = $api->get('properties?per_page=100')['data'] ?? [];

$page_title = 'AR Aging Report';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/reports/index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
        <h5 class="fw-bold mb-0"><i class="bi bi-layers me-2 text-warning"></i>Accounts Receivable Aging</h5>
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
<!-- KPI Summary cards -->
<?php
$buckets   = $data['buckets'];
$colors    = ['current'=>'success','1_30'=>'warning','31_60'=>'orange','61_90'=>'danger','over_90'=>'dark'];
$bgColors  = ['current'=>'#d1fae5','1_30'=>'#fef9c3','31_60'=>'#ffedd5','61_90'=>'#fee2e2','over_90'=>'#f3f4f6'];
?>
<div class="row g-3 mb-3">
    <div class="col">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="text-muted small">Grand Total AR</div>
            <div class="fs-5 fw-bold text-danger"><?= money($data['grand_total']) ?></div>
        </div>
    </div>
    <?php foreach ($buckets as $key => $bucket): ?>
    <div class="col">
        <div class="card border-0 shadow-sm text-center py-3" style="background:<?= $bgColors[$key] ?>">
            <div class="text-muted small"><?= e($bucket['label']) ?></div>
            <div class="fs-6 fw-bold"><?= money($bucket['total']) ?></div>
            <small class="text-muted"><?= count($bucket['rows']) ?> invoice(s)</small>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Aging detail per bucket -->
<?php foreach ($buckets as $key => $bucket): if (empty($bucket['rows'])) continue; ?>
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold small d-flex justify-content-between">
        <span><?= e($bucket['label']) ?></span>
        <span class="text-muted"><?= money($bucket['total']) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Invoice #</th>
                    <th>Tenant</th>
                    <th>Unit</th>
                    <th>Property</th>
                    <th>Due Date</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Balance</th>
                    <th class="text-center">Days</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bucket['rows'] as $row): ?>
            <tr>
                <td class="small"><code><?= e($row['invoice_number']) ?></code></td>
                <td class="small"><?= e($row['tenant_name']) ?></td>
                <td class="small"><?= e($row['unit_number']) ?></td>
                <td class="small"><?= e($row['property_name']) ?></td>
                <td class="small"><?= fmt_date($row['due_date']) ?></td>
                <td class="text-end small"><?= money($row['total_amount']) ?></td>
                <td class="text-end small text-success"><?= money($row['amount_paid']) ?></td>
                <td class="text-end small fw-semibold text-danger"><?= money($row['balance']) ?></td>
                <td class="text-center">
                    <?php $d = (int)$row['days_overdue']; ?>
                    <?php if ($d <= 0): ?>
                    <span class="badge bg-success">current</span>
                    <?php elseif ($d <= 30): ?>
                    <span class="badge bg-warning text-dark"><?= $d ?>d</span>
                    <?php elseif ($d <= 60): ?>
                    <span class="badge bg-orange text-dark" style="background:#f97316!important"><?= $d ?>d</span>
                    <?php else: ?>
                    <span class="badge bg-danger"><?= $d ?>d</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?= BASE_URL ?>/invoices/view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-1"><i class="bi bi-eye"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php elseif ($res && !$data): ?>
<div class="alert alert-warning small"><?= e($res['message'] ?? 'No data returned.') ?></div>
<?php endif; ?>
<?php include BASE_PATH . '/includes/footer.php'; ?>
