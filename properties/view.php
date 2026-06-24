<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'landlord');

$api = new ApiClient();
$id  = int_param('id');
if (!$id) { redirect(BASE_URL . '/properties/index'); }

$res  = $api->get("properties/$id");
$prop = $res['data'] ?? null;
if (!$prop) { set_flash('error', 'Property not found.'); redirect(BASE_URL . '/properties/index'); }

$units      = $prop['units'] ?? [];
$stats_res  = $api->get("properties/$id/stats");
$stats      = $stats_res['data'] ?? [];

$page_title = 'Property — ' . $prop['name'];
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3 gap-2">
    <a href="<?= BASE_URL ?>/properties/index" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0 flex-grow-1"><?= e($prop['name']) ?></h5>
    <?php if (is_manager()): ?>
    <a href="<?= BASE_URL ?>/properties/edit?id=<?= $prop['id'] ?>" class="btn btn-sm btn-outline-warning">
        <i class="bi bi-pencil me-1"></i>Edit
    </a>
    <a href="<?= BASE_URL ?>/units/add?property_id=<?= $prop['id'] ?>" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Add Unit
    </a>
    <?php endif; ?>
</div>

<?php if ($stats): ?>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center py-2">
            <div class="fs-4 fw-bold text-primary"><?= $stats['total_units'] ?? count($units) ?></div>
            <div class="small text-muted">Total Units</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center py-2">
            <div class="fs-4 fw-bold text-success"><?= $stats['occupied'] ?? 0 ?></div>
            <div class="small text-muted">Occupied</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center py-2">
            <div class="fs-5 fw-bold text-info money"><?= e(money($stats['potential_monthly_revenue'] ?? 0)) ?></div>
            <div class="small text-muted">Monthly Potential</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center py-2">
            <div class="fs-5 fw-bold text-warning money"><?= e(money($stats['year_income'] ?? 0)) ?></div>
            <div class="small text-muted">Year Income</div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <?php if (!empty($prop['image'])): ?>
                <img src="<?= BASE_URL ?>/assets/uploads/<?= e($prop['image']) ?>" class="img-fluid rounded mb-3">
                <?php else: ?>
                <div class="text-center py-3 text-secondary bg-light rounded mb-3"><i class="bi bi-buildings" style="font-size:3rem"></i></div>
                <?php endif; ?>
                <h6 class="fw-bold"><?= e($prop['name']) ?></h6>
                <p class="text-muted small"><i class="bi bi-geo-alt me-1"></i><?= e($prop['address_line1'] ?? $prop['address'] ?? '') ?>, <?= e($prop['address_city'] ?? $prop['city'] ?? '') ?>, <?= e($prop['address_county'] ?? $prop['county'] ?? '') ?></p>
                <hr>
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted">Type</dt><dd class="col-7"><?= ucfirst($prop['property_type'] ?? '') ?></dd>
                    <dt class="col-5 text-muted">Landlord</dt><dd class="col-7"><?= e($prop['landlord_name'] ?? '—') ?></dd>
                    <dt class="col-5 text-muted">Email</dt><dd class="col-7"><?= e($prop['landlord_email'] ?? '—') ?></dd>
                    <dt class="col-5 text-muted">Added</dt><dd class="col-7"><?= fmt_date($prop['created_at'] ?? '') ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between">
                <h6 class="mb-0 fw-semibold">Units (<?= count($units) ?>)</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Unit No.</th><th>Type</th><th>Beds/Bath</th><th>Rent</th><th>Deposit</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($units): foreach ($units as $unit): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($unit['unit_number']) ?></td>
                            <td><?= strtoupper(e($unit['unit_type'] ?? '')) ?></td>
                            <td><?= $unit['bedrooms'] ?? 0 ?> bd / <?= $unit['bathrooms'] ?? 0 ?> ba</td>
                            <td><?= money($unit['rent_amount']) ?></td>
                            <td><?= money($unit['deposit_amount'] ?? 0) ?></td>
                            <td><?= unit_badge($unit['status']) ?></td>
                            <td>
                                <a href="<?= BASE_URL ?>/units/view?id=<?= $unit['id'] ?>" class="btn btn-xs btn-outline-primary btn-sm py-0 px-1"><i class="bi bi-eye"></i></a>
                                <a href="<?= BASE_URL ?>/units/edit?id=<?= $unit['id'] ?>" class="btn btn-xs btn-outline-secondary btn-sm py-0 px-1"><i class="bi bi-pencil"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">No units added yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
