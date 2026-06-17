<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api     = new ApiClient();
$search  = get_param('search');
$status  = get_param('status');
$prop_id = int_param('property_id');
$page    = max(1, int_param('page'));

$res = $api->get('units', array_filter([
    'search'      => $search,
    'status'      => $status,
    'property_id' => $prop_id ?: null,
    'page'        => $page,
    'per_page'    => ROWS_PER_PAGE,
], fn($v) => $v !== null && $v !== '' && $v !== 0));

$units = $res['data'] ?? [];
$meta  = $res['meta'] ?? ['total' => 0, 'total_pages' => 1, 'current_page' => 1, 'per_page' => ROWS_PER_PAGE];
$total = $meta['total'] ?? 0;
$pg    = ['total' => $total, 'per_page' => $meta['per_page'], 'page' => $meta['current_page'], 'total_pages' => $meta['total_pages'], 'offset' => ($meta['current_page'] - 1) * $meta['per_page']];

$propRes    = $api->get('properties', ['per_page' => 200]);
$properties = $propRes['data'] ?? [];

$page_title = 'Units';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-door-open me-2 text-primary"></i>Units</h5>
    <?php if (is_manager()): ?>
    <a href="<?= BASE_URL ?>/units/add.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Add Unit</a>
    <?php endif; ?>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4"><input type="text" name="search" class="form-control form-control-sm" placeholder="Search unit/property..." value="<?= e($search) ?>"></div>
            <div class="col-md-3">
                <select name="property_id" class="form-select form-select-sm">
                    <option value="">All Properties</option>
                    <?php foreach ($properties as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $prop_id==$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <?php foreach (['available','occupied','maintenance','reserved'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-primary">Filter</button>
                <a href="<?= BASE_URL ?>/units/index.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>#</th><th>Unit</th><th>Property</th><th>Type</th><th>Rent</th><th>Deposit</th><th>Current Tenant</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if ($units): $sn = $pg['offset'] + 1; foreach ($units as $u): ?>
                <tr>
                    <td><?= $sn++ ?></td>
                    <td class="fw-semibold"><?= e($u['unit_number']) ?></td>
                    <td><?= e($u['property_name'] ?? '—') ?></td>
                    <td><?= strtoupper(e($u['unit_type'])) ?></td>
                    <td><?= money($u['rent_amount']) ?></td>
                    <td><?= money($u['deposit_amount']) ?></td>
                    <td><?= !empty($u['tenant_name']) ? e($u['tenant_name']) : '<span class="text-muted small">—</span>' ?></td>
                    <td><?= unit_badge($u['status']) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/units/view.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-1"><i class="bi bi-eye"></i></a>
                        <?php if (is_manager()): ?>
                        <a href="<?= BASE_URL ?>/units/edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-1"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No units found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Showing <?= count($units) ?> of <?= $total ?></small>
        <?= pagination_links($pg, BASE_URL . '/units/index.php?search=' . urlencode($search) . '&status=' . urlencode($status)) ?>
    </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
