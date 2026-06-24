<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api    = new ApiClient();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/tenants/add'); }

    $first_name = trim(post('first_name'));
    $last_name  = trim(post('last_name'));
    $email      = post('email');
    $phone      = post('phone');
    $id_number  = post('id_number');
    $dob        = post('dob') ?: null;
    $gender     = post('gender') ?: null;
    $ec_name    = post('emergency_contact_name');
    $ec_phone   = post('emergency_contact_phone');
    $ec_rel     = post('emergency_contact_relation');
    $occupation = post('occupation');
    $employer   = post('employer');
    $income     = post('monthly_income') ? (float)post('monthly_income') : null;
    $notes      = post('notes');

    if (!$first_name) $errors[] = 'First name is required.';
    if (!$last_name)  $errors[] = 'Last name is required.';
    if (!$email)      $errors[] = 'Email is required.';
    if (!$phone)      $errors[] = 'Phone number is required.';
    if (!$id_number)  $errors[] = 'National ID number is required.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';

    if (!$errors) {
        $res = $api->post('tenants', [
            'first_name'               => $first_name,
            'last_name'                => $last_name,
            'email'                    => $email,
            'phone'                    => $phone,
            'id_number'                => $id_number,
            'id_type'                  => post('id_type') ?: 'national_id',
            'dob'                      => $dob,
            'gender'                   => $gender,
            'emergency_contact_name'   => $ec_name,
            'emergency_contact_phone'  => $ec_phone,
            'emergency_contact_relation' => $ec_rel,
            'occupation'               => $occupation,
            'employer'                 => $employer,
            'monthly_income'           => $income,
            'notes'                    => $notes,
        ]);
        if (!empty($res['success'])) {
            $pwd = $res['data']['default_password'] ?? ('Tenant@' . substr(preg_replace('/\D/', '', $id_number), -4));
            set_flash('success', "Tenant \"$first_name $last_name\" added. Login: $email — Default password: $pwd");
            redirect(BASE_URL . '/tenants/index');
        }
        $errors[] = $res['message'] ?? 'Failed to save tenant.';
    }
}

$page_title = 'Add Tenant';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3">
    <a href="<?= BASE_URL ?>/tenants/index" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0">Add New Tenant</h5>
</div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST">
            <?= csrf_field() ?>
            <h6 class="fw-semibold mb-3 text-primary">Personal Information</h6>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label fw-semibold">First Name *</label><input type="text" name="first_name" class="form-control" value="<?= e(post('first_name')) ?>" required></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Last Name *</label><input type="text" name="last_name" class="form-control" value="<?= e(post('last_name')) ?>" required></div>
                <div class="col-md-4"><label class="form-label fw-semibold">ID / Document Number *</label><input type="text" name="id_number" class="form-control" value="<?= e(post('id_number')) ?>" required></div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">ID Type</label>
                    <select name="id_type" class="form-select">
                        <option value="national_id"     <?= (post('id_type')||'national_id')==='national_id'    ?'selected':'' ?>>National ID</option>
                        <option value="passport"        <?= post('id_type')==='passport'       ?'selected':'' ?>>Passport</option>
                        <option value="alien_id"        <?= post('id_type')==='alien_id'       ?'selected':'' ?>>Alien ID</option>
                        <option value="driving_license" <?= post('id_type')==='driving_license'?'selected':'' ?>>Driving License</option>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label fw-semibold">Email Address *</label><input type="email" name="email" class="form-control" value="<?= e(post('email')) ?>" required></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Phone Number *</label><input type="tel" name="phone" class="form-control" value="<?= e(post('phone')) ?>" placeholder="07XXXXXXXX" required></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Date of Birth</label><input type="date" name="dob" class="form-control" value="<?= e(post('dob')) ?>"></div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">— Select —</option>
                        <option value="male"   <?= post('gender')==='male'?'selected':'' ?>>Male</option>
                        <option value="female" <?= post('gender')==='female'?'selected':'' ?>>Female</option>
                        <option value="other"  <?= post('gender')==='other'?'selected':'' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label fw-semibold">Occupation</label><input type="text" name="occupation" class="form-control" value="<?= e(post('occupation')) ?>"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Employer</label><input type="text" name="employer" class="form-control" value="<?= e(post('employer')) ?>"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Monthly Income (<?= get_setting('currency_symbol', CURRENCY_SYMBOL) ?>)</label><input type="number" step="0.01" name="monthly_income" class="form-control" value="<?= e(post('monthly_income')) ?>"></div>

                <div class="col-12"><hr><h6 class="fw-semibold text-primary">Emergency Contact</h6></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Name</label><input type="text" name="emergency_contact_name" class="form-control" value="<?= e(post('emergency_contact_name')) ?>"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Phone</label><input type="tel" name="emergency_contact_phone" class="form-control" value="<?= e(post('emergency_contact_phone')) ?>"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Relationship</label><input type="text" name="emergency_contact_relation" class="form-control" value="<?= e(post('emergency_contact_relation')) ?>" placeholder="e.g. Spouse, Parent"></div>

                <div class="col-12"><label class="form-label fw-semibold">Notes</label><textarea name="notes" class="form-control" rows="2"><?= e(post('notes')) ?></textarea></div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save Tenant</button>
                    <a href="<?= BASE_URL ?>/tenants/index" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
