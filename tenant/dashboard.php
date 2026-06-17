<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$user = current_user();
if ($user['role'] !== 'tenant') { redirect(BASE_URL . '/dashboard/index.php'); }

$api = new ApiClient();

// Active lease
$lease_res = $api->get('leases', ['status' => 'active', 'per_page' => 1]);
$lease     = $lease_res['data'][0] ?? null;

// Invoices — outstanding balance + recent
$inv_res  = $api->get('invoices', ['per_page' => 5]);
$invoices = $inv_res['data'] ?? [];

$outstanding_balance = 0;
$overdue_count       = 0;
$next_invoice        = null;
foreach ($invoices as $inv) {
    $bal = (float)$inv['total_amount'] - (float)$inv['amount_paid'];
    if ($bal > 0) {
        $outstanding_balance += $bal;
        if ($inv['status'] === 'overdue') $overdue_count++;
        if (!$next_invoice) $next_invoice = $inv;
    }
}

// Recent payments
$pay_res  = $api->get('payments', ['per_page' => 5]);
$payments = $pay_res['data'] ?? [];

// Open maintenance requests
$maint_res  = $api->get('maintenance', ['per_page' => 5]);
$maints     = $maint_res['data'] ?? [];
$open_maint = array_filter($maints, fn($m) => in_array($m['status'] ?? '', ['open','in_progress']));

$page_title = 'My Dashboard';
include BASE_PATH . '/includes/header.php';
?>

<!-- Welcome banner -->
<div class="card border-0 mb-4" style="background:linear-gradient(135deg,#1a3c5e 0%,#2563eb 100%);color:#fff;border-radius:16px">
    <div class="card-body p-4">
        <div class="row align-items-center">
            <div class="col">
                <div class="small opacity-75 mb-1"><?= date('l, d F Y') ?></div>
                <h4 class="fw-bold mb-1">Welcome back, <?= e(explode(' ', $user['name'])[0]) ?>!</h4>
                <?php if ($lease): ?>
                <div class="opacity-75">
                    <i class="bi bi-geo-alt me-1"></i><?= e($lease['property_name'] ?? '') ?>
                    &nbsp;·&nbsp; Unit <strong><?= e($lease['unit_number'] ?? '') ?></strong>
                </div>
                <?php else: ?>
                <div class="opacity-75">No active lease — contact management for assistance.</div>
                <?php endif; ?>
            </div>
            <?php if ($lease): ?>
            <div class="col-auto d-none d-md-block text-end">
                <div class="opacity-75 small">Monthly Rent</div>
                <div class="fs-3 fw-bold"><?= money((float)($lease['monthly_rent'] ?? $lease['rent_amount'] ?? 0)) ?></div>
                <div class="opacity-75 small">Due day <?= $lease['payment_day'] ?? $lease['rent_due_day'] ?? 1 ?> each month</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- KPI row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="kpi-card <?= $outstanding_balance > 0 ? 'kpi-red' : 'kpi-green' ?>">
            <div class="kpi-icon"><i class="bi bi-<?= $outstanding_balance > 0 ? 'exclamation-circle' : 'check-circle' ?>"></i></div>
            <div class="kpi-value" style="font-size:1.1rem"><?= money($outstanding_balance) ?></div>
            <div class="kpi-label">Outstanding Balance</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card <?= $overdue_count > 0 ? 'kpi-orange' : 'kpi-teal' ?>">
            <div class="kpi-icon"><i class="bi bi-receipt"></i></div>
            <div class="kpi-value"><?= $overdue_count ?></div>
            <div class="kpi-label">Overdue Invoice<?= $overdue_count !== 1 ? 's' : '' ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
            <div class="kpi-value"><?= count($payments) ?></div>
            <div class="kpi-label">Recent Payments</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card <?= count($open_maint) > 0 ? 'kpi-yellow' : 'kpi-teal' ?>">
            <div class="kpi-icon"><i class="bi bi-wrench"></i></div>
            <div class="kpi-value"><?= count($open_maint) ?></div>
            <div class="kpi-label">Open Requests</div>
        </div>
    </div>
</div>

<!-- Quick actions -->
<div class="row g-3 mb-4">
    <?php if ($lease): ?>
    <div class="col-6 col-md-3">
        <a href="<?= BASE_URL ?>/payments/mpesa_pay.php?lease_id=<?= $lease['id'] ?>" class="card text-decoration-none text-center p-3 h-100 border-0 shadow-sm action-card">
            <div class="fs-2 text-success mb-1"><i class="bi bi-phone-fill"></i></div>
            <div class="fw-semibold">Pay via M-Pesa</div>
            <div class="small text-muted">STK Push to your phone</div>
        </a>
    </div>
    <?php endif; ?>
    <div class="col-6 col-md-3">
        <a href="<?= BASE_URL ?>/tenant/invoices.php" class="card text-decoration-none text-center p-3 h-100 border-0 shadow-sm action-card">
            <div class="fs-2 text-primary mb-1"><i class="bi bi-receipt"></i></div>
            <div class="fw-semibold">My Invoices</div>
            <div class="small text-muted">View & download invoices</div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= BASE_URL ?>/tenant/payments.php" class="card text-decoration-none text-center p-3 h-100 border-0 shadow-sm action-card">
            <div class="fs-2 text-info mb-1"><i class="bi bi-clock-history"></i></div>
            <div class="fw-semibold">Payment History</div>
            <div class="small text-muted">All your transactions</div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= BASE_URL ?>/maintenance/add.php" class="card text-decoration-none text-center p-3 h-100 border-0 shadow-sm action-card">
            <div class="fs-2 text-warning mb-1"><i class="bi bi-tools"></i></div>
            <div class="fw-semibold">Request Repair</div>
            <div class="small text-muted">Log a maintenance issue</div>
        </a>
    </div>
</div>

<!-- Content row -->
<div class="row g-3">
    <!-- Recent invoices -->
    <div class="col-md-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-receipt me-2 text-primary"></i>Recent Invoices</span>
                <a href="<?= BASE_URL ?>/tenant/invoices.php" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Invoice</th><th>Period</th><th>Amount</th><th>Balance</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($invoices): foreach ($invoices as $inv):
                        $bal = (float)$inv['total_amount'] - (float)$inv['amount_paid'];
                    ?>
                    <tr>
                        <td><code class="small"><?= e($inv['invoice_number']) ?></code></td>
                        <td class="small"><?= !empty($inv['period_month']) ? month_name($inv['period_month']) . ' ' . $inv['period_year'] : fmt_date($inv['invoice_date']) ?></td>
                        <td><?= money($inv['total_amount']) ?></td>
                        <td class="<?= $bal > 0 ? 'text-danger fw-semibold' : 'text-success' ?>"><?= money($bal) ?></td>
                        <td><?= invoice_badge($inv['status']) ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">No invoices yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right column: lease info + maintenance -->
    <div class="col-md-5">
        <!-- Lease summary -->
        <?php if ($lease): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-file-earmark-text me-2 text-primary"></i>My Lease</span>
                <a href="<?= BASE_URL ?>/tenant/lease.php" class="btn btn-sm btn-outline-secondary">Details</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr><td class="text-muted ps-3">Unit</td><td class="pe-3 fw-semibold"><?= e($lease['unit_number'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted ps-3">Monthly Rent</td><td class="pe-3 fw-semibold text-primary"><?= money((float)($lease['monthly_rent'] ?? 0)) ?></td></tr>
                    <tr><td class="text-muted ps-3">Lease Ends</td>
                        <td class="pe-3">
                            <?php
                            $days_left = $lease['end_date'] ? (int)ceil((strtotime($lease['end_date']) - time()) / 86400) : null;
                            echo fmt_date($lease['end_date']);
                            if ($days_left !== null && $days_left <= 60):
                            ?> <span class="badge bg-warning text-dark ms-1"><?= $days_left ?>d left</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr><td class="text-muted ps-3">Status</td><td class="pe-3"><?= lease_badge($lease['status']) ?></td></tr>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Open maintenance requests -->
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-wrench me-2 text-warning"></i>Maintenance</span>
                <a href="<?= BASE_URL ?>/tenant/maintenance.php" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <?php if ($maints): ?>
            <ul class="list-group list-group-flush">
                <?php foreach (array_slice($maints, 0, 4) as $m):
                    $sc = ['open'=>'danger','in_progress'=>'warning','completed'=>'success','closed'=>'secondary'][$m['status']] ?? 'secondary';
                ?>
                <li class="list-group-item d-flex justify-content-between align-items-start py-2">
                    <div>
                        <div class="small fw-semibold"><?= e($m['title'] ?? $m['description'] ?? 'Maintenance request') ?></div>
                        <div class="small text-muted"><?= fmt_date($m['created_at']) ?></div>
                    </div>
                    <span class="badge bg-<?= $sc ?> flex-shrink-0"><?= ucfirst(str_replace('_',' ',$m['status'])) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <div class="card-body text-center text-muted py-3 small">
                <i class="bi bi-check-circle text-success me-1"></i>No open maintenance requests.
            </div>
            <?php endif; ?>
            <div class="card-footer bg-white py-2">
                <a href="<?= BASE_URL ?>/maintenance/add.php" class="btn btn-sm btn-outline-warning w-100">
                    <i class="bi bi-plus-circle me-1"></i>New Request
                </a>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
