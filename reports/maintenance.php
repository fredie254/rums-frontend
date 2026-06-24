<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api  = new ApiClient();
$year = int_param('year') ?: (int)date('Y');

// ── Maintenance report ────────────────────────────────────────
$maint_res  = $api->get('reports/maintenance', [
    'date_from' => "$year-01-01",
    'date_to'   => "$year-12-31",
]);
$maint_data  = $maint_res['data'] ?? [];
$summary     = $maint_data['summary']     ?? [];
$by_category = $maint_data['by_category'] ?? [];
$by_property = $maint_data['by_property'] ?? [];

$total_requests = (int)($summary['total']       ?? 0);
$open_requests  = (int)(($summary['open'] ?? 0) + ($summary['in_progress'] ?? 0));
$total_cost     = (float)($summary['total_cost'] ?? 0);

// ── Recent requests ───────────────────────────────────────────
$recent_res = $api->get('maintenance', ['per_page' => 10]);
$recent     = $recent_res['data'] ?? [];

// ── Build by_status from summary ────────────────────────────
$by_status_map = [];
foreach (['open','in_progress','completed','cancelled'] as $s) {
    if (isset($summary[$s])) $by_status_map[$s] = $summary[$s];
}

// ── Build by_priority from summary ───────────────────────────
$by_priority_map = [];
foreach (['urgent','high','medium','low'] as $p) {
    if (isset($summary[$p])) $by_priority_map[$p] = $summary[$p];
}

$page_title = 'Maintenance Report';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-wrench me-2 text-warning"></i>Maintenance Report <?= $year ?></h5>
    <div class="d-flex gap-2">
        <form method="GET" class="d-flex gap-2">
            <input type="number" name="year" class="form-control form-control-sm" value="<?= $year ?>" style="width:90px">
            <button class="btn btn-sm btn-outline-primary">Apply</button>
        </form>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="kpi-card kpi-blue"><div class="kpi-icon"><i class="bi bi-clipboard-check"></i></div><div class="kpi-value"><?= $total_requests ?></div><div class="kpi-label">Total Requests</div></div></div>
    <div class="col-md-3"><div class="kpi-card kpi-orange"><div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div><div class="kpi-value"><?= $open_requests ?></div><div class="kpi-label">Open / In Progress</div></div></div>
    <div class="col-md-3"><div class="kpi-card kpi-red"><div class="kpi-icon"><i class="bi bi-currency-exchange"></i></div><div class="kpi-value"><?= money($total_cost) ?></div><div class="kpi-label">Total Cost <?= $year ?></div></div></div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm"><div class="card-header bg-white fw-semibold">By Status</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <?php foreach ($by_status_map as $s => $cnt): ?><tr><td><?= maintenance_badge($s) ?></td><td class="fw-semibold"><?= $cnt ?></td></tr><?php endforeach; ?>
            </table></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm"><div class="card-header bg-white fw-semibold">By Priority</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <?php foreach ($by_priority_map as $p => $cnt): ?><tr><td><?= priority_badge($p) ?></td><td class="fw-semibold"><?= $cnt ?></td></tr><?php endforeach; ?>
            </table></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm"><div class="card-header bg-white fw-semibold">By Category</div>
            <div class="table-responsive"><table class="table table-sm mb-0 small">
                <thead class="table-light"><tr><th>Category</th><th>Count</th><th>Cost</th></tr></thead>
                <tbody><?php foreach ($by_category as $c): ?><tr><td><?= ucfirst(str_replace('_',' ',$c['category'])) ?></td><td><?= $c['count'] ?></td><td><?= money($c['total_cost']) ?></td></tr><?php endforeach; ?></tbody>
            </table></div>
        </div>
    </div>
</div>
<div class="card shadow-sm"><div class="card-header bg-white fw-semibold">Recent Requests</div>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead class="table-light"><tr><th>Request #</th><th>Title</th><th>Unit</th><th>Priority</th><th>Status</th><th>Cost</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $r): ?>
            <tr>
                <td><a href="<?= BASE_URL ?>/maintenance/view?id=<?= $r['id'] ?>"><code><?= e($r['request_number'] ?? '') ?></code></a></td>
                <td><?= e(substr($r['issue_title'] ?? '', 0, 40)) ?></td>
                <td><?= e($r['property_name'] ?? '') ?>/<?= e($r['unit_number'] ?? '') ?></td>
                <td><?= priority_badge($r['priority'] ?? 'low') ?></td>
                <td><?= maintenance_badge($r['status'] ?? 'open') ?></td>
                <td><?= !empty($r['total_cost']) ? money($r['total_cost']) : '—' ?></td>
                <td><?= fmt_date($r['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?><tr><td colspan="7" class="text-center text-muted py-3">No requests.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
