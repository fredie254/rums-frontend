<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api = new ApiClient();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verify_csrf()) { set_flash('error', 'Invalid CSRF token.'); redirect(BASE_URL . '/notifications/templates.php'); }
    $id  = int_param('id', 0, $_POST);
    $res = $api->delete("message-templates/$id");
    if ($res['success'] ?? false) {
        set_flash('success', 'Template deleted.');
    } else {
        set_flash('error', $res['message'] ?? 'Delete failed.');
    }
    redirect(BASE_URL . '/notifications/templates.php');
}

$category = get_param('category');
$channel  = get_param('channel');

$query = array_filter(['category' => $category ?: null, 'channel' => $channel ?: null], fn($v) => $v !== null);
$res       = $api->get('message-templates', $query);
$templates = $res['data'] ?? [];

$page_title = 'Message Templates';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/notifications/index.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h5 class="fw-bold mb-0"><i class="bi bi-file-text me-2 text-primary"></i>Message Templates</h5>
    </div>
    <a href="<?= BASE_URL ?>/notifications/templates/add.php" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg me-1"></i>New Template
    </a>
</div>

<?= flash_html() ?>

<!-- Filters -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Category</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach (['payment', 'lease', 'maintenance', 'broadcast', 'general'] as $c): ?>
                    <option value="<?= $c ?>" <?= $category === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Channel</label>
                <select name="channel" class="form-select form-select-sm">
                    <option value="">All Channels</option>
                    <?php foreach (['sms', 'email', 'both'] as $c): ?>
                    <option value="<?= $c ?>" <?= $channel === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="?" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Channel</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($templates): foreach ($templates as $tpl):
                    $catColors = ['payment' => 'success', 'lease' => 'primary', 'maintenance' => 'warning', 'broadcast' => 'info', 'general' => 'secondary'];
                    $catColor  = $catColors[$tpl['category']] ?? 'secondary';
                ?>
                <tr>
                    <td class="fw-semibold small"><?= e($tpl['name']) ?></td>
                    <td><span class="badge bg-<?= $catColor ?>"><?= ucfirst($tpl['category']) ?></span></td>
                    <td>
                        <?php if ($tpl['channel'] === 'sms'): ?>
                            <i class="bi bi-chat-text text-success"></i> SMS
                        <?php elseif ($tpl['channel'] === 'email'): ?>
                            <i class="bi bi-envelope text-info"></i> Email
                        <?php else: ?>
                            <i class="bi bi-chat-text text-success"></i>+<i class="bi bi-envelope text-info"></i> Both
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= $tpl['subject'] ? e(mb_strimwidth($tpl['subject'], 0, 50, '...')) : '—' ?></td>
                    <td>
                        <?php if ($tpl['is_active']): ?>
                        <span class="badge bg-success">Active</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= e($tpl['created_by_name'] ?? '—') ?></td>
                    <td class="text-end">
                        <!-- Preview -->
                        <button class="btn btn-xs btn-sm btn-outline-secondary py-0 px-1"
                                title="Preview" data-bs-toggle="modal" data-bs-target="#previewModal"
                                onclick="showPreview(<?= htmlspecialchars(json_encode($tpl), ENT_QUOTES) ?>)">
                            <i class="bi bi-eye"></i>
                        </button>
                        <a href="<?= BASE_URL ?>/notifications/templates/edit.php?id=<?= $tpl['id'] ?>"
                           class="btn btn-xs btn-sm btn-outline-primary py-0 px-1" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php if (is_admin()): ?>
                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this template?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $tpl['id'] ?>">
                            <button type="submit" class="btn btn-xs btn-sm btn-outline-danger py-0 px-1" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="7" class="text-center text-muted py-5">
                    <i class="bi bi-file-text fs-2 d-block mb-2"></i>No templates found.
                    <a href="<?= BASE_URL ?>/notifications/templates/add.php" class="btn btn-sm btn-primary mt-2">Create Template</a>
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-semibold" id="previewTitle"></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="previewSubject" class="mb-2 small text-muted d-none">
                    <strong>Subject:</strong> <span id="previewSubjectText"></span>
                </div>
                <div id="previewBody" class="border rounded p-3 bg-light" style="white-space:pre-wrap;font-size:.9rem"></div>
            </div>
        </div>
    </div>
</div>

<script>
function showPreview(tpl) {
    document.getElementById('previewTitle').textContent = tpl.name;
    const subRow = document.getElementById('previewSubject');
    if (tpl.subject) {
        document.getElementById('previewSubjectText').textContent = tpl.subject;
        subRow.classList.remove('d-none');
    } else {
        subRow.classList.add('d-none');
    }
    document.getElementById('previewBody').innerHTML = tpl.channel === 'email'
        ? tpl.body
        : tpl.body.replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
