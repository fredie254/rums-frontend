<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$api    = new ApiClient();
$errors = [];
$user   = current_user();

// Units dropdown — only needed for non-tenants (API auto-detects for tenants)
$units = [];
if ($user['role'] !== 'tenant') {
    $units_res = $api->get('units', ['per_page' => 500]);
    $units     = $units_res['data'] ?? [];
}

// Staff dropdown for manager assignment (requires admin or manager role for GET /users)
$staff = [];
if (is_manager()) {
    $a_res = $api->get('users', ['role' => 'admin',   'status' => 'active', 'per_page' => 200]);
    $m_res = $api->get('users', ['role' => 'manager', 'status' => 'active', 'per_page' => 200]);
    $staff = array_merge($a_res['data'] ?? [], $m_res['data'] ?? []);
    usort($staff, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));
}

$pre_unit_id = int_param('unit_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/maintenance/add'); }

    $unit_id     = int_param('unit_id', 0, 'post');
    $issue_title = post('issue_title');
    $description = post('description');
    $category    = post('category');
    $priority    = post('priority') ?: 'medium';
    $assigned_to = int_param('assigned_to', 0, 'post') ?: null;

    if ($user['role'] !== 'tenant' && !$unit_id) $errors[] = 'Unit is required.';
    if (!$issue_title)  $errors[] = 'Title is required.';
    if (!$description)  $errors[] = 'Description is required.';

    if (!$errors) {
        $payload = array_filter([
            'unit_id'     => $user['role'] !== 'tenant' ? $unit_id : null,
            'issue_title' => $issue_title,
            'description' => $description,
            'category'    => $category,
            'priority'    => $priority,
            'assigned_to' => $assigned_to,
        ], fn($v) => $v !== null && $v !== '');

        $res = $api->post('maintenance', $payload);
        if (!empty($res['success'])) {
            $num      = $res['data']['request_number'] ?? '';
            $backPage = $user['role'] === 'tenant'
                ? BASE_URL . '/tenant/maintenance'
                : BASE_URL . '/maintenance/index';
            set_flash('success', "Maintenance request $num submitted.");
            redirect($backPage);
        }
        $errors[] = $res['message'] ?? 'Failed to submit request.';
    }
}

$categories = ['plumbing','electrical','structural','pest_control','appliance','painting','cleaning','security','other'];
$backUrl    = $user['role'] === 'tenant' ? BASE_URL . '/tenant/maintenance' : BASE_URL . '/maintenance/index';
$page_title = 'New Maintenance Request';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3">
    <a href="<?= $backUrl ?>" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0">New Maintenance Request</h5>
</div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="card shadow-sm"><div class="card-body">
    <form method="POST">
        <?= csrf_field() ?>
        <div class="row g-3">
            <?php if ($user['role'] !== 'tenant'): ?>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Unit *</label>
                <select name="unit_id" class="form-select" required>
                    <option value="">— Select Unit —</option>
                    <?php foreach ($units as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= ($pre_unit_id == $u['id'] || int_param('unit_id',0,'post') == $u['id']) ? 'selected' : '' ?>>
                        <?= e($u['property_name'] ?? '') ?> / <?= e($u['unit_number']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="<?= $user['role'] !== 'tenant' ? 'col-md-6' : 'col-12' ?>">
                <label class="form-label fw-semibold">Title *</label>
                <input type="text" name="issue_title" class="form-control" value="<?= e(post('issue_title')) ?>" placeholder="Brief description of issue" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Category</label>
                <select name="category" class="form-select">
                    <?php foreach ($categories as $c): ?><option value="<?= $c ?>" <?= post('category')===$c?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$c)) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Priority</label>
                <select name="priority" class="form-select">
                    <?php foreach (['low','medium','high','urgent'] as $p): ?><option value="<?= $p ?>" <?= (post('priority') ?: 'medium')===$p?'selected':'' ?>><?= ucfirst($p) ?></option><?php endforeach; ?>
                </select>
            </div>
            <?php if (is_manager() && $staff): ?>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Assign To</label>
                <select name="assigned_to" class="form-select">
                    <option value="">— Unassigned —</option>
                    <?php foreach ($staff as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-12"><label class="form-label fw-semibold">Detailed Description *</label><textarea name="description" class="form-control" rows="4" required><?= e(post('description')) ?></textarea></div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-warning"><i class="bi bi-check-circle me-1"></i>Submit Request</button>
                <a href="<?= $backUrl ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </form>
</div></div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
