<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$api  = new ApiClient();
$id   = int_param('id');
$user = current_user();
$role = $user['role'] ?? '';
$listUrl = $role === 'tenant' ? BASE_URL . '/tenant/maintenance' : BASE_URL . '/maintenance/index';

if (!$id) { redirect($listUrl); }

$res = $api->get("maintenance/$id");
$req = $res['data'] ?? null;
if (!$req) { set_flash('error', 'Request not found.'); redirect($listUrl); }

$status = $req['status'] ?? 'open';

// ── POST handler ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/maintenance/view?id=' . $id); }
    $action = post('action');

    // Admin / manager: full update form
    if ($action === 'manager_update' && is_manager()) {
        $new_status  = post('status');
        $assigned_to = int_param('assigned_to', 0, 'post') ?: null;
        $actual_cost = post('actual_cost') !== '' ? (float)post('actual_cost') : null;
        $notes       = post('resolution_notes');

        $payload = array_filter([
            'status'      => $new_status,
            'assigned_to' => $assigned_to,
            'notes'       => $notes ?: null,
        ], fn($v) => $v !== null && $v !== '');
        if ($actual_cost !== null) {
            $payload['materials_cost'] = $actual_cost;
        }
        if (in_array($new_status, ['completed', 'resolved'])) {
            $payload['work_completed'] = date('Y-m-d H:i:s');
        }
        $upd = $api->patch("maintenance/$id", $payload);
        set_flash(!empty($upd['success']) ? 'success' : 'error', $upd['message'] ?? 'Request updated.');
        redirect(BASE_URL . '/maintenance/view?id=' . $id);
    }

    // Maintenance staff: mark as complete
    if ($action === 'mark_complete' && $role === 'maintenance') {
        $note = trim(post('completion_note'));
        if (!$note) {
            set_flash('error', 'A completion note is required before marking this task complete.');
            redirect(BASE_URL . '/maintenance/view?id=' . $id);
        }
        $upd = $api->post("maintenance/$id/complete", ['completion_notes' => $note]);
        set_flash(!empty($upd['success']) ? 'success' : 'error', $upd['message'] ?? 'Status updated.');
        redirect(BASE_URL . '/maintenance/view?id=' . $id);
    }

    // Tenant: approve completion
    if ($action === 'approve' && $role === 'tenant') {
        $note = trim(post('approval_note'));
        if (!$note) {
            set_flash('error', 'Please provide a note when approving this request.');
            redirect(BASE_URL . '/maintenance/view?id=' . $id);
        }
        $upd = $api->post("maintenance/$id/approve", ['approval_note' => $note]);
        set_flash(!empty($upd['success']) ? 'success' : 'error', $upd['message'] ?? 'Status updated.');
        redirect(BASE_URL . '/maintenance/view?id=' . $id);
    }

    // Tenant: reopen request
    if ($action === 'reopen' && $role === 'tenant') {
        $note = trim(post('reopen_note'));
        if (!$note) {
            set_flash('error', 'Please explain why you are reopening this request.');
            redirect(BASE_URL . '/maintenance/view?id=' . $id);
        }
        $upd = $api->post("maintenance/$id/reopen", ['reopen_note' => $note]);
        set_flash(!empty($upd['success']) ? 'success' : 'error', $upd['message'] ?? 'Status updated.');
        redirect(BASE_URL . '/maintenance/view?id=' . $id);
    }

    // Fallback
    set_flash('error', 'Action not permitted.');
    redirect(BASE_URL . '/maintenance/view?id=' . $id);
}

// ── Staff dropdown for manager update form ────────────────────────
$staff = [];
if (is_manager()) {
    $a_res   = $api->get('users', ['role' => 'admin',       'status' => 'active', 'per_page' => 200]);
    $m_res   = $api->get('users', ['role' => 'manager',     'status' => 'active', 'per_page' => 200]);
    $mnt_res = $api->get('users', ['role' => 'maintenance', 'status' => 'active', 'per_page' => 200]);
    $staff   = array_merge($a_res['data'] ?? [], $m_res['data'] ?? [], $mnt_res['data'] ?? []);
    usort($staff, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));
}

// ── Activity log ──────────────────────────────────────────────────
$logs_res = $api->get("maintenance/$id/logs");
$logs     = $logs_res['data'] ?? [];

// ── Role flags for view logic ─────────────────────────────────────
$isTenant      = $role === 'tenant';
$isMaintStaff  = $role === 'maintenance';
$isAssignedToMe = $isMaintStaff && ((int)($req['assigned_to'] ?? 0) === (int)$user['id']);

// ── Find approval actor from log ──────────────────────────────────
$approvedByName = null;
$approvedAt     = null;
foreach (array_reverse($logs) as $logEntry) {
    if ($logEntry['action'] === 'approved') {
        $approvedByName = $logEntry['user_name'] ?? 'Tenant';
        $approvedAt     = $logEntry['created_at'] ?? null;
        break;
    }
}

$page_title = 'Maintenance Request';
include BASE_PATH . '/includes/header.php';
?>
<?php if ($flash = get_flash()): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-3">
    <?= e($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex align-items-center mb-3 gap-2">
    <?php
    $backUrl = $isTenant
        ? BASE_URL . '/tenant/maintenance'
        : BASE_URL . '/maintenance/index';
    ?>
    <a href="<?= $backUrl ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0 flex-grow-1"><?= e($req['issue_title'] ?? '') ?></h5>
    <?= priority_badge($req['priority'] ?? 'low') ?> <?= maintenance_badge($status) ?>
    <?php if (is_manager()): ?>
    <a href="<?= BASE_URL ?>/maintenance/edit?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary ms-1" title="Edit">
        <i class="bi bi-pencil"></i>
    </a>
    <?php endif; ?>
    <?php if (is_manager() && $status !== 'in_progress'): ?>
    <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
            onclick="deleteMaintenance(<?= $id ?>, '<?= e($req['request_number'] ?? '') ?>')">
        <i class="bi bi-trash"></i>
    </button>
    <?php endif; ?>
</div>

<div class="row g-3">
    <!-- ── Left column: details ── -->
    <div class="col-md-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold small">
                <i class="bi bi-info-circle me-1 text-primary"></i>Request Details
            </div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted">Request #</dt>
                    <dd class="col-7"><code><?= e($req['request_number'] ?? '') ?></code></dd>
                    <dt class="col-5 text-muted">Unit</dt>
                    <dd class="col-7"><?= e($req['property_name'] ?? '') ?>/<?= e($req['unit_number'] ?? '') ?></dd>
                    <?php if (!$isTenant): ?>
                    <dt class="col-5 text-muted">Tenant</dt>
                    <dd class="col-7"><?= !empty($req['tenant_name']) ? e($req['tenant_name']) : '—' ?></dd>
                    <?php endif; ?>
                    <dt class="col-5 text-muted">Category</dt>
                    <dd class="col-7"><?= ucfirst(str_replace('_', ' ', $req['category'] ?? '')) ?></dd>
                    <dt class="col-5 text-muted">Reported</dt>
                    <dd class="col-7"><?= fmt_date($req['created_at']) ?></dd>
                    <dt class="col-5 text-muted">Assigned To</dt>
                    <dd class="col-7">
                        <?php if (!empty($req['assigned_to_name'])): ?>
                            <?= e($req['assigned_to_name']) ?>
                            <?php if ($isAssignedToMe): ?>
                            <span class="badge bg-primary-subtle text-primary ms-1">You</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">Unassigned</span>
                        <?php endif; ?>
                    </dd>
                    <?php if (!empty($req['total_cost']) && $req['total_cost'] > 0): ?>
                    <dt class="col-5 text-muted">Total Cost</dt>
                    <dd class="col-7"><?= money($req['total_cost']) ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($req['work_completed'])): ?>
                    <dt class="col-5 text-muted">Completed</dt>
                    <dd class="col-7"><?= fmt_date($req['work_completed']) ?></dd>
                    <?php endif; ?>
                    <?php if ($status === 'completed'): ?>
                    <dt class="col-5 text-muted">Approval</dt>
                    <dd class="col-7">
                        <span class="badge bg-warning text-dark">
                            <i class="bi bi-hourglass-split me-1"></i>Pending approval by tenant or manager
                        </span>
                    </dd>
                    <?php elseif ($status === 'resolved'): ?>
                    <dt class="col-5 text-muted">Approved by</dt>
                    <dd class="col-7">
                        <i class="bi bi-patch-check-fill text-success me-1"></i>
                        <span class="fw-semibold text-success"><?= e($approvedByName ?: 'Tenant') ?></span>
                        <?php if ($approvedAt): ?>
                        <div class="text-muted" style="font-size:.75rem"><?= fmt_date($approvedAt, 'd M Y, H:i') ?></div>
                        <?php endif; ?>
                    </dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-journal me-1"></i>Description</div>
            <div class="card-body small"><?= nl2br(e($req['description'] ?? '')) ?></div>
        </div>

        <?php if (!empty($req['notes'])): ?>
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold small text-success">
                <i class="bi bi-check-circle me-1"></i>Resolution Notes
            </div>
            <div class="card-body small"><?= nl2br(e($req['notes'])) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Right column: action panels + activity log ── -->
    <div class="col-md-8">

        <!-- ── MANAGER: Full update form ── -->
        <?php if (is_manager()): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-pencil-square me-1 text-warning"></i>Update Request
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="manager_update">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <?php foreach (['open','in_progress','completed','resolved','cancelled'] as $s): ?>
                                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>>
                                    <?= ucfirst(str_replace('_', ' ', $s)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($staff): ?>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Assign To</label>
                            <select name="assigned_to" class="form-select">
                                <option value="">— Unassigned —</option>
                                <?php foreach ($staff as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ($req['assigned_to'] ?? '') == $s['id'] ? 'selected' : '' ?>>
                                    <?= e($s['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">
                                Actual Cost (<?= get_setting('currency_symbol', CURRENCY_SYMBOL) ?>)
                            </label>
                            <input type="number" step="0.01" name="actual_cost" class="form-control"
                                   value="<?= $req['total_cost'] ?? '' ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Resolution Notes</label>
                            <textarea name="resolution_notes" class="form-control" rows="3"><?= e($req['notes'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-check-circle me-1"></i>Update Request
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── MAINTENANCE STAFF: Mark as Complete ── -->
        <?php if ($isAssignedToMe && in_array($status, ['open', 'in_progress'], true)): ?>
        <div class="card shadow-sm mb-3 border-info">
            <div class="card-header bg-info-subtle fw-semibold">
                <i class="bi bi-tools me-1 text-info"></i>Mark Task as Complete
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    Describe the work you carried out. The tenant will be notified to review and approve.
                </p>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="mark_complete">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Completion Note <span class="text-danger">*</span>
                        </label>
                        <textarea name="completion_note" class="form-control" rows="4" required
                                  placeholder="Describe what was done, materials used, and any follow-up recommendations…"></textarea>
                    </div>
                    <button type="submit" class="btn btn-info text-white">
                        <i class="bi bi-check2-circle me-1"></i>Submit Completion
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── TENANT: Approve or Reopen ── -->
        <?php if ($isTenant && $status === 'completed'): ?>
        <div class="card shadow-sm mb-3 border-success">
            <div class="card-header bg-success-subtle fw-semibold">
                <i class="bi bi-patch-check me-1 text-success"></i>Work Completed — Your Approval Required
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    The maintenance team has marked this request as complete. Please inspect the work and either
                    <strong>approve</strong> to close it, or <strong>reopen</strong> if the issue was not resolved.
                </p>
                <div class="row g-3">
                    <!-- Approve form -->
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100 border-success-subtle">
                            <h6 class="fw-semibold text-success mb-2">
                                <i class="bi bi-check-circle me-1"></i>Approve &amp; Close
                            </h6>
                            <form method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="approve">
                                <div class="mb-2">
                                    <textarea name="approval_note" class="form-control form-control-sm" rows="3" required
                                              placeholder="Confirm the issue is resolved (e.g. 'Pipe fixed, no more leaks')…"></textarea>
                                </div>
                                <button type="submit" class="btn btn-success btn-sm w-100">
                                    <i class="bi bi-check2-circle me-1"></i>Approve &amp; Close
                                </button>
                            </form>
                        </div>
                    </div>
                    <!-- Reopen form -->
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100 border-danger-subtle">
                            <h6 class="fw-semibold text-danger mb-2">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Reopen Request
                            </h6>
                            <form method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reopen">
                                <div class="mb-2">
                                    <textarea name="reopen_note" class="form-control form-control-sm" rows="3" required
                                              placeholder="Describe what is still wrong or was not fixed correctly…"></textarea>
                                </div>
                                <button type="submit" class="btn btn-danger btn-sm w-100">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reopen
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── TENANT: Reopen resolved request ── -->
        <?php if ($isTenant && $status === 'resolved'): ?>
        <div class="card shadow-sm mb-3 border-warning">
            <div class="card-header bg-warning-subtle fw-semibold">
                <i class="bi bi-exclamation-triangle me-1 text-warning"></i>Issue Recurring?
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    This request was previously resolved. If the issue has come back, you can reopen it.
                </p>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reopen">
                    <div class="mb-2">
                        <textarea name="reopen_note" class="form-control form-control-sm" rows="3" required
                                  placeholder="Describe what has recurred or is still not right…"></textarea>
                    </div>
                    <button type="submit" class="btn btn-warning btn-sm">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reopen Request
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Activity Log Timeline ── -->
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold d-flex align-items-center">
                <i class="bi bi-clock-history me-2 text-primary"></i>Activity Log
                <span class="badge bg-primary-subtle text-primary ms-auto"><?= count($logs) ?></span>
            </div>
            <?php if ($logs): ?>
            <div class="card-body p-0">
                <ul class="list-unstyled mb-0 activity-timeline">
                <?php
                $actionMeta = [
                    'created'          => ['icon' => 'bi-plus-circle-fill',       'color' => 'success'],
                    'status_changed'   => ['icon' => 'bi-arrow-repeat',            'color' => 'primary'],
                    'assigned'         => ['icon' => 'bi-person-check-fill',       'color' => 'info'],
                    'note_added'       => ['icon' => 'bi-chat-left-text-fill',     'color' => 'secondary'],
                    'completed'        => ['icon' => 'bi-check-circle-fill',       'color' => 'success'],
                    'approved'         => ['icon' => 'bi-patch-check-fill',        'color' => 'success'],
                    'reopened'         => ['icon' => 'bi-arrow-counterclockwise',  'color' => 'danger'],
                    'priority_changed' => ['icon' => 'bi-exclamation-circle-fill', 'color' => 'warning'],
                ];
                foreach ($logs as $i => $log):
                    $meta   = $actionMeta[$log['action']] ?? ['icon' => 'bi-dot', 'color' => 'secondary'];
                    $label  = ucwords(str_replace('_', ' ', $log['action']));
                    $isLast = $i === count($logs) - 1;
                ?>
                <li class="d-flex gap-3 px-3 py-3 <?= !$isLast ? 'border-bottom' : '' ?>">
                    <div class="flex-shrink-0 d-flex flex-column align-items-center" style="width:28px">
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-<?= $meta['color'] ?>-subtle"
                             style="width:28px;height:28px">
                            <i class="bi <?= $meta['icon'] ?> text-<?= $meta['color'] ?>" style="font-size:.8rem"></i>
                        </div>
                        <?php if (!$isLast): ?>
                        <div class="flex-grow-1 border-start border-2 border-light mt-1" style="min-height:12px"></div>
                        <?php endif; ?>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex align-items-baseline gap-2 flex-wrap">
                            <span class="fw-semibold small"><?= e($label) ?></span>
                            <?php if (!empty($log['from_value']) || !empty($log['to_value'])): ?>
                            <span class="small text-muted">
                                <?php if ($log['from_value']): ?>
                                <span class="badge bg-secondary-subtle text-secondary"><?= e($log['from_value']) ?></span>
                                <?php endif; ?>
                                <?php if ($log['from_value'] && $log['to_value']): ?>
                                <i class="bi bi-arrow-right small mx-1"></i>
                                <?php endif; ?>
                                <?php if ($log['to_value']): ?>
                                <span class="badge bg-primary-subtle text-primary"><?= e($log['to_value']) ?></span>
                                <?php endif; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($log['note'])): ?>
                        <div class="small text-muted mt-1"><?= e($log['note']) ?></div>
                        <?php endif; ?>
                        <div class="small text-muted mt-1">
                            <i class="bi bi-person me-1"></i><?= e($log['user_name'] ?? $log['actor_name'] ?? 'System') ?>
                            <span class="mx-1">·</span>
                            <i class="bi bi-clock me-1"></i><?= fmt_date($log['created_at'], 'd M Y, H:i') ?>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
                </ul>
            </div>
            <?php else: ?>
            <div class="card-body text-center text-muted py-4">
                <i class="bi bi-clock-history d-block fs-3 mb-2 opacity-25"></i>
                <small>No activity recorded yet.</small>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (is_manager()): ?>
<script>
function deleteMaintenance(id, ref) {
    if (!confirm('Delete work order ' + ref + '?\n\nThis action cannot be undone.')) return;
    fetch('<?= BASE_URL ?>/maintenance/delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>'},
        body: JSON.stringify({id: id})
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            window.location.href = '<?= BASE_URL ?>/maintenance/index';
        } else {
            alert(res.message || 'Failed to delete work order.');
        }
    })
    .catch(() => alert('Network error. Please try again.'));
}
</script>
<?php endif; ?>
<?php include BASE_PATH . '/includes/footer.php'; ?>
