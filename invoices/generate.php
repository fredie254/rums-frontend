<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api    = new ApiClient();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/invoices/generate.php'); }

    $month    = (int)post('period_month');
    $year     = (int)post('period_year');
    $due_day  = (int)post('due_day') ?: 10;
    $lease_id = int_param('lease_id', 0, 'post') ?: null;

    if (!$month || !$year) { $errors[] = 'Month and year are required.'; }

    if (!$errors) {
        $payload = ['month' => $month, 'year' => $year];
        if ($lease_id)  $payload['lease_id']   = $lease_id;
        if (int_param('property_id', 0, 'post')) $payload['property_id'] = int_param('property_id', 0, 'post');

        $res = $api->post('invoices/bulk', $payload);
        if (!empty($res['success'])) {
            set_flash('success', $res['message'] ?? 'Invoices generated.');
            redirect(BASE_URL . '/invoices/index.php?month=' . $month . '&year=' . $year);
        }
        $errors[] = $res['message'] ?? 'Failed to generate invoices.';
    }
}

$pre_lease_id = int_param('lease_id');
$page_title   = 'Generate Invoices';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3">
    <a href="<?= BASE_URL ?>/invoices/index.php" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0">Generate Monthly Invoices</h5>
</div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="card shadow-sm" style="max-width:520px">
    <div class="card-body">
        <p class="text-muted small">
            Generates rent invoices for all active leases for the selected period.
            Invoices already existing for that period will be skipped.
            The due date for each invoice is set automatically from the <strong>payment day</strong> configured on each lease.
            Utility charges are pulled from the unit.
        </p>
        <form method="POST">
            <?= csrf_field() ?>
            <?php if ($pre_lease_id): ?>
            <input type="hidden" name="lease_id" value="<?= $pre_lease_id ?>">
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label fw-semibold">Month *</label>
                    <select name="period_month" class="form-select" required>
                        <?php for ($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $m==(int)date('m')?'selected':'' ?>><?= month_name($m) ?></option><?php endfor; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Year *</label>
                    <input type="number" name="period_year" class="form-control" value="<?= date('Y') ?>" required min="2020">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Filter by Property <span class="text-muted fw-normal">(optional — leave blank for all)</span></label>
                    <?php
                    $props = (new ApiClient())->get('properties?per_page=100')['data'] ?? [];
                    ?>
                    <select name="property_id" class="form-select">
                        <option value="">All Properties</option>
                        <?php foreach ($props as $prop): ?>
                        <option value="<?= $prop['id'] ?>"><?= e($prop['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2 align-items-center">
                    <button type="submit" class="btn btn-info text-white"><i class="bi bi-receipt me-1"></i>Generate Invoices</button>
                    <a href="<?= BASE_URL ?>/invoices/mark-overdue.php" class="btn btn-outline-warning"><i class="bi bi-clock-history me-1"></i>Mark Overdue</a>
                </div>
            </div>
        </form>
    </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
