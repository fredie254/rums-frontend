<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'accountant');

$api   = new ApiClient();
$month = max(1, min(12, int_param('month') ?: (int)date('m')));
$year  = int_param('year') ?: (int)date('Y');

$period_start = sprintf('%04d-%02d-01', $year, $month);
$period_end   = date('Y-m-t', strtotime($period_start));

// ── Income (payments summary) ─────────────────────────────────
$pay_sum  = $api->get('payments/summary', ['date_from' => $period_start, 'date_to' => $period_end]);
$income   = (float)($pay_sum['data']['total'] ?? 0);

// ── Expenses summary ──────────────────────────────────────────
$exp_sum  = $api->get('expenses/summary', ['date_from' => $period_start, 'date_to' => $period_end]);
$expenses = (float)($exp_sum['data']['paid'] ?? 0);
$pend_exp = (int)($exp_sum['data']['pending'] ?? 0); // count not sum; re-fetch count separately
$net      = $income - $expenses;

// Pending expense count (number of records, not amount)
$pend_res  = $api->get('expenses', ['status' => 'pending', 'date_from' => '2000-01-01', 'date_to' => $period_end, 'per_page' => 1]);
$pend_exp  = (int)($pend_res['meta']['total'] ?? 0);

// ── Accounts Receivable ───────────────────────────────────────
$kpi_res     = $api->get('reports/dashboard');
$kpi         = $kpi_res['data'] ?? [];
$outstanding = (float)($kpi['accounts_receivable']['amount'] ?? 0);
$overdue_cnt = (int)($kpi['accounts_receivable']['count'] ?? 0);
$overdue_amt = (float)($kpi['accounts_receivable']['amount'] ?? 0);

// ── Payment breakdown by method & type ───────────────────────
$fin_res  = $api->get('reports/financial', ['date_from' => $period_start, 'date_to' => $period_end]);
$fin_data = $fin_res['data'] ?? [];

// Aggregate income by payment_type and method from financial report
$by_type   = [];
$by_method = [];
foreach ($fin_data['income'] ?? [] as $row) {
    $type   = $row['payment_type']   ?? 'other';
    $method = $row['payment_method'] ?? 'other';
    $amt    = (float)$row['amount'];
    $by_type[$type]   = ($by_type[$type]   ?? 0) + $amt;
    $by_method[$method] = ($by_method[$method] ?? 0) + $amt;
}
arsort($by_type);
arsort($by_method);

// ── AR Aging (from invoices endpoint by status) ───────────────
// Fetch outstanding invoices to compute aging buckets client-side
$aging_res  = $api->get('invoices', ['status' => 'outstanding', 'per_page' => 500]);
$aging_rows = $aging_res['data'] ?? [];

$aging = ['current_amt' => 0, 'aged_30' => 0, 'aged_60' => 0, 'aged_90' => 0, 'aged_90plus' => 0];
$today = new DateTime();
foreach ($aging_rows as $inv) {
    $bal  = isset($inv['balance']) ? (float)$inv['balance'] : ((float)($inv['total_amount'] ?? 0) - (float)($inv['amount_paid'] ?? 0));
    if ($bal <= 0) continue;
    try { $due = new DateTime($inv['due_date'] ?? date('Y-m-d')); } catch (Throwable $e) { continue; }
    $days = (int)$today->diff($due)->format('%r%a'); // negative = overdue
    if ($days >= 0)         $aging['current_amt'] += $bal;
    elseif ($days >= -30)   $aging['aged_30']     += $bal;
    elseif ($days >= -60)   $aging['aged_60']     += $bal;
    elseif ($days >= -90)   $aging['aged_90']     += $bal;
    else                    $aging['aged_90plus'] += $bal;
}

// ── Recent Transactions ───────────────────────────────────────
$recent_res = $api->get('payments', ['per_page' => 10]);
$recent_tx  = $recent_res['data'] ?? [];

$page_title = 'Accounting Dashboard';
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-0"><i class="bi bi-calculator me-2 text-primary"></i>Accounting Dashboard</h5>
        <small class="text-muted">Period: <?= month_name($month) ?> <?= $year ?></small>
    </div>
    <div class="d-flex gap-2">
        <form method="GET" class="d-flex gap-2">
            <select name="month" class="form-select form-select-sm" style="width:120px">
                <?php for ($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $month==$m?'selected':'' ?>><?= month_name($m) ?></option><?php endfor; ?>
            </select>
            <input type="number" name="year" class="form-control form-control-sm" value="<?= $year ?>" style="width:85px">
            <button class="btn btn-sm btn-outline-primary">Apply</button>
        </form>
        <a href="<?= BASE_URL ?>/accountant/reconciliation" class="btn btn-sm btn-outline-success">
            <i class="bi bi-check2-all me-1"></i>Reconcile
        </a>
    </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-arrow-down-circle"></i></div>
            <div class="kpi-value"><?= money($income) ?></div>
            <div class="kpi-label">Income</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-arrow-up-circle"></i></div>
            <div class="kpi-value"><?= money($expenses) ?></div>
            <div class="kpi-label">Expenses</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="kpi-card <?= $net >= 0 ? 'kpi-teal' : 'kpi-orange' ?>">
            <div class="kpi-icon"><i class="bi bi-wallet2"></i></div>
            <div class="kpi-value"><?= money($net) ?></div>
            <div class="kpi-label">Net <?= $net >= 0 ? 'Income' : 'Loss' ?></div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="kpi-card kpi-yellow">
            <div class="kpi-icon"><i class="bi bi-clock-history"></i></div>
            <div class="kpi-value"><?= money($outstanding) ?></div>
            <div class="kpi-label">Accounts Receivable</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-exclamation-circle"></i></div>
            <div class="kpi-value"><?= $overdue_cnt ?></div>
            <div class="kpi-label">Overdue Invoices</div>
            <div class="kpi-sub"><?= money($overdue_amt) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-receipt-cutoff"></i></div>
            <div class="kpi-value"><?= $pend_exp ?></div>
            <div class="kpi-label">Pending Expenses</div>
        </div>
    </div>
</div>

<!-- AR Aging Summary -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-hourglass-split me-2 text-warning"></i>Accounts Receivable Aging</div>
    <div class="card-body py-3">
        <div class="row g-3 text-center">
            <?php
            $aging_buckets = [
                ['Current',   $aging['current_amt'], 'success'],
                ['1–30 days', $aging['aged_30'],     'info'],
                ['31–60 days',$aging['aged_60'],     'warning'],
                ['61–90 days',$aging['aged_90'],     'orange'],
                ['90+ days',  $aging['aged_90plus'], 'danger'],
            ];
            $aging_total = array_sum(array_column($aging_buckets, 1));
            foreach ($aging_buckets as [$label, $amt, $cls]):
                $pct = $aging_total > 0 ? round($amt / $aging_total * 100) : 0;
            ?>
            <div class="col">
                <div class="border rounded p-2">
                    <div class="small text-muted mb-1"><?= $label ?></div>
                    <div class="fw-bold text-<?= $cls ?>"><?= money($amt) ?></div>
                    <div class="progress mt-1" style="height:4px"><div class="progress-bar bg-<?= $cls ?>" style="width:<?= $pct ?>%"></div></div>
                    <small class="text-muted"><?= $pct ?>%</small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-end mt-2">
            <a href="<?= BASE_URL ?>/accountant/aging" class="btn btn-sm btn-outline-warning">
                <i class="bi bi-table me-1"></i>Full Aging Report
            </a>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Income Breakdown by Type -->
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-pie-chart me-1 text-success"></i>Income by Type</div>
            <div class="card-body">
                <canvas id="incomeTypeChart" height="200"></canvas>
                <table class="table table-sm mt-2 mb-0">
                    <?php foreach ($by_type as $type => $total): ?>
                    <tr><td><?= ucfirst($type) ?></td><td class="text-end fw-semibold"><?= money($total) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
    <!-- Income Breakdown by Method -->
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-pie-chart me-1 text-info"></i>Income by Method</div>
            <div class="card-body">
                <canvas id="incomeMethodChart" height="200"></canvas>
                <table class="table table-sm mt-2 mb-0">
                    <?php foreach ($by_method as $method => $total): ?>
                    <tr><td><?= ucfirst(str_replace('_',' ',$method)) ?></td><td class="text-end fw-semibold"><?= money($total) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
    <!-- Income vs Expense bar -->
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-bar-chart me-1 text-primary"></i>Income vs Expenses</div>
            <div class="card-body">
                <canvas id="incExpChart" height="220"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Quick Actions</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <a href="<?= BASE_URL ?>/accountant/reconciliation" class="btn btn-outline-success btn-sm"><i class="bi bi-check2-all me-1"></i>Payment Reconciliation</a>
                <a href="<?= BASE_URL ?>/accountant/aging" class="btn btn-outline-warning btn-sm"><i class="bi bi-hourglass me-1"></i>AR Aging Report</a>
                <a href="<?= BASE_URL ?>/accountant/expenses" class="btn btn-outline-danger btn-sm"><i class="bi bi-receipt-cutoff me-1"></i>Manage Expenses</a>
                <a href="<?= BASE_URL ?>/accountant/statements" class="btn btn-outline-primary btn-sm"><i class="bi bi-file-earmark-bar-graph me-1"></i>Income Statement</a>
                <a href="<?= BASE_URL ?>/invoices/index?status=overdue" class="btn btn-outline-dark btn-sm"><i class="bi bi-exclamation-triangle me-1"></i>Overdue Invoices</a>
                <a href="<?= BASE_URL ?>/payments/index" class="btn btn-outline-secondary btn-sm"><i class="bi bi-cash-coin me-1"></i>All Payments</a>
            </div>
        </div>
    </div>
</div>

<!-- Recent Transactions -->
<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-1 text-secondary"></i>Recent Transactions</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Reference</th><th>Tenant</th><th>Unit</th><th>Amount</th><th>Type</th><th>Method</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recent_tx as $tx): ?>
            <tr>
                <td><a href="<?= BASE_URL ?>/payments/view?id=<?= $tx['id'] ?>"><code class="small"><?= e($tx['payment_ref'] ?? '—') ?></code></a></td>
                <td><?= e($tx['tenant_name'] ?? '—') ?></td>
                <td><?= e($tx['unit_number'] ?? '—') ?></td>
                <td class="fw-semibold <?= ($tx['status']??'') === 'completed' ? 'text-success' : '' ?>"><?= money($tx['amount']) ?></td>
                <td><?= ucfirst($tx['payment_type'] ?? '') ?></td>
                <td><?= ucfirst(str_replace('_',' ',$tx['payment_method'] ?? '')) ?></td>
                <td><?= fmt_date($tx['payment_date']) ?></td>
                <td><?= payment_badge($tx['status'] ?? 'pending') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$recent_tx): ?>
            <tr><td colspan="8" class="text-center text-muted py-3">No transactions yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const CHART_COLORS = ['#1a56db','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316'];

new Chart(document.getElementById('incomeTypeChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(fn($k) => ucfirst($k), array_keys($by_type))) ?>,
        datasets: [{ data: <?= json_encode(array_values($by_type)) ?>, backgroundColor: CHART_COLORS, borderWidth: 2 }]
    },
    options: { responsive: true, cutout: '55%', plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } } }
});

new Chart(document.getElementById('incomeMethodChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(fn($k) => ucfirst(str_replace('_',' ',$k)), array_keys($by_method))) ?>,
        datasets: [{ data: <?= json_encode(array_values($by_method)) ?>, backgroundColor: CHART_COLORS, borderWidth: 2 }]
    },
    options: { responsive: true, cutout: '55%', plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } } }
});

new Chart(document.getElementById('incExpChart'), {
    type: 'bar',
    data: {
        labels: ['<?= month_name($month) ?> <?= $year ?>'],
        datasets: [
            { label: 'Income',   data: [<?= $income ?>],   backgroundColor: '#10b981', borderRadius: 6 },
            { label: 'Expenses', data: [<?= $expenses ?>],  backgroundColor: '#ef4444', borderRadius: 6 },
            { label: 'Net',      data: [<?= $net ?>],       backgroundColor: <?= $net >= 0 ? "'#1a56db'" : "'#f97316'" ?>, borderRadius: 6 }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => 'Ksh ' + Number(v).toLocaleString() } } }
    }
});
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
