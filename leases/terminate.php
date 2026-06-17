<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api = new ApiClient();
$id  = int_param('id');
if (!$id) { redirect(BASE_URL . '/leases/index.php'); }

$res   = $api->get("leases/$id");
$lease = $res['data'] ?? null;
if (!$lease || $lease['status'] !== 'active') {
    set_flash('error', 'Active lease not found.');
    redirect(BASE_URL . '/leases/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/leases/terminate.php?id=' . $id); }
    $reason = post('termination_reason');
    $res = $api->post("leases/$id/terminate", ['reason' => $reason]);
    if (!empty($res['success'])) {
        set_flash('success', "Lease terminated. Unit {$lease['unit_number']} is now available.");
        redirect(BASE_URL . '/leases/view.php?id=' . $id);
    }
    set_flash('error', $res['message'] ?? 'Failed to terminate lease.');
    redirect(BASE_URL . '/leases/terminate.php?id=' . $id);
}

$page_title = 'Terminate Lease';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3">
    <a href="<?= BASE_URL ?>/leases/view.php?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0">Terminate Lease — <code><?= e($lease['lease_number']) ?></code></h5>
</div>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i>This action will terminate the lease and mark the unit as available. This cannot be undone.</div>
<div class="card shadow-sm"><div class="card-body">
    <form method="POST">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-12"><label class="form-label fw-semibold">Reason for Termination</label><textarea name="termination_reason" class="form-control" rows="3" required placeholder="Explain the reason for early termination..."></textarea></div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle me-1"></i>Confirm Termination</button>
                <a href="<?= BASE_URL ?>/leases/view.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </form>
</div></div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
