<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'maintenance');

$api = new ApiClient();
$page_title = 'Maintenance Dashboard';

$me = current_user();

/* ── Work order stats ── */
$summary = $api->get('maintenance/summary')['data'] ?? [];
$stats = [
    'urgent_count'    => $summary['urgent']      ?? 0,
    'high_count'      => $summary['high']         ?? 0,
    'open_count'      => $summary['open']         ?? 0,
    'active_count'    => $summary['in_progress']  ?? 0,
    'pending_count'   => 0,
    'completed_count' => $summary['completed']    ?? 0,
    'resolved_count'  => 0,
    'total_cost'      => $summary['total_cost']   ?? 0,
];

/* ── Active work orders (recent, up to 20) ── */
$recent = $api->get('maintenance', ['status' => 'active', 'per_page' => 20])['data'] ?? [];

/* ── My assigned tasks (maintenance role only) ── */
/* API already restricts the list to the logged-in user for maintenance role,
   so $recent already contains only their assignments. For admin/manager $my_tasks stays empty. */
$my_tasks = [];
if ($me['role'] === 'maintenance') {
    $my_tasks = $recent;
}

/* ── Cost by property (all time) ── */
$current_year    = date('Y');
$report_all      = $api->get('reports/maintenance', [
    'date_from' => '2000-01-01',
    'date_to'   => $current_year . '-12-31',
])['data'] ?? [];
$cost_by_prop = $report_all['by_property'] ?? [];

/* ── Monthly requests trend (last 6 months) ── */
$six_months_ago  = date('Y-m-d', strtotime('-6 months'));
$today           = date('Y-m-d');
$report_trend    = $api->get('reports/maintenance', [
    'date_from' => $six_months_ago,
    'date_to'   => $today,
])['data'] ?? [];
$trend = $report_trend['trend'] ?? [];

include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0"><i class="bi bi-tools me-2 text-warning"></i>Maintenance Dashboard</h5>
    <a href="<?= BASE_URL ?>/maintenance/add.php" class="btn btn-warning btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Work Order
    </a>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <div class="card shadow-sm kpi-red text-white">
            <div class="card-body py-3 text-center">
                <div class="fs-3 fw-bold"><?= $stats['urgent_count'] ?></div>
                <div class="small opacity-75">Urgent</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card shadow-sm kpi-orange text-white">
            <div class="card-body py-3 text-center">
                <div class="fs-3 fw-bold"><?= $stats['high_count'] ?></div>
                <div class="small opacity-75">High Priority</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card shadow-sm kpi-yellow text-white">
            <div class="card-body py-3 text-center">
                <div class="fs-3 fw-bold"><?= $stats['open_count'] ?></div>
                <div class="small opacity-75">Open</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card shadow-sm kpi-blue text-white">
            <div class="card-body py-3 text-center">
                <div class="fs-3 fw-bold"><?= $stats['active_count'] ?></div>
                <div class="small opacity-75">In Progress</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card shadow-sm kpi-green text-white">
            <div class="card-body py-3 text-center">
                <div class="fs-3 fw-bold"><?= $stats['completed_count'] ?></div>
                <div class="small opacity-75">Completed</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card shadow-sm kpi-purple text-white">
            <div class="card-body py-3 text-center">
                <div class="fs-3 fw-bold"><?= money($stats['total_cost'] ?? 0) ?></div>
                <div class="small opacity-75">Total Cost</div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <div class="col-md-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-2"><span class="fw-semibold">Maintenance Cost by Property</span></div>
            <div class="card-body"><canvas id="costChart" height="150"></canvas></div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-2"><span class="fw-semibold">Requests Trend (6 months)</span></div>
            <div class="card-body"><canvas id="trendChart" height="150"></canvas></div>
        </div>
    </div>
</div>

<?php if ($my_tasks): ?>
<!-- My Assigned Tasks -->
<div class="card shadow-sm border-warning mb-4">
    <div class="card-header bg-warning-subtle py-2 d-flex justify-content-between align-items-center">
        <span class="fw-semibold text-warning-emphasis"><i class="bi bi-person-check me-1"></i>My Assigned Tasks (<?= count($my_tasks) ?>)</span>
        <a href="<?= BASE_URL ?>/maintenance_staff/work_orders.php" class="btn btn-sm btn-outline-warning">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>#</th><th>Issue</th><th>Unit</th><th>Priority</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($my_tasks as $t): ?>
                <tr>
                    <td class="font-monospace small"><?= e($t['request_number'] ?? $t['id']) ?></td>
                    <td><?= e($t['issue_title'] ?? $t['description']) ?><br>
                        <small class="text-muted"><?= e($t['property_name']) ?> / <?= e($t['unit_number']) ?></small></td>
                    <td><?= e($t['unit_number']) ?></td>
                    <td><?= priority_badge($t['priority']) ?></td>
                    <td><?= maintenance_badge($t['status']) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/maintenance_staff/work_orders.php?id=<?= $t['id'] ?>" class="btn btn-xs btn-primary">
                            <i class="bi bi-pencil me-1"></i>Update
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- All Active Work Orders -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Active Work Orders</span>
        <a href="<?= BASE_URL ?>/maintenance_staff/work_orders.php" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>#</th><th>Issue</th><th>Unit / Property</th><th>Reported By</th><th>Priority</th><th>Assigned To</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php if (!$recent): ?>
                    <tr><td colspan="8" class="text-center text-muted py-3">No active work orders.</td></tr>
                <?php else: ?>
                    <?php foreach ($recent as $r): ?>
                    <tr>
                        <td class="font-monospace small"><?= e($r['request_number'] ?? $r['id']) ?></td>
                        <td>
                            <div class="fw-semibold"><?= e($r['issue_title'] ?? substr($r['description'],0,40)) ?></div>
                            <small class="text-muted"><?= fmt_date($r['created_at']) ?></small>
                        </td>
                        <td><?= e($r['property_name']) ?> / <?= e($r['unit_number']) ?></td>
                        <td><?= e($r['tenant_name'] ?: '—') ?></td>
                        <td><?= priority_badge($r['priority']) ?></td>
                        <td><?= e($r['assigned_to_name'] ?? '—') ?></td>
                        <td><?= maintenance_badge($r['status']) ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>/maintenance_staff/work_orders.php?id=<?= $r['id'] ?>" class="btn btn-xs btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Cost by property chart
    const costCtx = document.getElementById('costChart').getContext('2d');
    new Chart(costCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($cost_by_prop, 'name')) ?>,
            datasets: [{
                label: 'Total Cost (<?= get_setting('currency_symbol', CURRENCY_SYMBOL) ?>)',
                data: <?= json_encode(array_column($cost_by_prop, 'cost')) ?>,
                backgroundColor: 'rgba(253,126,20,0.7)',
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { callback: v => '<?= get_setting('currency_symbol', CURRENCY_SYMBOL) ?>' + v.toLocaleString() } } }
        }
    });

    // Trend chart
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($trend, 'month')) ?>,
            datasets: [{
                label: 'Requests',
                data: <?= json_encode(array_column($trend, 'count')) ?>,
                borderColor: '#ffc107',
                backgroundColor: 'rgba(255,193,7,0.15)',
                fill: true,
                tension: 0.3,
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            plugins: { legend: { display: false } }
        }
    });
});
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
