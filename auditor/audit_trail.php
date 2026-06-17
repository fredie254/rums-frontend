<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'auditor');

$api        = new ApiClient();
$page_title = 'Audit Trail';

/* ── filters ── */
$filter_action    = get_param('action', '');
$filter_module    = get_param('module', '');
$filter_user      = int_param('user_id', 0);
$filter_date_from = get_param('date_from', date('Y-m-01'));
$filter_date_to   = get_param('date_to',   date('Y-m-d'));
$filter_ip        = get_param('ip', '');

/* ── paging ── */
$per_page = 50;
$page     = max(1, int_param('page', 1, 'GET'));

/* ── filter dropdown data ── */
$meta    = $api->get('audit-logs/meta');
$actions = $meta['data']['actions'] ?? [];
$modules = $meta['data']['modules'] ?? [];
$users   = $meta['data']['users']   ?? [];

/* ── build query ── */
$q = array_filter([
    'date_from' => $filter_date_from,
    'date_to'   => $filter_date_to,
    'action'    => $filter_action ?: null,
    'module'    => $filter_module ?: null,
    'user_id'   => $filter_user   ?: null,
    'ip'        => $filter_ip     ?: null,
], fn($v) => $v !== null && $v !== '');

/* ── CSV export ── */
if (get_param('export') === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit_trail_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Time', 'User', 'Email', 'Role', 'Action', 'Module', 'Record ID', 'Description', 'IP Address']);

    $exp_page = 1;
    do {
        $r = $api->get('audit-logs', array_merge($q, ['page' => $exp_page, 'per_page' => 200]));
        foreach ($r['data'] ?? [] as $row) {
            fputcsv($out, [
                $row['id'],            $row['created_at'],
                $row['user_name']  ?? '', $row['user_email'] ?? '',
                $row['user_role']  ?? '', $row['action'],
                $row['module'],        $row['record_id']    ?? '',
                $row['description'] ?? '', $row['ip_address'] ?? '',
            ]);
        }
        $exp_page++;
    } while ($exp_page <= (int)($r['meta']['total_pages'] ?? 1));

    fclose($out);
    exit;
}

/* ── fetch page ── */
$res   = $api->get('audit-logs', array_merge($q, ['page' => $page, 'per_page' => $per_page]));
$logs  = $res['data'] ?? [];
$rmeta = $res['meta'] ?? [];

$total_filtered = (int)($rmeta['total']       ?? 0);
$pg = [
    'total'       => $total_filtered,
    'per_page'    => $per_page,
    'page'        => (int)($rmeta['current_page'] ?? 1),
    'total_pages' => (int)($rmeta['total_pages']  ?? 1),
    'offset'      => ((int)($rmeta['current_page'] ?? 1) - 1) * $per_page,
];

/* ── URL for pagination (no page= in it) ── */
$filter_qs  = !empty($q) ? '?' . http_build_query($q) : '';
$paginate_url = BASE_URL . '/auditor/audit_trail.php' . $filter_qs;

include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0"><i class="bi bi-journal-text me-2 text-info"></i>Audit Trail</h5>
    <a href="?<?= http_build_query(array_merge($q, ['export' => 'csv'])) ?>" class="btn btn-sm btn-outline-success">
        <i class="bi bi-download me-1"></i>Export CSV
    </a>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filter_date_from) ?>">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filter_date_to) ?>">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Action</label>
                <select name="action" class="form-select form-select-sm">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $a): ?>
                    <option value="<?= e($a) ?>" <?= $filter_action === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Module</label>
                <select name="module" class="form-select form-select-sm">
                    <option value="">All Modules</option>
                    <?php foreach ($modules as $m): ?>
                    <option value="<?= e($m) ?>" <?= $filter_module === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">User</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="0">All Users</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $filter_user == $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">IP Address</label>
                <input type="text" name="ip" class="form-control form-control-sm" value="<?= e($filter_ip) ?>" placeholder="e.g. 192.168.1.1">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm">Filter</button>
                <a href="audit_trail.php" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Audit Events <span class="badge bg-secondary"><?= number_format($total_filtered) ?></span></span>
        <small class="text-muted"><?= e($filter_date_from) ?> – <?= e($filter_date_to) ?></small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Time</th><th>User</th><th>Role</th><th>Action</th>
                        <th>Module</th><th>Record</th><th>Description</th><th>IP</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$logs): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No audit events found.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log):
                        $action_colors = ['LOGIN'=>'success','LOGOUT'=>'secondary','CREATE'=>'primary','UPDATE'=>'info','DELETE'=>'danger','VIEW'=>'light text-dark'];
                        $bc = $action_colors[$log['action']] ?? 'secondary';
                    ?>
                    <tr>
                        <td class="text-nowrap small"><?= fmt_date($log['created_at'], true) ?></td>
                        <td>
                            <div><?= e($log['user_name'] ?? '—') ?></div>
                            <small class="text-muted"><?= e($log['user_email'] ?? '') ?></small>
                        </td>
                        <td><span class="badge bg-secondary small"><?= e($log['user_role'] ?? '—') ?></span></td>
                        <td><span class="badge bg-<?= $bc ?>"><?= e($log['action']) ?></span></td>
                        <td><?= e($log['module']) ?></td>
                        <td><?= $log['record_id'] ? '#' . $log['record_id'] : '—' ?></td>
                        <td class="text-truncate" style="max-width:200px" title="<?= e($log['description'] ?? '') ?>"><?= e($log['description'] ?? '') ?></td>
                        <td class="font-monospace small"><?= e($log['ip_address'] ?? '—') ?></td>
                        <td>
                            <?php if (!empty($log['old_value']) || !empty($log['new_value'])): ?>
                            <button class="btn btn-xs btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#diffModal<?= $log['id'] ?>">
                                <i class="bi bi-file-diff"></i>
                            </button>
                            <div class="modal fade" id="diffModal<?= $log['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Change Details — #<?= $log['id'] ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body row g-3">
                                            <div class="col-md-6">
                                                <h6 class="text-danger">Before</h6>
                                                <pre class="bg-light p-2 rounded small" style="max-height:300px;overflow:auto"><?= e($log['old_value'] ?: '(none)') ?></pre>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="text-success">After</h6>
                                                <pre class="bg-light p-2 rounded small" style="max-height:300px;overflow:auto"><?= e($log['new_value'] ?: '(none)') ?></pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white">
        <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">Showing <?= count($logs) ?> of <?= number_format($total_filtered) ?></small>
            <?= pagination_links($pg, $paginate_url) ?>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
