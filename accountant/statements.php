<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'accountant', 'auditor');

$api      = new ApiClient();
$year     = int_param('year', (int)date('Y'));
$property = int_param('property_id', 0);

// ── Property dropdown ─────────────────────────────────────────
$prop_res   = $api->get('properties', ['per_page' => 500]);
$properties = $prop_res['data'] ?? [];
$years      = range((int)date('Y'), (int)date('Y') - 4);

// ── Financial report for the full year ───────────────────────
$params = array_filter([
    'date_from'   => "$year-01-01",
    'date_to'     => "$year-12-31",
    'property_id' => $property ?: null,
], fn($v) => $v !== null && $v !== '');

$fin_res  = $api->get('reports/financial', $params);
$fin_data = $fin_res['data'] ?? [];

// ── Build monthly income map ──────────────────────────────────
$inc_monthly = array_fill(1, 12, 0.0);
$inc_by_type = [];
foreach ($fin_data['income'] ?? [] as $row) {
    $mo  = (int)substr($row['period'], 5, 2);
    $amt = (float)$row['amount'];
    $inc_monthly[$mo] += $amt;
    $type = $row['category'] ?? 'other';
    $inc_by_type[$type] = ($inc_by_type[$type] ?? 0) + $amt;
}
arsort($inc_by_type);

// ── Build monthly expenses map ────────────────────────────────
$exp_monthly = array_fill(1, 12, 0.0);
$exp_by_cat  = [];
foreach ($fin_data['expenses'] ?? [] as $row) {
    $mo  = (int)substr($row['period'], 5, 2);
    $amt = (float)$row['amount'];
    $exp_monthly[$mo] += $amt;
    $cat = $row['category'] ?? 'other';
    $exp_by_cat[$cat] = ($exp_by_cat[$cat] ?? 0) + $amt;
}
arsort($exp_by_cat);

$total_income   = array_sum($inc_monthly);
$total_expenses = array_sum($exp_monthly);
$net_income     = $total_income - $total_expenses;

$months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

$page_title = 'Income Statement';
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0"><i class="bi bi-file-earmark-bar-graph me-2 text-success"></i>Income Statement</h5>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        <a href="<?= BASE_URL ?>/accountant/dashboard" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-4 d-print-none">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">Year</label>
                <select name="year" class="form-select form-select-sm">
                    <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>" <?= $year==$y?'selected':''?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Property</label>
                <select name="property_id" class="form-select form-select-sm">
                    <option value="0">All Properties</option>
                    <?php foreach ($properties as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $property==$p['id']?'selected':''?>><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm">Apply</button>
            </div>
        </form>
    </div>
</div>

<!-- Print Header -->
<div class="d-none d-print-block text-center mb-4">
    <h4 class="fw-bold"><?= APP_NAME ?></h4>
    <h5>Income Statement — <?= $year ?></h5>
    <?php if ($property): $pname = array_column($properties,'name','id')[$property] ?? ''; ?>
    <p class="text-muted">Property: <?= e($pname) ?></p>
    <?php endif; ?>
    <p class="text-muted small">Generated: <?= date('d M Y H:i') ?></p>
    <hr>
</div>

<!-- Summary KPIs -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm kpi-green text-white">
            <div class="card-body py-3">
                <div class="small opacity-75">Total Income (<?= $year ?>)</div>
                <div class="fs-4 fw-bold"><?= money($total_income) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm kpi-red text-white">
            <div class="card-body py-3">
                <div class="small opacity-75">Total Expenses (<?= $year ?>)</div>
                <div class="fs-4 fw-bold"><?= money($total_expenses) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm <?= $net_income >= 0 ? 'kpi-blue' : 'kpi-orange' ?> text-white">
            <div class="card-body py-3">
                <div class="small opacity-75">Net Income</div>
                <div class="fs-4 fw-bold"><?= money($net_income) ?></div>
                <?php if ($total_income > 0): ?>
                <div class="small opacity-75">Margin: <?= round($net_income/$total_income*100,1) ?>%</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Chart -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-2"><span class="fw-semibold">Monthly Income vs Expenses — <?= $year ?></span></div>
    <div class="card-body"><canvas id="ivsChart" height="90"></canvas></div>
</div>

<!-- Monthly Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-2"><span class="fw-semibold">Monthly Breakdown</span></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Month</th>
                        <?php foreach ($months as $m): ?><th class="text-end"><?= $m ?></th><?php endforeach; ?>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-success">
                        <td class="fw-semibold">Income</td>
                        <?php foreach (range(1,12) as $m): ?><td class="text-end"><?= money($inc_monthly[$m]) ?></td><?php endforeach; ?>
                        <td class="text-end fw-bold"><?= money($total_income) ?></td>
                    </tr>
                    <tr class="table-danger">
                        <td class="fw-semibold">Expenses</td>
                        <?php foreach (range(1,12) as $m): ?><td class="text-end"><?= money($exp_monthly[$m]) ?></td><?php endforeach; ?>
                        <td class="text-end fw-bold"><?= money($total_expenses) ?></td>
                    </tr>
                    <tr class="fw-bold table-light">
                        <td>Net</td>
                        <?php foreach (range(1,12) as $m):
                            $net = $inc_monthly[$m] - $exp_monthly[$m];
                        ?>
                        <td class="text-end <?= $net >= 0 ? 'text-success':'text-danger' ?>"><?= money($net) ?></td>
                        <?php endforeach; ?>
                        <td class="text-end <?= $net_income >= 0 ? 'text-success':'text-danger' ?>"><?= money($net_income) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Income by Type + Expenses by Category -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-2"><span class="fw-semibold">Income by Type</span></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Type</th><th class="text-end">Amount</th><th class="text-end">%</th></tr></thead>
                    <tbody>
                    <?php foreach ($inc_by_type as $type => $amt):
                        $pct = $total_income > 0 ? round($amt/$total_income*100,1) : 0;
                    ?>
                    <tr>
                        <td><?= ucfirst(str_replace('_',' ',$type)) ?></td>
                        <td class="text-end"><?= money($amt) ?></td>
                        <td class="text-end"><?= $pct ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-light fw-bold">
                        <td>Total</td>
                        <td class="text-end"><?= money($total_income) ?></td>
                        <td class="text-end">100%</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-2"><span class="fw-semibold">Expenses by Category</span></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Category</th><th class="text-end">Amount</th><th class="text-end">%</th></tr></thead>
                    <tbody>
                    <?php foreach ($exp_by_cat as $cat => $amt):
                        $pct = $total_expenses > 0 ? round($amt/$total_expenses*100,1) : 0;
                    ?>
                    <tr>
                        <td><?= ucfirst(str_replace('_',' ',$cat)) ?></td>
                        <td class="text-end"><?= money($amt) ?></td>
                        <td class="text-end"><?= $pct ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-light fw-bold">
                        <td>Total</td>
                        <td class="text-end"><?= money($total_expenses) ?></td>
                        <td class="text-end">100%</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Formal P&L Statement -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-dark text-white py-2">
        <span class="fw-semibold">Profit & Loss Statement — <?= $year ?></span>
    </div>
    <div class="card-body">
        <table class="table table-borderless table-sm mb-0" style="max-width:500px">
            <tbody>
                <tr class="fw-bold text-uppercase text-muted"><td>INCOME</td><td></td></tr>
                <?php foreach ($inc_by_type as $type => $amt): ?>
                <tr>
                    <td class="ps-3"><?= ucfirst(str_replace('_',' ',$type)) ?></td>
                    <td class="text-end"><?= money($amt) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="fw-bold border-top">
                    <td>Total Income</td>
                    <td class="text-end text-success"><?= money($total_income) ?></td>
                </tr>
                <tr><td colspan="2" class="pt-3"></td></tr>
                <tr class="fw-bold text-uppercase text-muted"><td>EXPENSES</td><td></td></tr>
                <?php foreach ($exp_by_cat as $cat => $amt): ?>
                <tr>
                    <td class="ps-3"><?= ucfirst(str_replace('_',' ',$cat)) ?></td>
                    <td class="text-end"><?= money($amt) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="fw-bold border-top">
                    <td>Total Expenses</td>
                    <td class="text-end text-danger"><?= money($total_expenses) ?></td>
                </tr>
                <tr><td colspan="2"><hr></td></tr>
                <tr class="fw-bold fs-5">
                    <td>Net Income</td>
                    <td class="text-end <?= $net_income >= 0 ? 'text-success':'text-danger' ?>"><?= money($net_income) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('ivsChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($months) ?>,
            datasets: [
                {
                    label: 'Income',
                    data: <?= json_encode(array_values($inc_monthly)) ?>,
                    backgroundColor: 'rgba(25,135,84,0.7)',
                    borderRadius: 4
                },
                {
                    label: 'Expenses',
                    data: <?= json_encode(array_values($exp_monthly)) ?>,
                    backgroundColor: 'rgba(220,53,69,0.7)',
                    borderRadius: 4
                },
                {
                    label: 'Net',
                    data: <?= json_encode(array_map(fn($m) => round($inc_monthly[$m] - $exp_monthly[$m], 2), range(1,12))) ?>,
                    type: 'line',
                    borderColor: '#0d6efd',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    tension: 0.3,
                    pointRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => '<?= get_setting('currency_symbol', CURRENCY_SYMBOL) ?>' + v.toLocaleString() }
                }
            },
            plugins: { legend: { position: 'top' } }
        }
    });
});
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
