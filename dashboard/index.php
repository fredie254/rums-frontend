<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$user = current_user();

// Role-based dashboard redirects
$role = $user['role'] ?? 'tenant';
if ($role === 'landlord') {
    redirect(BASE_URL . '/landlord/dashboard.php');
} elseif ($role === 'accountant') {
    redirect(BASE_URL . '/accountant/dashboard.php');
} elseif ($role === 'maintenance') {
    redirect(BASE_URL . '/maintenance_staff/dashboard.php');
} elseif ($role === 'auditor') {
    redirect(BASE_URL . '/auditor/dashboard.php');
} elseif ($role === 'security') {
    redirect(BASE_URL . '/security/dashboard.php');
} elseif ($role === 'tenant') {
    redirect(BASE_URL . '/tenant/dashboard.php');
}

$api = new ApiClient();

// KPI stats
$kpi_res = $api->get('reports/dashboard');
$kpi     = $kpi_res['data'] ?? [];

$total_units      = (int)($kpi['units']['total']      ?? 0);
$occupied_units   = (int)($kpi['units']['occupied']   ?? 0);
$available_units  = (int)($kpi['units']['available']  ?? 0);
$monthly_revenue  = (float)($kpi['revenue']['current_month'] ?? 0);
$active_leases    = (int)($kpi['leases']['active']    ?? 0);
$expiring_30d     = (int)($kpi['leases']['expiring_30d'] ?? 0);
$open_maintenance = (int)($kpi['maintenance']['open'] ?? 0);
$overdue_count    = (int)($kpi['accounts_receivable']['count'] ?? 0);
$occupancy_rate   = (float)($kpi['occupancy_rate']    ?? 0);

// Additional counts via separate calls
$prop_res        = $api->get('properties', ['per_page' => 1]);
$total_properties= (int)($prop_res['meta']['total'] ?? 0);

$ten_res      = $api->get('tenants', ['per_page' => 1]);
$total_tenants= (int)($ten_res['meta']['total'] ?? 0);

// Recent payments
$pay_res        = $api->get('payments', ['per_page' => 7]);
$recent_payments= $pay_res['data'] ?? [];

// Overdue invoices
$ov_res          = $api->get('invoices', ['status' => 'overdue', 'per_page' => 5]);
$overdue_invoices= $ov_res['data'] ?? [];

// Monthly revenue chart — last 6 months via financial report
$date_from = date('Y-m-01', strtotime('-5 months'));
$fin_res   = $api->get('reports/financial', ['date_from' => $date_from, 'date_to' => date('Y-m-d')]);
$chart_months = [];
foreach ($fin_res['data']['income'] ?? [] as $row) {
    $chart_months[$row['period']] = ($chart_months[$row['period']] ?? 0) + (float)$row['amount'];
}
ksort($chart_months);
$chart_labels = array_map(fn($p) => date('M Y', strtotime($p . '-01')), array_keys($chart_months));
$chart_values = array_values($chart_months);

// Expiring leases (within 30 days)
$exp_res        = $api->get('leases/expiring', ['days' => 30]);
$expiring_leases= $exp_res['data'] ?? [];

// Maintenance unit count for doughnut
$maint_units = (int)($kpi['units']['maintenance'] ?? 0);

$page_title = 'Dashboard';
include BASE_PATH . '/includes/header.php';
?>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0 fw-bold">Dashboard</h4>
        <small class="text-muted">Welcome back, <?= e($user['name']) ?> &mdash; <?= date('l, d F Y') ?></small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/invoices/generate.php" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-receipt me-1"></i>Generate Invoices
        </a>
        <a href="<?= BASE_URL ?>/payments/add.php" class="btn btn-sm btn-warning">
            <i class="bi bi-plus-circle me-1"></i>Record Payment
        </a>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-buildings"></i></div>
            <div class="kpi-value"><?= $total_properties ?></div>
            <div class="kpi-label">Properties</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-door-open"></i></div>
            <div class="kpi-value"><?= $occupied_units ?>/<?= $total_units ?></div>
            <div class="kpi-label">Units Occupied</div>
            <div class="kpi-sub"><?= $occupancy_rate ?>% occupancy</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-yellow">
            <div class="kpi-icon"><i class="bi bi-people"></i></div>
            <div class="kpi-value"><?= $total_tenants ?></div>
            <div class="kpi-label">Active Tenants</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-purple">
            <div class="kpi-icon"><i class="bi bi-cash-coin"></i></div>
            <div class="kpi-value"><?= money($monthly_revenue) ?></div>
            <div class="kpi-label">Revenue This Month</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-teal">
            <div class="kpi-icon"><i class="bi bi-file-earmark-text"></i></div>
            <div class="kpi-value"><?= $active_leases ?></div>
            <div class="kpi-label">Active Leases</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-wrench"></i></div>
            <div class="kpi-value"><?= $open_maintenance ?></div>
            <div class="kpi-label">Open Maintenance</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-exclamation-circle"></i></div>
            <div class="kpi-value"><?= $overdue_count ?></div>
            <div class="kpi-label">Overdue Invoices</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-cyan">
            <div class="kpi-icon"><i class="bi bi-door-closed"></i></div>
            <div class="kpi-value"><?= $available_units ?></div>
            <div class="kpi-label">Vacant Units</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card <?= $expiring_30d > 0 ? 'kpi-yellow' : 'kpi-teal' ?>" style="cursor:pointer" onclick="document.getElementById('expiring-widget').scrollIntoView({behavior:'smooth'})">
            <div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div>
            <div class="kpi-value"><?= $expiring_30d ?></div>
            <div class="kpi-label">Expiring (30d)</div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
    <!-- Revenue Chart -->
    <div class="col-md-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-graph-up text-primary me-2"></i>Monthly Revenue (Last 6 Months)</h6>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" height="100"></canvas>
            </div>
        </div>
    </div>
    <!-- Occupancy Doughnut -->
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-pie-chart text-success me-2"></i>Unit Status</h6>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <canvas id="occupancyChart" height="180"></canvas>
                <div class="mt-3 d-flex gap-3 small">
                    <span><span class="badge bg-success">&nbsp;</span> Available</span>
                    <span><span class="badge bg-primary">&nbsp;</span> Occupied</span>
                    <span><span class="badge bg-warning">&nbsp;</span> Maintenance</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tables Row -->
<div class="row g-3">
    <!-- Recent Payments -->
    <div class="col-md-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history text-success me-2"></i>Recent Payments</h6>
                <a href="<?= BASE_URL ?>/payments/index.php" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Reference</th><th>Tenant</th><th>Unit</th><th>Amount</th><th>Method</th><th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($recent_payments): foreach ($recent_payments as $p): ?>
                        <tr>
                            <td><code class="small"><?= e($p['payment_ref'] ?? '—') ?></code></td>
                            <td><?= e($p['tenant_name'] ?? '—') ?></td>
                            <td><small><?= e($p['property_name'] ?? '') ?> / <?= e($p['unit_number'] ?? '') ?></small></td>
                            <td class="fw-semibold text-success"><?= money($p['amount']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= ucfirst($p['payment_method'] ?? '') ?></span></td>
                            <td><small><?= fmt_date($p['payment_date']) ?></small></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">No payments yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Overdue Invoices -->
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Overdue Invoices</h6>
                <a href="<?= BASE_URL ?>/invoices/index.php?status=overdue" class="btn btn-sm btn-outline-danger">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Tenant</th><th>Unit</th><th>Amount</th><th>Due</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($overdue_invoices): foreach ($overdue_invoices as $inv): ?>
                        <tr>
                            <td><?= e($inv['tenant_name'] ?? '—') ?></td>
                            <td><small><?= e($inv['unit_number'] ?? '') ?></small></td>
                            <td class="text-danger fw-semibold"><?= money($inv['total_amount']) ?></td>
                            <td><small class="text-danger"><?= fmt_date($inv['due_date']) ?></small></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No overdue invoices.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($expiring_leases): ?>
<!-- Expiring Leases Widget -->
<div class="row g-3 mb-4" id="expiring-widget">
    <div class="col-12">
        <div class="card shadow-sm border-warning">
            <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold text-warning"><i class="bi bi-hourglass-split me-2"></i>Leases Expiring Within 30 Days (<?= count($expiring_leases) ?>)</h6>
                <a href="<?= BASE_URL ?>/leases/index.php" class="btn btn-sm btn-outline-warning">All Leases</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th>Tenant</th><th>Property / Unit</th><th>Rent</th><th>End Date</th><th>Days Left</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($expiring_leases as $el): ?>
                        <tr class="<?= ($el['days_remaining'] ?? 99) <= 7 ? 'table-danger' : 'table-warning' ?> bg-opacity-25">
                            <td class="fw-semibold"><?= e($el['tenant_name'] ?? '—') ?></td>
                            <td><?= e($el['property_name'] ?? '') ?> / <?= e($el['unit_number'] ?? '') ?></td>
                            <td><?= money($el['monthly_rent'] ?? 0) ?></td>
                            <td><?= fmt_date($el['end_date']) ?></td>
                            <td><span class="badge <?= ($el['days_remaining'] ?? 99) <= 7 ? 'bg-danger' : 'bg-warning text-dark' ?>"><?= $el['days_remaining'] ?? '?' ?> days</span></td>
                            <td><a href="<?= BASE_URL ?>/leases/view.php?id=<?= $el['id'] ?>" class="btn btn-xs btn-sm btn-outline-primary py-0 px-1"><i class="bi bi-eye"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Chart Data (JSON) -->
<script>
const revenueLabels = <?= json_encode(array_values($chart_labels)) ?>;
const revenueData   = <?= json_encode(array_values($chart_values)) ?>;

const occupancyData = {
    available:   <?= $available_units ?>,
    occupied:    <?= $occupied_units ?>,
    maintenance: <?= $maint_units ?>,
    reserved:    0
};
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
