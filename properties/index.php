<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'landlord');

$api    = new ApiClient();
$search = get_param('search');
$type   = get_param('type');
$page   = max(1, int_param('page'));

$query = array_filter([
    'search'  => $search ?: null,
    'status'  => 'active',
    'page'    => $page,
    'per_page'=> ROWS_PER_PAGE,
], fn($v) => $v !== null && $v !== '');

// For landlord role, filter by landlord_id
if (current_user()['role'] === 'landlord') {
    $ll = $api->get('landlords', ['user_id' => (int)$_SESSION['user_id'], 'per_page' => 1]);
    $lid = (int)($ll['data'][0]['id'] ?? 0);
    if ($lid) $query['landlord_id'] = $lid;
}
if ($type) $query['type'] = $type;

$res        = $api->get('properties', $query);
$properties = $res['data'] ?? [];
$meta       = $res['meta'] ?? ['total' => 0, 'total_pages' => 1, 'current_page' => 1, 'per_page' => ROWS_PER_PAGE];
$total      = $meta['total'] ?? 0;
$pg         = ['total' => $total, 'per_page' => $meta['per_page'], 'page' => $meta['current_page'], 'total_pages' => $meta['total_pages'], 'offset' => ($meta['current_page'] - 1) * $meta['per_page']];

$page_title = 'Properties';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-buildings me-2 text-primary"></i>Properties</h5>
    <?php if (is_manager()): ?>
    <a href="<?= BASE_URL ?>/properties/add" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>Add Property
    </a>
    <?php endif; ?>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name, address..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <option value="residential" <?= $type==='residential'?'selected':'' ?>>Residential</option>
                    <option value="commercial"  <?= $type==='commercial'?'selected':'' ?>>Commercial</option>
                    <option value="mixed"       <?= $type==='mixed'?'selected':'' ?>>Mixed</option>
                </select>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-sm btn-outline-primary">Filter</button>
                <a href="<?= BASE_URL ?>/properties/index" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
<?php if ($properties): foreach ($properties as $prop): ?>
    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100 property-card">
            <div class="property-img bg-light d-flex align-items-center justify-content-center">
                <?php if (!empty($prop['image'])): ?>
                    <img src="<?= BASE_URL ?>/assets/uploads/<?= e($prop['image']) ?>" class="img-fluid" style="max-height:160px;object-fit:cover;width:100%">
                <?php else: ?>
                    <i class="bi bi-buildings text-secondary" style="font-size:4rem"></i>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-1">
                    <h6 class="fw-bold mb-0"><?= e($prop['name']) ?></h6>
                    <span class="badge bg-<?= $prop['property_type']==='residential'?'success':($prop['property_type']==='commercial'?'info':'warning') ?>">
                        <?= ucfirst($prop['property_type'] ?? '') ?>
                    </span>
                </div>
                <p class="text-muted small mb-2"><i class="bi bi-geo-alt me-1"></i><?= e($prop['address_line1'] ?? $prop['address'] ?? '') ?>, <?= e($prop['address_city'] ?? $prop['city'] ?? '') ?></p>
                <div class="row text-center g-0 border rounded overflow-hidden mb-2">
                    <div class="col border-end py-1">
                        <div class="fw-bold"><?= $prop['total_units'] ?? 0 ?></div>
                        <div class="small text-muted">Total</div>
                    </div>
                    <div class="col border-end py-1">
                        <div class="fw-bold text-success"><?= $prop['occupied_units'] ?? 0 ?></div>
                        <div class="small text-muted">Occupied</div>
                    </div>
                    <div class="col py-1">
                        <div class="fw-bold text-warning"><?= ($prop['available_units'] ?? (($prop['total_units'] ?? 0) - ($prop['occupied_units'] ?? 0))) ?></div>
                        <div class="small text-muted">Vacant</div>
                    </div>
                </div>
                <small class="text-muted"><i class="bi bi-person me-1"></i><?= e($prop['landlord_name'] ?? '—') ?></small>
            </div>
            <div class="card-footer bg-white border-top-0 d-flex gap-2">
                <a href="<?= BASE_URL ?>/properties/view?id=<?= $prop['id'] ?>" class="btn btn-sm btn-outline-primary flex-fill">
                    <i class="bi bi-eye me-1"></i>View
                </a>
                <?php if (is_manager()): ?>
                <a href="<?= BASE_URL ?>/properties/edit?id=<?= $prop['id'] ?>" class="btn btn-sm btn-outline-secondary flex-fill">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; else: ?>
    <div class="col-12"><div class="text-center py-5 text-muted"><i class="bi bi-buildings fs-1"></i><p class="mt-2">No properties found.</p></div></div>
<?php endif; ?>
</div>

<div class="mt-3 d-flex justify-content-between align-items-center">
    <small class="text-muted">Showing <?= count($properties) ?> of <?= $total ?> properties</small>
    <?= pagination_links($pg, BASE_URL . '/properties/index?search=' . urlencode($search) . '&type=' . urlencode($type)) ?>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
