<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$user = current_user();
if ($user['role'] !== 'tenant') { redirect(BASE_URL . '/dashboard/index'); }

$api = new ApiClient();

// API auto-scopes to tenant's leases
$res    = $api->get('leases', ['status' => 'active', 'per_page' => 1]);
$lease  = $res['data'][0] ?? null;

// Also fetch expired/terminated for history
$hist_res = $api->get('leases', ['per_page' => 10]);
$history  = array_filter($hist_res['data'] ?? [], fn($l) => $l['status'] !== 'active');

$page_title = 'My Lease';
include BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-4">
    <div>
        <h5 class="fw-bold mb-1"><i class="bi bi-file-earmark-text me-2 text-primary"></i>My Lease</h5>
        <small class="text-muted">Your current tenancy agreement and unit details</small>
    </div>
    <a href="<?= BASE_URL ?>/tenant/dashboard" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Dashboard
    </a>
</div>

<?php if ($lease): ?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-file-earmark me-2"></i><?= e($lease['lease_number']) ?></span>
                <?= lease_badge($lease['status']) ?>
            </div>
            <div class="card-body">
                <div class="row g-0">
                    <div class="col-md-6">
                        <h6 class="fw-semibold text-muted text-uppercase small mb-2">Property</h6>
                        <p class="fw-semibold mb-1"><?= e($lease['property_name'] ?? '—') ?></p>
                        <?php if (!empty($lease['address_line1'])): ?>
                        <p class="text-muted small mb-3"><?= e($lease['address_line1']) ?><?= !empty($lease['address_city']) ? ', ' . e($lease['address_city']) : '' ?></p>
                        <?php endif; ?>
                        <h6 class="fw-semibold text-muted text-uppercase small mb-2">Unit</h6>
                        <p class="fw-semibold mb-3">
                            Unit <?= e($lease['unit_number'] ?? '—') ?>
                            <span class="badge bg-light text-dark border ms-1"><?= strtoupper($lease['unit_type'] ?? '') ?></span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-semibold text-muted text-uppercase small mb-2">Lease Period</h6>
                        <div class="d-flex gap-3 mb-3">
                            <div>
                                <div class="small text-muted">Start</div>
                                <div class="fw-semibold"><?= fmt_date($lease['start_date'], 'd M Y') ?></div>
                            </div>
                            <div class="text-muted align-self-center"><i class="bi bi-arrow-right"></i></div>
                            <div>
                                <div class="small text-muted">End</div>
                                <div class="fw-semibold">
                                    <?= fmt_date($lease['end_date'], 'd M Y') ?>
                                    <?php
                                    $days = $lease['end_date'] ? (int)ceil((strtotime($lease['end_date']) - time()) / 86400) : null;
                                    if ($days !== null && $days > 0 && $days <= 60):
                                    ?><br><span class="badge bg-warning text-dark"><?= $days ?>d left</span><?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <h6 class="fw-semibold text-muted text-uppercase small mb-2">Payment</h6>
                        <div class="small">Due on day <strong><?= $lease['payment_day'] ?? $lease['rent_due_day'] ?? 1 ?></strong> of each month</div>
                    </div>
                </div>
                <hr>
                <div class="row g-3">
                    <div class="col-6 col-md-4">
                        <div class="text-muted small">Monthly Rent</div>
                        <div class="fw-bold text-primary fs-5"><?= money((float)($lease['monthly_rent'] ?? $lease['rent_amount'] ?? 0)) ?></div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="text-muted small">Security Deposit</div>
                        <div class="fw-semibold"><?= money((float)($lease['deposit_amount'] ?? 0)) ?></div>
                    </div>
                </div>
                <?php if (!empty($lease['terms'])): ?>
                <hr>
                <h6 class="fw-semibold text-muted text-uppercase small">Terms & Conditions</h6>
                <div class="small text-muted" style="white-space:pre-wrap"><?= e($lease['terms']) ?></div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-white d-flex gap-2">
                <a href="<?= BASE_URL ?>/payments/mpesa_pay?lease_id=<?= $lease['id'] ?>" class="btn btn-success">
                    <i class="bi bi-phone me-1"></i>Pay via M-Pesa
                </a>
                <a href="<?= BASE_URL ?>/tenant/invoices" class="btn btn-outline-primary">
                    <i class="bi bi-receipt me-1"></i>View Invoices
                </a>
            </div>
        </div>
    </div>

    <!-- Side info -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white fw-semibold small text-uppercase text-muted">Quick Summary</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr>
                        <td class="text-muted ps-3">Lease #</td>
                        <td class="pe-3"><code class="small"><?= e($lease['lease_number']) ?></code></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-3">Status</td>
                        <td class="pe-3"><?= lease_badge($lease['status']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-3">Duration</td>
                        <td class="pe-3 small">
                            <?php
                            if ($lease['start_date'] && $lease['end_date']) {
                                $months = (int)round((strtotime($lease['end_date']) - strtotime($lease['start_date'])) / (30 * 86400));
                                echo $months . ' month' . ($months !== 1 ? 's' : '');
                            }
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-semibold small text-uppercase text-muted">Need Help?</div>
            <div class="card-body">
                <p class="text-muted small mb-3">If you have questions about your lease or need to discuss renewal, contact management.</p>
                <a href="<?= BASE_URL ?>/maintenance/add" class="btn btn-outline-warning btn-sm w-100 mb-2">
                    <i class="bi bi-tools me-1"></i>Report a Maintenance Issue
                </a>
                <a href="<?= BASE_URL ?>/tenant/dashboard" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="bi bi-house me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Lease history -->
<?php if ($history): ?>
<div class="mt-4">
    <h6 class="fw-semibold text-muted text-uppercase small mb-2">Previous Leases</h6>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Lease #</th><th>Unit</th><th>Period</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($history as $h): ?>
                <tr>
                    <td><code class="small"><?= e($h['lease_number']) ?></code></td>
                    <td><?= e($h['unit_number'] ?? '—') ?></td>
                    <td class="small"><?= fmt_date($h['start_date']) ?> – <?= fmt_date($h['end_date']) ?></td>
                    <td><?= lease_badge($h['status']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<div class="card shadow-sm text-center py-5">
    <div class="card-body">
        <i class="bi bi-file-earmark-x fs-1 text-muted opacity-25 d-block mb-3"></i>
        <h5 class="text-muted">No Active Lease Found</h5>
        <p class="text-muted small">You don't have an active lease at the moment. Contact management for assistance.</p>
        <a href="<?= BASE_URL ?>/tenant/dashboard" class="btn btn-outline-primary btn-sm mt-2">
            <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>
</div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/footer.php'; ?>
