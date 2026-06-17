<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'auditor');

$api = new ApiClient();

// ── All data in one shot ───────────────────────────────────────
$dash      = $api->get('reports/dashboard')['data']        ?? [];
$occ       = $api->get('reports/occupancy')['data']        ?? [];
$arrears   = $api->get('reports/arrears', ['months' => 6])['data'] ?? [];
$maint     = $api->get('reports/maintenance', ['date_from' => date('Y-01-01'), 'date_to' => date('Y-m-d')])['data'] ?? [];
$finData   = $api->get('reports/financial', ['date_from' => date('Y-01-01'), 'date_to' => date('Y-m-d')])['data'] ?? [];
$tenantAna = $api->get('reports/tenant-analytics')['data'] ?? [];

// ── KPI extraction ─────────────────────────────────────────────
$units        = $dash['units']               ?? [];
$revenue      = $dash['revenue']             ?? [];
$ar           = $dash['accounts_receivable'] ?? [];
$maintKpi     = $dash['maintenance']         ?? [];
$leases       = $dash['leases']              ?? [];
$occRate      = (float)($dash['occupancy_rate'] ?? 0);

$totalUnits   = (int)($units['total']       ?? 0);
$occupied     = (int)($units['occupied']    ?? 0);
$available    = (int)($units['available']   ?? 0);
$inMaint      = (int)($units['maintenance'] ?? 0);

$monthRevenue = (float)($revenue['current_month'] ?? 0);
$yearRevenue  = (float)($revenue['current_year']  ?? 0);
$arBalance    = (float)($ar['balance']            ?? 0);
$arCount      = (int)($ar['count']                ?? 0);

// ── Monthly revenue for bar chart (12 months) ─────────────────
$months        = [];
$monthlyRev    = [];
$monthlyCollect = [];
for ($i = 11; $i >= 0; $i--) {
    $months[] = date('M y', strtotime("-$i months"));
    $monthlyRev[] = 0;
}
foreach ($finData['income'] ?? [] as $row) {
    $mo = (int)substr($row['period'], 5, 2) - 1;
    $yr = (int)substr($row['period'], 0, 4);
    // Map to slot
    for ($i = 0; $i < 12; $i++) {
        $slotDate = date('Y-m', strtotime("-" . (11 - $i) . " months"));
        if ($row['period'] === $slotDate) {
            $monthlyRev[$i] = round((float)$row['amount'], 2);
            break;
        }
    }
}

// ── Arrears monthly trend (last 6 months) ─────────────────────
$arTrendLabels  = [];
$arTrendBilled  = [];
$arTrendCollect = [];
foreach ($arrears['trend'] ?? [] as $row) {
    $arTrendLabels[]  = date('M y', strtotime($row['month'] . '-01'));
    $arTrendBilled[]  = (float)$row['billed'];
    $arTrendCollect[] = (float)$row['collected'];
}

// ── Occupancy by property ─────────────────────────────────────
$propLabels = [];
$propOcc    = [];
$propAvail  = [];
foreach ($occ['by_property'] ?? [] as $p) {
    $propLabels[] = $p['property_name'];
    $propOcc[]    = (int)$p['occupied'];
    $propAvail[]  = (int)$p['available'];
}

// ── Maintenance status ────────────────────────────────────────
$maintSummary = $maint['summary'] ?? [];
$maintOpen    = (int)($maintSummary['open']        ?? 0);
$maintProg    = (int)($maintSummary['in_progress'] ?? 0);
$maintDone    = (int)($maintSummary['completed']   ?? 0);

// ── Worst offenders (top 5) ───────────────────────────────────
$worstOffenders = array_slice($arrears['worstOffenders'] ?? [], 0, 5);

// ── Expiring leases ───────────────────────────────────────────
$expiringSoon = array_slice($tenantAna['expiringSoon'] ?? [], 0, 8);

$page_title = 'Executive Dashboard';
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h5 class="fw-bold mb-0"><i class="bi bi-speedometer2 me-2 text-primary"></i>Executive Dashboard</h5>
        <small class="text-muted">Real-time overview — <?= date('d M Y, H:i') ?></small>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        <a href="<?= BASE_URL ?>/reports/index.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-grid me-1"></i>All Reports
        </a>
    </div>
</div>

<!-- ── Row 1: KPI Cards ─────────────────────────────────────── -->
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-2">
        <div class="card text-center py-2 border-0 shadow-sm h-100" style="border-left:4px solid #0d6efd !important">
            <div class="fs-1 fw-bold text-primary"><?= $occRate ?>%</div>
            <div class="small text-muted fw-semibold">Occupancy Rate</div>
            <div class="text-success small"><?= $occupied ?>/<?= $totalUnits ?> units</div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card text-center py-2 border-0 shadow-sm h-100" style="border-left:4px solid #198754 !important">
            <div class="fs-4 fw-bold text-success"><?= money($monthRevenue) ?></div>
            <div class="small text-muted fw-semibold">This Month Revenue</div>
            <div class="text-muted small">YTD: <?= money($yearRevenue) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card text-center py-2 border-0 shadow-sm h-100" style="border-left:4px solid #dc3545 !important">
            <div class="fs-4 fw-bold text-danger"><?= money($arBalance) ?></div>
            <div class="small text-muted fw-semibold">Accounts Receivable</div>
            <div class="text-muted small"><?= $arCount ?> outstanding invoice<?= $arCount !== 1 ? 's' : '' ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card text-center py-2 border-0 shadow-sm h-100" style="border-left:4px solid #ffc107 !important">
            <div class="fs-4 fw-bold text-warning"><?= $maintOpen + $maintProg ?></div>
            <div class="small text-muted fw-semibold">Open Maintenance</div>
            <div class="text-muted small"><?= (int)($maintSummary['urgent'] ?? 0) ?> urgent</div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card text-center py-2 border-0 shadow-sm h-100" style="border-left:4px solid #0dcaf0 !important">
            <div class="fs-4 fw-bold text-info"><?= (int)($leases['active'] ?? 0) ?></div>
            <div class="small text-muted fw-semibold">Active Leases</div>
            <div class="text-<?= (int)($leases['expiring_30d'] ?? 0) > 0 ? 'warning' : 'muted' ?> small">
                <?= (int)($leases['expiring_30d'] ?? 0) ?> expiring ≤30d
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card text-center py-2 border-0 shadow-sm h-100" style="border-left:4px solid #6f42c1 !important">
            <div class="fs-4 fw-bold text-purple"><?= $available ?></div>
            <div class="small text-muted fw-semibold">Vacant Units</div>
            <div class="text-muted small"><?= $inMaint ?> under maintenance</div>
        </div>
    </div>
</div>

<!-- ── Row 2: Revenue + Occupancy charts ────────────────────── -->
<div class="row g-3 mb-3">
    <!-- Monthly Revenue Bar -->
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-bar-chart me-1 text-primary"></i>Monthly Revenue (12 months)</span>
                <a href="<?= BASE_URL ?>/reports/financial.php" class="small text-primary">Details &rarr;</a>
            </div>
            <div class="card-body">
                <canvas id="chartRevenue" height="80"></canvas>
            </div>
        </div>
    </div>
    <!-- Occupancy donut -->
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-pie-chart me-1 text-success"></i>Occupancy Status</span>
                <a href="<?= BASE_URL ?>/reports/occupancy.php" class="small text-primary">Details &rarr;</a>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <canvas id="chartOccupancy" style="max-height:180px"></canvas>
                <div class="mt-2 text-center">
                    <span class="badge bg-success me-1">Occupied: <?= $occupied ?></span>
                    <span class="badge bg-primary me-1">Vacant: <?= $available ?></span>
                    <span class="badge bg-warning">Maint: <?= $inMaint ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Row 3: Arrears trend + Maintenance + AR aging donut ──── -->
<div class="row g-3 mb-3">
    <!-- Arrears trend -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-graph-down-arrow me-1 text-danger"></i>Billed vs Collected (6 months)</span>
                <a href="<?= BASE_URL ?>/reports/arrears.php" class="small text-primary">Arrears Analysis &rarr;</a>
            </div>
            <div class="card-body">
                <canvas id="chartArrears" height="100"></canvas>
            </div>
        </div>
    </div>
    <!-- Maintenance breakdown -->
    <div class="col-lg-3">
        <div class="card shadow-sm h-100">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-wrench me-1 text-warning"></i>Maintenance</span>
                <a href="<?= BASE_URL ?>/reports/maintenance.php" class="small text-primary">Details &rarr;</a>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <canvas id="chartMaint" style="max-height:160px"></canvas>
                <div class="mt-2 text-center small">
                    <span class="badge bg-danger me-1">Open: <?= $maintOpen ?></span>
                    <span class="badge bg-warning me-1">In Progress: <?= $maintProg ?></span>
                    <span class="badge bg-success">Done: <?= $maintDone ?></span>
                </div>
            </div>
        </div>
    </div>
    <!-- Occupancy by property -->
    <div class="col-lg-3">
        <div class="card shadow-sm h-100">
            <div class="card-header py-2 fw-semibold py-2">
                <i class="bi bi-building me-1 text-info"></i>By Property
            </div>
            <div class="card-body p-0">
                <?php if ($propLabels): ?>
                <canvas id="chartPropOcc" style="max-height:200px;padding:8px"></canvas>
                <?php else: ?>
                <div class="text-center text-muted py-4 small">No property data.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Row 4: Worst offenders + Expiring leases ─────────────── -->
<div class="row g-3">
    <!-- Worst arrears offenders -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-exclamation-triangle me-1 text-danger"></i>Top Arrears Offenders</span>
                <a href="<?= BASE_URL ?>/reports/arrears.php" class="small text-primary">Full list &rarr;</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>Tenant</th><th>Unit</th><th class="text-end">Outstanding</th><th class="text-end">Days</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($worstOffenders): foreach ($worstOffenders as $w): ?>
                        <tr>
                            <td class="fw-semibold small"><?= e($w['tenant_name']) ?></td>
                            <td class="small text-muted"><?= e($w['unit_number'] ?? '—') ?></td>
                            <td class="text-end text-danger fw-semibold small"><?= money($w['total_outstanding']) ?></td>
                            <td class="text-end">
                                <span class="badge bg-<?= (int)$w['max_days_overdue'] > 60 ? 'danger' : ((int)$w['max_days_overdue'] > 30 ? 'warning' : 'secondary') ?>">
                                    <?= (int)$w['max_days_overdue'] ?>d
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="4" class="text-center text-muted py-3 small">No outstanding arrears.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Expiring leases -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-calendar-x me-1 text-warning"></i>Leases Expiring (90 days)</span>
                <a href="<?= BASE_URL ?>/reports/occupancy.php" class="small text-primary">Full list &rarr;</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>Tenant</th><th>Unit</th><th>Expires</th><th class="text-end">Days Left</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($expiringSoon): foreach ($expiringSoon as $l): ?>
                        <tr>
                            <td class="fw-semibold small"><?= e($l['tenant_name']) ?></td>
                            <td class="small text-muted"><?= e($l['unit_number'] ?? '—') ?></td>
                            <td class="small"><?= fmt_date($l['end_date']) ?></td>
                            <td class="text-end">
                                <span class="badge bg-<?= (int)$l['days_remaining'] <= 14 ? 'danger' : ((int)$l['days_remaining'] <= 30 ? 'warning' : 'info') ?>">
                                    <?= $l['days_remaining'] ?>d
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="4" class="text-center text-muted py-3 small">No leases expiring in 90 days.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// ── Chart.js — shared palette ───────────────────────────────
const BLUE   = '#0d6efd', GREEN  = '#198754', RED = '#dc3545',
      AMBER  = '#ffc107', TEAL   = '#20c997', GRAY = '#adb5bd',
      PURPLE = '#6f42c1', ORANGE = '#fd7e14';

// ── Revenue Bar ──────────────────────────────────────────────
new Chart(document.getElementById('chartRevenue'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($months) ?>,
        datasets: [{
            label: 'Revenue (<?= CURRENCY_SYMBOL ?>)',
            data: <?= json_encode(array_values($monthlyRev)) ?>,
            backgroundColor: BLUE + '99',
            borderColor: BLUE,
            borderWidth: 1,
            borderRadius: 4,
        }],
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => '<?= CURRENCY_SYMBOL ?>' + v.toLocaleString() } },
        },
    },
});

// ── Occupancy Doughnut ───────────────────────────────────────
new Chart(document.getElementById('chartOccupancy'), {
    type: 'doughnut',
    data: {
        labels: ['Occupied', 'Vacant', 'Maintenance'],
        datasets: [{ data: [<?= $occupied ?>, <?= $available ?>, <?= $inMaint ?>], backgroundColor: [GREEN, BLUE, AMBER], borderWidth: 2 }],
    },
    options: { responsive: true, plugins: { legend: { display: false } }, cutout: '65%' },
});

// ── Arrears Line ─────────────────────────────────────────────
new Chart(document.getElementById('chartArrears'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($arTrendLabels) ?>,
        datasets: [
            { label: 'Billed',    data: <?= json_encode($arTrendBilled)  ?>, backgroundColor: BLUE  + '88', borderColor: BLUE,  borderWidth:1, borderRadius:3 },
            { label: 'Collected', data: <?= json_encode($arTrendCollect) ?>, backgroundColor: GREEN + '88', borderColor: GREEN, borderWidth:1, borderRadius:3 },
        ],
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => '<?= CURRENCY_SYMBOL ?>' + v.toLocaleString() } } },
    },
});

// ── Maintenance Doughnut ─────────────────────────────────────
new Chart(document.getElementById('chartMaint'), {
    type: 'doughnut',
    data: {
        labels: ['Open', 'In Progress', 'Completed'],
        datasets: [{ data: [<?= $maintOpen ?>, <?= $maintProg ?>, <?= $maintDone ?>], backgroundColor: [RED, AMBER, GREEN], borderWidth: 2 }],
    },
    options: { responsive: true, plugins: { legend: { display: false } }, cutout: '60%' },
});

<?php if ($propLabels): ?>
// ── Property Occupancy Bar ───────────────────────────────────
new Chart(document.getElementById('chartPropOcc'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($propLabels) ?>,
        datasets: [
            { label: 'Occupied', data: <?= json_encode($propOcc)   ?>, backgroundColor: GREEN + 'bb', borderRadius: 3 },
            { label: 'Vacant',   data: <?= json_encode($propAvail) ?>, backgroundColor: BLUE  + 'bb', borderRadius: 3 },
        ],
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { x: { stacked: false, beginAtZero: true }, y: { stacked: false } },
    },
});
<?php endif; ?>
</script>

<style>
@media print {
    .btn, nav, #sidebarToggle, .sidebar { display: none !important; }
    .card { break-inside: avoid; }
}
</style>
<?php include BASE_PATH . '/includes/footer.php'; ?>
