<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$api = new ApiClient();
$id  = int_param('id');
if (!$id) { redirect(BASE_URL . '/users/index.php'); }

$res  = $api->get("users/$id");
$user = $res['data'] ?? null;
if (!$user) { set_flash('error', 'User not found.'); redirect(BASE_URL . '/users/index.php'); }

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

$roleBadges = ['admin'=>'danger','manager'=>'primary','landlord'=>'warning text-dark','accountant'=>'info text-dark','maintenance'=>'orange','auditor'=>'purple','security'=>'dark','tenant'=>'success'];

$errors  = [];
$isSelf  = ($id == $_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/users/edit.php?id=' . $id); }

    $name       = post('name');
    $phone      = post('phone');
    $role       = post('role');
    $new_status = post('status');
    $new_pass   = $_POST['new_password']     ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    if (!$name)                              $errors[] = 'Full name is required.';
    if ($role && !isset($all_roles[$role]))  $errors[] = 'Invalid role selected.';
    if ($new_pass && strlen($new_pass) < 8)  $errors[] = 'New password must be at least 8 characters.';
    if ($new_pass && $new_pass !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $payload = array_filter([
            'name'  => $name,
            'phone' => $phone ?: null,
            'role'  => $role,
        ], fn($v) => $v !== null);

        if ($new_pass) $payload['password'] = $new_pass;

        $r1 = $api->patch("users/$id", $payload);

        // Only update status for other users
        if (!$isSelf && $new_status) {
            $api->patch("users/$id/status", ['status' => $new_status]);
        }

        if (!empty($r1['success'])) {
            set_flash('success', "User \"{$name}\" updated successfully.");
            redirect(BASE_URL . '/users/index.php');
        }
        $errors[] = $r1['message'] ?? 'Failed to update user.';
    }
}

// Current values (post > db)
$cur_role   = post('role')   ?: $user['role'];
$cur_status = post('status') ?: $user['status'];

$page_title = 'Edit User';
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex align-items-center mb-4">
    <a href="<?= BASE_URL ?>/users/index.php" class="btn btn-sm btn-outline-secondary me-3">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h5 class="fw-bold mb-0">Edit User</h5>
        <small class="text-muted">Update details, role or password for <?= e($user['name']) ?></small>
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
    <!-- Edit form -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex align-items-center gap-3">
                <div class="avatar-sm bg-<?= $roleBadges[$user['role']] ?? 'secondary' ?> rounded-circle d-flex align-items-center justify-content-center fw-bold">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
                <div>
                    <div class="fw-semibold"><?= e($user['name']) ?></div>
                    <small class="text-muted"><?= e($user['email']) ?></small>
                </div>
                <span class="ms-auto badge bg-<?= $roleBadges[$user['role']] ?? 'secondary' ?>"><?= $all_roles[$user['role']]['label'] ?? ucfirst($user['role']) ?></span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="row g-3">

                        <!-- Basic info -->
                        <div class="col-12">
                            <h6 class="fw-semibold text-muted text-uppercase small mb-2">Basic Information</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?= e(post('name') ?: $user['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email <span class="text-muted small">(read-only)</span></label>
                            <input type="email" class="form-control bg-light" value="<?= e($user['email']) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="tel" name="phone" class="form-control" value="<?= e(post('phone') ?: ($user['phone'] ?? '')) ?>" placeholder="07XXXXXXXX">
                        </div>

                        <!-- Role & status -->
                        <div class="col-12 mt-1">
                            <h6 class="fw-semibold text-muted text-uppercase small mb-2">Access Control</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role</label>
                            <select name="role" id="roleSelect" class="form-select" onchange="updateRoleHint(this.value)">
                                <?php foreach ($all_roles as $rkey => $rcfg): ?>
                                <option value="<?= $rkey ?>" <?= $cur_role === $rkey ? 'selected' : '' ?>><?= $rcfg['label'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div id="roleHint" class="form-text text-muted mt-1" style="min-height:1.2em">
                                <?= $all_roles[$cur_role]['desc'] ?? '' ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <?php if ($isSelf): ?>
                            <input class="form-control bg-light" value="Active (cannot change own status)" readonly>
                            <?php else: ?>
                            <select name="status" class="form-select">
                                <option value="active"    <?= $cur_status === 'active'    ? 'selected' : '' ?>>Active</option>
                                <option value="inactive"  <?= $cur_status === 'inactive'  ? 'selected' : '' ?>>Inactive</option>
                                <option value="suspended" <?= $cur_status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                            </select>
                            <?php endif; ?>
                        </div>

                        <!-- Password reset -->
                        <div class="col-12 mt-1">
                            <h6 class="fw-semibold text-muted text-uppercase small mb-2">Change Password <span class="fw-normal text-muted">(optional)</span></h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">New Password</label>
                            <div class="input-group">
                                <input type="password" name="new_password" id="newPassword" class="form-control" minlength="8" placeholder="Leave blank to keep current">
                                <button type="button" class="btn btn-outline-secondary" onclick="toggleVis('newPassword')"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" id="confirmPassword" class="form-control" minlength="8" placeholder="Repeat new password">
                                <button type="button" class="btn btn-outline-secondary" onclick="toggleVis('confirmPassword')"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>

                        <div class="col-12 pt-2 border-top d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i>Save Changes
                            </button>
                            <a href="<?= BASE_URL ?>/users/index.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Account info panel -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white fw-semibold small text-uppercase text-muted">Account Info</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr>
                        <td class="text-muted ps-3">User ID</td>
                        <td class="fw-semibold pe-3">#<?= $user['id'] ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-3">Status</td>
                        <td class="pe-3">
                            <?php if ($user['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php elseif ($user['status'] === 'suspended'): ?>
                                <span class="badge bg-danger">Suspended</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-3">Joined</td>
                        <td class="pe-3"><small><?= fmt_date($user['created_at'], 'd M Y') ?></small></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-3">Last Login</td>
                        <td class="pe-3">
                            <small><?= !empty($user['last_login']) ? fmt_date($user['last_login'], 'd M Y H:i') : '<span class="text-muted fst-italic">Never</span>' ?></small>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php if (!$isSelf): ?>
        <div class="card border-danger-subtle shadow-sm">
            <div class="card-header bg-white fw-semibold small text-uppercase text-muted">Quick Actions</div>
            <div class="card-body d-grid gap-2">
                <?php if ($user['status'] === 'active'): ?>
                <a href="<?= BASE_URL ?>/users/set_status.php?id=<?= $id ?>&status=suspended&csrf=<?= csrf_token() ?>"
                   class="btn btn-sm btn-outline-danger"
                   onclick="return confirm('Suspend this user? They will not be able to log in.')">
                    <i class="bi bi-slash-circle me-1"></i>Suspend Account
                </a>
                <?php else: ?>
                <a href="<?= BASE_URL ?>/users/set_status.php?id=<?= $id ?>&status=active&csrf=<?= csrf_token() ?>"
                   class="btn btn-sm btn-outline-success">
                    <i class="bi bi-check-circle me-1"></i>Activate Account
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const roleMeta = <?= json_encode(array_map(fn($r) => $r['desc'], $all_roles)) ?>;

function updateRoleHint(val) {
    document.getElementById('roleHint').textContent = roleMeta[val] || '';
}

function toggleVis(id) {
    const el  = document.getElementById(id);
    const btn = el.nextElementSibling;
    el.type   = el.type === 'password' ? 'text' : 'password';
    btn.innerHTML = el.type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
}
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
