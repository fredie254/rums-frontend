<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'landlord');

$api        = new ApiClient();
$page_title = 'Landlord Dashboard';

$me          = current_user();
$landlord_id = null;

/* Admin/manager can view a specific landlord */
$view_landlord_id = int_param('landlord_id', 0, 'GET');
if (is_manager() && $view_landlord_id) {
    $landlord_id = $view_landlord_id;
} else {
    /* Find landlord by user_id via API */
    $ll_res      = $api->get('landlords', ['user_id' => $me['id'], 'per_page' => 1]);
    $landlord_id = (int)(($ll_res['data'][0]['id'] ?? null));
}

if (!$landlord_id && $me['role'] === 'landlord') {
    set_flash('error', 'No landlord profile linked to your account.');
    redirect(BASE_URL . '/dashboard/index.php');
}

/* ── Load landlord info (includes properties array) ── */
$landlord   = null;
$properties = [];
if ($landlord_id) {
    $ll         = $api->get("landlords/$landlord_id");
    $landlord   = $ll['data'] ?? null;
    $properties = $landlord['properties'] ?? [];
}

/* ── Filters ── */
$year  = int_param('year', (int)date('Y'));
$years = range((int)date('Y'), (int)date('Y') - 4);

/* ── Portfolio KPIs (computed from properties array) ── */
$total_units    = array_sum(array_column($properties, 'total_units'));
$occupied_units = array_sum(array_column($properties, 'occupied_units'));
$vacancy_rate   = $total_units > 0 ? round((($total_units - $occupied_units) / $total_units) * 100, 1) : 0;

/* ── Revenue data from payments API ── */
$pay_res   = $api->get('payments', [
    'landlord_id' => $landlord_id,
    'date_from'   => "$year-01-01",
    'date_to'     => "$year-12-31",
    'per_page'    => 500,
]);
$all_pays    = $pay_res['data'] ?? [];
$year_income = 0;
$month_income = 0;
$inc_map   = array_fill(1, 12, 0.0);
$prop_rev  = []; /* keyed by property_id */
foreach ($all_pays as $p) {
    $year_income += (float)$p['amount'];
    if (date('Y-m') === substr($p['payment_date'], 0, 7)) {
        $month_income += (float)$p['amount'];
    }
    $mo = (int)substr($p['payment_date'], 5, 2);
    if ($mo >= 1 && $mo <= 12) {
        $inc_map[$mo] += (float)$p['amount'];
    }
    $pid = $p['property_id'] ?? null;
    if ($pid) {
        $prop_rev[$pid] = ($prop_rev[$pid] ?? 0) + (float)$p['amount'];
    }
}

/* ── Commission deduction ── */
$commission_rate  = $landlord ? (float)($landlord['commission_rate'] ?? 0) : 0;
$gross_year       = $year_income;
$commission_year  = $gross_year * ($commission_rate / 100);
$net_year         = $gross_year - $commission_year;

$gross_month      = $month_income;
$commission_month = $gross_month * ($commission_rate / 100);
$net_month        = $gross_month - $commission_month;

/* ── Outstanding AR (global via reports/financial) ── */
$fin_res        = $api->get('reports/financial', ['date_from' => "$year-01-01", 'date_to' => "$year-12-31"]);
$outstanding_ar = (float)(($fin_res['data']['summary']['outstanding_ar'] ?? 0));

/* ── Per-property revenue table ── */
/* Build from $properties cross-referenced against $prop_rev aggregated above */
$prop_revenue = [];
foreach ($properties as $prop) {
    $pid  = $prop['id'];
    $rev  = $prop_rev[$pid] ?? 0.0;
    $prop_revenue[] = [
        'id'       => $pid,
        'name'     => $prop['name'],
        'total_u'  => (int)($prop['total_units'] ?? 0),
        'occupied' => (int)($prop['occupied_units'] ?? 0),
        'revenue'  => $rev,
    ];
}
/* Sort descending by revenue */
usort($prop_revenue, fn($a, $b) => $b['revenue'] <=> $a['revenue']);

/* ── Maintenance summary ── */
$maint_res     = $api->get('maintenance/summary');
$maint_raw     = $maint_res['data'] ?? [];
/* Normalise: API may return array of {status, count} objects or a keyed map */
$maint_summary = [];
if ($maint_raw) {
    if (isset($maint_raw[0])) {
        /* Array of objects */
        foreach ($maint_raw as $row) {
            $maint_summary[$row['status']] = (int)($row['count'] ?? $row['cnt'] ?? 0);
        }
    } else {
        /* Already a keyed map */
        foreach ($maint_raw as $k => $v) {
            $maint_summary[$k] = (int)$v;
        }
    }
}

include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-0"><i class="bi bi-building me-2 text-primary"></i>Owner Portfolio Dashboard</h5>
        <?php if ($landlord): ?>
        <small class="text-muted"><?= e($landlord['name']) ?> — Commission rate: <?= $commission_rate ?>%</small>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <form method="GET" class="d-inline">
            <?php if ($view_landlord_id): ?>
            <input type="hidden" name="landlord_id" value="<?= $view_landlord_id ?>">
            <?php endif; ?>
            <select name="year" class="form-select form-select-sm d-inline w-auto" onchange="this.form.submit()">
                <?php foreach ($years as $y): ?>
                <option value="<?= $y ?>" <?= $year==$y?'selected':''?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php if ($me['role'] === 'landlord'): ?>
        <a href="<?= BASE_URL ?>/landlord/statement.php" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-file-earmark-text me-1"></i>Statement
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- KPI Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <div class="card shadow-sm kpi-blue text-white"><div class="card-body py-3 text-center">
            <div class="fs-3 fw-bold"><?= count($properties) ?></div>
            <div class="small opacity-75">Properties</div>
        </div></div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card shadow-sm kpi-teal text-white"><div class="card-body py-3 text-center">
            <div class="fs-3 fw-bold"><?= $total_units ?></div>
            <div class="small opacity-75">Total Units</div>
        </div></div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card shadow-sm kpi-green text-white"><div class="card-body py-3 text-center">
            <div class="fs-3 fw-bold"><?= $occupied_units ?></div>
            <div class="small opacity-75">Occupied</div>
        </div></div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card shadow-sm <?= $vacancy_rate > 20 ? 'kpi-red' : 'kpi-yellow' ?> text-white"><div class="card-body py-3 text-center">
            <div class="fs-3 fw-bold"><?= $vacancy_rate ?>%</div>
            <div class="small opacity-75">Vacancy Rate</div>
        </div></div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card shadow-sm kpi-green text-white"><div class="card-body py-3 text-center">
            <div class="fs-3 fw-bold"><?= money($net_month) ?></div>
            <div class="small opacity-75">Net This Month</div>
        </div></div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card shadow-sm kpi-orange text-white"><div class="card-body py-3 text-center">
            <div class="fs-3 fw-bold"><?= money($outstanding_ar) ?></div>
            <div class="small opacity-75">Outstanding AR</div>
        </div></div>
    </div>
</div>

<!-- Revenue Summary -->
<div class="row g-4 mb-4">
    <div class="col-md-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-2"><span class="fw-semibold">Monthly Income — <?= $year ?></span></div>
            <div class="card-body"><canvas id="incomeChart" height="120"></canvas></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-2"><span class="fw-semibold">Earnings Summary</span></div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td>Gross (Year)</td><td class="text-end fw-bold"><?= money($gross_year) ?></td></tr>
                    <tr class="text-muted"><td>Commission (<?= $commission_rate ?>%)</td><td class="text-end">- <?= money($commission_year) ?></td></tr>
                    <tr class="fw-bold border-top"><td>Net (Year)</td><td class="text-end text-success fs-5"><?= money($net_year) ?></td></tr>
                    <tr><td colspan="2"><hr class="my-2"></td></tr>
                    <tr><td>Gross (Month)</td><td class="text-end"><?= money($gross_month) ?></td></tr>
                    <tr class="text-muted"><td>Commission</td><td class="text-end">- <?= money($commission_month) ?></td></tr>
                    <tr class="fw-bold border-top"><td>Net (Month)</td><td class="text-end text-success"><?= money($net_month) ?></td></tr>
                    <tr><td colspan="2"><hr class="my-2"></td></tr>
                    <tr class="text-danger"><td>Outstanding AR</td><td class="text-end"><?= money($outstanding_ar) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Per-Property Performance -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-2"><span class="fw-semibold">Property Performance — <?= $year ?></span></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Property</th>
                        <th class="text-center">Units</th>
                        <th class="text-center">Occupied</th>
                        <th class="text-center">Occupancy</th>
                        <th class="text-end">Gross Revenue</th>
                        <th class="text-end">Net (after <?= $commission_rate ?>%)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($prop_revenue as $p):
                    $occ_pct = $p['total_u'] > 0 ? round($p['occupied']/$p['total_u']*100) : 0;
                    $net_rev  = $p['revenue'] * (1 - $commission_rate/100);
                ?>
                <tr>
                    <td class="fw-semibold"><?= e($p['name']) ?></td>
                    <td class="text-center"><?= $p['total_u'] ?></td>
                    <td class="text-center"><?= $p['occupied'] ?></td>
                    <td class="text-center">
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:6px">
                                <div class="progress-bar bg-<?= $occ_pct >= 80 ? 'success' : ($occ_pct >= 50 ? 'warning' : 'danger') ?>" style="width:<?= $occ_pct ?>%"></div>
                            </div>
                            <small><?= $occ_pct ?>%</small>
                        </div>
                    </td>
                    <td class="text-end"><?= money($p['revenue']) ?></td>
                    <td class="text-end text-success fw-semibold"><?= money($net_rev) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/properties/view.php?id=<?= $p['id'] ?>" class="btn btn-xs btn-outline-primary">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Maintenance Summary -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-2"><span class="fw-semibold">Maintenance Overview</span></div>
            <div class="card-body">
                <?php
                $m_labels = ['open'=>'Open','in_progress'=>'In Progress','completed'=>'Completed','resolved'=>'Resolved','pending'=>'Pending'];
                $m_colors = ['open'=>'warning','in_progress'=>'primary','completed'=>'success','resolved'=>'success','pending'=>'secondary'];
                foreach ($m_labels as $key => $label):
                    $cnt = $maint_summary[$key] ?? 0;
                ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span><?= $label ?></span>
                    <span class="badge bg-<?= $m_colors[$key] ?>"><?= $cnt ?></span>
                </div>
                <?php endforeach; ?>
                <a href="<?= BASE_URL ?>/maintenance/index.php" class="btn btn-sm btn-outline-warning mt-2 w-100">
                    <i class="bi bi-wrench me-1"></i>View All Requests
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-2"><span class="fw-semibold">Properties List</span></div>
            <div class="list-group list-group-flush">
                <?php foreach (array_slice($properties, 0, 6) as $p):
                    $occ = $p['total_units'] > 0 ? round($p['occupied_units']/$p['total_units']*100) : 0;
                ?>
                <a href="<?= BASE_URL ?>/properties/view.php?id=<?= $p['id'] ?>" class="list-group-item list-group-item-action py-2">
                    <div class="d-flex justify-content-between">
                        <span class="fw-semibold"><?= e($p['name']) ?></span>
                        <small class="text-muted"><?= $p['occupied_units'] ?>/<?= $p['total_units'] ?> units</small>
                    </div>
                    <div class="progress mt-1" style="height:3px">
                        <div class="progress-bar bg-<?= $occ >= 80?'success':($occ>=50?'warning':'danger') ?>" style="width:<?= $occ ?>%"></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('incomeChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
            datasets: [
                {
                    label: 'Gross',
                    data: <?= json_encode(array_values($inc_map)) ?>,
                    backgroundColor: 'rgba(13,110,253,0.6)',
                    borderRadius: 4
                },
                {
                    label: 'Net (after commission)',
                    data: <?= json_encode(array_map(fn($v) => round($v * (1 - $commission_rate/100), 2), array_values($inc_map))) ?>,
                    backgroundColor: 'rgba(25,135,84,0.6)',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => '<?= get_setting('currency_symbol', CURRENCY_SYMBOL) ?>' + v.toLocaleString() }
                }
            }
        }
    });
});
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
