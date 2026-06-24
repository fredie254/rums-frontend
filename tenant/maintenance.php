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

<?php if ($flash = get_flash()): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-3">
    <?= e($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

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
    $filterStatuses = ['' => 'All', 'open' => 'Open', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'resolved' => 'Resolved', 'cancelled' => 'Cancelled'];
    foreach ($filterStatuses as $sv => $sl):
    ?>
    <a href="?status=<?= $sv ?>" class="btn btn-sm <?= $status === $sv ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $sl ?></a>
    <?php endforeach; ?>
</div>

<?php if ($items): ?>
<div class="row g-3">
    <?php foreach ($items as $m):
        $mStatus = $m['status'] ?? 'open';
        $statusColors = [
            'open'        => 'danger',
            'in_progress' => 'warning',
            'completed'   => 'info',
            'resolved'    => 'success',
            'cancelled'   => 'secondary',
        ];
        $sc = $statusColors[$mStatus] ?? 'secondary';
        $priority_colors = ['urgent' => 'danger', 'high' => 'danger', 'medium' => 'warning', 'low' => 'success'];
        $pc = $priority_colors[$m['priority'] ?? ''] ?? 'secondary';

        $needsApproval = $mStatus === 'completed';
        $canReopen     = in_array($mStatus, ['completed', 'resolved'], true);
    ?>
    <div class="col-md-6">
        <div class="card shadow-sm h-100 <?= $needsApproval ? 'border-info' : '' ?>">
            <?php if ($needsApproval): ?>
            <div class="card-header bg-info-subtle py-1 small fw-semibold text-info">
                <i class="bi bi-patch-question me-1"></i>Awaiting your approval
            </div>
            <?php endif; ?>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="fw-semibold mb-0"><?= e($m['issue_title'] ?? 'Maintenance Request') ?></h6>
                    <span class="badge bg-<?= $sc ?> flex-shrink-0 ms-2">
                        <?= ucfirst(str_replace('_', ' ', $mStatus)) ?>
                    </span>
                </div>
                <?php if (!empty($m['description'])): ?>
                <p class="small text-muted mb-2"><?= e(mb_substr($m['description'], 0, 120)) ?><?= mb_strlen($m['description'] ?? '') > 120 ? '…' : '' ?></p>
                <?php endif; ?>
                <div class="d-flex gap-3 small text-muted flex-wrap">
                    <?php if (!empty($m['priority'])): ?>
                    <span><i class="bi bi-flag me-1 text-<?= $pc ?>"></i><?= ucfirst($m['priority']) ?> priority</span>
                    <?php endif; ?>
                    <?php if (!empty($m['category'])): ?>
                    <span><i class="bi bi-tag me-1"></i><?= ucfirst(str_replace('_', ' ', $m['category'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($m['assigned_to_name'])): ?>
                    <span><i class="bi bi-person me-1"></i><?= e($m['assigned_to_name']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer bg-white py-2">
                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <small class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= fmt_date($m['created_at']) ?></small>
                    <div class="d-flex gap-1">
                        <?php if ($needsApproval): ?>
                        <button type="button" class="btn btn-sm btn-success py-0 px-2"
                                onclick="openApproveModal(<?= $m['id'] ?>, <?= htmlspecialchars(json_encode($m['issue_title']), ENT_QUOTES) ?>)">
                            <i class="bi bi-check2-circle me-1"></i>Approve
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2"
                                onclick="openReopenModal(<?= $m['id'] ?>, <?= htmlspecialchars(json_encode($m['issue_title']), ENT_QUOTES) ?>)">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reopen
                        </button>
                        <?php elseif ($canReopen): ?>
                        <button type="button" class="btn btn-sm btn-outline-warning py-0 px-2"
                                onclick="openReopenModal(<?= $m['id'] ?>, <?= htmlspecialchars(json_encode($m['issue_title']), ENT_QUOTES) ?>)">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reopen
                        </button>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/maintenance/view?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2">
                            <i class="bi bi-eye me-1"></i>View
                        </a>
                    </div>
                </div>
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

<!-- ── Approve Modal ── -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= BASE_URL ?>/maintenance/view" id="approveForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="approve">
                <input type="hidden" id="approve_id" value="">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-success"><i class="bi bi-check2-circle me-2"></i>Approve Completion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        You are approving the work on <strong id="approve_title"></strong>.
                        This will close the request.
                    </p>
                    <label class="form-label fw-semibold">
                        Your Note <span class="text-danger">*</span>
                    </label>
                    <textarea name="approval_note" class="form-control" rows="4" required
                              placeholder="Confirm the work was done satisfactorily (e.g. 'Pipe is fixed, no more leaks. Thank you.')"></textarea>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check2-circle me-1"></i>Confirm Approval
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Reopen Modal ── -->
<div class="modal fade" id="reopenModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= BASE_URL ?>/maintenance/view" id="reopenForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reopen">
                <input type="hidden" id="reopen_id" value="">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-danger"><i class="bi bi-arrow-counterclockwise me-2"></i>Reopen Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        You are reopening <strong id="reopen_title"></strong>.
                        Please explain what is still wrong or what has recurred.
                    </p>
                    <label class="form-label fw-semibold">
                        Reason for Reopening <span class="text-danger">*</span>
                    </label>
                    <textarea name="reopen_note" class="form-control" rows="4" required
                              placeholder="Describe the issue that still exists or has returned…"></textarea>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reopen Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openApproveModal(id, title) {
    document.getElementById('approve_id').value = id;
    document.getElementById('approve_title').textContent = title;
    document.getElementById('approveForm').action = '<?= BASE_URL ?>/maintenance/view?id=' + id;
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}
function openReopenModal(id, title) {
    document.getElementById('reopen_id').value = id;
    document.getElementById('reopen_title').textContent = title;
    document.getElementById('reopenForm').action = '<?= BASE_URL ?>/maintenance/view?id=' + id;
    new bootstrap.Modal(document.getElementById('reopenModal')).show();
}
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
