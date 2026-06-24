<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$user = current_user();
if ($user['role'] !== 'tenant') { redirect(BASE_URL . '/dashboard/index'); }

$api    = new ApiClient();
$page   = max(1, int_param('page'));
$status = get_param('status');

$query = array_filter([
    'page'     => $page,
    'per_page' => ROWS_PER_PAGE,
    'status'   => $status ?: null,
], fn($v) => $v !== null);

$res    = $api->get('maintenance', $query);
$items  = $res['data'] ?? [];
$meta   = $res['meta'] ?? [];
$total  = $meta['total'] ?? 0;
$pg     = [
    'total'       => $total,
    'per_page'    => $meta['per_page']     ?? ROWS_PER_PAGE,
    'page'        => $meta['current_page'] ?? 1,
    'total_pages' => $meta['total_pages']  ?? 1,
    'offset'      => (($meta['current_page'] ?? 1) - 1) * ($meta['per_page'] ?? ROWS_PER_PAGE),
];

$page_title = 'Maintenance Requests';
include BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-4">
    <div>
        <h5 class="fw-bold mb-1"><i class="bi bi-wrench me-2 text-warning"></i>Maintenance Requests</h5>
        <small class="text-muted">Track repair and maintenance requests for your unit</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/maintenance/add" class="btn btn-warning btn-sm">
            <i class="bi bi-plus-circle me-1"></i>New Request
        </a>
        <a href="<?= BASE_URL ?>/tenant/dashboard" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Dashboard
        </a>
    </div>
</div>

<!-- Status filters -->
<div class="d-flex gap-2 mb-3 flex-wrap">
    <?php
    $statuses = ['' => 'All', 'open' => 'Open', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'closed' => 'Closed'];
    $statusColors = ['open' => 'danger', 'in_progress' => 'warning', 'completed' => 'success', 'closed' => 'secondary'];
    foreach ($statuses as $sv => $sl):
    ?>
    <a href="?status=<?= $sv ?>" class="btn btn-sm <?= $status === $sv ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $sl ?></a>
    <?php endforeach; ?>
</div>

<?php if ($items): ?>
<div class="row g-3">
    <?php foreach ($items as $m):
        $sc = $statusColors[$m['status'] ?? ''] ?? 'secondary';
        $priority_colors = ['high' => 'danger', 'medium' => 'warning', 'low' => 'success', 'urgent' => 'danger'];
        $pc = $priority_colors[$m['priority'] ?? ''] ?? 'secondary';
    ?>
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="fw-semibold mb-0"><?= e($m['title'] ?? $m['description'] ?? 'Maintenance Request') ?></h6>
                    <span class="badge bg-<?= $sc ?> flex-shrink-0 ms-2">
                        <?= ucfirst(str_replace('_', ' ', $m['status'] ?? '')) ?>
                    </span>
                </div>
                <?php if (!empty($m['description']) && !empty($m['title'])): ?>
                <p class="small text-muted mb-2"><?= e(substr($m['description'], 0, 120)) ?><?= strlen($m['description']) > 120 ? '…' : '' ?></p>
                <?php endif; ?>
                <div class="d-flex gap-3 small text-muted">
                    <?php if (!empty($m['priority'])): ?>
                    <span><i class="bi bi-flag me-1 text-<?= $pc ?>"></i><?= ucfirst($m['priority']) ?> priority</span>
                    <?php endif; ?>
                    <?php if (!empty($m['category'])): ?>
                    <span><i class="bi bi-tag me-1"></i><?= ucfirst($m['category']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
                <small class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= fmt_date($m['created_at']) ?></small>
                <a href="<?= BASE_URL ?>/maintenance/view?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2">
                    <i class="bi bi-eye me-1"></i>View
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($total > $pg['per_page']): ?>
<div class="d-flex justify-content-end mt-3">
    <?= pagination_links($pg, BASE_URL . '/tenant/maintenance?' . http_build_query(array_filter(['status' => $status]))) ?>
</div>
<?php endif; ?>

<?php else: ?>
<div class="card shadow-sm text-center py-5">
    <div class="card-body">
        <i class="bi bi-tools fs-1 text-muted opacity-25 d-block mb-3"></i>
        <h5 class="text-muted">No Requests Found</h5>
        <p class="text-muted small">Have an issue with your unit? Submit a maintenance request and our team will attend to it.</p>
        <a href="<?= BASE_URL ?>/maintenance/add" class="btn btn-warning mt-2">
            <i class="bi bi-plus-circle me-1"></i>Submit a Request
        </a>
    </div>
</div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/footer.php'; ?>
