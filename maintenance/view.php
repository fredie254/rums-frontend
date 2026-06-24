<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$api = new ApiClient();
$id  = int_param('id');
if (!$id) { redirect(BASE_URL . '/maintenance/index'); }

$res = $api->get("maintenance/$id");
$req = $res['data'] ?? null;
if (!$req) { set_flash('error', 'Request not found.'); redirect(BASE_URL . '/maintenance/index'); }

// Status update (managers only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_manager()) {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/maintenance/view?id=' . $id); }
    $new_status  = post('status');
    $assigned_to = int_param('assigned_to', 0, 'post') ?: null;
    $actual_cost = post('actual_cost') !== '' ? (float)post('actual_cost') : null;
    $notes       = post('resolution_notes');

    $payload = array_filter([
        'status'      => $new_status,
        'assigned_to' => $assigned_to,
        'notes'       => $notes ?: null,
    ], fn($v) => $v !== null && $v !== '');
    if ($actual_cost !== null) {
        $payload['materials_cost'] = $actual_cost; // map to closest API field
    }
    // Set work_completed timestamp when completing
    if (in_array($new_status, ['completed', 'resolved'])) {
        $payload['work_completed'] = date('Y-m-d H:i:s');
    }

    $upd = $api->patch("maintenance/$id", $payload);
    set_flash(!empty($upd['success']) ? 'success' : 'error', $upd['message'] ?? 'Request updated.');
    redirect(BASE_URL . '/maintenance/view?id=' . $id);
}

// Staff dropdown for manager update form
$staff = [];
if (is_manager()) {
    $a_res = $api->get('users', ['role' => 'admin',   'status' => 'active', 'per_page' => 200]);
    $m_res = $api->get('users', ['role' => 'manager', 'status' => 'active', 'per_page' => 200]);
    $staff = array_merge($a_res['data'] ?? [], $m_res['data'] ?? []);
    usort($staff, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));
}

$page_title = 'Maintenance Request';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3 gap-2">
    <a href="<?= BASE_URL ?>/maintenance/index" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0 flex-grow-1"><?= e($req['issue_title'] ?? '') ?></h5>
    <?= priority_badge($req['priority'] ?? 'low') ?> <?= maintenance_badge($req['status'] ?? 'open') ?>
</div>
<div class="row g-3">
    <div class="col-md-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-info-circle me-1 text-primary"></i>Request Details</div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted">Request #</dt><dd class="col-7"><code><?= e($req['request_number'] ?? '') ?></code></dd>
                    <dt class="col-5 text-muted">Unit</dt><dd class="col-7"><?= e($req['property_name'] ?? '') ?>/<?= e($req['unit_number'] ?? '') ?></dd>
                    <dt class="col-5 text-muted">Tenant</dt><dd class="col-7"><?= !empty($req['tenant_name']) ? e($req['tenant_name']) : '—' ?></dd>
                    <dt class="col-5 text-muted">Category</dt><dd class="col-7"><?= ucfirst(str_replace('_',' ',$req['category'] ?? '')) ?></dd>
                    <dt class="col-5 text-muted">Reported</dt><dd class="col-7"><?= fmt_date($req['created_at']) ?></dd>
                    <dt class="col-5 text-muted">Assigned To</dt><dd class="col-7"><?= !empty($req['assigned_to_name']) ? e($req['assigned_to_name']) : '<span class="text-muted">Unassigned</span>' ?></dd>
                    <?php if (!empty($req['total_cost']) && $req['total_cost'] > 0): ?>
                    <dt class="col-5 text-muted">Total Cost</dt><dd class="col-7"><?= money($req['total_cost']) ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($req['work_completed'])): ?>
                    <dt class="col-5 text-muted">Completed</dt><dd class="col-7"><?= fmt_date($req['work_completed']) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-journal me-1"></i>Description</div>
            <div class="card-body small"><?= nl2br(e($req['description'] ?? '')) ?></div>
        </div>
        <?php if (!empty($req['notes'])): ?>
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold small text-success"><i class="bi bi-check-circle me-1"></i>Resolution Notes</div>
            <div class="card-body small"><?= nl2br(e($req['notes'])) ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php if (is_manager()): ?>
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-pencil-square me-1 text-warning"></i>Update Request</div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <?php foreach (['open','assigned','in_progress','completed','closed','cancelled'] as $s): ?>
                                <option value="<?= $s ?>" <?= ($req['status'] ?? '')===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($staff): ?>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Assign To</label>
                            <select name="assigned_to" class="form-select">
                                <option value="">— Unassigned —</option>
                                <?php foreach ($staff as $s): ?><option value="<?= $s['id'] ?>" <?= ($req['assigned_to'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-4"><label class="form-label fw-semibold">Actual Cost (<?= get_setting('currency_symbol', CURRENCY_SYMBOL) ?>)</label><input type="number" step="0.01" name="actual_cost" class="form-control" value="<?= $req['total_cost'] ?? '' ?>"></div>
                        <div class="col-12"><label class="form-label fw-semibold">Resolution Notes</label><textarea name="resolution_notes" class="form-control" rows="3"><?= e($req['notes'] ?? '') ?></textarea></div>
                        <div class="col-12"><button type="submit" class="btn btn-warning"><i class="bi bi-check-circle me-1"></i>Update Request</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
