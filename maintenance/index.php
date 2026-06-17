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
$pg       = ['total' => $total, 'per_page' => $meta['per_page'], 'page' => $meta['current_page'], 'total_pages' => $meta['total_pages'], 'offset' => ($meta['current_page'] - 1) * $meta['per_page']];

$page_title = 'Maintenance Requests';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-wrench me-2 text-warning"></i>Maintenance Requests</h5>
    <a href="<?= BASE_URL ?>/maintenance/add.php" class="btn btn-warning btn-sm"><i class="bi bi-plus-circle me-1"></i>New Request</a>
</div>
<div class="card shadow-sm mb-3"><div class="card-body py-2">
    <form method="GET" class="row g-2">
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm">
                <option value="">All Status</option>
                <?php foreach (['open','assigned','in_progress','completed','closed','cancelled'] as $s): ?><option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="priority" class="form-select form-select-sm">
                <option value="">All Priorities</option>
                <?php foreach (['urgent','high','medium','low'] as $p): ?><option value="<?= $p ?>" <?= $priority===$p?'selected':'' ?>><?= ucfirst($p) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Filter</button><a href="<?= BASE_URL ?>/maintenance/index.php" class="btn btn-sm btn-outline-secondary ms-1">Reset</a></div>
    </form>
</div></div>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>#</th><th>Request #</th><th>Title</th><th>Unit</th><th>Tenant</th><th>Category</th><th>Priority</th><th>Assigned To</th><th>Status</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php if ($requests): $sn = $pg['offset']+1; foreach ($requests as $r): ?>
                <tr>
                    <td><?= $sn++ ?></td>
                    <td><code class="small"><?= e($r['request_number'] ?? '') ?></code></td>
                    <td><a href="<?= BASE_URL ?>/maintenance/view.php?id=<?= $r['id'] ?>"><?= e(mb_substr($r['issue_title'] ?? '', 0, 40)) ?><?= mb_strlen($r['issue_title'] ?? '') > 40 ? '…' : '' ?></a></td>
                    <td><?= e($r['property_name'] ?? '') ?>/<?= e($r['unit_number'] ?? '') ?></td>
                    <td><?= !empty($r['tenant_name']) ? e($r['tenant_name']) : '<span class="text-muted small">—</span>' ?></td>
                    <td><?= ucfirst(str_replace('_',' ',$r['category'] ?? '')) ?></td>
                    <td><?= priority_badge($r['priority'] ?? 'low') ?></td>
                    <td><?= !empty($r['assigned_to_name']) ? e($r['assigned_to_name']) : '<span class="text-muted small">Unassigned</span>' ?></td>
                    <td><?= maintenance_badge($r['status'] ?? 'open') ?></td>
                    <td><?= fmt_date($r['created_at']) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/maintenance/view.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-1"><i class="bi bi-eye"></i></a>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="11" class="text-center text-muted py-4">No requests found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between"><small class="text-muted"><?= count($requests) ?> of <?= $total ?></small><?= pagination_links($pg, BASE_URL . '/maintenance/index.php?' . http_build_query(['status'=>$status,'priority'=>$priority])) ?></div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
