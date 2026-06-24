<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api    = new ApiClient();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/landlords/add'); }

    $data = [
        'name'            => post('name'),
        'email'           => post('email'),
        'phone'           => post('phone'),
        'id_number'       => post('id_number'),
        'kra_pin'         => post('kra_pin'),
        'bank_name'       => post('bank_name'),
        'bank_account'    => post('bank_account'),
        'bank_branch'     => post('bank_branch'),
        'mpesa_number'    => post('mpesa_number'),
        'commission_rate' => (float)post('commission_rate'),
        'notes'           => post('notes'),
    ];

    if (!$data['name'])      $errors[] = 'Full name is required.';
    if (!$data['email'])     $errors[] = 'Email is required.';
    if (!$data['phone'])     $errors[] = 'Phone is required.';
    if (!$data['id_number']) $errors[] = 'ID number is required.';

    if (!$errors) {
        $res = $api->post('landlords', $data);
        if (!empty($res['success'])) {
            set_flash('success', $res['message'] ?? 'Landlord added successfully.');
            redirect(BASE_URL . '/landlords/index');
        }
        $errors[] = $res['message'] ?? 'Failed to save landlord.';
    }
}

$page_title = 'Add Landlord';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3">
    <a href="<?= BASE_URL ?>/landlords/index" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0">Add New Landlord</h5>
</div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="card shadow-sm"><div class="card-body">
    <form method="POST">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-12"><h6 class="text-primary fw-semibold">Personal Details</h6></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Full Name *</label><input type="text" name="name" class="form-control" value="<?= e(post('name')) ?>" required></div>
            <div class="col-md-6"><label class="form-label fw-semibold">National ID *</label><input type="text" name="id_number" class="form-control" value="<?= e(post('id_number')) ?>" required></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Email *</label><input type="email" name="email" class="form-control" value="<?= e(post('email')) ?>" required></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Phone *</label><input type="tel" name="phone" class="form-control" value="<?= e(post('phone')) ?>" placeholder="07XXXXXXXX" required></div>
            <div class="col-md-4"><label class="form-label fw-semibold">KRA PIN</label><input type="text" name="kra_pin" class="form-control" value="<?= e(post('kra_pin')) ?>"></div>
            <div class="col-md-4"><label class="form-label fw-semibold">Commission Rate (%)</label><input type="number" step="0.01" name="commission_rate" class="form-control" value="<?= e(post('commission_rate') ?: '0') ?>" min="0" max="100"></div>
            <div class="col-md-4"><label class="form-label fw-semibold">M-Pesa Number</label><input type="tel" name="mpesa_number" class="form-control" value="<?= e(post('mpesa_number')) ?>" placeholder="2547XXXXXXXX"></div>
            <div class="col-12"><h6 class="text-primary fw-semibold mt-2">Bank Details</h6></div>
            <div class="col-md-4"><label class="form-label fw-semibold">Bank Name</label><input type="text" name="bank_name" class="form-control" value="<?= e(post('bank_name')) ?>"></div>
            <div class="col-md-4"><label class="form-label fw-semibold">Account Number</label><input type="text" name="bank_account" class="form-control" value="<?= e(post('bank_account')) ?>"></div>
            <div class="col-md-4"><label class="form-label fw-semibold">Branch</label><input type="text" name="bank_branch" class="form-control" value="<?= e(post('bank_branch')) ?>"></div>
            <div class="col-12"><label class="form-label fw-semibold">Notes</label><textarea name="notes" class="form-control" rows="2"><?= e(post('notes')) ?></textarea></div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save Landlord</button>
                <a href="<?= BASE_URL ?>/landlords/index" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </form>
</div></div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
