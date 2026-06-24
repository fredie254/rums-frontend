<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'maintenance');

$api = new ApiClient();
$page_title = 'Work Orders';

$me = current_user();

/* ── Handle status updates ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $id     = int_param('id');
    $action = post_param('action');

    if ($action === 'start') {
        $api->post("maintenance/$id/start", []);
        audit_log('UPDATE', 'maintenance', $id, 'Work started');
        set_flash('success', 'Work order started.');
    }

    if ($action === 'complete') {
        $labour_h  = (float)post_param('labour_hours');
        $mat_cost  = (float)post_param('materials_cost');
        $lab_cost  = (float)post_param('labour_cost');
        $contractor = post_param('contractor_name');
        $notes     = post_param('completion_notes');

        $api->post("maintenance/$id/complete", [
            'labour_hours'     => $labour_h,
            'materials_cost'   => $mat_cost,
            'labour_cost'      => $lab_cost,
            'contractor_name'  => $contractor ?: null,
            'completion_notes' => $notes,
        ]);
        audit_log('UPDATE', 'maintenance', $id, 'Work completed');
        set_flash('success', 'Work order marked as completed.');
    }

    if ($action === 'assign' && is_manager()) {
        $assigned = int_param('assigned_to');
        $api->post("maintenance/$id/assign", ['assigned_to' => $assigned]);
        audit_log('UPDATE', 'maintenance', $id, 'Work order assigned to user '.$assigned);
        set_flash('success', 'Work order assigned.');
    }

    redirect(BASE_URL . '/maintenance_staff/work_orders' . ($_GET ? '?'.http_build_query($_GET) : ''));
}

/* ── View single work order ── */
$view_id    = int_param('id', 0, 'GET');
$work_order = null;
if ($view_id) {
    $work_order = $api->get("maintenance/$view_id")['data'] ?? null;
}

/* ── Dropdowns (properties + staff) ── */
$properties = $api->get('properties', ['per_page' => 200])['data'] ?? [];
$all_users  = $api->get('users', ['status' => 'active', 'per_page' => 200])['data'] ?? [];
$staff = array_values(array_filter($all_users, function ($u) {
    return in_array($u['role'], ['maintenance', 'manager', 'admin']);
}));

/* ── Filters ── */
$filter_status   = get_param('status', 'active');
$filter_priority = get_param('priority', '');
$filter_property = int_param('property_id', 0);
$filter_assigned = int_param('assigned_to', 0);

/* ── Build API filter params ── */
$filters = ['per_page' => 200];

if ($filter_status === 'active') {
    $filters['status'] = 'active';
} elseif ($filter_status !== 'all') {
    $filters['status'] = $filter_status;
}

if ($filter_priority) { $filters['priority']    = $filter_priority; }
if ($filter_property) { $filters['property_id'] = $filter_property; }
if ($filter_assigned) { $filters['assigned_to'] = $filter_assigned; }

/* For maintenance role the API restricts server-side — no client-side filter needed. */

$orders = $api->get('maintenance', $filters)['data'] ?? [];

include BASE_PATH . '/includes/header.php';
?>

<?= flash_html() ?>

<?php if ($work_order): ?>
<!-- ── Single Work Order Detail ── -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-clipboard-check me-2 text-warning"></i>Work Order #<?= e($work_order['request_number'] ?? $work_order['id']) ?></h5>
    <a href="work_orders" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>All Orders</a>
</div>

<div class="row g-4">
    <div class="col-md-8">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white py-2 d-flex justify-content-between">
                <span class="fw-semibold"><?= e($work_order['issue_title'] ?? $work_order['description']) ?></span>
                <div><?= priority_badge($work_order['priority']) ?> <?= maintenance_badge($work_order['status']) ?></div>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Property</dt>
                    <dd class="col-sm-9"><?= e($work_order['property_name']) ?> — Unit <?= e($work_order['unit_number']) ?></dd>
                    <dt class="col-sm-3">Reported By</dt>
                    <dd class="col-sm-9"><?= e($work_order['tenant_name'] ?: '—') ?> <?= $work_order['tenant_phone'] ? '('.$work_order['tenant_phone'].')' : '' ?></dd>
                    <dt class="col-sm-3">Assigned To</dt>
                    <dd class="col-sm-9"><?= e($work_order['assigned_to_name'] ?? '—') ?></dd>
                    <dt class="col-sm-3">Reported</dt>
                    <dd class="col-sm-9"><?= fmt_date($work_order['created_at'], true) ?></dd>
                    <?php if ($work_order['work_started']): ?>
                    <dt class="col-sm-3">Started</dt>
                    <dd class="col-sm-9"><?= fmt_date($work_order['work_started'], true) ?></dd>
                    <?php endif; ?>
                    <?php if ($work_order['work_completed']): ?>
                    <dt class="col-sm-3">Completed</dt>
                    <dd class="col-sm-9"><?= fmt_date($work_order['work_completed'], true) ?></dd>
                    <?php endif; ?>
                </dl>
                <?php if ($work_order['description']): ?>
                <hr><p class="mb-0"><?= nl2br(e($work_order['description'])) ?></p>
                <?php endif; ?>
                <?php if ($work_order['notes']): ?>
                <hr><h6 class="text-muted">Notes</h6><p class="mb-0"><?= nl2br(e($work_order['notes'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cost Summary -->
        <?php if ($work_order['materials_cost'] || $work_order['labour_cost']): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white py-2"><span class="fw-semibold">Cost Summary</span></div>
            <div class="card-body">
                <table class="table table-sm mb-0" style="max-width:300px">
                    <tr><td>Materials</td><td class="text-end"><?= money($work_order['materials_cost'] ?? 0) ?></td></tr>
                    <tr><td>Labour (<?= $work_order['labour_hours'] ?? 0 ?> hrs)</td><td class="text-end"><?= money($work_order['labour_cost'] ?? 0) ?></td></tr>
                    <tr class="fw-bold border-top"><td>Total</td><td class="text-end"><?= money(($work_order['materials_cost'] ?? 0) + ($work_order['labour_cost'] ?? 0)) ?></td></tr>
                </table>
                <?php if ($work_order['contractor_name']): ?>
                <small class="text-muted">Contractor: <?= e($work_order['contractor_name']) ?></small>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-md-4">
        <!-- Actions Panel -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white py-2"><span class="fw-semibold">Actions</span></div>
            <div class="card-body d-grid gap-2">
                <?php if ($work_order['status'] === 'open' || $work_order['status'] === 'pending'): ?>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $work_order['id'] ?>">
                    <button name="action" value="start" class="btn btn-warning w-100">
                        <i class="bi bi-play-fill me-1"></i>Start Work
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($work_order['status'] === 'in_progress'): ?>
                <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#completeModal">
                    <i class="bi bi-check2-circle me-1"></i>Mark Complete
                </button>
                <?php endif; ?>

                <?php if (is_manager()): ?>
                <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#assignModal">
                    <i class="bi bi-person-check me-1"></i>Assign / Reassign
                </button>
                <?php endif; ?>

                <a href="<?= BASE_URL ?>/maintenance/view?id=<?= $work_order['id'] ?>" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-eye me-1"></i>Full Details
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Complete Modal -->
<div class="modal fade" id="completeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $work_order['id'] ?>">
                <input type="hidden" name="action" value="complete">
                <div class="modal-header">
                    <h5 class="modal-title">Complete Work Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Labour Hours</label>
                            <input type="number" name="labour_hours" step="0.5" min="0" class="form-control" value="<?= $work_order['labour_hours'] ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Materials Cost</label>
                            <input type="number" name="materials_cost" step="0.01" min="0" class="form-control" value="<?= $work_order['materials_cost'] ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Labour Cost</label>
                            <input type="number" name="labour_cost" step="0.01" min="0" class="form-control" value="<?= $work_order['labour_cost'] ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Contractor Name</label>
                            <input type="text" name="contractor_name" class="form-control" value="<?= e($work_order['contractor_name'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Completion Notes</label>
                            <textarea name="completion_notes" class="form-control" rows="3" placeholder="Describe work done, parts replaced, etc."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>Mark Complete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Modal -->
<?php if (is_manager()): ?>
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $work_order['id'] ?>">
                <input type="hidden" name="action" value="assign">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Work Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Assign To</label>
                    <select name="assigned_to" class="form-select" required>
                        <option value="">Select Staff</option>
                        <?php foreach ($staff as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $work_order['assigned_to'] == $s['id'] ? 'selected':'' ?>><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ── Work Orders List ── -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0"><i class="bi bi-clipboard-list me-2 text-warning"></i>Work Orders</h5>
    <a href="<?= BASE_URL ?>/maintenance/add" class="btn btn-warning btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Request
    </a>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="active"      <?= $filter_status==='active'     ?'selected':''?>>Active</option>
                    <option value="all"         <?= $filter_status==='all'        ?'selected':''?>>All</option>
                    <option value="open"        <?= $filter_status==='open'       ?'selected':''?>>Open</option>
                    <option value="in_progress" <?= $filter_status==='in_progress'?'selected':''?>>In Progress</option>
                    <option value="completed"   <?= $filter_status==='completed'  ?'selected':''?>>Completed</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Priority</label>
                <select name="priority" class="form-select form-select-sm">
                    <option value="">All Priorities</option>
                    <option value="urgent" <?= $filter_priority==='urgent'?'selected':''?>>Urgent</option>
                    <option value="high"   <?= $filter_priority==='high'  ?'selected':''?>>High</option>
                    <option value="medium" <?= $filter_priority==='medium'?'selected':''?>>Medium</option>
                    <option value="low"    <?= $filter_priority==='low'   ?'selected':''?>>Low</option>
                </select>
            </div>
            <?php if (is_manager()): ?>
            <div class="col-auto">
                <label class="form-label small mb-1">Property</label>
                <select name="property_id" class="form-select form-select-sm">
                    <option value="0">All</option>
                    <?php foreach ($properties as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $filter_property==$p['id']?'selected':''?>><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Assigned To</label>
                <select name="assigned_to" class="form-select form-select-sm">
                    <option value="0">Anyone</option>
                    <?php foreach ($staff as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $filter_assigned==$s['id']?'selected':''?>><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm">Filter</button>
                <a href="work_orders" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-2">
        <span class="fw-semibold">Work Orders <span class="badge bg-secondary"><?= count($orders) ?></span></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Issue</th>
                        <th>Unit / Property</th>
                        <th>Tenant</th>
                        <th>Priority</th>
                        <th>Assigned</th>
                        <th>Cost</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$orders): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No work orders found.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $o):
                        $total_cost = ($o['materials_cost'] ?? 0) + ($o['labour_cost'] ?? 0);
                    ?>
                    <tr>
                        <td class="font-monospace small"><?= e($o['request_number'] ?? $o['id']) ?></td>
                        <td>
                            <div class="fw-semibold"><?= e($o['issue_title'] ?? substr($o['description'],0,35)) ?></div>
                            <small class="text-muted"><?= fmt_date($o['created_at']) ?></small>
                        </td>
                        <td><?= e($o['property_name'] ?? '—') ?> / <?= e($o['unit_number'] ?? '—') ?></td>
                        <td><?= e($o['tenant_name'] ?? '—') ?></td>
                        <td><?= priority_badge($o['priority']) ?></td>
                        <td><?= e($o['assigned_to_name'] ?? '<span class="text-muted">Unassigned</span>') ?></td>
                        <td><?= $total_cost > 0 ? money($total_cost) : '—' ?></td>
                        <td><?= maintenance_badge($o['status']) ?></td>
                        <td>
                            <a href="?id=<?= $o['id'] ?>" class="btn btn-xs btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/footer.php'; ?>
