<?php
require_once __DIR__ . '/../../config/config.php';
require_role('admin', 'manager');

$api  = new ApiClient();
$res  = $api->get('lease-templates');
$tpls = $res['data'] ?? [];

$page_title = 'Lease Templates';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/leases/index" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
        <h5 class="fw-bold mb-0"><i class="bi bi-layout-text-window me-2 text-primary"></i>Lease Templates</h5>
    </div>
    <a href="<?= BASE_URL ?>/leases/templates/add" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-circle me-1"></i>New Template
    </a>
</div>

<div class="alert alert-info small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    Templates pre-fill lease terms on the <strong>New Lease</strong> form.
    Use placeholders such as <code>{{TENANT_NAME}}</code>, <code>{{UNIT_NUMBER}}</code>, <code>{{MONTHLY_RENT}}</code>, <code>{{START_DATE}}</code>, <code>{{END_DATE}}</code> — they are substituted automatically when the template is loaded.
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>#</th><th>Name</th><th>Type</th><th>Default</th><th>Updated</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if ($tpls): $i = 1; foreach ($tpls as $t): ?>
            <tr>
                <td><?= $i++ ?></td>
                <td class="fw-semibold"><?= e($t['name']) ?></td>
                <td><span class="badge bg-light text-dark"><?= ucfirst(str_replace('-',' ',$t['lease_type'])) ?></span></td>
                <td>
                    <?php if ($t['is_default']): ?>
                    <span class="badge bg-primary"><i class="bi bi-star-fill me-1"></i>Default</span>
                    <?php else: ?>
                    <span class="text-muted small">—</span>
                    <?php endif; ?>
                </td>
                <td class="small text-muted"><?= fmt_date($t['updated_at'] ?? $t['created_at']) ?></td>
                <td>
                    <a href="<?= BASE_URL ?>/leases/templates/edit?id=<?= $t['id'] ?>"
                       class="btn btn-sm btn-outline-primary py-0 px-2"><i class="bi bi-pencil"></i> Edit</a>
                    <button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="deleteTemplate(<?= $t['id'] ?>, '<?= e(addslashes($t['name'])) ?>')">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No templates yet. <a href="<?= BASE_URL ?>/leases/templates/add">Create one</a>.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function deleteTemplate(id, name) {
    if (!confirm('Delete template "' + name + '"? This cannot be undone.')) return;
    fetch(`<?= rtrim(env('APP_URL',''), '/') ?>/api/v1/lease-templates/${id}`, {
        method: 'DELETE',
        headers: { 'Authorization': 'Bearer <?= $_SESSION['api_token'] ?? '' ?>' }
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) location.reload();
        else alert(res.message || 'Failed to delete template.');
    });
}
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
