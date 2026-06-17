<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'landlord', 'accountant');

$api        = new ApiClient();
$page_title = 'Landlord Statement';

$me          = current_user();
$landlord_id = null;

/* Admin/accountant can view any landlord */
$view_id = int_param('landlord_id', 0, 'GET');
if (is_manager() || $me['role'] === 'accountant') {
    if ($view_id) $landlord_id = $view_id;
}

/* Resolve landlord for non-admin/accountant, or admin who didn't pass landlord_id */
if (!$landlord_id) {
    $ll_res      = $api->get('landlords', ['user_id' => $me['id'], 'per_page' => 1]);
    $landlord_id = (int)(($ll_res['data'][0]['id'] ?? null));
}

if (!$landlord_id) {
    set_flash('error', 'No landlord profile linked to your account.');
    redirect(BASE_URL . '/landlord/dashboard.php');
}

/* ── Load landlord (includes properties array) ── */
$ll       = $api->get("landlords/$landlord_id");
$landlord = $ll['data'] ?? null;

if (!$landlord) {
    set_flash('error', 'Landlord not found.');
    redirect(BASE_URL . '/landlord/dashboard.php');
}

$properties = $landlord['properties'] ?? [];

/* ── Filters ── */
$period    = get_param('period', date('Y-m'));
[$yr, $mo] = explode('-', $period);
$date_from = "$yr-$mo-01";
$date_to   = date('Y-m-t', strtotime($date_from));

/* ── Payments in period ── */
$pay_res  = $api->get('payments', [
    'landlord_id' => $landlord_id,
    'date_from'   => $date_from,
    'date_to'     => $date_to,
    'per_page'    => 500,
]);
$payments = $pay_res['data'] ?? [];

/* ── Expenses in period ── */
/* Fetch all for landlord in the period, then filter PHP-side to approved/paid */
$exp_res  = $api->get('expenses', [
    'landlord_id' => $landlord_id,
    'date_from'   => $date_from,
    'date_to'     => $date_to,
    'per_page'    => 500,
]);
$expenses = array_filter(
    $exp_res['data'] ?? [],
    fn($e) => in_array($e['status'], ['approved', 'paid'])
);

/* ── Calculations ── */
$gross_income    = array_sum(array_column($payments, 'amount'));
$total_expenses  = array_sum(array_column($expenses, 'amount'));
$commission_rate = (float)($landlord['commission_rate'] ?? 0);
$management_fee  = $gross_income * ($commission_rate / 100);
$net_remittance  = $gross_income - $management_fee - $total_expenses;

include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
    <h5 class="fw-bold mb-0"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Landlord Statement</h5>
    <div class="d-flex gap-2">
        <form method="GET" class="d-inline">
            <input type="hidden" name="landlord_id" value="<?= $landlord_id ?>">
            <input type="month" name="period" class="form-control form-control-sm d-inline w-auto" value="<?= e($period) ?>" onchange="this.form.submit()">
        </form>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </div>
</div>

<!-- Statement Document -->
<div class="card shadow-sm" id="statementDoc">
    <div class="card-body p-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-7">
                <h4 class="fw-bold"><?= APP_NAME ?></h4>
                <p class="text-muted mb-0">Property Management Statement</p>
                <p class="text-muted small">Period: <?= date('F Y', strtotime($date_from)) ?></p>
            </div>
            <div class="col-5 text-end">
                <h6 class="fw-bold mb-1">Owner:</h6>
                <p class="mb-0"><?= e($landlord['name']) ?></p>
                <p class="mb-0 text-muted small"><?= e($landlord['email']) ?></p>
                <?php if ($landlord['kra_pin']): ?>
                <p class="mb-0 text-muted small">KRA PIN: <?= e($landlord['kra_pin']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <hr>

        <!-- Properties -->
        <h6 class="text-muted mb-2">MANAGED PROPERTIES</h6>
        <div class="d-flex flex-wrap gap-2 mb-4">
            <?php foreach ($properties as $p): ?>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= e($p['name']) ?></span>
            <?php endforeach; ?>
        </div>

        <!-- Income Section -->
        <h6 class="fw-bold text-uppercase text-success mb-2">Income Received</h6>
        <table class="table table-sm mb-3">
            <thead class="table-light">
                <tr><th>Date</th><th>Property / Unit</th><th>Tenant</th><th>Type</th><th>Method</th><th class="text-end">Amount</th></tr>
            </thead>
            <tbody>
            <?php if (!$payments): ?>
                <tr><td colspan="6" class="text-muted text-center">No payments recorded.</td></tr>
            <?php else: ?>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?= fmt_date($p['payment_date']) ?></td>
                    <td><?= e($p['property_name']) ?> / <?= e($p['unit_number']) ?></td>
                    <td><?= e($p['tenant_name'] ?? '—') ?></td>
                    <td><?= ucfirst(str_replace('_',' ',$p['payment_type'] ?? '')) ?></td>
                    <td><?= ucfirst($p['payment_method']) ?></td>
                    <td class="text-end"><?= money($p['amount']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="fw-bold table-success">
                    <td colspan="5">Total Income</td>
                    <td class="text-end"><?= money($gross_income) ?></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>

        <!-- Deductions -->
        <h6 class="fw-bold text-uppercase text-danger mb-2">Deductions</h6>
        <table class="table table-sm mb-3">
            <thead class="table-light"><tr><th>Description</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
                <tr>
                    <td>Management Fee (<?= $commission_rate ?>% of <?= money($gross_income) ?>)</td>
                    <td class="text-end"><?= money($management_fee) ?></td>
                </tr>
                <?php foreach ($expenses as $ex): ?>
                <tr>
                    <td><?= e($ex['description']) ?> — <?= e($ex['property_name'] ?? '') ?></td>
                    <td class="text-end"><?= money($ex['amount']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="fw-bold table-danger">
                    <td>Total Deductions</td>
                    <td class="text-end"><?= money($management_fee + $total_expenses) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Net Remittance -->
        <div class="text-end mt-3">
            <table class="table table-borderless ms-auto" style="max-width:300px">
                <tr><td>Gross Income</td><td class="text-end"><?= money($gross_income) ?></td></tr>
                <tr><td>Management Fee</td><td class="text-end text-danger">- <?= money($management_fee) ?></td></tr>
                <tr><td>Expenses</td><td class="text-end text-danger">- <?= money($total_expenses) ?></td></tr>
                <tr class="fw-bold fs-5 border-top">
                    <td>NET REMITTANCE</td>
                    <td class="text-end <?= $net_remittance >= 0 ? 'text-success':'text-danger' ?>"><?= money($net_remittance) ?></td>
                </tr>
            </table>
        </div>

        <?php if ($landlord['bank_name'] || $landlord['bank_account']): ?>
        <hr>
        <div class="row">
            <div class="col-6">
                <h6 class="text-muted">Remittance Account</h6>
                <p class="mb-0"><?= e($landlord['bank_name'] ?? '') ?></p>
                <?php if ($landlord['bank_account']): ?>
                <p class="mb-0">A/C: <?= e($landlord['bank_account']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <p class="text-muted small mt-4">Generated: <?= date('d M Y H:i') ?> · <?= APP_NAME ?></p>
    </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
