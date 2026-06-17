<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api     = new ApiClient();
$year    = int_param('year') ?: (int)date('Y');
$prop_id = int_param('property_id');

// ── Property dropdown ─────────────────────────────────────────
$prop_res   = $api->get('properties', ['per_page' => 500]);
$properties = $prop_res['data'] ?? [];

$fin_params = array_filter([
    'date_from'   => "$year-01-01",
    'date_to'     => "$year-12-31",
    'property_id' => $prop_id ?: null,
], fn($v) => $v !== null && $v !== '');

// ── Parallel-ish API calls ────────────────────────────────────
$occ_res   = $api->get('reports/occupancy', $prop_id ? ['property_id' => $prop_id] : []);
$fin_res   = $api->get('reports/financial', $fin_params);
$maint_res = $api->get('reports/maintenance', $fin_params);

$occ_data   = $occ_res['data']   ?? [];
$fin_data   = $fin_res['data']   ?? [];
$maint_data = $maint_res['data'] ?? [];

// ── Occupancy data ────────────────────────────────────────────
$totals      = $occ_data['totals']      ?? ['total' => 0, 'occupied' => 0, 'available' => 0, 'maintenance' => 0];
$occ_by_prop = $occ_data['by_property'] ?? [];
$type_dist   = array_map(fn($t) => ['label' => strtoupper($t['unit_type']), 'cnt' => $t['total']], $occ_data['by_type'] ?? []);

$total_units    = (int)($totals['total']    ?? 0);
$occupied_units = (int)($totals['occupied'] ?? 0);
$occupancy_rate = $total_units > 0 ? round($occupied_units / $total_units * 100, 1) : 0;

// ── Financial summary ─────────────────────────────────────────
$fin_summary = $fin_data['summary'] ?? [];
$total_revenue = (float)($fin_summary['total_income'] ?? 0);

// ── Monthly income by month ───────────────────────────────────
$monthly_collected = array_fill(0, 12, 0.0);
foreach ($fin_data['income'] ?? [] as $row) {
    $mo = (int)substr($row['period'], 5, 2) - 1;
    if ($mo >= 0 && $mo < 12) $monthly_collected[$mo] += (float)$row['amount'];
}

// ── Revenue by property (from financial per-property call) ────
// Build from occ_by_prop (occupancy already per-property),
// and aggregate revenue per property from payments
$rev_by_prop = [];
$pay_res = $api->get('payments', array_merge($fin_params, ['per_page' => 500]));
foreach ($pay_res['data'] ?? [] as $p) {
    $prop = $p['property_name'] ?? 'Unknown';
    $rev_by_prop[$prop] = ($rev_by_prop[$prop] ?? 0) + (float)$p['amount'];
}
arsort($rev_by_prop);

// ── Avg rent from financial / occupancy ──────────────────────
$avg_rent = ($occupied_units > 0 && $total_revenue > 0)
    ? round($total_revenue / max($occupied_units * 12, 1), 2)
    : 0;

// ── Maintenance data ──────────────────────────────────────────
$total_maint = (float)($maint_data['summary']['total_cost'] ?? 0);
$maint_by_prop = $maint_data['by_property'] ?? [];

// ── Monthly occupancy trend (from lease trend data) ───────────
$trend_raw  = $occ_data['trend'] ?? [];
$monthly_occ = array_fill(0, 12, 0);
foreach ($trend_raw as $t) {
    $mo = (int)substr($t['month'], 5, 2) - 1;
    if ($mo >= 0 && $mo < 12) $monthly_occ[$mo] = (int)$t['new_leases'];
}

// Expected monthly rent (use total_revenue / 12 as proxy)
$expected_monthly = $total_revenue > 0 ? round($total_revenue / 12, 2) : 0;

// ── Top 15 revenue by unit (approximate from payments) ───────
$rev_by_unit = [];
foreach ($pay_res['data'] ?? [] as $p) {
    $label = ($p['property_name'] ?? '') . ' / ' . ($p['unit_number'] ?? '');
    $rev_by_unit[$label] = ($rev_by_unit[$label] ?? 0) + (float)$p['amount'];
}
arsort($rev_by_unit);
$rev_by_unit = array_slice($rev_by_unit, 0, 15, true);

$page_title = 'Unit Performance Analytics';
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-0"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Unit Performance Analytics</h5>
        <small class="text-muted">Year <?= $year ?><?= $prop_id ? ' — ' . e(array_column($properties,'name','id')[$prop_id] ?? '') : '' ?></small>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <form method="GET" class="d-flex gap-2">
            <select name="property_id" class="form-select form-select-sm" style="width:180px">
                <option value="">All Properties</option>
                <?php foreach ($properties as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $prop_id==$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="number" name="year" class="form-control form-control-sm" value="<?= $year ?>" style="width:85px" min="2020">
            <button class="btn btn-sm btn-outline-primary">Apply</button>
        </form>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button>
    </div>
</div>

<!-- ── KPI Summary ───────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-door-open"></i></div>
            <div class="kpi-value"><?= $total_units ?></div>
            <div class="kpi-label">Total Units</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-person-check"></i></div>
            <div class="kpi-value"><?= $occupied_units ?></div>
            <div class="kpi-label">Occupied</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="kpi-card kpi-purple">
            <div class="kpi-icon"><i class="bi bi-percent"></i></div>
            <div class="kpi-value"><?= $occupancy_rate ?>%</div>
            <div class="kpi-label">Occupancy Rate</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="kpi-card kpi-teal">
            <div class="kpi-icon"><i class="bi bi-cash-coin"></i></div>
            <div class="kpi-value"><?= money($total_revenue) ?></div>
            <div class="kpi-label">Revenue <?= $year ?></div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="kpi-card kpi-yellow">
            <div class="kpi-icon"><i class="bi bi-tag"></i></div>
            <div class="kpi-value"><?= money($avg_rent) ?></div>
            <div class="kpi-label">Avg Monthly Rent</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-wrench"></i></div>
            <div class="kpi-value"><?= money($total_maint) ?></div>
            <div class="kpi-label">Maintenance Cost</div>
        </div>
    </div>
</div>

<!-- ── Row 1: Occupancy by Property + Revenue by Property ───── -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-building me-2 text-primary"></i>Occupancy by Property
            </div>
            <div class="card-body">
                <canvas id="occByPropChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-currency-exchange me-2 text-success"></i>Revenue by Property (<?= $year ?>)
            </div>
            <div class="card-body">
                <canvas id="revByPropChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ── Row 2: Monthly Occupancy Trend + Unit Type ─────────── -->
<div class="row g-3 mb-4">
    <div class="col-md-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-graph-up me-2 text-info"></i>New Leases Trend (<?= $year ?>)
            </div>
            <div class="card-body">
                <canvas id="occTrendChart" height="160"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-pie-chart me-2 text-warning"></i>Unit Type Distribution
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <canvas id="typeDistChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ── Row 3: Rent Collection Rate + Maintenance Cost ────────── -->
<div class="row g-3 mb-4">
    <div class="col-md-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-bar-chart me-2 text-success"></i>Monthly Rent Collection (<?= $year ?>)
            </div>
            <div class="card-body">
                <canvas id="collectionChart" height="160"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-tools me-2 text-danger"></i>Maintenance Cost by Property (<?= $year ?>)
            </div>
            <div class="card-body">
                <canvas id="maintCostChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ── Row 4: Top Revenue Units ────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-trophy me-2 text-warning"></i>Top 15 Revenue-Generating Units (<?= $year ?>)
            </div>
            <div class="card-body">
                <canvas id="topUnitsChart" height="80"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ── Row 5: Property Summary Table ─────────────────────── -->
<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-table me-2 text-secondary"></i>Property Occupancy Summary
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Property</th><th class="text-center">Total</th><th class="text-center">Occupied</th><th class="text-center">Available</th><th class="text-center">Maintenance</th><th>Occupancy Rate</th></tr>
            </thead>
            <tbody>
            <?php foreach ($occ_by_prop as $row):
                $rate = (int)($row['total_units'] ?? 0) > 0 ? round($row['occupied'] / $row['total_units'] * 100, 1) : 0;
                $bar_color = $rate >= 80 ? 'success' : ($rate >= 50 ? 'warning' : 'danger');
            ?>
            <tr>
                <td class="fw-semibold"><?= e($row['property_name']) ?></td>
                <td class="text-center"><?= $row['total_units'] ?></td>
                <td class="text-center text-success fw-semibold"><?= $row['occupied'] ?></td>
                <td class="text-center text-primary"><?= $row['available'] ?></td>
                <td class="text-center text-warning"><?= $row['maintenance'] ?></td>
                <td style="min-width:180px">
                    <div class="d-flex align-items-center gap-2">
                        <div class="progress flex-grow-1" style="height:8px">
                            <div class="progress-bar bg-<?= $bar_color ?>" style="width:<?= $rate ?>%"></div>
                        </div>
                        <span class="small fw-semibold" style="min-width:38px"><?= $rate ?>%</span>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const CHART_COLORS = ['#1a56db','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316','#84cc16','#ec4899','#14b8a6'];

const occByPropData = {
    labels:   <?= json_encode(array_column($occ_by_prop, 'property_name')) ?>,
    occupied: <?= json_encode(array_map(fn($r) => (int)$r['occupied'], $occ_by_prop)) ?>,
    available:<?= json_encode(array_map(fn($r) => (int)$r['available'], $occ_by_prop)) ?>,
    maint:    <?= json_encode(array_map(fn($r) => (int)$r['maintenance'], $occ_by_prop)) ?>
};
const revByPropData = {
    labels: <?= json_encode(array_keys($rev_by_prop)) ?>,
    data:   <?= json_encode(array_values($rev_by_prop)) ?>
};
const occTrendData   = <?= json_encode(array_values($monthly_occ)) ?>;
const typeDistData   = {
    labels: <?= json_encode(array_column($type_dist, 'label')) ?>,
    data:   <?= json_encode(array_map(fn($r) => (int)$r['cnt'], $type_dist)) ?>
};
const collectionData = {
    collected: <?= json_encode(array_values($monthly_collected)) ?>,
    expected:  <?= $expected_monthly ?>
};
const maintCostData  = {
    labels: <?= json_encode(array_column($maint_by_prop, 'name')) ?>,
    data:   <?= json_encode(array_map(fn($r) => (float)$r['cost'], $maint_by_prop)) ?>
};
const topUnitsData   = {
    labels:  <?= json_encode(array_keys($rev_by_unit)) ?>,
    revenue: <?= json_encode(array_values($rev_by_unit)) ?>
};

new Chart(document.getElementById('occByPropChart'), {
    type: 'bar',
    data: {
        labels: occByPropData.labels,
        datasets: [
            { label: 'Occupied',    data: occByPropData.occupied,  backgroundColor: '#10b981' },
            { label: 'Available',   data: occByPropData.available, backgroundColor: '#3b82f6' },
            { label: 'Maintenance', data: occByPropData.maint,     backgroundColor: '#f59e0b' },
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } } }
});

new Chart(document.getElementById('revByPropChart'), {
    type: 'bar',
    data: { labels: revByPropData.labels, datasets: [{ label: 'Revenue (Ksh)', data: revByPropData.data, backgroundColor: CHART_COLORS, borderRadius: 5 }] },
    options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { callback: v => 'Ksh ' + Number(v).toLocaleString() } } } }
});

new Chart(document.getElementById('occTrendChart'), {
    type: 'line',
    data: { labels: MONTHS, datasets: [{ label: 'New Leases', data: occTrendData, borderColor: '#1a56db', backgroundColor: 'rgba(26,86,219,.08)', borderWidth: 2.5, pointRadius: 5, fill: true, tension: 0.4 }] },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});

new Chart(document.getElementById('typeDistChart'), {
    type: 'doughnut',
    data: { labels: typeDistData.labels, datasets: [{ data: typeDistData.data, backgroundColor: CHART_COLORS, borderWidth: 2 }] },
    options: { responsive: true, cutout: '58%', plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
});

new Chart(document.getElementById('collectionChart'), {
    type: 'bar',
    data: {
        labels: MONTHS,
        datasets: [
            { label: 'Collected', data: collectionData.collected, backgroundColor: 'rgba(16,185,129,.8)', borderRadius: 4 },
            { label: 'Expected', data: Array(12).fill(collectionData.expected), type: 'line', borderColor: '#ef4444', borderDash: [6,3], borderWidth: 2, pointRadius: 0, fill: false }
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { callback: v => 'Ksh ' + Number(v).toLocaleString() } } } }
});

new Chart(document.getElementById('maintCostChart'), {
    type: 'bar',
    data: { labels: maintCostData.labels, datasets: [{ label: 'Cost (Ksh)', data: maintCostData.data, backgroundColor: '#ef4444', borderRadius: 5 }] },
    options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { callback: v => 'Ksh ' + Number(v).toLocaleString() } } } }
});

new Chart(document.getElementById('topUnitsChart'), {
    type: 'bar',
    data: { labels: topUnitsData.labels, datasets: [{ label: 'Revenue (Ksh)', data: topUnitsData.revenue, backgroundColor: '#1a56db', borderRadius: 4 }] },
    options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { callback: v => 'Ksh ' + Number(v).toLocaleString() } } } }
});
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
