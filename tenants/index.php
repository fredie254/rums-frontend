<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api    = new ApiClient();
$search = get_param('search');
$page   = max(1, int_param('page'));

$query = array_filter([
    'search'   => $search ?: null,
    'page'     => $page,
    'per_page' => ROWS_PER_PAGE,
], fn($v) => $v !== null && $v !== '');

$res     = $api->get('tenants', $query);
$tenants = $res['data'] ?? [];
$meta    = $res['meta'] ?? [];
$total   = (int)($meta['total'] ?? 0);
$pg      = ['total' => $total, 'per_page' => (int)($meta['per_page'] ?? ROWS_PER_PAGE), 'page' => (int)($meta['current_page'] ?? $page), 'total_pages' => (int)($meta['total_pages'] ?? 1), 'offset' => ((int)($meta['current_page'] ?? $page) - 1) * (int)($meta['per_page'] ?? ROWS_PER_PAGE)];

$page_title = 'Tenants';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>Tenants</h5>
    <?php if (is_manager()): ?>
    <a href="<?= BASE_URL ?>/tenants/add" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Add Tenant</a>
    <?php endif; ?>
</div>

<?php if ($flash = get_flash()): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
    <?= e($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2">
            <div class="col-md-5"><input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name or email..." value="<?= e($search) ?>"></div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-primary">Search</button>
                <a href="<?= BASE_URL ?>/tenants/index" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>#</th><th>Name</th><th>Contact</th><th>ID Number</th><th>Unit</th><th>Lease</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if ($tenants): $sn = $pg['offset'] + 1; foreach ($tenants as $t): ?>
                <tr id="tenant-row-<?= $t['id'] ?>">
                    <td><?= $sn++ ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($t['full_name'] ?? (($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? ''))) ?></div>
                        <small class="text-muted"><?= e($t['occupation'] ?? '') ?></small>
                    </td>
                    <td>
                        <div><?= e($t['email'] ?? '') ?></div>
                        <small class="text-muted"><?= e($t['phone'] ?? '') ?></small>
                    </td>
                    <td><code><?= e($t['id_number'] ?? '') ?></code></td>
                    <td><?= !empty($t['unit_number']) ? e($t['property_name'] ?? '') . ' / ' . e($t['unit_number']) : '<span class="text-muted small">—</span>' ?></td>
                    <td><?= !empty($t['lease_status']) ? lease_badge($t['lease_status']) : '<span class="text-muted small">No lease</span>' ?></td>
                    <td><?= ($t['status'] ?? '') === 'active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                    <td class="text-nowrap">
                        <a href="<?= BASE_URL ?>/tenants/view?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-1" title="View"><i class="bi bi-eye"></i></a>
                        <?php if (is_manager()): ?>
                        <a href="<?= BASE_URL ?>/tenants/edit?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-1" title="Edit"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                        <?php if (is_admin()): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1"
                                title="Delete"
                                onclick="deleteTenant(<?= $t['id'] ?>, '<?= e(addslashes($t['full_name'] ?? ($t['first_name'] . ' ' . $t['last_name']))) ?>')">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No tenants found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between">
        <small class="text-muted">Showing <?= count($tenants) ?> of <?= $total ?></small>
        <?= pagination_links($pg, BASE_URL . '/tenants/index?search=' . urlencode($search)) ?>
    </div>
</div>

<script>
function deleteTenant(id, name) {
    if (!confirm('Delete tenant "' + name + '"?\n\nThis removes the tenant record permanently. Their login account will be deactivated.')) return;
    fetch('<?= BASE_URL ?>/tenants/delete.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>'},
        body: JSON.stringify({id: id})
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            document.getElementById('tenant-row-' + id)?.remove();
        } else {
            alert(res.message || 'Failed to delete tenant.');
        }
    })
    .catch(() => alert('Network error. Please try again.'));
}
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
