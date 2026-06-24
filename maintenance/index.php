<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$api      = new ApiClient();
$status   = get_param('status');
$priority = get_param('priority');
$page     = max(1, int_param('page'));

$query = array_filter([
    'status'   => $status   ?: null,
    'priority' => $priority ?: null,
    'page'     => $page,
    'per_page' => ROWS_PER_PAGE,
], fn($v) => $v !== null && $v !== '');

$res      = $api->get('maintenance', $query);
$requests = $res['data'] ?? [];
$meta     = $res['meta'] ?? ['total' => 0, 'total_pages' => 1, 'current_page' => 1, 'per_page' => ROWS_PER_PAGE];
$total    = $meta['total'] ?? 0;
$pg       = [
    'total'       => $total,
    'per_page'    => $meta['per_page'],
    'page'        => $meta['current_page'],
    'total_pages' => $meta['total_pages'],
    'offset'      => ($meta['current_page'] - 1) * $meta['per_page'],
];

$page_title = 'Maintenance Requests';
include BASE_PATH . '/includes/header.php';
?>

<?php if ($flash = get_flash()): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
    <?= e($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-wrench me-2 text-warning"></i>Maintenance Requests</h5>
    <?php if (is_manager()): ?>
    <a href="<?= BASE_URL ?>/maintenance/add" class="btn btn-warning btn-sm">
        <i class="bi bi-plus-circle me-1"></i>New Request
    </a>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <?php foreach (['open','in_progress','completed','resolved','cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="priority" class="form-select form-select-sm">
                    <option value="">All Priorities</option>
                    <?php foreach (['urgent','high','medium','low'] as $p): ?>
                    <option value="<?= $p ?>" <?= $priority === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <a href="<?= BASE_URL ?>/maintenance/index" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Request #</th>
                    <th>Title</th>
                    <th>Unit</th>
                    <th>Tenant</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Assigned To</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($requests): $sn = $pg['offset'] + 1; foreach ($requests as $r): ?>
                <tr id="mnt-row-<?= $r['id'] ?>">
                    <td class="text-muted small"><?= $sn++ ?></td>
                    <td><code class="small"><?= e($r['request_number'] ?? '') ?></code></td>
                    <td>
                        <a href="<?= BASE_URL ?>/maintenance/view?id=<?= $r['id'] ?>" class="text-decoration-none fw-semibold">
                            <?= e(mb_substr($r['issue_title'] ?? '', 0, 45)) ?><?= mb_strlen($r['issue_title'] ?? '') > 45 ? '…' : '' ?>
                        </a>
                    </td>
                    <td class="small"><?= e($r['property_name'] ?? '') ?> / <?= e($r['unit_number'] ?? '') ?></td>
                    <td class="small"><?= !empty($r['tenant_name']) ? e($r['tenant_name']) : '<span class="text-muted">—</span>' ?></td>
                    <td class="small"><?= $r['category'] ? ucfirst(str_replace('_', ' ', $r['category'])) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= priority_badge($r['priority'] ?? 'low') ?></td>
                    <td class="small"><?= !empty($r['assigned_to_name']) ? e($r['assigned_to_name']) : '<span class="text-muted">Unassigned</span>' ?></td>
                    <td><?= maintenance_badge($r['status'] ?? 'open') ?></td>
                    <td class="small text-nowrap"><?= fmt_date($r['created_at']) ?></td>
                    <td class="text-nowrap text-end">
                        <!-- View -->
                        <a href="<?= BASE_URL ?>/maintenance/view?id=<?= $r['id'] ?>"
                           class="btn btn-sm btn-outline-primary py-0 px-1" title="View">
                            <i class="bi bi-eye"></i>
                        </a>
                        <?php if (is_manager()): ?>
                        <!-- Edit -->
                        <a href="<?= BASE_URL ?>/maintenance/edit?id=<?= $r['id'] ?>"
                           class="btn btn-sm btn-outline-secondary py-0 px-1" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php endif; ?>
                        <?php if (current_user()['role'] === 'admin' || is_manager()): ?>
                        <!-- Delete -->
                        <button type="button"
                                class="btn btn-sm btn-outline-danger py-0 px-1" title="Delete"
                                onclick="deleteMaintenance(<?= $r['id'] ?>, '<?= e(addslashes($r['request_number'] ?? '')) ?>')">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr>
                    <td colspan="11" class="text-center text-muted py-5">
                        <i class="bi bi-wrench d-block fs-2 mb-2 opacity-25"></i>
                        No maintenance requests found.
                        <?php if (is_manager()): ?>
                        <br><a href="<?= BASE_URL ?>/maintenance/add" class="btn btn-sm btn-warning mt-2">
                            <i class="bi bi-plus-circle me-1"></i>Create First Request
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Showing <?= count($requests) ?> of <?= $total ?> requests</small>
        <?= pagination_links($pg, BASE_URL . '/maintenance/index?' . http_build_query(array_filter(['status' => $status, 'priority' => $priority]))) ?>
    </div>
</div>

<script>
function deleteMaintenance(id, ref) {
    if (!confirm('Delete work order ' + ref + '?\n\nThis action cannot be undone.')) return;
    fetch('<?= BASE_URL ?>/maintenance/delete', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= csrf_token() ?>'
        },
        body: JSON.stringify({id: id})
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const row = document.getElementById('mnt-row-' + id);
            if (row) row.remove();
            // Update the count
            const countEl = document.querySelector('.card-footer small');
            if (countEl) {
                const remaining = document.querySelectorAll('tr[id^="mnt-row-"]').length;
                countEl.textContent = 'Showing ' + remaining + ' of <?= $total ?> requests';
            }
        } else {
            alert(res.message || 'Failed to delete work order.');
        }
    })
    .catch(() => alert('Network error. Please try again.'));
}
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
