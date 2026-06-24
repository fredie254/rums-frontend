<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'accountant', 'auditor');

$api    = new ApiClient();
$months = max(3, min(24, int_param('months') ?: 12));
$propId = int_param('property_id');

$props = $api->get('properties', ['per_page' => 100])['data'] ?? [];

$params = array_filter(['months' => $months, 'property_id' => $propId ?: null], fn($v) => $v !== null);
$res    = $api->get('reports/arrears', $params);
$data   = $res['data'] ?? [];

$trend          = $data['trend']           ?? [];
$worstOffenders = $data['worstOffenders']  ?? [];
$byProperty     = $data['byProperty']      ?? [];
$effectiveness  = $data['effectiveness']   ?? [];

$totalOutstanding = (float)($effectiveness['total_outstanding'] ?? 0);
$totalBilled      = (float)($effectiveness['total_billed']      ?? 0);
$totalCollected   = (float)($effectiveness['total_collected']   ?? 0);
$collectionRate   = (float)($effectiveness['collection_rate']   ?? 0);

// Export URL
$exportParams = http_build_query(array_filter(['property_id' => $propId ?: null, 'report' => 'arrears', 'format' => 'csv'], fn($v) => $v !== null));
$exportUrl    = env('APP_URL', BASE_URL) . '/api/v1/reports/export?' . $exportParams;

// Chart data
$trendLabels   = array_map(fn($r) => date('M y', strtotime($r['month'] . '-01')), $trend);
$trendBilled   = array_map(fn($r) => round((float)$r['billed'],      2), $trend);
$trendCollect  = array_map(fn($r) => round((float)$r['collected'],   2), $trend);
$trendOutstand = array_map(fn($r) => round((float)$r['outstanding'], 2), $trend);

$propNames     = array_map(fn($r) => $r['property_name'], $byProperty);
$propAmounts   = array_map(fn($r) => round((float)$r['outstanding'], 2), $byProperty);

$page_title = 'Arrears Analysis';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/reports/index" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h5 class="fw-bold mb-0"><i class="bi bi-graph-down-arrow me-2 text-danger"></i>Arrears Analysis</h5>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <form method="get" class="d-flex gap-2">
            <select name="property_id" class="form-select form-select-sm" style="width:160px">
                <option value="">All Properties</option>
                <?php foreach ($props as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $propId == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="months" class="form-select form-select-sm" style="width:110px">
                <?php foreach ([3,6,12,24] as $m): ?>
                <option value="<?= $m ?>" <?= $months == $m ? 'selected' : '' ?>><?= $m ?> months</option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-primary">Apply</button>
        </form>
        <a href="<?= $exportUrl ?>" class="btn btn-sm btn-outline-success" download>
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
        </a>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-printer"></i>
        </button>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card text-center py-3 border-0 shadow-sm" style="border-left:4px solid #dc3545 !important">
            <div class="fs-4 fw-bold text-danger"><?= money($totalOutstanding) ?></div>
            <div class="small text-muted">Total Outstanding (3mo)</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-3 border-0 shadow-sm" style="border-left:4px solid #0d6efd !important">
            <div class="fs-4 fw-bold text-primary"><?= money($totalBilled) ?></div>
            <div class="small text-muted">Total Billed (3mo)</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-3 border-0 shadow-sm" style="border-left:4px solid #198754 !important">
            <div class="fs-4 fw-bold text-success"><?= money($totalCollected) ?></div>
            <div class="small text-muted">Total Collected (3mo)</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-3 border-0 shadow-sm" style="border-left:4px solid <?= $collectionRate >= 90 ? '#198754' : ($collectionRate >= 70 ? '#ffc107' : '#dc3545') ?> !important">
            <div class="fs-4 fw-bold text-<?= $collectionRate >= 90 ? 'success' : ($collectionRate >= 70 ? 'warning' : 'danger') ?>"><?= $collectionRate ?>%</div>
            <div class="small text-muted">Collection Effectiveness</div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header py-2 fw-semibold">
                <i class="bi bi-bar-chart me-1 text-danger"></i>Billed vs Collected vs Outstanding
            </div>
            <div class="card-body">
                <canvas id="chartTrend" height="85"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header py-2 fw-semibold">
                <i class="bi bi-building me-1 text-info"></i>Outstanding by Property
            </div>
            <div class="card-body">
                <canvas id="chartByProp" height="<?= count($propNames) > 3 ? 120 : 100 ?>"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Worst Offenders Table -->
<div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-person-exclamation me-1 text-danger"></i>Worst Arrears Offenders</span>
        <span class="badge bg-danger"><?= count($worstOffenders) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Tenant</th>
                    <th>Property / Unit</th>
                    <th class="text-end">Outstanding</th>
                    <th class="text-center">Invoices</th>
                    <th>Oldest Due</th>
                    <th class="text-center">Max Days</th>
                    <th>Email</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($worstOffenders): foreach ($worstOffenders as $i => $w): ?>
                <tr>
                    <td class="text-muted small"><?= $i + 1 ?></td>
                    <td class="fw-semibold small"><?= e($w['tenant_name']) ?></td>
                    <td class="small"><?= e($w['property_name'] ?? '—') ?> / <?= e($w['unit_number'] ?? '—') ?></td>
                    <td class="text-end fw-bold text-danger"><?= money($w['total_outstanding']) ?></td>
                    <td class="text-center"><span class="badge bg-secondary"><?= $w['overdue_count'] ?></span></td>
                    <td class="small text-muted"><?= fmt_date($w['oldest_due']) ?></td>
                    <td class="text-center">
                        <span class="badge bg-<?= (int)$w['max_days_overdue'] > 90 ? 'danger' : ((int)$w['max_days_overdue'] > 30 ? 'warning' : 'secondary') ?>">
                            <?= (int)$w['max_days_overdue'] ?>d
                        </span>
                    </td>
                    <td class="small text-muted"><?= e($w['email'] ?? '—') ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/reports/ledger?tenant_name=<?= urlencode($w['tenant_name']) ?>" class="btn btn-xs btn-sm btn-outline-primary py-0 px-1">
                            <i class="bi bi-journal-text"></i> Ledger
                        </a>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No outstanding arrears.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- By Property Summary -->
<?php if ($byProperty): ?>
<div class="card shadow-sm">
    <div class="card-header py-2 fw-semibold"><i class="bi bi-building me-1"></i>Arrears by Property</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr><th>Property</th><th class="text-end">Outstanding</th><th class="text-center">Tenants Owing</th><th class="text-center">Invoices</th></tr>
            </thead>
            <tbody>
                <?php foreach ($byProperty as $p): ?>
                <tr>
                    <td class="fw-semibold"><?= e($p['property_name']) ?></td>
                    <td class="text-end text-danger fw-semibold"><?= money($p['outstanding']) ?></td>
                    <td class="text-center"><?= $p['tenants_owing'] ?></td>
                    <td class="text-center"><?= $p['invoice_count'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
const BLUE = '#0d6efd', GREEN = '#198754', RED = '#dc3545', AMBER = '#ffc107';

new Chart(document.getElementById('chartTrend'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($trendLabels) ?>,
        datasets: [
            { label: 'Billed',      data: <?= json_encode($trendBilled)   ?>, backgroundColor: BLUE  + '99', borderColor: BLUE,  borderWidth:1, borderRadius:3 },
            { label: 'Collected',   data: <?= json_encode($trendCollect)  ?>, backgroundColor: GREEN + '99', borderColor: GREEN, borderWidth:1, borderRadius:3 },
            { label: 'Outstanding', data: <?= json_encode($trendOutstand) ?>, type: 'line', borderColor: RED, backgroundColor: RED + '22', borderWidth:2, fill:true, tension:0.3, pointRadius:4 },
        ],
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => '<?= CURRENCY_SYMBOL ?>' + v.toLocaleString() } } },
    },
});

new Chart(document.getElementById('chartByProp'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($propNames) ?>,
        datasets: [{ label: 'Outstanding', data: <?= json_encode($propAmounts) ?>, backgroundColor: RED + '99', borderColor: RED, borderWidth:1, borderRadius:3 }],
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true, ticks: { callback: v => '<?= CURRENCY_SYMBOL ?>' + v.toLocaleString() } } },
    },
});
</script>

<style>
@media print {
    .btn, nav, .sidebar, form { display: none !important; }
    .card { break-inside: avoid; }
}
</style>
<?php include BASE_PATH . '/includes/footer.php'; ?>
