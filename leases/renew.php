<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api    = new ApiClient();
$id     = int_param('id');
$errors = [];

if (!$id) { redirect(BASE_URL . '/leases/index'); }

$res   = $api->get("leases/$id");
$lease = $res['data'] ?? null;
if (!$lease) { set_flash('error', 'Lease not found.'); redirect(BASE_URL . '/leases/index'); }
if ($lease['status'] !== 'active') {
    set_flash('error', 'Only active leases can be renewed.');
    redirect(BASE_URL . '/leases/view?id=' . $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/leases/renew?id=' . $id); }

    $new_end_date  = post('new_end_date');
    $new_rent      = (float)post('new_monthly_rent');
    $notes         = post('notes');

    if (!$new_end_date)          $errors[] = 'New end date is required.';
    if ($new_end_date && $new_end_date <= $lease['end_date']) $errors[] = 'New end date must be after the current end date (' . fmt_date($lease['end_date']) . ').';
    if ($new_rent <= 0)          $errors[] = 'New monthly rent must be greater than 0.';

    if (!$errors) {
        $res = $api->post("leases/$id/renew", [
            'new_end_date'      => $new_end_date,
            'new_monthly_rent'  => $new_rent,
            'notes'             => $notes ?: null,
        ]);
        if (!empty($res['success'])) {
            set_flash('success', 'Lease renewed successfully.');
            redirect(BASE_URL . '/leases/view?id=' . $id);
        }
        $errors[] = $res['message'] ?? 'Failed to renew lease.';
    }
}

$cur_sym    = get_setting('currency_symbol', CURRENCY_SYMBOL);
$page_title = 'Renew Lease';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3">
    <a href="<?= BASE_URL ?>/leases/view?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0">Renew Lease — <code><?= e($lease['lease_number']) ?></code></h5>
</div>

<?php if ($errors): ?><div class="alert alert-danger small"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="row g-3">
    <!-- Current lease summary -->
    <div class="col-md-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-file-earmark-text me-1 text-primary"></i>Current Lease</div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-6 text-muted">Tenant</dt><dd class="col-6"><?= e($lease['tenant_name'] ?? '—') ?></dd>
                    <dt class="col-6 text-muted">Unit</dt><dd class="col-6"><?= e($lease['unit_number'] ?? '—') ?></dd>
                    <dt class="col-6 text-muted">Property</dt><dd class="col-6"><?= e($lease['property_name'] ?? '—') ?></dd>
                    <dt class="col-6 text-muted">Start Date</dt><dd class="col-6"><?= fmt_date($lease['start_date']) ?></dd>
                    <dt class="col-6 text-muted">End Date</dt><dd class="col-6 fw-bold text-danger"><?= fmt_date($lease['end_date']) ?></dd>
                    <dt class="col-6 text-muted">Monthly Rent</dt><dd class="col-6 fw-bold"><?= money($lease['monthly_rent'] ?? 0) ?></dd>
                    <?php if (!empty($lease['days_remaining'])): ?>
                    <dt class="col-6 text-muted">Days Left</dt>
                    <dd class="col-6">
                        <?php $dr = (int)$lease['days_remaining']; ?>
                        <span class="badge bg-<?= $dr < 30 ? 'danger' : 'warning text-dark' ?>"><?= $dr ?> days</span>
                    </dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <div class="alert alert-info small">
            <i class="bi bi-info-circle me-1"></i>
            <strong>Extension model:</strong> The same lease is extended with a new end date and optionally a new rent. A renewal record is created for audit purposes.
        </div>
    </div>

    <!-- Renewal form -->
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-arrow-clockwise me-1 text-primary"></i>New Lease Terms</div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">New End Date *</label>
                            <input type="date" name="new_end_date" class="form-control"
                                   value="<?= e(post('new_end_date') ?: date('Y-m-d', strtotime($lease['end_date'] . ' +1 year'))) ?>"
                                   min="<?= date('Y-m-d', strtotime($lease['end_date'] . ' +1 day')) ?>"
                                   required>
                            <div class="form-text">Must be after <?= fmt_date($lease['end_date']) ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">New Monthly Rent (<?= $cur_sym ?>) *</label>
                            <input type="number" step="0.01" name="new_monthly_rent" class="form-control"
                                   value="<?= e(post('new_monthly_rent') ?: number_format($lease['monthly_rent'], 2, '.', '')) ?>"
                                   required min="1">
                            <div class="form-text">Current: <?= money($lease['monthly_rent'] ?? 0) ?></div>
                        </div>

                        <?php if (!empty($lease['escalation_type']) && $lease['escalation_type'] !== 'none'): ?>
                        <div class="col-12">
                            <div class="alert alert-warning small mb-0 py-2">
                                <i class="bi bi-graph-up-arrow me-1"></i>
                                This lease has a <strong><?= $lease['escalation_type'] ?></strong> escalation of
                                <?= $lease['escalation_type'] === 'fixed'
                                    ? money($lease['escalation_rate'] ?? 0)
                                    : number_format($lease['escalation_rate'] ?? 0, 2) . '%' ?>
                                per <?= $lease['escalation_frequency'] ?? 'year' ?>.
                                Suggested new rent:
                                <strong>
                                <?php
                                $suggested = $lease['escalation_type'] === 'fixed'
                                    ? $lease['monthly_rent'] + $lease['escalation_rate']
                                    : $lease['monthly_rent'] * (1 + $lease['escalation_rate'] / 100);
                                echo money(round($suggested, 2));
                                ?>
                                </strong>
                                <button type="button" class="btn btn-xs btn-sm btn-warning ms-2 py-0"
                                        onclick="document.querySelector('[name=new_monthly_rent]').value='<?= number_format(round($suggested, 2), 2, '.', '') ?>'">
                                    Use suggested
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Renewal Notes</label>
                            <textarea name="notes" class="form-control" rows="3"
                                      placeholder="Reason for renewal, any changed conditions…"><?= e(post('notes')) ?></textarea>
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i>Confirm Renewal
                            </button>
                            <a href="<?= BASE_URL ?>/leases/view?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Renewal history -->
        <?php $renewals = $lease['renewals'] ?? []; if ($renewals): ?>
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-clock-history me-1"></i>Previous Renewals</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Date</th><th>Old End</th><th>New End</th><th>Old Rent</th><th>New Rent</th><th>Notes</th></tr></thead>
                    <tbody>
                    <?php foreach ($renewals as $r): ?>
                    <tr>
                        <td><?= fmt_date($r['created_at']) ?></td>
                        <td><?= fmt_date($r['old_end_date']) ?></td>
                        <td><?= fmt_date($r['new_end_date']) ?></td>
                        <td><?= money($r['old_monthly_rent']) ?></td>
                        <td class="text-primary"><?= money($r['new_monthly_rent']) ?></td>
                        <td class="text-muted small"><?= e($r['notes'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
