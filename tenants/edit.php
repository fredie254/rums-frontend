<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api = new ApiClient();
$id  = int_param('id');
if (!$id) { redirect(BASE_URL . '/tenants/index'); }

$res    = $api->get("tenants/$id");
$tenant = $res['data'] ?? null;
if (!$tenant) { set_flash('error', 'Tenant not found.'); redirect(BASE_URL . '/tenants/index'); }

$active_lease = $tenant['active_lease'] ?? null;

// Load available (vacant) units for the assignment form
$units_res = $api->get('units', ['status' => 'available', 'per_page' => 200]);
$units     = $units_res['data'] ?? [];

// Group units by property for the select
$units_by_property = [];
foreach ($units as $u) {
    $units_by_property[$u['property_name'] ?? 'Unknown'][] = $u;
}

$errors = [];

// ── Handle profile update ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'profile') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/tenants/edit?id=' . $id); }

    $first_name = trim(post('first_name'));
    $last_name  = trim(post('last_name'));
    $phone      = post('phone');
    $status     = post('status');

    if (!$first_name) $errors[] = 'First name is required.';
    if (!$phone)      $errors[] = 'Phone is required.';

    if (!$errors) {
        $upd = $api->put("tenants/$id", [
            'first_name'               => $first_name,
            'last_name'                => $last_name,
            'phone'                    => $phone,
            'status'                   => $status,
            'id_number'                => post('id_number'),
            'id_type'                  => post('id_type') ?: 'national_id',
            'dob'                      => post('dob') ?: null,
            'gender'                   => post('gender') ?: null,
            'emergency_contact_name'   => post('emergency_contact_name'),
            'emergency_contact_phone'  => post('emergency_contact_phone'),
            'emergency_contact_relation' => post('emergency_contact_relation'),
            'occupation'               => post('occupation'),
            'employer'                 => post('employer'),
            'monthly_income'           => post('monthly_income') ? (float)post('monthly_income') : null,
            'notes'                    => post('notes'),
        ]);
        if (!empty($upd['success'])) {
            set_flash('success', 'Tenant profile updated.');
            redirect(BASE_URL . '/tenants/view?id=' . $id);
        }
        $errors[] = $upd['message'] ?? 'Failed to update tenant.';
    }
}

// ── Handle unit assignment (create lease) ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'assign_unit') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/tenants/edit?id=' . $id); }

    $unit_id      = (int)post('unit_id');
    $start_date   = post('start_date');
    $end_date     = post('end_date');
    $monthly_rent = (float)post('monthly_rent');
    $payment_day  = (int)(post('payment_day') ?: 1);
    $deposit      = post('deposit_amount') ? (float)post('deposit_amount') : null;

    if (!$unit_id)    $errors[] = 'Please select a unit.';
    if (!$start_date) $errors[] = 'Start date is required.';
    if (!$end_date)   $errors[] = 'End date is required.';
    if ($end_date && $start_date && $end_date <= $start_date) $errors[] = 'End date must be after start date.';
    if ($monthly_rent <= 0) $errors[] = 'Monthly rent must be greater than zero.';

    if (!$errors) {
        $lease_res = $api->post('leases', array_filter([
            'tenant_id'      => $id,
            'unit_id'        => $unit_id,
            'start_date'     => $start_date,
            'end_date'       => $end_date,
            'monthly_rent'   => $monthly_rent,
            'payment_day'    => $payment_day,
            'deposit_amount' => $deposit,
        ], fn($v) => $v !== null));

        if (!empty($lease_res['success'])) {
            set_flash('success', 'Unit assigned and lease created successfully.');
            redirect(BASE_URL . '/tenants/view?id=' . $id);
        }
        $errors[] = $lease_res['message'] ?? 'Failed to create lease.';
    }
}

$page_title = 'Edit Tenant';
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex align-items-center mb-4">
    <a href="<?= BASE_URL ?>/tenants/view?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary me-3">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h5 class="fw-bold mb-0">Edit Tenant — <?= e($tenant['first_name'] . ' ' . $tenant['last_name']) ?></h5>
        <small class="text-muted"><?= e($tenant['email']) ?></small>
    </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger alert-dismissible">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- ── Profile form ────────────────────────────────────────── -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-person me-2 text-primary"></i>Personal Information
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="profile">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" value="<?= e($tenant['first_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" value="<?= e($tenant['last_name']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">ID / Document Number <span class="text-danger">*</span></label>
                            <input type="text" name="id_number" class="form-control" value="<?= e($tenant['id_number'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">ID Type</label>
                            <select name="id_type" class="form-select">
                                <option value="national_id"     <?= ($tenant['id_type']??'national_id')==='national_id'    ?'selected':'' ?>>National ID</option>
                                <option value="passport"        <?= ($tenant['id_type']??'')==='passport'       ?'selected':'' ?>>Passport</option>
                                <option value="alien_id"        <?= ($tenant['id_type']??'')==='alien_id'       ?'selected':'' ?>>Alien ID</option>
                                <option value="driving_license" <?= ($tenant['id_type']??'')==='driving_license'?'selected':'' ?>>Driving License</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone <span class="text-danger">*</span></label>
                            <input type="tel" name="phone" class="form-control" value="<?= e($tenant['phone']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email <span class="text-muted small">(read-only)</span></label>
                            <input type="email" class="form-control bg-light" value="<?= e($tenant['email']) ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Date of Birth</label>
                            <input type="date" name="dob" class="form-control" value="<?= e($tenant['dob'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="">—</option>
                                <option value="male"   <?= ($tenant['gender'] ?? '')==='male'   ? 'selected':'' ?>>Male</option>
                                <option value="female" <?= ($tenant['gender'] ?? '')==='female' ? 'selected':'' ?>>Female</option>
                                <option value="other"  <?= ($tenant['gender'] ?? '')==='other'  ? 'selected':'' ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Occupation</label>
                            <input type="text" name="occupation" class="form-control" value="<?= e($tenant['occupation'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Employer</label>
                            <input type="text" name="employer" class="form-control" value="<?= e($tenant['employer'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Monthly Income</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light small"><?= get_setting('currency_symbol', CURRENCY_SYMBOL) ?></span>
                                <input type="number" step="0.01" name="monthly_income" class="form-control" value="<?= e($tenant['monthly_income'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-12"><hr class="my-1"><h6 class="fw-semibold text-muted small text-uppercase">Emergency Contact</h6></div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Name</label>
                            <input type="text" name="emergency_contact_name" class="form-control" value="<?= e($tenant['emergency_contact_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="tel" name="emergency_contact_phone" class="form-control" value="<?= e($tenant['emergency_contact_phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Relationship</label>
                            <input type="text" name="emergency_contact_relation" class="form-control" value="<?= e($tenant['emergency_contact_relation'] ?? '') ?>" placeholder="e.g. Spouse">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Account Status</label>
                            <select name="status" class="form-select">
                                <option value="active"      <?= ($tenant['status'] ?? '')==='active'      ? 'selected':'' ?>>Active</option>
                                <option value="inactive"    <?= ($tenant['status'] ?? '')==='inactive'    ? 'selected':'' ?>>Inactive</option>
                                <option value="blacklisted" <?= ($tenant['status'] ?? '')==='blacklisted' ? 'selected':'' ?>>Blacklisted</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"><?= e($tenant['notes'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12 border-top pt-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i>Save Profile
                            </button>
                            <a href="<?= BASE_URL ?>/tenants/view?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Right column ────────────────────────────────────────── -->
    <div class="col-lg-4">

        <!-- Current unit / lease status -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-door-open me-2 text-primary"></i>Unit Assignment
            </div>
            <?php if ($active_lease): ?>
            <div class="card-body">
                <div class="alert alert-success d-flex gap-2 py-2 small mb-3">
                    <i class="bi bi-check-circle-fill flex-shrink-0 mt-1"></i>
                    <div>Currently assigned to <strong>Unit <?= e($active_lease['unit_number']) ?></strong> at <?= e($active_lease['property_name']) ?></div>
                </div>
                <table class="table table-sm mb-0">
                    <tr><td class="text-muted">Unit</td><td class="fw-semibold"><?= e($active_lease['unit_number']) ?></td></tr>
                    <tr><td class="text-muted">Property</td><td><?= e($active_lease['property_name']) ?></td></tr>
                    <tr><td class="text-muted">Rent</td><td><?= money((float)($active_lease['monthly_rent'] ?? 0)) ?>/mo</td></tr>
                    <tr><td class="text-muted">Lease ends</td><td><?= fmt_date($active_lease['end_date']) ?></td></tr>
                </table>
            </div>
            <div class="card-footer bg-white py-2">
                <a href="<?= BASE_URL ?>/leases/view?id=<?= $active_lease['id'] ?>" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-file-earmark-text me-1"></i>View Lease
                </a>
            </div>
            <?php else: ?>
            <div class="card-body">
                <div class="alert alert-warning d-flex gap-2 py-2 small mb-3">
                    <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
                    <div>This tenant has <strong>no active unit</strong>. Assign one below.</div>
                </div>

                <?php if ($units): ?>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="assign_unit">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Select Unit <span class="text-danger">*</span></label>
                            <select name="unit_id" id="unitSelect" class="form-select" required onchange="fillRent(this)">
                                <option value="">— Choose a vacant unit —</option>
                                <?php foreach ($units_by_property as $propName => $propUnits): ?>
                                <optgroup label="<?= e($propName) ?>">
                                    <?php foreach ($propUnits as $u): ?>
                                    <option value="<?= $u['id'] ?>"
                                        data-rent="<?= (float)($u['rent_amount'] ?? 0) ?>"
                                        data-deposit="<?= (float)($u['deposit_amount'] ?? 0) ?>">
                                        Unit <?= e($u['unit_number']) ?> — <?= strtoupper($u['unit_type'] ?? '') ?> — <?= money((float)($u['rent_amount'] ?? 0)) ?>/mo
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" id="endDate" class="form-control" value="<?= date('Y-m-d', strtotime('+1 year')) ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Monthly Rent <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light small"><?= get_setting('currency_symbol', CURRENCY_SYMBOL) ?></span>
                                <input type="number" step="0.01" name="monthly_rent" id="monthlyRent" class="form-control" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Security Deposit</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light small"><?= get_setting('currency_symbol', CURRENCY_SYMBOL) ?></span>
                                <input type="number" step="0.01" name="deposit_amount" id="depositAmount" class="form-control" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Payment Due Day</label>
                            <select name="payment_day" class="form-select">
                                <?php for ($d = 1; $d <= 28; $d++): ?>
                                <option value="<?= $d ?>" <?= $d === 1 ? 'selected' : '' ?>>Day <?= $d ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-house-check me-1"></i>Assign Unit & Create Lease
                            </button>
                        </div>
                    </div>
                </form>
                <?php else: ?>
                <div class="text-center text-muted py-2 small">
                    <i class="bi bi-door-closed d-block fs-3 mb-2 opacity-25"></i>
                    No vacant units available.<br>
                    <a href="<?= BASE_URL ?>/units/add" class="btn btn-sm btn-outline-primary mt-2">Add a Unit</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Linked user account -->
        <?php if (!empty($tenant['user_id'])): ?>
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-semibold small text-uppercase text-muted">Linked Login Account</div>
            <div class="card-body py-2 px-3">
                <div class="small text-muted mb-1">User ID #<?= $tenant['user_id'] ?></div>
                <div class="small"><?= e($tenant['email']) ?></div>
                <div class="mt-2">
                    <a href="<?= BASE_URL ?>/users/edit?id=<?= $tenant['user_id'] ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-person-gear me-1"></i>Manage Login
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function fillRent(sel) {
    const opt = sel.options[sel.selectedIndex];
    const rent    = opt.dataset.rent    || '';
    const deposit = opt.dataset.deposit || '';
    document.getElementById('monthlyRent').value   = rent    ? parseFloat(rent).toFixed(2)    : '';
    document.getElementById('depositAmount').value = deposit ? parseFloat(deposit).toFixed(2) : '';
}
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
