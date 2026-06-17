<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api    = new ApiClient();
$search = get_param('search');
$status = get_param('status') ?: 'active';
$page   = max(1, int_param('page'));

$query = array_filter([
    'search'   => $search ?: null,
    'status'   => $status ?: null,
    'page'     => $page,
    'per_page' => ROWS_PER_PAGE,
], fn($v) => $v !== null && $v !== '');

$res    = $api->get('leases', $query);
$leases = $res['data'] ?? [];
$meta   = $res['meta'] ?? ['total' => 0, 'total_pages' => 1, 'current_page' => 1, 'per_page' => ROWS_PER_PAGE];
$total  = $meta['total'] ?? 0;
$pg     = [
    'total'       => $total,
    'per_page'    => $meta['per_page'],
    'page'        => $meta['current_page'],
    'total_pages' => $meta['total_pages'],
    'offset'      => ($meta['current_page'] - 1) * $meta['per_page'],
];

$page_title = 'Leases';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="fw-bold mb-0"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Leases</h5>
    <div class="d-flex gap-2">
        <?php if (is_manager()): ?>
        <a href="<?= BASE_URL ?>/leases/templates/index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-layout-text-window me-1"></i>Templates</a>
        <a href="<?= BASE_URL ?>/leases/add.php" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>New Lease</a>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm mb-3"><div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search lease #, tenant, unit…" value="<?= e($search) ?>">
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm">
                <option value="">All Status</option>
                <?php foreach (['active','expired','terminated'] as $s): ?>
                <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-sm btn-outline-primary">Filter</button>
            <a href="<?= BASE_URL ?>/leases/index.php" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
        </div>
        <div class="col-auto ms-auto">
            <a href="<?= BASE_URL ?>/leases/index.php?status=active&expiring=1" class="btn btn-sm btn-outline-warning">
                <i class="bi bi-clock-history me-1"></i>Expiring Soon
            </a>
        </div>
    </form>
</div></div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Lease #</th>
                    <th>Tenant</th>
                    <th>Property / Unit</th>
                    <th>Rent</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Status</th>
                    <th>Signed</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($leases): $sn = $pg['offset']+1; foreach ($leases as $l): ?>
                <tr>
                    <td><?= $sn++ ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/leases/view.php?id=<?= $l['id'] ?>">
                            <code><?= e($l['lease_number']) ?></code>
                        </a>
                        <?php if (!empty($l['lease_type']) && $l['lease_type'] !== 'fixed-term'): ?>
                        <br><span class="badge bg-light text-dark" style="font-size:.65rem"><?= ucfirst(str_replace('-',' ',$l['lease_type'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="fw-semibold"><?= e($l['tenant_name'] ?? '') ?></div>
                        <small class="text-muted"><?= e($l['tenant_phone'] ?? '') ?></small>
                    </td>
                    <td><?= e($l['property_name'] ?? '') ?> / <strong><?= e($l['unit_number'] ?? '') ?></strong></td>
                    <td><?= money($l['monthly_rent'] ?? 0) ?>
                        <?php if (!empty($l['escalation_type']) && $l['escalation_type'] !== 'none'): ?>
                        <i class="bi bi-graph-up-arrow text-warning ms-1" title="Escalation: <?= e($l['escalation_type']) ?>"></i>
                        <?php endif; ?>
                    </td>
                    <td><?= fmt_date($l['start_date']) ?></td>
                    <td>
                        <?= fmt_date($l['end_date']) ?>
                        <?php if ($l['status'] === 'active' && !empty($l['days_remaining'])): ?>
                        <?php $dr = (int)$l['days_remaining']; ?>
                        <?php if ($dr < 60): ?>
                        <br><span class="badge bg-<?= $dr < 30 ? 'danger' : 'warning text-dark' ?>" style="font-size:.65rem"><?= $dr ?>d left</span>
                        <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td><?= lease_badge($l['status']) ?></td>
                    <td>
                        <?php if (!empty($l['signed_at'])): ?>
                        <span class="badge bg-success" title="Signed <?= fmt_date($l['signed_at']) ?>"><i class="bi bi-patch-check"></i></span>
                        <?php else: ?>
                        <span class="badge bg-light text-muted border">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= BASE_URL ?>/leases/view.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-1" title="View"><i class="bi bi-eye"></i></a>
                        <?php if ($l['status'] === 'active' && is_manager()): ?>
                        <a href="<?= BASE_URL ?>/leases/renew.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-1" title="Renew"><i class="bi bi-arrow-clockwise"></i></a>
                        <a href="<?= BASE_URL ?>/leases/terminate.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-danger py-0 px-1" title="Terminate"><i class="bi bi-x-circle"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="10" class="text-center text-muted py-4">No leases found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted"><?= count($leases) ?> of <?= $total ?> lease(s)</small>
        <?= pagination_links($pg, BASE_URL . '/leases/index.php?search=' . urlencode($search) . '&status=' . urlencode($status)) ?>
    </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
