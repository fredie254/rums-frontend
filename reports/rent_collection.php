<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'accountant');

$api     = new ApiClient();
$month   = int_param('month') ?: (int)date('n');
$year    = int_param('year')  ?: (int)date('Y');
$prop_id = int_param('property_id');

// ── Clamp month to valid range ────────────────────────────────
$month = max(1, min(12, $month));

// ── Property dropdown ─────────────────────────────────────────
$prop_res   = $api->get('properties', ['per_page' => 500]);
$properties = $prop_res['data'] ?? [];

// ── Rent collection summary ───────────────────────────────────
$rc_params = array_filter([
    'year'        => $year,
    'month'       => $month,
    'property_id' => $prop_id ?: null,
], fn($v) => $v !== null && $v !== '');

$rc_res  = $api->get('reports/rent-collection', $rc_params);
$rc_data = $rc_res['data'] ?? [];

$period          = $rc_data['period']          ?? "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT);
$expected        = (float)($rc_data['expected']        ?? 0);
$collected       = (float)($rc_data['collected']       ?? 0);
$outstanding     = (float)($rc_data['outstanding']     ?? 0);
$collection_rate = (float)($rc_data['collection_rate'] ?? 0);

// ── Invoice detail table ──────────────────────────────────────
$inv_params = array_filter([
    'month'       => $month,
    'year'        => $year,
    'property_id' => $prop_id ?: null,
    'per_page'    => 500,
], fn($v) => $v !== null && $v !== '');

$inv_res  = $api->get('invoices', $inv_params);
$invoices = $inv_res['data'] ?? [];

// ── Status → badge color map ──────────────────────────────────
function inv_row_class(string $status): string {
    return match ($status) {
        'paid'                    => 'success',
        'partial'                 => 'warning',
        'unpaid', 'overdue', 'sent', 'draft' => 'danger',
        default                   => 'secondary',
    };
}

$page_title = 'Rent Collection Report';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0">
        <i class="bi bi-cash-stack me-2 text-info"></i>Rent Collection &mdash;
        <?= month_name($month) ?> <?= $year ?>
    </h5>
    <div class="d-flex gap-2">
        <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
            <select name="month" class="form-select form-select-sm" style="width:130px">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>>
                        <?= month_name($m) ?>
                    </option>
                <?php endfor; ?>
            </select>
            <input type="number" name="year" class="form-control form-control-sm"
                   value="<?= $year ?>" min="2000" max="2099" style="width:90px">
            <select name="property_id" class="form-select form-select-sm" style="width:160px">
                <option value="">All Properties</option>
                <?php foreach ($properties as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $prop_id == $p['id'] ? 'selected' : '' ?>>
                        <?= e($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-primary">
                <i class="bi bi-funnel me-1"></i>Filter
            </button>
        </form>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-printer"></i>
        </button>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-receipt"></i></div>
            <div class="kpi-value"><?= money($expected) ?></div>
            <div class="kpi-label">Expected Rent</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-cash-coin"></i></div>
            <div class="kpi-value"><?= money($collected) ?></div>
            <div class="kpi-label">Collected</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-exclamation-circle"></i></div>
            <div class="kpi-value"><?= money($outstanding) ?></div>
            <div class="kpi-label">Outstanding</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-teal">
            <div class="kpi-icon"><i class="bi bi-percent"></i></div>
            <div class="kpi-value"><?= number_format($collection_rate, 1) ?>%</div>
            <div class="kpi-label">Collection Rate</div>
        </div>
    </div>
</div>

<!-- Collection Rate Progress Bar -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="d-flex justify-content-between mb-1">
            <span class="fw-semibold small">Collection Progress</span>
            <span class="fw-bold small text-<?= $collection_rate >= 80 ? 'success' : ($collection_rate >= 50 ? 'warning' : 'danger') ?>">
                <?= number_format($collection_rate, 1) ?>%
            </span>
        </div>
        <div class="progress" style="height:18px; border-radius:9px">
            <div class="progress-bar bg-<?= $collection_rate >= 80 ? 'success' : ($collection_rate >= 50 ? 'warning' : 'danger') ?> fw-semibold"
                 role="progressbar"
                 style="width:<?= min(100, $collection_rate) ?>%; border-radius:9px"
                 aria-valuenow="<?= $collection_rate ?>"
                 aria-valuemin="0" aria-valuemax="100">
                <?= $collection_rate >= 10 ? number_format($collection_rate, 1) . '%' : '' ?>
            </div>
        </div>
        <div class="d-flex justify-content-between mt-1">
            <small class="text-muted"><?= money($collected) ?> collected</small>
            <small class="text-muted"><?= money($outstanding) ?> remaining</small>
        </div>
    </div>
</div>

<!-- Invoice Detail Table -->
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-table text-secondary me-1"></i>Invoice Details &mdash;
            <?= month_name($month) ?> <?= $year ?>
        </h6>
        <span class="badge bg-secondary"><?= count($invoices) ?> invoice<?= count($invoices) !== 1 ? 's' : '' ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Tenant</th>
                    <th>Unit</th>
                    <th>Property</th>
                    <th class="text-end">Rent Amount</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Balance</th>
                    <th>Status</th>
                    <th>Due Date</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($invoices): $sn = 1; foreach ($invoices as $inv):
                $status      = $inv['status'] ?? 'unpaid';
                $rent_amount = (float)($inv['amount'] ?? 0);
                $amount_paid = (float)($inv['amount_paid'] ?? 0);
                $balance     = (float)($inv['balance'] ?? ($rent_amount - $amount_paid));
                $row_cls     = inv_row_class($status);
            ?>
                <tr>
                    <td><?= $sn++ ?></td>
                    <td class="fw-semibold"><?= e($inv['tenant_name'] ?? '—') ?></td>
                    <td><?= e($inv['unit_number'] ?? '—') ?></td>
                    <td><?= e($inv['property_name'] ?? '—') ?></td>
                    <td class="text-end"><?= money($rent_amount) ?></td>
                    <td class="text-end text-success fw-semibold"><?= money($amount_paid) ?></td>
                    <td class="text-end <?= $balance > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>">
                        <?= money($balance) ?>
                    </td>
                    <td>
                        <span class="badge bg-<?= $row_cls ?>">
                            <?= ucfirst($status) ?>
                        </span>
                    </td>
                    <td><?= fmt_date($inv['due_date'] ?? null) ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-4 d-block mb-1"></i>
                        No invoices found for <?= month_name($month) ?> <?= $year ?>.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
            <?php if ($invoices): ?>
            <tfoot class="table-light fw-semibold">
                <tr>
                    <td colspan="4" class="text-end">Totals (<?= count($invoices) ?> rows)</td>
                    <td class="text-end"><?= money(array_sum(array_column($invoices, 'amount'))) ?></td>
                    <td class="text-end text-success"><?= money(array_sum(array_column($invoices, 'amount_paid'))) ?></td>
                    <td class="text-end text-danger"><?= money(array_sum(array_map(fn($i) => max(0, (float)($i['amount'] ?? 0) - (float)($i['amount_paid'] ?? 0)), $invoices))) ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
