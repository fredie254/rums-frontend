<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$api = new ApiClient();
$id  = int_param('id');
if (!$id) { redirect(BASE_URL . '/users/index.php'); }

$res  = $api->get("users/$id");
$user = $res['data'] ?? null;
if (!$user) { set_flash('error', 'User not found.'); redirect(BASE_URL . '/users/index.php'); }

// Fetch user's API tokens
$tok_res = $api->get("users/$id/tokens");
$tokens  = $tok_res['data'] ?? [];

$all_roles = [
    'admin'       => ['label' => 'Admin',       'badge' => 'danger',           'desc' => 'Full system access — settings, users, all data.'],
    'manager'     => ['label' => 'Manager',     'badge' => 'primary',          'desc' => 'Manage properties, tenants, leases and operations.'],
    'landlord'    => ['label' => 'Landlord',    'badge' => 'warning text-dark','desc' => 'View own portfolio, units and income statements.'],
    'accountant'  => ['label' => 'Accountant',  'badge' => 'info text-dark',   'desc' => 'Financials, invoices, payments, AR aging and reports.'],
    'maintenance' => ['label' => 'Maintenance', 'badge' => 'orange',           'desc' => 'View and update assigned work orders.'],
    'auditor'     => ['label' => 'Auditor',     'badge' => 'purple',           'desc' => 'Read-only access to audit trail and compliance reports.'],
    'security'    => ['label' => 'Security',    'badge' => 'dark',             'desc' => 'Manage visitor log, occupancy log and incidents.'],
    'tenant'      => ['label' => 'Tenant',      'badge' => 'success',          'desc' => 'Self-service portal — lease, invoices, maintenance requests.'],
];

$roleCfg = $all_roles[$user['role']] ?? ['label' => ucfirst($user['role']), 'badge' => 'secondary', 'desc' => ''];
$isSelf  = ($id == $_SESSION['user_id']);

$page_title = 'View User — ' . $user['name'];
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex align-items-center mb-4">
    <a href="<?= BASE_URL ?>/users/index.php" class="btn btn-sm btn-outline-secondary me-3">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div class="flex-grow-1">
        <h5 class="fw-bold mb-0">User Profile</h5>
        <small class="text-muted">Viewing account details for <?= e($user['name']) ?></small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/users/edit.php?id=<?= $id ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <?php if (!$isSelf): ?>
        <?php if ($user['status'] === 'active'): ?>
        <a href="<?= BASE_URL ?>/users/set_status.php?id=<?= $id ?>&status=suspended&csrf=<?= csrf_token() ?>"
           class="btn btn-outline-danger btn-sm"
           onclick="return confirm('Suspend <?= e(addslashes($user['name'])) ?>?')">
            <i class="bi bi-slash-circle me-1"></i>Suspend
        </a>
        <?php else: ?>
        <a href="<?= BASE_URL ?>/users/set_status.php?id=<?= $id ?>&status=active&csrf=<?= csrf_token() ?>"
           class="btn btn-outline-success btn-sm">
            <i class="bi bi-check-circle me-1"></i>Activate
        </a>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">

    <!-- ── Left: profile card ─────────────────────────────────── -->
    <div class="col-lg-4">

        <!-- Avatar card -->
        <div class="card shadow-sm text-center mb-3">
            <div class="card-body pt-4 pb-3">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center fw-bold rounded-circle bg-<?= $roleCfg['badge'] ?>"
                     style="width:72px;height:72px;font-size:2rem;color:inherit">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
                <h5 class="fw-bold mb-1"><?= e($user['name']) ?></h5>
                <div class="text-muted small mb-2"><?= e($user['email']) ?></div>
                <span class="badge bg-<?= $roleCfg['badge'] ?> fs-6 px-3"><?= $roleCfg['label'] ?></span>
                <?php if ($isSelf): ?>
                <div class="mt-2"><span class="badge bg-light text-muted border">This is your account</span></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Account status card -->
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white fw-semibold small text-uppercase text-muted">Account Status</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr>
                        <td class="text-muted ps-3">Status</td>
                        <td class="pe-3">
                            <?php if ($user['status'] === 'active'): ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>
                            <?php elseif ($user['status'] === 'suspended'): ?>
                                <span class="badge bg-danger"><i class="bi bi-slash-circle me-1"></i>Suspended</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><i class="bi bi-dash-circle me-1"></i>Inactive</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-3">User ID</td>
                        <td class="pe-3 font-monospace small">#<?= $user['id'] ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-3">Joined</td>
                        <td class="pe-3 small"><?= fmt_date($user['created_at'], 'd M Y') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-3">Last Login</td>
                        <td class="pe-3 small">
                            <?= !empty($user['last_login'])
                                ? fmt_date($user['last_login'], 'd M Y H:i')
                                : '<span class="fst-italic text-muted">Never</span>' ?>
                        </td>
                    </tr>
                    <?php if (!empty($user['phone'])): ?>
                    <tr>
                        <td class="text-muted ps-3">Phone</td>
                        <td class="pe-3 small"><?= e($user['phone']) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Role description -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-semibold small text-uppercase text-muted">Role Permissions</div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-<?= $roleCfg['badge'] ?>"><?= $roleCfg['label'] ?></span>
                </div>
                <p class="text-muted small mb-0"><?= $roleCfg['desc'] ?></p>
            </div>
        </div>
    </div>

    <!-- ── Right: activity & tokens ───────────────────────────── -->
    <div class="col-lg-8">

        <!-- Quick actions -->
        <div class="row g-3 mb-4">
            <div class="col-sm-4">
                <a href="<?= BASE_URL ?>/users/edit.php?id=<?= $id ?>" class="card text-decoration-none border-0 shadow-sm text-center p-3 action-card">
                    <div class="fs-3 text-primary mb-1"><i class="bi bi-pencil-square"></i></div>
                    <div class="fw-semibold small">Edit Profile</div>
                </a>
            </div>
            <div class="col-sm-4">
                <a href="<?= BASE_URL ?>/users/edit.php?id=<?= $id ?>#password" class="card text-decoration-none border-0 shadow-sm text-center p-3 action-card">
                    <div class="fs-3 text-warning mb-1"><i class="bi bi-key"></i></div>
                    <div class="fw-semibold small">Reset Password</div>
                </a>
            </div>
            <?php if (!$isSelf): ?>
            <div class="col-sm-4">
                <?php if ($user['status'] === 'active'): ?>
                <a href="<?= BASE_URL ?>/users/set_status.php?id=<?= $id ?>&status=suspended&csrf=<?= csrf_token() ?>"
                   class="card text-decoration-none border-0 shadow-sm text-center p-3 action-card"
                   onclick="return confirm('Suspend this account?')">
                    <div class="fs-3 text-danger mb-1"><i class="bi bi-slash-circle"></i></div>
                    <div class="fw-semibold small">Suspend</div>
                </a>
                <?php else: ?>
                <a href="<?= BASE_URL ?>/users/set_status.php?id=<?= $id ?>&status=active&csrf=<?= csrf_token() ?>"
                   class="card text-decoration-none border-0 shadow-sm text-center p-3 action-card">
                    <div class="fs-3 text-success mb-1"><i class="bi bi-check-circle"></i></div>
                    <div class="fw-semibold small">Activate</div>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- API Tokens -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-key me-2 text-warning"></i>API Tokens</span>
                <span class="badge bg-secondary"><?= count($tokens) ?></span>
            </div>
            <?php if ($tokens): ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Scopes</th>
                            <th>Last Used</th>
                            <th>Expires</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tokens as $tok): ?>
                    <tr>
                        <td class="fw-semibold small"><?= e($tok['name'] ?? 'Unnamed') ?></td>
                        <td>
                            <?php foreach (explode(',', $tok['scopes'] ?? '') as $sc): if (!trim($sc)) continue; ?>
                            <span class="badge bg-light text-dark border small"><?= e(trim($sc)) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td class="small text-muted"><?= !empty($tok['last_used']) ? fmt_date($tok['last_used'], 'd M Y H:i') : '—' ?></td>
                        <td class="small text-muted"><?= !empty($tok['expires_at']) ? fmt_date($tok['expires_at'], 'd M Y') : 'Never' ?></td>
                        <td>
                            <?php if (!empty($tok['revoked'])): ?>
                            <span class="badge bg-danger">Revoked</span>
                            <?php elseif (!empty($tok['expires_at']) && strtotime($tok['expires_at']) < time()): ?>
                            <span class="badge bg-warning text-dark">Expired</span>
                            <?php else: ?>
                            <span class="badge bg-success">Active</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card-body text-center text-muted py-4 small">
                <i class="bi bi-key fs-3 d-block mb-2 opacity-25"></i>
                No API tokens issued for this user.
            </div>
            <?php endif; ?>
        </div>

        <!-- Activity timeline placeholder -->
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-activity me-2 text-primary"></i>Account Details
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded">
                            <div class="text-muted small mb-1">Account Created</div>
                            <div class="fw-semibold"><?= fmt_date($user['created_at'], 'd M Y') ?></div>
                            <div class="text-muted small"><?= fmt_date($user['created_at'], 'H:i') ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded">
                            <div class="text-muted small mb-1">Last Login</div>
                            <?php if (!empty($user['last_login'])): ?>
                            <div class="fw-semibold"><?= fmt_date($user['last_login'], 'd M Y') ?></div>
                            <div class="text-muted small"><?= fmt_date($user['last_login'], 'H:i') ?></div>
                            <?php else: ?>
                            <div class="fw-semibold text-muted fst-italic">Never logged in</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded">
                            <div class="text-muted small mb-1">Role</div>
                            <div><span class="badge bg-<?= $roleCfg['badge'] ?> fs-6"><?= $roleCfg['label'] ?></span></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded">
                            <div class="text-muted small mb-1">Active API Tokens</div>
                            <div class="fw-semibold">
                                <?= count(array_filter($tokens, fn($t) => empty($t['revoked']) && (empty($t['expires_at']) || strtotime($t['expires_at']) > time()))) ?>
                                <span class="text-muted small fw-normal">/ <?= count($tokens) ?> total</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
