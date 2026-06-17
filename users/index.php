<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$api    = new ApiClient();
$search = get_param('search');
$role   = get_param('role');
$status = get_param('status') ?: 'all';
$page   = max(1, int_param('page'));

$query = array_filter([
    'search'   => $search ?: null,
    'role'     => $role   ?: null,
    'status'   => $status,
    'page'     => $page,
    'per_page' => ROWS_PER_PAGE,
], fn($v) => $v !== null && $v !== '');

$res   = $api->get('users', $query);
$users = $res['data'] ?? [];
$meta  = $res['meta'] ?? [];
$total = $meta['total'] ?? 0;
$pg    = [
    'total'       => $total,
    'per_page'    => $meta['per_page']     ?? ROWS_PER_PAGE,
    'page'        => $meta['current_page'] ?? 1,
    'total_pages' => $meta['total_pages']  ?? 1,
    'offset'      => (($meta['current_page'] ?? 1) - 1) * ($meta['per_page'] ?? ROWS_PER_PAGE),
];

// Fetch all users (no filter) once for KPI counts
$allRes   = $api->get('users', ['status' => 'all', 'per_page' => 500]);
$allUsers = $allRes['data'] ?? [];

$counts = ['total' => count($allUsers), 'active' => 0, 'suspended' => 0, 'inactive' => 0];
$roleCounts = [];
foreach ($allUsers as $u) {
    $counts[$u['status']] = ($counts[$u['status']] ?? 0) + 1;
    $roleCounts[$u['role']] = ($roleCounts[$u['role']] ?? 0) + 1;
}

$all_roles = [
    'admin'       => ['label' => 'Admin',       'badge' => 'danger'],
    'manager'     => ['label' => 'Manager',     'badge' => 'primary'],
    'landlord'    => ['label' => 'Landlord',    'badge' => 'warning text-dark'],
    'accountant'  => ['label' => 'Accountant',  'badge' => 'info text-dark'],
    'maintenance' => ['label' => 'Maintenance', 'badge' => 'orange'],
    'auditor'     => ['label' => 'Auditor',     'badge' => 'purple'],
    'security'    => ['label' => 'Security',    'badge' => 'dark'],
    'tenant'      => ['label' => 'Tenant',      'badge' => 'success'],
];

$page_title = 'User Management';
include BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-4">
    <div>
        <h5 class="fw-bold mb-1"><i class="bi bi-person-gear me-2 text-primary"></i>User Management</h5>
        <small class="text-muted">Create, manage and control access for all system users</small>
    </div>
    <a href="<?= BASE_URL ?>/users/add.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Add User
    </a>
</div>

<!-- KPI Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-people-fill"></i></div>
            <div class="kpi-value"><?= $counts['total'] ?></div>
            <div class="kpi-label">Total Users</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-person-check-fill"></i></div>
            <div class="kpi-value"><?= $counts['active'] ?></div>
            <div class="kpi-label">Active</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-person-dash-fill"></i></div>
            <div class="kpi-value"><?= $counts['suspended'] ?></div>
            <div class="kpi-label">Suspended</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-yellow">
            <div class="kpi-icon"><i class="bi bi-person-x-fill"></i></div>
            <div class="kpi-value"><?= $counts['inactive'] ?></div>
            <div class="kpi-label">Inactive</div>
        </div>
    </div>
</div>

<!-- Role breakdown chips -->
<div class="d-flex flex-wrap gap-2 mb-4">
    <?php foreach ($all_roles as $rkey => $rcfg): if (empty($roleCounts[$rkey])) continue; ?>
    <a href="?role=<?= $rkey ?>&status=all" class="text-decoration-none">
        <span class="badge bg-<?= $rcfg['badge'] ?> fs-6 px-3 py-2">
            <?= $rcfg['label'] ?> <span class="fw-normal ms-1 opacity-75"><?= $roleCounts[$rkey] ?></span>
        </span>
    </a>
    <?php endforeach; ?>
    <?php if ($role || $status !== 'all' || $search): ?>
    <a href="<?= BASE_URL ?>/users/index.php" class="text-decoration-none">
        <span class="badge bg-light text-muted border fs-6 px-3 py-2">
            <i class="bi bi-x-circle me-1"></i>Clear filters
        </span>
    </a>
    <?php endif; ?>
</div>

<!-- Filter bar -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search name or email…" value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <select name="role" class="form-select form-select-sm">
                    <option value="">All Roles</option>
                    <?php foreach ($all_roles as $rkey => $rcfg): ?>
                    <option value="<?= $rkey ?>" <?= $role === $rkey ? 'selected' : '' ?>><?= $rcfg['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="all"       <?= $status === 'all'       ? 'selected' : '' ?>>All Statuses</option>
                    <option value="active"    <?= $status === 'active'    ? 'selected' : '' ?>>Active</option>
                    <option value="inactive"  <?= $status === 'inactive'  ? 'selected' : '' ?>>Inactive</option>
                    <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary">Search</button>
                <a href="<?= BASE_URL ?>/users/index.php" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Users table -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:36px">#</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Joined</th>
                    <th class="text-end pe-3">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($users): $sn = $pg['offset'] + 1; foreach ($users as $u):
                $initials = strtoupper(substr($u['name'], 0, 1));
                $avatarColors = ['admin'=>'bg-danger','manager'=>'bg-primary','landlord'=>'bg-warning text-dark',
                                 'accountant'=>'bg-info text-dark','maintenance'=>'bg-orange','auditor'=>'bg-purple',
                                 'security'=>'bg-dark','tenant'=>'bg-success'];
                $avatarCls = $avatarColors[$u['role']] ?? 'bg-secondary';
                $roleCfg   = $all_roles[$u['role']] ?? ['label' => ucfirst($u['role']), 'badge' => 'secondary'];
                $isSelf    = $u['id'] == $_SESSION['user_id'];
            ?>
            <tr>
                <td class="text-muted small ps-3"><?= $sn++ ?></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-sm <?= $avatarCls ?> rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0">
                            <?= $initials ?>
                        </div>
                        <div>
                            <div class="fw-semibold"><?= e($u['name']) ?> <?= $isSelf ? '<span class="badge bg-light text-muted border small">You</span>' : '' ?></div>
                            <div class="small text-muted"><?= e($u['email']) ?></div>
                            <?php if (!empty($u['phone'])): ?><div class="small text-muted"><?= e($u['phone']) ?></div><?php endif; ?>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge bg-<?= $roleCfg['badge'] ?>"><?= $roleCfg['label'] ?></span>
                </td>
                <td>
                    <?php if ($u['status'] === 'active'): ?>
                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>
                    <?php elseif ($u['status'] === 'suspended'): ?>
                        <span class="badge bg-danger"><i class="bi bi-slash-circle me-1"></i>Suspended</span>
                    <?php else: ?>
                        <span class="badge bg-secondary"><i class="bi bi-dash-circle me-1"></i>Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <small class="text-muted">
                        <?= !empty($u['last_login']) ? fmt_date($u['last_login'], 'd M Y') : '<span class="text-muted fst-italic">Never</span>' ?>
                    </small>
                </td>
                <td><small class="text-muted"><?= fmt_date($u['created_at']) ?></small></td>
                <td class="text-end pe-3">
                    <div class="d-flex gap-1 justify-content-end">
                        <a href="<?= BASE_URL ?>/users/view.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="<?= BASE_URL ?>/users/edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php if (!$isSelf): ?>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" title="More actions">
                                <i class="bi bi-three-dots"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <?php if ($u['status'] !== 'active'): ?>
                                <li>
                                    <a class="dropdown-item text-success" href="<?= BASE_URL ?>/users/set_status.php?id=<?= $u['id'] ?>&status=active&csrf=<?= csrf_token() ?>">
                                        <i class="bi bi-check-circle me-2"></i>Activate
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if ($u['status'] !== 'suspended'): ?>
                                <li>
                                    <a class="dropdown-item text-danger" href="<?= BASE_URL ?>/users/set_status.php?id=<?= $u['id'] ?>&status=suspended&csrf=<?= csrf_token() ?>">
                                        <i class="bi bi-slash-circle me-2"></i>Suspend
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if ($u['status'] !== 'inactive'): ?>
                                <li>
                                    <a class="dropdown-item text-secondary" href="<?= BASE_URL ?>/users/set_status.php?id=<?= $u['id'] ?>&status=inactive&csrf=<?= csrf_token() ?>">
                                        <i class="bi bi-dash-circle me-2"></i>Deactivate
                                    </a>
                                </li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>/users/reset_password.php?id=<?= $u['id'] ?>">
                                        <i class="bi bi-key me-2"></i>Reset Password
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr>
                <td colspan="7" class="text-center py-5 text-muted">
                    <i class="bi bi-people fs-2 d-block mb-2 opacity-25"></i>
                    No users found<?= ($search || $role || $status !== 'all') ? ' matching your filters' : '' ?>.
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">
            Showing <?= count($users) ?> of <?= $total ?> user<?= $total !== 1 ? 's' : '' ?>
        </small>
        <?= pagination_links($pg, BASE_URL . '/users/index.php?' . http_build_query(array_filter(['search' => $search, 'role' => $role, 'status' => $status !== 'all' ? $status : null]))) ?>
    </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
