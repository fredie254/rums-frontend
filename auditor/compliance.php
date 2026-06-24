<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'auditor');

$page_title = 'Compliance Report';

$api   = new ApiClient();
$year  = int_param('year', (int)date('Y'));
$years = range((int)date('Y'), (int)date('Y') - 3);
$today = new DateTime();

// 1. leases_no_invoices — active leases with no invoice this year
//    API approach: fetch all active leases, then check for each year invoice
$leases_res = $api->get('leases', ['status' => 'active', 'per_page' => 500]);
$all_leases = $leases_res['data'] ?? [];

$inv_year_res = $api->get('invoices', [
    'date_from' => "$year-01-01",
    'date_to'   => "$year-12-31",
    'per_page'  => 1000,
]);
$leased_with_invoices = array_unique(array_column($inv_year_res['data'] ?? [], 'lease_id'));

$leases_no_invoices = array_values(array_filter($all_leases, fn($l) => !in_array((int)$l['id'], array_map('intval', $leased_with_invoices), true)));

// 2. payments_no_invoice — payments without an invoice link
$pay_no_inv_res = $api->get('payments', ['no_invoice' => '1', 'per_page' => 50]);
$payments_no_invoice = $pay_no_inv_res['data'] ?? [];

// 3. inactive_users — via API
$inactive_users_res = $api->get('users', ['status' => 'active', 'per_page' => 500]);
$all_users  = $inactive_users_res['data'] ?? [];
$cutoff     = (new DateTime())->modify('-90 days');
$inactive_users = array_filter($all_users, function ($u) use ($cutoff) {
    if (empty($u['last_login'])) return true;
    try { return new DateTime($u['last_login']) < $cutoff; } catch (Throwable $e) { return false; }
});

// 4. severely_overdue — via API
$inv_res = $api->get('invoices', ['status' => 'outstanding', 'per_page' => 500]);
$all_invoices = $inv_res['data'] ?? [];
$severely_overdue = array_filter($all_invoices, function ($i) use ($today) {
    if (empty($i['due_date'])) return false;
    try { $due = new DateTime($i['due_date']); } catch (Throwable $e) { return false; }
    if ($due > $today) return false;
    return (int)$today->diff($due)->days > 60;
});
$severely_overdue = array_map(function ($i) use ($today) {
    try {
        $due = new DateTime($i['due_date']);
        $i['days_overdue'] = (int)$today->diff($due)->days;
    } catch (Throwable $e) {
        $i['days_overdue'] = 0;
    }
    $i['balance'] = (float)$i['total_amount'] - (float)$i['amount_paid'];
    return $i;
}, $severely_overdue);

// 5. stale_expenses — via API
$exp_res = $api->get('expenses', ['status' => 'pending', 'per_page' => 200, 'date_from' => '2000-01-01', 'date_to' => date('Y-m-d')]);
$all_expenses = $exp_res['data'] ?? [];
$cutoff30 = (new DateTime())->modify('-30 days');
$stale_expenses = array_filter($all_expenses, function ($e) use ($cutoff30) {
    if (empty($e['created_at'])) return false;
    try { return new DateTime($e['created_at']) < $cutoff30; } catch (Throwable $e) { return false; }
});

// 6. stale_maintenance — via API
$maint_res = $api->get('maintenance', ['status' => 'active', 'per_page' => 200]);
$all_maint = $maint_res['data'] ?? [];
$cutoff14  = (new DateTime())->modify('-14 days');
$stale_maintenance = array_filter($all_maint, function ($m) use ($cutoff14) {
    if (empty($m['created_at'])) return false;
    try { return new DateTime($m['created_at']) < $cutoff14; } catch (Throwable $e) { return false; }
});
$stale_maintenance = array_map(function ($m) use ($today) {
    try {
        $m['days_open'] = (int)$today->diff(new DateTime($m['created_at']))->days;
    } catch (Throwable $e) {
        $m['days_open'] = 0;
    }
    return $m;
}, $stale_maintenance);

// 7. annual financial summary — via API
$fin_res = $api->get('reports/financial', ['date_from' => "$year-01-01", 'date_to' => "$year-12-31"]);
$fin_sum = $fin_res['data']['summary'] ?? [];
$annual  = [
    'income'   => (float)($fin_sum['total_income']   ?? 0),
    'expenses' => (float)($fin_sum['total_expenses']  ?? 0),
];

$issues = [
    'leases_no_invoices'  => count($leases_no_invoices),
    'payments_no_invoice' => count($payments_no_invoice),
    'inactive_users'      => count($inactive_users),
    'severely_overdue'    => count($severely_overdue),
    'stale_expenses'      => count($stale_expenses),
    'stale_maintenance'   => count($stale_maintenance),
];
$total_issues = array_sum($issues);

include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0"><i class="bi bi-clipboard2-check me-2 text-success"></i>Compliance Report</h5>
    <div class="d-flex gap-2">
        <form method="GET" class="d-inline">
            <select name="year" class="form-select form-select-sm d-inline w-auto" onchange="this.form.submit()">
                <?php foreach ($years as $y): ?>
                <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </div>
</div>

<!-- Compliance Score -->
<div class="card shadow-sm mb-4 <?= $total_issues === 0 ? 'border-success' : ($total_issues < 5 ? 'border-warning' : 'border-danger') ?>">
    <div class="card-body py-3">
        <div class="row align-items-center">
            <div class="col-auto">
                <?php if ($total_issues === 0): ?>
                <i class="bi bi-shield-check text-success" style="font-size:3rem"></i>
                <?php elseif ($total_issues < 5): ?>
                <i class="bi bi-shield-exclamation text-warning" style="font-size:3rem"></i>
                <?php else: ?>
                <i class="bi bi-shield-x text-danger" style="font-size:3rem"></i>
                <?php endif; ?>
            </div>
            <div class="col">
                <h5 class="mb-0">
                    <?= $total_issues === 0 ? 'All checks passed — fully compliant' : "$total_issues compliance issue(s) found" ?>
                </h5>
                <p class="text-muted mb-0 small">As of <?= fmt_date(date('Y-m-d')) ?> · Fiscal Year <?= $year ?></p>
            </div>
            <div class="col-auto">
                <div class="fs-1 fw-bold <?= $total_issues === 0 ? 'text-success' : ($total_issues < 5 ? 'text-warning' : 'text-danger') ?>">
                    <?= max(0, 100 - ($total_issues * 5)) ?>%
                </div>
                <small class="text-muted">Compliance Score</small>
            </div>
        </div>
    </div>
</div>

<!-- Annual Financial Summary -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm kpi-green text-white"><div class="card-body py-3">
            <div class="small opacity-75">Total Income <?= $year ?></div>
            <div class="fs-5 fw-bold"><?= money($annual['income']) ?></div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm kpi-red text-white"><div class="card-body py-3">
            <div class="small opacity-75">Total Expenses <?= $year ?></div>
            <div class="fs-5 fw-bold"><?= money($annual['expenses']) ?></div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm kpi-blue text-white"><div class="card-body py-3">
            <div class="small opacity-75">Net Income <?= $year ?></div>
            <div class="fs-5 fw-bold"><?= money($annual['income'] - $annual['expenses']) ?></div>
        </div></div>
    </div>
</div>

<!-- Checklist -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-2"><span class="fw-semibold">Compliance Checklist</span></div>
    <div class="list-group list-group-flush">
        <?php
        $checks = [
            ['Leases have invoices generated',           $issues['leases_no_invoices'],  'leases_no_invoices'],
            ['All payments linked to invoices',           $issues['payments_no_invoice'], 'payments_no_invoice'],
            ['No severely overdue receivables (>60d)',   $issues['severely_overdue'],    'severely_overdue'],
            ['No stale pending expenses (>30d)',         $issues['stale_expenses'],       'stale_expenses'],
            ['Maintenance requests resolved within 14d', $issues['stale_maintenance'],   'stale_maintenance'],
            ['No inactive active users (>90d)',          $issues['inactive_users'],       'inactive_users'],
        ];
        foreach ($checks as [$label, $count, $anchor]):
        ?>
        <div class="list-group-item d-flex justify-content-between align-items-center py-2">
            <div>
                <?php if ($count === 0): ?>
                <i class="bi bi-check-circle-fill text-success me-2"></i>
                <?php else: ?>
                <i class="bi bi-x-circle-fill text-danger me-2"></i>
                <?php endif; ?>
                <?= $label ?>
            </div>
            <?php if ($count > 0): ?>
            <a href="#<?= $anchor ?>" class="badge bg-danger text-decoration-none"><?= $count ?> issue<?= $count > 1 ? 's' : '' ?></a>
            <?php else: ?>
            <span class="badge bg-success">OK</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Detail Sections -->
<?php if ($leases_no_invoices): ?>
<div id="leases_no_invoices" class="card shadow-sm mb-4 border-warning">
    <div class="card-header bg-warning-subtle py-2"><span class="fw-semibold text-warning-emphasis">Active Leases With No Invoice This Year</span></div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Lease #</th><th>Tenant</th><th>Unit</th><th>Start</th><th>End</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($leases_no_invoices as $r): ?>
            <tr>
                <td><?= e($r['lease_number']) ?></td>
                <td><?= e($r['tenant_name'] ?? '—') ?></td>
                <td><?= e($r['property_name'] ?? '') ?> / <?= e($r['unit_number'] ?? '') ?></td>
                <td><?= fmt_date($r['start_date']) ?></td>
                <td><?= fmt_date($r['end_date']) ?></td>
                <td><a href="<?= BASE_URL ?>/invoices/generate" class="btn btn-xs btn-warning">Generate Invoice</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($payments_no_invoice): ?>
<div id="payments_no_invoice" class="card shadow-sm mb-4 border-warning">
    <div class="card-header bg-warning-subtle py-2"><span class="fw-semibold text-warning-emphasis">Payments Not Linked to an Invoice</span></div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Ref</th><th>Tenant</th><th>Method</th><th>Date</th><th class="text-end">Amount</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($payments_no_invoice as $r): ?>
            <tr>
                <td><?= e($r['payment_ref'] ?? '—') ?></td>
                <td><?= e($r['tenant_name'] ?? '—') ?></td>
                <td><span class="badge bg-secondary"><?= e(ucfirst($r['payment_method'] ?? '')) ?></span></td>
                <td><?= fmt_date($r['payment_date']) ?></td>
                <td class="text-end"><?= money($r['amount']) ?></td>
                <td><a href="<?= BASE_URL ?>/payments/view?id=<?= $r['id'] ?>" class="btn btn-xs btn-warning">Review</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($severely_overdue): ?>
<div id="severely_overdue" class="card shadow-sm mb-4 border-danger">
    <div class="card-header bg-danger-subtle py-2"><span class="fw-semibold text-danger-emphasis">Severely Overdue Receivables (&gt;60 days)</span></div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Invoice</th><th>Tenant</th><th>Unit</th><th>Due</th><th>Days</th><th class="text-end">Balance</th></tr></thead>
            <tbody>
            <?php foreach ($severely_overdue as $r): ?>
            <tr>
                <td><a href="<?= BASE_URL ?>/invoices/view?id=<?= $r['id'] ?>"><?= e($r['invoice_number']) ?></a></td>
                <td><?= e($r['tenant_name']) ?></td>
                <td><?= e($r['property_name'] ?? '') ?> / <?= e($r['unit_number'] ?? '') ?></td>
                <td><?= fmt_date($r['due_date']) ?></td>
                <td class="text-danger fw-bold"><?= $r['days_overdue'] ?></td>
                <td class="text-end text-danger fw-bold"><?= money($r['balance']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($stale_expenses): ?>
<div id="stale_expenses" class="card shadow-sm mb-4 border-warning">
    <div class="card-header bg-warning-subtle py-2"><span class="fw-semibold text-warning-emphasis">Stale Pending Expenses (&gt;30 days)</span></div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Description</th><th>Category</th><th>Property</th><th>Date</th><th class="text-end">Amount</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($stale_expenses as $r): ?>
            <tr>
                <td><?= e($r['description']) ?></td>
                <td><?= ucfirst(str_replace('_', ' ', $r['category'])) ?></td>
                <td><?= e($r['property_name'] ?? '—') ?></td>
                <td><?= fmt_date($r['expense_date']) ?></td>
                <td class="text-end"><?= money($r['amount']) ?></td>
                <td><a href="<?= BASE_URL ?>/accountant/expenses" class="btn btn-xs btn-warning">Review</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($stale_maintenance): ?>
<div id="stale_maintenance" class="card shadow-sm mb-4 border-info">
    <div class="card-header bg-info-subtle py-2"><span class="fw-semibold text-info-emphasis">Open Maintenance Requests (&gt;14 days)</span></div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>ID</th><th>Issue</th><th>Unit</th><th>Priority</th><th>Days Open</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($stale_maintenance as $r): ?>
            <tr>
                <td><?= e($r['request_number'] ?? $r['id']) ?></td>
                <td><?= e($r['issue_title'] ?? substr($r['description'] ?? '', 0, 40)) ?></td>
                <td><?= e($r['property_name'] ?? '') ?> / <?= e($r['unit_number'] ?? '') ?></td>
                <td><?= priority_badge($r['priority']) ?></td>
                <td class="text-warning fw-bold"><?= $r['days_open'] ?></td>
                <td><a href="<?= BASE_URL ?>/maintenance/view?id=<?= $r['id'] ?>" class="btn btn-xs btn-info">View</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($inactive_users): ?>
<div id="inactive_users" class="card shadow-sm mb-4 border-secondary">
    <div class="card-header bg-secondary-subtle py-2"><span class="fw-semibold">Inactive Active Users (&gt;90 days)</span></div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Role</th><th>Last Login</th></tr></thead>
            <tbody>
            <?php foreach ($inactive_users as $r): ?>
            <tr>
                <td><?= e($r['name']) ?></td>
                <td><?= e($r['email']) ?></td>
                <td><span class="badge bg-secondary"><?= e($r['role']) ?></span></td>
                <td><?= $r['last_login'] ? fmt_date($r['last_login'], 'd M Y, H:i') : '<span class="text-muted">Never</span>' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/footer.php'; ?>
