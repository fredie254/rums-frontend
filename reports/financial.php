<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api     = new ApiClient();
$year    = int_param('year') ?: (int)date('Y');
$prop_id = int_param('property_id');

// ── Property dropdown ─────────────────────────────────────────
$prop_res   = $api->get('properties', ['per_page' => 500]);
$properties = $prop_res['data'] ?? [];

$date_from = "$year-01-01";
$date_to   = "$year-12-31";

$fin_params = array_filter([
    'date_from'   => $date_from,
    'date_to'     => $date_to,
    'property_id' => $prop_id ?: null,
], fn($v) => $v !== null && $v !== '');

// ── Financial report ──────────────────────────────────────────
$fin_res  = $api->get('reports/financial', $fin_params);
$fin_data = $fin_res['data'] ?? [];
$summary  = $fin_data['summary'] ?? [];

$total_collected  = (float)($summary['total_income'] ?? 0);
$total_outstanding= (float)($summary['outstanding_ar'] ?? 0);

// ── Monthly revenue by month ──────────────────────────────────
$monthly_chart = array_fill(0, 12, 0.0);
$by_type_map   = [];
foreach ($fin_data['income'] ?? [] as $row) {
    $mo  = (int)substr($row['period'], 5, 2) - 1; // 0-based
    $amt = (float)$row['amount'];
    if ($mo >= 0 && $mo < 12) $monthly_chart[$mo] += $amt;
    $type = $row['category'] ?? 'other';
    $by_type_map[$type] = ($by_type_map[$type] ?? 0) + $amt;
}
arsort($by_type_map);

// ── By payment method (from summary) ─────────────────────────
$sum_params = array_filter([
    'date_from'   => $date_from,
    'date_to'     => $date_to,
    'property_id' => $prop_id ?: null,
], fn($v) => $v !== null && $v !== '');

$pay_sum = $api->get('payments/summary', $sum_params);
$psum    = $pay_sum['data'] ?? [];
$by_method = array_filter([
    ['payment_method' => 'mpesa', 'total' => (float)($psum['mpesa_total'] ?? 0)],
    ['payment_method' => 'bank',  'total' => (float)($psum['bank_total']  ?? 0)],
    ['payment_method' => 'cash',  'total' => (float)($psum['cash_total']  ?? 0)],
], fn($r) => $r['total'] > 0);

// ── Top paying tenants ────────────────────────────────────────
$tenant_pay = [];
$page       = 1;
do {
    $tp_res  = $api->get('payments', array_merge($sum_params, ['per_page' => 200, 'page' => $page]));
    $tp_data = $tp_res['data'] ?? [];
    foreach ($tp_data as $p) {
        $name = $p['tenant_name'] ?? 'Unknown';
        $tenant_pay[$name] = ($tenant_pay[$name] ?? ['total' => 0, 'payments' => 0]);
        $tenant_pay[$name]['total']    += (float)$p['amount'];
        $tenant_pay[$name]['payments'] += 1;
    }
    $total_pages = (int)($tp_res['meta']['total_pages'] ?? 1);
    $page++;
} while ($page <= $total_pages && $page <= 5); // cap at 5 pages = 1000 records

arsort($tenant_pay);
$top_tenants = array_slice($tenant_pay, 0, 10, true);

$page_title = 'Financial Report';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-currency-exchange me-2 text-primary"></i>Financial Report <?= $year ?></h5>
    <div class="d-flex gap-2">
        <form method="GET" class="d-flex gap-2">
            <input type="number" name="year" class="form-control form-control-sm" value="<?= $year ?>" style="width:90px">
            <select name="property_id" class="form-select form-select-sm" style="width:160px">
                <option value="">All Properties</option>
                <?php foreach ($properties as $p): ?><option value="<?= $p['id'] ?>" <?= $prop_id==$p['id']?'selected':'' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-primary">Apply</button>
        </form>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button>
    </div>
</div>

<!-- Summary KPIs -->
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="kpi-card kpi-green"><div class="kpi-icon"><i class="bi bi-cash-coin"></i></div><div class="kpi-value"><?= money($total_collected) ?></div><div class="kpi-label">Total Collected <?= $year ?></div></div></div>
    <div class="col-md-4"><div class="kpi-card kpi-red"><div class="kpi-icon"><i class="bi bi-exclamation-circle"></i></div><div class="kpi-value"><?= money($total_outstanding) ?></div><div class="kpi-label">Outstanding</div></div></div>
    <div class="col-md-4"><div class="kpi-card kpi-blue"><div class="kpi-icon"><i class="bi bi-graph-up"></i></div><div class="kpi-value"><?= pct($total_collected, $total_collected + $total_outstanding) ?></div><div class="kpi-label">Collection Rate</div></div></div>
</div>

<div class="row g-3 mb-4">
    <!-- Revenue Chart -->
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-graph-up text-primary me-1"></i>Monthly Revenue</div>
            <div class="card-body"><canvas id="revenueChart" height="120"></canvas></div>
        </div>
    </div>
    <!-- By Method -->
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-pie-chart me-1 text-info"></i>By Payment Method</div>
            <div class="card-body">
                <canvas id="methodChart" height="180"></canvas>
                <table class="table table-sm mt-2 mb-0">
                    <?php foreach ($by_method as $m): ?>
                    <tr><td><?= ucfirst(str_replace('_',' ',$m['payment_method'])) ?></td><td class="text-end fw-semibold"><?= money($m['total']) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- By Type -->
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">By Payment Type</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Type</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                    <?php foreach ($by_type_map as $type => $total): ?>
                        <tr><td><?= ucfirst($type) ?></td><td class="text-end"><?= money($total) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- Top Tenants -->
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Top Paying Tenants</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Tenant</th><th class="text-end">Payments</th><th class="text-end">Total Paid</th></tr></thead>
                    <tbody>
                    <?php foreach ($top_tenants as $name => $data): ?>
                        <tr><td><?= e($name) ?></td><td class="text-end"><?= $data['payments'] ?></td><td class="text-end fw-semibold"><?= money($data['total']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$top_tenants): ?><tr><td colspan="3" class="text-center text-muted py-3">No payment data.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const revData = <?= json_encode(array_values($monthly_chart)) ?>;
const methodLabels = <?= json_encode(array_map(fn($m) => ucfirst(str_replace('_',' ',$m['payment_method'])), array_values($by_method))) ?>;
const methodData   = <?= json_encode(array_map(fn($m) => (float)$m['total'], array_values($by_method))) ?>;
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
