<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api = new ApiClient();
$id  = int_param('id');
$res = $api->get("landlords/$id");
$ll  = $res['data'] ?? null;
if (!$ll) { set_flash('error', 'Not found.'); redirect(BASE_URL . '/landlords/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/landlords/edit.php?id=' . $id); }

    $data = [
        'name'            => post('name'),
        'phone'           => post('phone'),
        'status'          => post('status'),
        'id_number'       => post('id_number'),
        'kra_pin'         => post('kra_pin'),
        'bank_name'       => post('bank_name'),
        'bank_account'    => post('bank_account'),
        'bank_branch'     => post('bank_branch'),
        'mpesa_number'    => post('mpesa_number'),
        'commission_rate' => (float)post('commission_rate'),
        'notes'           => post('notes'),
    ];

    if (!$data['name'])  $errors[] = 'Name is required.';
    if (!$data['phone']) $errors[] = 'Phone is required.';

    if (!$errors) {
        $upd = $api->put("landlords/$id", $data);
        if (!empty($upd['success'])) {
            set_flash('success', 'Landlord updated.');
            redirect(BASE_URL . '/landlords/view.php?id=' . $id);
        }
        $errors[] = $upd['message'] ?? 'Failed to update landlord.';
    }
    $ll = array_merge($ll, $data, ['user_status' => $data['status']]);
}

$page_title = 'Edit Landlord';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3">
    <a href="<?= BASE_URL ?>/landlords/view.php?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0">Edit Landlord — <?= e($ll['name']) ?></h5>
</div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="card shadow-sm"><div class="card-body">
    <form method="POST"><?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label fw-semibold">Full Name *</label><input type="text" name="name" class="form-control" value="<?= e($ll['name']) ?>" required></div>
            <div class="col-md-6"><label class="form-label fw-semibold">National ID</label><input type="text" name="id_number" class="form-control" value="<?= e($ll['id_number']) ?>"></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Phone *</label><input type="tel" name="phone" class="form-control" value="<?= e($ll['phone']) ?>" required></div>
            <div class="col-md-3"><label class="form-label fw-semibold">KRA PIN</label><input type="text" name="kra_pin" class="form-control" value="<?= e($ll['kra_pin']) ?>"></div>
            <div class="col-md-3"><label class="form-label fw-semibold">Commission %</label><input type="number" step="0.01" name="commission_rate" class="form-control" value="<?= e($ll['commission_rate']) ?>"></div>
            <div class="col-md-6"><label class="form-label fw-semibold">M-Pesa Number</label><input type="tel" name="mpesa_number" class="form-control" value="<?= e($ll['mpesa_number']) ?>"></div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Status</label>
                <select name="status" class="form-select">
                    <option value="active" <?= ($ll['user_status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($ll['user_status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-4"><label class="form-label fw-semibold">Bank Name</label><input type="text" name="bank_name" class="form-control" value="<?= e($ll['bank_name']) ?>"></div>
            <div class="col-md-4"><label class="form-label fw-semibold">Account Number</label><input type="text" name="bank_account" class="form-control" value="<?= e($ll['bank_account']) ?>"></div>
            <div class="col-md-4"><label class="form-label fw-semibold">Branch</label><input type="text" name="bank_branch" class="form-control" value="<?= e($ll['bank_branch']) ?>"></div>
            <div class="col-12"><label class="form-label fw-semibold">Notes</label><textarea name="notes" class="form-control" rows="2"><?= e($ll['notes']) ?></textarea></div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Update</button>
                <a href="<?= BASE_URL ?>/landlords/view.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </form>
</div></div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
