<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'accountant');

$api    = new ApiClient();
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/invoices/mark-overdue.php'); }
    $res    = $api->post('invoices/mark-overdue', []);
    $result = $res['data'] ?? null;
    if (!empty($res['success'])) {
        set_flash('success', $res['message'] ?? 'Done.');
    } else {
        set_flash('error', $res['message'] ?? 'Failed.');
    }
}

$page_title = 'Mark Invoices Overdue';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3">
    <a href="<?= BASE_URL ?>/invoices/index.php" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0">Mark Invoices as Overdue</h5>
</div>

<?php flash_messages(); ?>

<div class="card shadow-sm" style="max-width:520px">
    <div class="card-body">
        <p class="text-muted small">
            This will update all <strong>unpaid</strong> and <strong>partial</strong> invoices whose due date
            (plus the lease's grace period) has passed to <span class="badge bg-danger">overdue</span>.
            It is safe to run multiple times — only invoices that qualify will be updated.
        </p>
        <?php if ($result !== null): ?>
        <div class="alert alert-info small"><strong><?= (int)$result['updated'] ?></strong> invoice(s) marked as overdue.</div>
        <?php endif; ?>
        <form method="POST">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-warning"><i class="bi bi-clock-history me-1"></i>Run Mark-Overdue</button>
        </form>
    </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
