<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'accountant', 'auditor');

$api      = new ApiClient();
$as_of    = get_param('as_of', date('Y-m-d'));
$property = int_param('property_id', 0);
$bucket   = get_param('bucket', 'all'); // all | current | 1-30 | 31-60 | 61-90 | 90plus

// ── Property dropdown ─────────────────────────────────────────
$prop_res   = $api->get('properties', ['per_page' => 500]);
$properties = $prop_res['data'] ?? [];

// ── Fetch all outstanding invoices (up to 1000) ───────────────
$params = array_filter([
    'status'      => 'outstanding',
    'property_id' => $property ?: null,
    'per_page'    => 1000,
], fn($v) => $v !== null && $v !== '');

$inv_res = $api->get('invoices', $params);
$all_inv = $inv_res['data'] ?? [];

// Filter to as_of date (only invoices with due_date <= as_of)
$rows = array_filter($all_inv, fn($inv) => ($inv['due_date'] ?? '9999-12-31') <= $as_of);
$rows = array_values($rows);

// Compute days_overdue relative to as_of
$as_of_dt = new DateTime($as_of);
foreach ($rows as &$r) {
    $due = new DateTime($r['due_date']);
    $diff = (int)$as_of_dt->diff($due)->format('%r%a');
    $r['days_overdue'] = -$diff; // positive = overdue
    $r['balance']      = (float)$r['balance'] ?? ((float)$r['total_amount'] - (float)$r['amount_paid']);
}
unset($r);

// Sort: most overdue first
usort($rows, fn($a, $b) => $b['days_overdue'] <=> $a['days_overdue']);

// ── Bucket filter ─────────────────────────────────────────────
$buckets = ['current' => [], '1-30' => [], '31-60' => [], '61-90' => [], '90plus' => []];
foreach ($rows as $r) {
    $d = (int)$r['days_overdue'];
    if ($d <= 0)       $buckets['current'][] = $r;
    elseif ($d <= 30)  $buckets['1-30'][]    = $r;
    elseif ($d <= 60)  $buckets['31-60'][]   = $r;
    elseif ($d <= 90)  $buckets['61-90'][]   = $r;
    else               $buckets['90plus'][]  = $r;
}

$display = ($bucket === 'all') ? $rows : ($buckets[$bucket] ?? $rows);

// ── Totals per bucket ─────────────────────────────────────────
$totals      = [];
foreach ($buckets as $key => $list) {
    $totals[$key] = array_sum(array_column($list, 'balance'));
}
$grand_total = array_sum(array_column($rows, 'balance'));

$page_title = 'AR Aging Report';
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0"><i class="bi bi-hourglass-split me-2 text-warning"></i>Accounts Receivable Aging</h5>
    <a href="<?= BASE_URL ?>/accountant/dashboard.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">As Of Date</label>
                <input type="date" name="as_of" class="form-control form-control-sm" value="<?= e($as_of) ?>">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Property</label>
                <select name="property_id" class="form-select form-select-sm">
                    <option value="0">All Properties</option>
                    <?php foreach ($properties as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $property == $p['id'] ? 'selected':'' ?>><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Bucket</label>
                <select name="bucket" class="form-select form-select-sm">
                    <option value="all"     <?= $bucket==='all'    ?'selected':''?>>All</option>
                    <option value="current" <?= $bucket==='current'?'selected':''?>>Current (not yet due)</option>
                    <option value="1-30"    <?= $bucket==='1-30'   ?'selected':''?>>1–30 days</option>
                    <option value="31-60"   <?= $bucket==='31-60'  ?'selected':''?>>31–60 days</option>
                    <option value="61-90"   <?= $bucket==='61-90'  ?'selected':''?>>61–90 days</option>
                    <option value="90plus"  <?= $bucket==='90plus' ?'selected':''?>>90+ days</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm">Filter</button>
                <a href="aging.php" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Aging Summary Cards -->
<div class="row g-3 mb-4">
    <?php
    $card_data = [
        ['current', 'Current',   'kpi-green',  'check-circle'],
        ['1-30',    '1–30 days', 'kpi-yellow', 'clock'],
        ['31-60',   '31–60 days','kpi-orange', 'exclamation-circle'],
        ['61-90',   '61–90 days','kpi-red',    'x-circle'],
        ['90plus',  '90+ days',  'kpi-purple', 'slash-circle'],
    ];
    foreach ($card_data as [$key, $label, $cls, $icon]):
        $pct = $grand_total > 0 ? round($totals[$key] / $grand_total * 100) : 0;
    ?>
    <div class="col-6 col-md">
        <div class="card shadow-sm <?= $cls ?> text-white h-100">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="small opacity-75"><?= $label ?></div>
                    <i class="bi bi-<?= $icon ?>"></i>
                </div>
                <div class="fs-6 fw-bold mt-1"><?= money($totals[$key]) ?></div>
                <div class="small opacity-75"><?= count($buckets[$key]) ?> invoices · <?= $pct ?>%</div>
                <div class="progress mt-2" style="height:3px;background:rgba(255,255,255,.3)">
                    <div class="progress-bar bg-white" style="width:<?= $pct ?>%"></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="col-12 col-md-2">
        <div class="card shadow-sm border-dark h-100">
            <div class="card-body py-3">
                <div class="small text-muted">Total Outstanding</div>
                <div class="fs-5 fw-bold text-dark"><?= money($grand_total) ?></div>
                <div class="small text-muted"><?= count($rows) ?> invoices</div>
            </div>
        </div>
    </div>
</div>

<!-- Aging Chart -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-2"><span class="fw-semibold">Aging Distribution</span></div>
    <div class="card-body">
        <canvas id="agingChart" height="80"></canvas>
    </div>
</div>

<!-- Detail Table -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-2 d-flex justify-content-between">
        <span class="fw-semibold">Outstanding Invoices as of <?= fmt_date($as_of) ?></span>
        <span class="badge bg-secondary"><?= count($display) ?> records</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th>
                        <th>Tenant</th>
                        <th>Unit</th>
                        <th>Due Date</th>
                        <th class="text-end">Invoice Amt</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Balance</th>
                        <th>Days Overdue</th>
                        <th>Bucket</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$display): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No outstanding invoices.</td></tr>
                <?php else: ?>
                    <?php foreach ($display as $row):
                        $d = (int)$row['days_overdue'];
                        if ($d <= 0)      { $b='Current';   $bc='success'; }
                        elseif ($d <= 30) { $b='1–30';      $bc='warning'; }
                        elseif ($d <= 60) { $b='31–60';     $bc='orange'; }
                        elseif ($d <= 90) { $b='61–90';     $bc='danger'; }
                        else              { $b='90+';       $bc='dark'; }
                    ?>
                    <tr>
                        <td><a href="<?= BASE_URL ?>/invoices/view.php?id=<?= $row['id'] ?>" class="fw-semibold"><?= e($row['invoice_number']) ?></a></td>
                        <td><?= e($row['tenant_name'] ?? '—') ?><br><small class="text-muted"><?= e($row['tenant_phone'] ?? '') ?></small></td>
                        <td><?= e($row['property_name'] ?? '') ?> / <?= e($row['unit_number'] ?? '') ?></td>
                        <td><?= fmt_date($row['due_date']) ?></td>
                        <td class="text-end"><?= money($row['total_amount']) ?></td>
                        <td class="text-end text-success"><?= money($row['amount_paid']) ?></td>
                        <td class="text-end fw-bold text-danger"><?= money($row['balance']) ?></td>
                        <td><?= $d > 0 ? $d.' days' : '<span class="text-success">Not due</span>' ?></td>
                        <td><span class="badge bg-<?= $bc ?>"><?= $b ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <?php if ($display): ?>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="4">TOTAL</td>
                        <td class="text-end"><?= money(array_sum(array_column($display,'total_amount'))) ?></td>
                        <td class="text-end text-success"><?= money(array_sum(array_column($display,'amount_paid'))) ?></td>
                        <td class="text-end text-danger"><?= money(array_sum(array_column($display,'balance'))) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('agingChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Current', '1–30 days', '31–60 days', '61–90 days', '90+ days'],
            datasets: [{
                label: 'Outstanding Balance (<?= get_setting('currency_symbol', CURRENCY_SYMBOL) ?>)',
                data: [
                    <?= $totals['current'] ?>,
                    <?= $totals['1-30'] ?>,
                    <?= $totals['31-60'] ?>,
                    <?= $totals['61-90'] ?>,
                    <?= $totals['90plus'] ?>
                ],
                backgroundColor: ['#198754','#ffc107','#fd7e14','#dc3545','#6f42c1'],
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
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
