<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$api    = new ApiClient();
$errors = [];

$all_roles = [
    'admin'       => ['label' => 'Admin',       'desc' => 'Full system access — settings, users, all data.'],
    'manager'     => ['label' => 'Manager',     'desc' => 'Manage properties, tenants, leases and operations.'],
    'landlord'    => ['label' => 'Landlord',    'desc' => 'View own portfolio, units and income statements.'],
    'accountant'  => ['label' => 'Accountant',  'desc' => 'Financials, invoices, payments, AR aging and reports.'],
    'maintenance' => ['label' => 'Maintenance', 'desc' => 'View and update assigned work orders.'],
    'auditor'     => ['label' => 'Auditor',     'desc' => 'Read-only access to audit trail and compliance reports.'],
    'security'    => ['label' => 'Security',    'desc' => 'Manage visitor log, occupancy log and incidents.'],
    'tenant'      => ['label' => 'Tenant',      'desc' => 'Self-service portal — lease, invoices, maintenance requests.'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/users/add'); }

    $name      = post('name');
    $email     = post('email');
    $phone     = post('phone');
    $role      = post('role');
    $id_number = trim(post('id_number'));
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if (!$name)                                                  $errors[] = 'Full name is required.';
    if (!$email)                                                 $errors[] = 'Email address is required.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL))   $errors[] = 'Enter a valid email address.';
    if (!$role || !isset($all_roles[$role]))                     $errors[] = 'Please select a valid role.';
    if ($role === 'tenant' && !$id_number)                       $errors[] = 'National ID is required for tenant accounts.';
    if (!$password || strlen($password) < 8)                    $errors[] = 'Password must be at least 8 characters.';
    if ($password && $password !== $confirm)                     $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $payload = array_filter([
            'name'      => $name,
            'email'     => $email,
            'phone'     => $phone ?: null,
            'role'      => $role,
            'password'  => $password,
            'id_number' => $id_number ?: null,
        ], fn($v) => $v !== null);
        $res = $api->post('users', $payload);

        if (!empty($res['success'])) {
            set_flash('success', "User \"$name\" created successfully.");
            redirect(BASE_URL . '/users/index');
        }
        $errors[] = $res['message'] ?? 'Failed to create user.';
    }
}

$sel_role = post('role');
$page_title = 'Add User';
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex align-items-center mb-4">
    <a href="<?= BASE_URL ?>/users/index" class="btn btn-sm btn-outline-secondary me-3">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h5 class="fw-bold mb-0">Add New User</h5>
        <small class="text-muted">Create a system account and assign a role</small>
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
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-person-plus me-2 text-primary"></i>User Details
            </div>
            <div class="card-body">
                <form method="POST" id="addUserForm">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                <input type="text" name="name" class="form-control" value="<?= e(post('name')) ?>" placeholder="e.g. John Doe" required autofocus>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" class="form-control" value="<?= e(post('email')) ?>" placeholder="user@example.com" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-telephone"></i></span>
                                <input type="tel" name="phone" class="form-control" value="<?= e(post('phone')) ?>" placeholder="07XXXXXXXX">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                            <select name="role" id="roleSelect" class="form-select" required onchange="updateRoleHint(this.value)">
                                <option value="">— Select a role —</option>
                                <?php foreach ($all_roles as $rkey => $rcfg): ?>
                                <option value="<?= $rkey ?>" <?= $sel_role === $rkey ? 'selected' : '' ?>>
                                    <?= $rcfg['label'] ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="roleHint" class="form-text text-muted mt-1" style="min-height:1.2em">
                                <?= $sel_role && isset($all_roles[$sel_role]) ? $all_roles[$sel_role]['desc'] : '' ?>
                            </div>
                        </div>
                        <!-- Landlord info banner -->
                        <div class="col-12" id="landlordFields" style="<?= $sel_role === 'landlord' ? '' : 'display:none' ?>">
                            <div class="alert alert-warning d-flex gap-2 small py-2 mb-0">
                                <i class="bi bi-person-badge-fill flex-shrink-0 mt-1"></i>
                                <div>A <strong>landlord profile</strong> will be auto-created. The landlord will appear in the Landlords section. You can complete their banking and ID details from the Landlords page after creation.</div>
                            </div>
                        </div>
                        <!-- Tenant fields -->
                        <div class="col-12" id="tenantFields" style="<?= $sel_role === 'tenant' ? '' : 'display:none' ?>">
                            <div class="alert alert-info d-flex gap-2 small py-2 mb-0">
                                <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                                <div>A <strong>tenant profile</strong> will be auto-created and the tenant will appear in the tenant list. Provide their National ID so the profile is complete.</div>
                            </div>
                            <label class="form-label fw-semibold mt-3">National ID Number <span class="text-danger">*</span></label>
                            <input type="text" name="id_number" id="idNumber" class="form-control" value="<?= e(post('id_number')) ?>" placeholder="e.g. 12345678">
                            <div class="form-text text-muted">Required for tenant profile.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password" id="password" class="form-control" minlength="8" placeholder="Min. 8 characters" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="toggleVis('password')"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" id="confirmPassword" class="form-control" minlength="8" placeholder="Repeat password" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="toggleVis('confirmPassword')"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>
                        <div class="col-12 pt-2 d-flex gap-2 border-top">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i>Create User
                            </button>
                            <a href="<?= BASE_URL ?>/users/index" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Role reference card -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-info-circle me-2 text-primary"></i>Role Reference
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php
                    $roleBadges = ['admin'=>'danger','manager'=>'primary','landlord'=>'warning text-dark','accountant'=>'info text-dark','maintenance'=>'orange','auditor'=>'purple','security'=>'dark','tenant'=>'success'];
                    foreach ($all_roles as $rkey => $rcfg):
                    ?>
                    <li class="list-group-item d-flex align-items-start gap-2 py-2 <?= $sel_role === $rkey ? 'bg-primary-soft' : '' ?>" id="roleRow_<?= $rkey ?>">
                        <span class="badge bg-<?= $roleBadges[$rkey] ?> mt-1 flex-shrink-0" style="width:80px;text-align:center"><?= $rcfg['label'] ?></span>
                        <small class="text-muted"><?= $rcfg['desc'] ?></small>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
const roleMeta = <?= json_encode(array_map(fn($r) => $r['desc'], $all_roles)) ?>;

function updateRoleHint(val) {
    document.getElementById('roleHint').textContent = roleMeta[val] || '';
    document.querySelectorAll('[id^="roleRow_"]').forEach(el => el.classList.remove('bg-primary-soft'));
    if (val) {
        const row = document.getElementById('roleRow_' + val);
        if (row) row.classList.add('bg-primary-soft');
    }
    // Show/hide role-specific info banners
    document.getElementById('landlordFields').style.display = val === 'landlord' ? '' : 'none';

    const tenantFields = document.getElementById('tenantFields');
    const idInput      = document.getElementById('idNumber');
    if (val === 'tenant') {
        tenantFields.style.display = '';
        idInput.setAttribute('required', 'required');
    } else {
        tenantFields.style.display = 'none';
        idInput.removeAttribute('required');
    }
}

function toggleVis(id) {
    const el  = document.getElementById(id);
    const btn = el.nextElementSibling;
    el.type   = el.type === 'password' ? 'text' : 'password';
    btn.innerHTML = el.type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
}
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
