<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api = new ApiClient();
$id  = int_param('id');
if (!$id) { redirect(BASE_URL . '/maintenance/index'); }

$res = $api->get("maintenance/$id");
$req = $res['data'] ?? null;
if (!$req) { set_flash('error', 'Request not found.'); redirect(BASE_URL . '/maintenance/index'); }

$errors = [];

// Staff dropdown
$a_res   = $api->get('users', ['role' => 'admin',       'status' => 'active', 'per_page' => 200]);
$m_res   = $api->get('users', ['role' => 'manager',     'status' => 'active', 'per_page' => 200]);
$mnt_res = $api->get('users', ['role' => 'maintenance', 'status' => 'active', 'per_page' => 200]);
$staff   = array_merge($a_res['data'] ?? [], $m_res['data'] ?? [], $mnt_res['data'] ?? []);
usort($staff, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

// Units dropdown
$units_res = $api->get('units', ['status' => 'occupied', 'per_page' => 500]);
$units     = $units_res['data'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/maintenance/edit?id=' . $id); }

    $issue_title = post('issue_title');
    $description = post('description');
    $category    = post('category');
    $priority    = post('priority');
    $assigned_to = int_param('assigned_to', 0, 'post') ?: null;
    $notes       = post('notes');

    if (!$issue_title) $errors[] = 'Title is required.';
    if (!$priority)    $errors[] = 'Priority is required.';

    if (!$errors) {
        $payload = array_filter([
            'issue_title' => $issue_title,
            'description' => $description ?: null,
            'category'    => $category    ?: null,
            'priority'    => $priority,
            'assigned_to' => $assigned_to,
            'notes'       => $notes       ?: null,
        ], fn($v) => $v !== null);

        $upd = $api->patch("maintenance/$id", $payload);
        if (!empty($upd['success'])) {
            set_flash('success', 'Work order updated.');
            redirect(BASE_URL . '/maintenance/view?id=' . $id);
        }
        $errors[] = $upd['message'] ?? 'Failed to update request.';
    }
}

$categories = ['plumbing','electrical','structural','pest_control','appliance','painting','cleaning','security','other'];

// Use stored values if no POST, otherwise sticky POST values
$v_title    = $_SERVER['REQUEST_METHOD'] === 'POST' ? post('issue_title')    : ($req['issue_title']    ?? '');
$v_desc     = $_SERVER['REQUEST_METHOD'] === 'POST' ? post('description')    : ($req['description']    ?? '');
$v_cat      = $_SERVER['REQUEST_METHOD'] === 'POST' ? post('category')       : ($req['category']       ?? '');
$v_priority = $_SERVER['REQUEST_METHOD'] === 'POST' ? post('priority')       : ($req['priority']       ?? 'medium');
$v_assigned = $_SERVER['REQUEST_METHOD'] === 'POST' ? int_param('assigned_to',0,'post') : ($req['assigned_to'] ?? '');
$v_notes    = $_SERVER['REQUEST_METHOD'] === 'POST' ? post('notes')          : ($req['notes']          ?? '');

$page_title = 'Edit Work Order';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3 gap-2">
    <a href="<?= BASE_URL ?>/maintenance/view?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div class="flex-grow-1">
        <h5 class="fw-bold mb-0">Edit Work Order</h5>
        <small class="text-muted"><code><?= e($req['request_number'] ?? '') ?></code> &mdash; <?= e(mb_substr($req['issue_title'] ?? '', 0, 60)) ?></small>
    </div>
    <?= priority_badge($req['priority'] ?? 'low') ?>
    <?= maintenance_badge($req['status'] ?? 'open') ?>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger alert-dismissible">
    <ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-pencil-square me-2 text-warning"></i>Work Order Details
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrf_field() ?>
            <div class="row g-3">

                <div class="col-12">
                    <label class="form-label fw-semibold">Issue Title <span class="text-danger">*</span></label>
                    <input type="text" name="issue_title" class="form-control" required
                           value="<?= e($v_title) ?>" placeholder="Brief description of the issue">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Category</label>
                    <select name="category" class="form-select">
                        <option value="">— Select category —</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= $c ?>" <?= $v_cat === $c ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $c)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Priority <span class="text-danger">*</span></label>
                    <select name="priority" class="form-select" required>
                        <?php foreach (['low','medium','high','urgent'] as $p): ?>
                        <option value="<?= $p ?>" <?= $v_priority === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Assigned To</label>
                    <select name="assigned_to" class="form-select">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($staff as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $v_assigned == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?> <small>(<?= ucfirst($s['role'] ?? '') ?>)</small></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" class="form-control" rows="4"><?= e($v_desc) ?></textarea>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Notes / Resolution Notes</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Internal notes or resolution details..."><?= e($v_notes) ?></textarea>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-circle me-1"></i>Save Changes
                    </button>
                    <a href="<?= BASE_URL ?>/maintenance/view?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>

            </div>
        </form>
    </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
