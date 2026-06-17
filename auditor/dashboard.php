<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'auditor');

$page_title = 'Auditor Dashboard';

$api   = new ApiClient();
$year  = (int)date('Y');
$month = (int)date('n');
$date_from = date('Y-m-01');
$date_to   = date('Y-m-d');

// Income this month + payment count
$pay_sum = $api->get('payments/summary', ['date_from' => $date_from, 'date_to' => $date_to]);
$psum = $pay_sum['data'] ?? [];
$income_this_month   = (float)($psum['total'] ?? 0);
$payments_this_month = (int)($psum['count']   ?? 0);

// Active users count
$users_res    = $api->get('users', ['status' => 'active', 'per_page' => 1]);
$active_users = (int)($users_res['meta']['total'] ?? 0);

// Overdue invoices count
$inv_res        = $api->get('invoices', ['status' => 'overdue', 'per_page' => 1]);
$overdue_invoices = (int)($inv_res['meta']['total'] ?? 0);

// Open maintenance count
$maint_sum      = $api->get('maintenance/summary');
$ms             = $maint_sum['data'] ?? [];
$open_maintenance = (int)(($ms['open'] ?? 0) + ($ms['in_progress'] ?? 0));

// Pending expenses
$exp_res         = $api->get('expenses', ['status' => 'pending', 'per_page' => 1]);
$pending_expenses = (int)($exp_res['meta']['total'] ?? 0);

// Audit log stats via API
$stats_res       = $api->get('audit-logs/stats');
$stats           = $stats_res['data'] ?? [];
$logs_today      = (int)($stats['logs_today']   ?? 0);
$logins_today    = (int)($stats['logins_today']  ?? 0);
$login_trend     = $stats['login_trend']     ?? [];
$module_activity = $stats['module_activity'] ?? [];

// Recent 25 audit events via API
$recent_res  = $api->get('audit-logs', [
    'date_from' => date('Y-m-d', strtotime('-7 days')),
    'date_to'   => date('Y-m-d'),
    'per_page'  => 25,
]);
$recent_logs = $recent_res['data'] ?? [];

// Large payments (flagged: >3× average) — via API
$pay_all_res = $api->get('payments', ['date_from' => "$year-01-01", 'date_to' => "$year-12-31", 'per_page' => 200]);
$pay_all     = $pay_all_res['data'] ?? [];
$avg_amount  = count($pay_all) > 0 ? array_sum(array_column($pay_all, 'amount')) / count($pay_all) : 0;
$large_payments = array_filter($pay_all, fn($p) => (float)$p['amount'] > $avg_amount * 3);

$kpi = [
    'active_users'         => $active_users,
    'payments_this_month'  => $payments_this_month,
    'income_this_month'    => $income_this_month,
    'logs_today'           => $logs_today,
    'logins_today'         => $logins_today,
    'overdue_invoices'     => $overdue_invoices,
    'open_maintenance'     => $open_maintenance,
    'pending_expenses'     => $pending_expenses,
];

include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0"><i class="bi bi-shield-check me-2 text-info"></i>Auditor Dashboard</h5>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/auditor/audit_trail.php" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-journal-text me-1"></i>Audit Trail
        </a>
        <a href="<?= BASE_URL ?>/auditor/compliance.php" class="btn btn-sm btn-outline-success">
            <i class="bi bi-clipboard2-check me-1"></i>Compliance
        </a>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm kpi-blue text-white"><div class="card-body py-3">
            <div class="small opacity-75">Income This Month</div>
            <div class="fs-5 fw-bold"><?= money($kpi['income_this_month']) ?></div>
            <div class="small opacity-75"><?= $kpi['payments_this_month'] ?> payments</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm kpi-teal text-white"><div class="card-body py-3">
            <div class="small opacity-75">Active Users</div>
            <div class="fs-5 fw-bold"><?= $kpi['active_users'] ?></div>
            <div class="small opacity-75"><?= $kpi['logins_today'] ?> logins today</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm kpi-red text-white"><div class="card-body py-3">
            <div class="small opacity-75">Overdue Invoices</div>
            <div class="fs-5 fw-bold"><?= $kpi['overdue_invoices'] ?></div>
            <div class="small opacity-75">require collection</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm kpi-purple text-white"><div class="card-body py-3">
            <div class="small opacity-75">Audit Events Today</div>
            <div class="fs-5 fw-bold"><?= $kpi['logs_today'] ?></div>
            <div class="small opacity-75"><?= $kpi['open_maintenance'] ?> open maintenance</div>
        </div></div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <div class="col-md-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-2"><span class="fw-semibold">Login Activity — Last 7 Days</span></div>
            <div class="card-body"><canvas id="loginChart" height="130"></canvas></div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-2"><span class="fw-semibold">Activity by Module (30 days)</span></div>
            <div class="card-body"><canvas id="moduleChart" height="130"></canvas></div>
        </div>
    </div>
</div>

<!-- Flags / Alerts -->
<?php if ($kpi['pending_expenses'] > 0 || $large_payments): ?>
<div class="row g-4 mb-4">
    <?php if ($kpi['pending_expenses'] > 0): ?>
    <div class="col-md-6">
        <div class="alert alert-warning d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
            <div>
                <strong><?= $kpi['pending_expenses'] ?> expense(s) pending approval.</strong><br>
                <a href="<?= BASE_URL ?>/accountant/expenses.php" class="alert-link">Review expenses →</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($large_payments): ?>
    <div class="col-12">
        <div class="card shadow-sm border-danger">
            <div class="card-header bg-danger-subtle py-2">
                <span class="fw-semibold text-danger-emphasis"><i class="bi bi-flag me-1"></i>Large Payments Flagged (>3× average)</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Ref</th><th>Tenant</th><th>Method</th><th class="text-end">Amount</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($large_payments as $lp): ?>
                    <tr>
                        <td><a href="<?= BASE_URL ?>/payments/view.php?id=<?= $lp['id'] ?>"><?= e($lp['payment_ref']) ?></a></td>
                        <td><?= e($lp['tenant_name'] ?? '—') ?></td>
                        <td><?= e(ucfirst($lp['payment_method'])) ?></td>
                        <td class="text-end fw-bold text-danger"><?= money($lp['amount']) ?></td>
                        <td><?= fmt_date($lp['payment_date']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Recent Audit Log -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Recent Audit Events</span>
        <a href="<?= BASE_URL ?>/auditor/audit_trail.php" class="btn btn-sm btn-outline-primary">Full Trail</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Time</th><th>User</th><th>Role</th><th>Action</th><th>Module</th><th>Description</th><th>IP</th></tr>
                </thead>
                <tbody>
                <?php if ($recent_logs): ?>
                <?php foreach ($recent_logs as $log):
                    $action_colors = ['LOGIN'=>'success','LOGOUT'=>'secondary','CREATE'=>'primary','UPDATE'=>'info','DELETE'=>'danger','VIEW'=>'light text-dark'];
                    $bc = $action_colors[$log['action']] ?? 'secondary';
                ?>
                <tr>
                    <td class="text-nowrap small"><?= fmt_date($log['created_at'], true) ?></td>
                    <td><?= e($log['user_name'] ?? '—') ?></td>
                    <td><span class="badge bg-secondary small"><?= e($log['user_role'] ?? '—') ?></span></td>
                    <td><span class="badge bg-<?= $bc ?>"><?= e($log['action']) ?></span></td>
                    <td><?= e($log['module']) ?></td>
                    <td class="text-truncate" style="max-width:220px"><?= e($log['description'] ?? '') ?></td>
                    <td class="font-monospace small text-muted"><?= e($log['ip_address'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No audit events in the last 7 days.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const lCtx = document.getElementById('loginChart').getContext('2d');
    new Chart(lCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($login_trend, 'day')) ?>,
            datasets: [{
                label: 'Logins',
                data: <?= json_encode(array_column($login_trend, 'cnt')) ?>,
                backgroundColor: 'rgba(13,202,240,0.7)',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    const mCtx = document.getElementById('moduleChart').getContext('2d');
    new Chart(mCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($module_activity, 'module')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($module_activity, 'cnt')) ?>,
                backgroundColor: ['#0d6efd','#198754','#ffc107','#dc3545','#6f42c1','#0dcaf0','#fd7e14','#20c997','#6c757d','#e83e8c'],
                hoverOffset: 5
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } }
        }
    });
});
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
