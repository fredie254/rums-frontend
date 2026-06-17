<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api    = new ApiClient();
$search = get_param('search');
$page   = max(1, int_param('page'));

$res       = $api->get('landlords', array_filter(['search' => $search, 'page' => $page, 'per_page' => ROWS_PER_PAGE]));
$landlords = $res['data'] ?? [];
$meta      = $res['meta'] ?? ['total' => 0, 'total_pages' => 1, 'current_page' => 1, 'per_page' => ROWS_PER_PAGE];
$total     = $meta['total'] ?? 0;
$pg        = ['total' => $total, 'per_page' => $meta['per_page'], 'page' => $meta['current_page'], 'total_pages' => $meta['total_pages'], 'offset' => ($meta['current_page'] - 1) * $meta['per_page']];

$page_title = 'Landlords';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-person-badge me-2 text-primary"></i>Landlords</h5>
    <a href="<?= BASE_URL ?>/landlords/add.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Add Landlord</a>
</div>
<div class="card shadow-sm mb-3"><div class="card-body py-2">
    <form method="GET" class="row g-2">
        <div class="col-md-4"><input type="text" name="search" class="form-control form-control-sm" placeholder="Search name, email, ID..." value="<?= e($search) ?>"></div>
        <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Search</button><a href="<?= BASE_URL ?>/landlords/index.php" class="btn btn-sm btn-outline-secondary ms-1">Reset</a></div>
    </form>
</div></div>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>#</th><th>Name</th><th>Contact</th><th>ID Number</th><th>Properties</th><th>Occupied Units</th><th>Commission</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($landlords): $sn = $pg['offset']+1; foreach ($landlords as $l): ?>
                <tr>
                    <td><?= $sn++ ?></td>
                    <td class="fw-semibold"><?= e($l['name']) ?></td>
                    <td><div><?= e($l['email']) ?></div><small><?= e($l['phone']) ?></small></td>
                    <td><code><?= e($l['id_number']) ?></code></td>
                    <td><?= $l['property_count'] ?? 0 ?></td>
                    <td><?= $l['occupied_units'] ?? 0 ?></td>
                    <td><?= $l['commission_rate'] ?>%</td>
                    <td><?= ($l['user_status'] ?? '') === 'active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/landlords/view.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-1"><i class="bi bi-eye"></i></a>
                        <a href="<?= BASE_URL ?>/landlords/edit.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-1"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No landlords found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between"><small class="text-muted"><?= count($landlords) ?> of <?= $total ?></small><?= pagination_links($pg, BASE_URL . '/landlords/index.php?search=' . urlencode($search)) ?></div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
