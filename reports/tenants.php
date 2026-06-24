<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'auditor');

$api    = new ApiClient();
$search = get_param('search');
$status = get_param('status');
$page   = max(1, int_param('page'));

// ── KPI counts (4 lightweight calls) ─────────────────────────
$total_res       = $api->get('tenants', ['per_page' => 1]);
$active_res      = $api->get('tenants', ['status' => 'active',      'per_page' => 1]);
$inactive_res    = $api->get('tenants', ['status' => 'inactive',    'per_page' => 1]);
$blacklisted_res = $api->get('tenants', ['status' => 'blacklisted', 'per_page' => 1]);

$count_total       = (int)($total_res['meta']['total']       ?? 0);
$count_active      = (int)($active_res['meta']['total']      ?? 0);
$count_inactive    = (int)($inactive_res['meta']['total']    ?? 0);
$count_blacklisted = (int)($blacklisted_res['meta']['total'] ?? 0);

// ── Paginated tenant list ─────────────────────────────────────
$list_params = array_filter([
    'search'   => $search  ?: null,
    'status'   => $status  ?: null,
    'page'     => $page,
    'per_page' => ROWS_PER_PAGE,
], fn($v) => $v !== null && $v !== '');

$res     = $api->get('tenants', $list_params);
$tenants = $res['data'] ?? [];
$meta    = $res['meta'] ?? ['total' => 0, 'total_pages' => 1, 'current_page' => 1, 'per_page' => ROWS_PER_PAGE];

$total_rows  = (int)($meta['total']        ?? 0);
$total_pages = (int)($meta['total_pages']  ?? 1);
$current_pg  = (int)($meta['current_page'] ?? 1);
$pg = [
    'total'       => $total_rows,
    'per_page'    => (int)($meta['per_page'] ?? ROWS_PER_PAGE),
    'page'        => $current_pg,
    'total_pages' => $total_pages,
    'offset'      => ($current_pg - 1) * (int)($meta['per_page'] ?? ROWS_PER_PAGE),
];

// ── Build URL base for pagination (preserve filters) ─────────
$url_base = BASE_URL . '/reports/tenants?'
    . http_build_query(array_filter([
        'search' => $search,
        'status' => $status,
    ], fn($v) => $v !== ''));

$page_title = 'Tenant Report';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0">
        <i class="bi bi-people me-2 text-primary"></i>Tenant Report
    </h5>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-printer"></i>
    </button>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-people"></i></div>
            <div class="kpi-value"><?= $count_total ?></div>
            <div class="kpi-label">Total Tenants</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-person-check"></i></div>
            <div class="kpi-value"><?= $count_active ?></div>
            <div class="kpi-label">Active</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-person-dash"></i></div>
            <div class="kpi-value"><?= $count_inactive ?></div>
            <div class="kpi-label">Inactive</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-person-x"></i></div>
            <div class="kpi-value"><?= $count_blacklisted ?></div>
            <div class="kpi-label">Blacklisted</div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Search by name, email, phone, ID number..."
                       value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value=""       <?= $status === ''            ? 'selected' : '' ?>>All Statuses</option>
                    <option value="active"      <?= $status === 'active'      ? 'selected' : '' ?>>Active</option>
                    <option value="inactive"    <?= $status === 'inactive'    ? 'selected' : '' ?>>Inactive</option>
                    <option value="blacklisted" <?= $status === 'blacklisted' ? 'selected' : '' ?>>Blacklisted</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <a href="<?= BASE_URL ?>/reports/tenants" class="btn btn-sm btn-outline-secondary">
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tenant Table -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>ID Number</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($tenants): $sn = $pg['offset'] + 1; foreach ($tenants as $t):
                $status_val = $t['status'] ?? 'inactive';
                $badge_cls  = match ($status_val) {
                    'active'      => 'success',
                    'inactive'    => 'secondary',
                    'blacklisted' => 'danger',
                    default       => 'secondary',
                };
                $full_name = e(trim(($t['full_name'] ?? '') ?: (($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? ''))));
            ?>
                <tr>
                    <td class="text-muted"><?= $sn++ ?></td>
                    <td class="fw-semibold"><?= $full_name ?></td>
                    <td><?= e($t['email'] ?? '—') ?></td>
                    <td><?= e($t['phone'] ?? '—') ?></td>
                    <td><code><?= e($t['id_number'] ?? '—') ?></code></td>
                    <td>
                        <span class="badge bg-<?= $badge_cls ?>">
                            <?= ucfirst($status_val) ?>
                        </span>
                    </td>
                    <td><?= fmt_date($t['created_at'] ?? null) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/tenants/view?id=<?= (int)$t['id'] ?>"
                           class="btn btn-sm btn-outline-primary py-0 px-1"
                           title="View tenant">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-4 d-block mb-1"></i>
                        No tenants found<?= $search ? ' matching "' . e($search) . '"' : '' ?>.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">
            Showing <?= count($tenants) ?> of <?= $total_rows ?>
            <?= $status ? ' &bull; Status: <strong>' . e(ucfirst($status)) . '</strong>' : '' ?>
        </small>
        <?= pagination_links($pg, $url_base) ?>
    </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
