<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$api    = new ApiClient();
$user   = current_user();
$uuid   = get_param('uuid');
$errors = [];

if (!$uuid) {
    set_flash('error', 'No document specified.');
    redirect(BASE_URL . '/documents/index.php');
}

// ── Load document ─────────────────────────────────────────────
$docRes = $api->get('documents/' . urlencode($uuid));
if (!($docRes['success'] ?? false)) {
    set_flash('error', $docRes['message'] ?? 'Document not found.');
    redirect(BASE_URL . '/documents/index.php');
}
$doc = $docRes['data'];

// ── Handle POST actions ───────────────────────────────────────
$activeTab = get_param('tab', 'overview');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $action = $_POST['_action'] ?? '';

        // ── Edit metadata ─────────────────────────────────────
        if ($action === 'edit' && is_manager()) {
            $payload = [
                'title'        => trim($_POST['title']        ?? ''),
                'description'  => trim($_POST['description']  ?? '') ?: null,
                'category'     => trim($_POST['category']     ?? '') ?: null,
                'access_level' => trim($_POST['access_level'] ?? 'internal'),
            ];
            $res = $api->patch('documents/' . urlencode($uuid), $payload);
            if ($res['success'] ?? false) {
                set_flash('success', 'Document updated.');
                redirect(BASE_URL . '/documents/view.php?uuid=' . urlencode($uuid) . '&tab=overview');
            } else {
                $errors[] = $res['message'] ?? 'Update failed.';
            }
            $activeTab = 'edit';

        // ── Upload new version ────────────────────────────────
        } elseif ($action === 'version' && (is_manager() || is_accountant())) {
            if (empty($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
                $errors[] = 'Please select a file for the new version.';
                $activeTab = 'version';
            } else {
                $fields = ['title' => $doc['title']];
                $res    = $api->upload('documents/' . urlencode($uuid) . '/version', $_FILES['file'], $fields);
                if ($res['success'] ?? false) {
                    $newUuid = $res['data']['uuid'] ?? $uuid;
                    set_flash('success', 'Version ' . ($res['data']['version'] ?? '') . ' uploaded.');
                    redirect(BASE_URL . '/documents/view.php?uuid=' . urlencode($newUuid) . '&tab=versions');
                } else {
                    $errors[] = $res['message'] ?? 'Version upload failed.';
                    $activeTab = 'version';
                }
            }

        // ── Delete ─────────────────────────────────────────────
        } elseif ($action === 'delete') {
            $res = $api->delete('documents/' . urlencode($uuid));
            if ($res['success'] ?? false) {
                set_flash('success', 'Document deleted.');
                redirect(BASE_URL . '/documents/index.php');
            } else {
                $errors[] = $res['message'] ?? 'Delete failed.';
            }
        }
    }
}

// ── Load versions (always, for version tab) ───────────────────
$versions = [];
if ($activeTab === 'versions' || $activeTab === 'version') {
    $vRes     = $api->get('documents/' . urlencode($uuid) . '/versions');
    $versions = $vRes['data']['data'] ?? [];
}

// ── Load access log (admin/manager only) ─────────────────────
$accessLog = [];
if ($activeTab === 'log' && is_manager()) {
    $logRes    = $api->get('documents/' . urlencode($uuid) . '/access-log');
    $accessLog = $logRes['data']['data'] ?? [];
}

$downloadUrl = rtrim(env('APP_URL', BASE_URL), '/') . '/api/v1/documents/' . urlencode($uuid) . '/download?api_token=' . urlencode($_SESSION['api_token'] ?? '');

// Icon helpers
$typeColors = [
    'lease'       => 'primary',
    'tenant'      => 'info',
    'property'    => 'success',
    'certificate' => 'warning',
    'financial'   => 'teal',
    'maintenance' => 'orange',
    'other'       => 'secondary',
];
$mimeIconFn = function (string $mime): string {
    if (str_contains($mime, 'pdf'))   return 'bi-file-earmark-pdf text-danger';
    if (str_contains($mime, 'word'))  return 'bi-file-earmark-word text-primary';
    if (str_contains($mime, 'excel') || str_contains($mime, 'spreadsheet')) return 'bi-file-earmark-excel text-success';
    if (str_contains($mime, 'image')) return 'bi-file-earmark-image text-info';
    return 'bi-file-earmark text-secondary';
};

$color      = $typeColors[$doc['document_type']] ?? 'secondary';
$mimeIcon   = $mimeIconFn($doc['mime_type']);
$sizeKb     = round($doc['file_size'] / 1024, 1);
$sizeFmt    = $sizeKb > 1024 ? round($sizeKb / 1024, 1) . ' MB' : $sizeKb . ' KB';
$canEdit    = is_manager();
$canVersion = is_manager() || is_accountant();
$canDelete  = is_manager() || ((int)($doc['uploaded_by'] ?? 0) === (int)$user['id']);

$page_title = e($doc['title']);
include BASE_PATH . '/includes/header.php';
?>

<!-- Breadcrumb / header row -->
<div class="d-flex align-items-center mb-3 gap-2 flex-wrap">
    <a href="<?= BASE_URL ?>/documents/index.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div class="flex-grow-1 min-w-0">
        <h5 class="fw-bold mb-0 text-truncate">
            <i class="bi <?= $mimeIcon ?> me-2"></i><?= e($doc['title']) ?>
        </h5>
        <small class="text-muted"><?= e($doc['file_name']) ?></small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e($downloadUrl) ?>" class="btn btn-sm btn-success" download>
            <i class="bi bi-download me-1"></i>Download
        </a>
        <?php if ($canDelete && !($doc['is_deleted'] ?? false)): ?>
        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
            <i class="bi bi-trash me-1"></i>Delete
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?= flash_html() ?>

<?php if ($doc['is_deleted'] ?? false): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-exclamation-triangle-fill"></i>
    This document has been deleted.
</div>
<?php endif; ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3" id="docTabs">
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'overview' ? 'active' : '' ?>"
           href="?uuid=<?= urlencode($uuid) ?>&tab=overview">
            <i class="bi bi-info-circle me-1"></i>Overview
        </a>
    </li>
    <?php if ($canEdit): ?>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'edit' ? 'active' : '' ?>"
           href="?uuid=<?= urlencode($uuid) ?>&tab=edit">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
    </li>
    <?php endif; ?>
    <li class="nav-item">
        <a class="nav-link <?= in_array($activeTab, ['versions','version']) ? 'active' : '' ?>"
           href="?uuid=<?= urlencode($uuid) ?>&tab=versions">
            <i class="bi bi-clock-history me-1"></i>Versions
        </a>
    </li>
    <?php if ($canVersion && !($doc['is_deleted'] ?? false)): ?>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'version' ? 'active' : '' ?>"
           href="?uuid=<?= urlencode($uuid) ?>&tab=version">
            <i class="bi bi-upload me-1"></i>New Version
        </a>
    </li>
    <?php endif; ?>
    <?php if (is_manager()): ?>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'log' ? 'active' : '' ?>"
           href="?uuid=<?= urlencode($uuid) ?>&tab=log">
            <i class="bi bi-shield-check me-1"></i>Access Log
        </a>
    </li>
    <?php endif; ?>
</ul>

<!-- ── Overview ──────────────────────────────────────────────── -->
<?php if ($activeTab === 'overview'): ?>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm" style="border-left: 3px solid var(--bs-<?= $color ?>)">
            <div class="card-header d-flex align-items-center gap-2 py-2">
                <i class="bi <?= $mimeIcon ?> fs-4"></i>
                <span class="fw-semibold"><?= e($doc['title']) ?></span>
                <?php if (($doc['version'] ?? 1) > 1): ?>
                <span class="badge bg-secondary ms-auto">v<?= $doc['version'] ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($doc['description']): ?>
                <p class="text-muted mb-3"><?= nl2br(e($doc['description'])) ?></p>
                <hr>
                <?php endif; ?>

                <dl class="row mb-0" style="font-size:.875rem">
                    <dt class="col-sm-4 text-muted">Document Type</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-<?= $color ?>"><?= ucfirst($doc['document_type']) ?></span>
                    </dd>

                    <?php if ($doc['category']): ?>
                    <dt class="col-sm-4 text-muted">Category</dt>
                    <dd class="col-sm-8"><span class="badge bg-light text-dark border"><?= e($doc['category']) ?></span></dd>
                    <?php endif; ?>

                    <dt class="col-sm-4 text-muted">Access Level</dt>
                    <dd class="col-sm-8">
                        <?php
                        $alColor = match($doc['access_level']) {
                            'private'  => 'danger',
                            'shared'   => 'success',
                            default    => 'secondary',
                        };
                        ?>
                        <span class="badge bg-<?= $alColor ?>"><?= ucfirst($doc['access_level']) ?></span>
                    </dd>

                    <dt class="col-sm-4 text-muted">Entity</dt>
                    <dd class="col-sm-8">
                        <?= ucfirst($doc['entity_type']) ?>
                        <?php if ($doc['entity_id']): ?><span class="text-muted">#<?= $doc['entity_id'] ?></span><?php endif; ?>
                    </dd>

                    <dt class="col-sm-4 text-muted">File Name</dt>
                    <dd class="col-sm-8 font-monospace" style="font-size:.8rem"><?= e($doc['file_name']) ?></dd>

                    <dt class="col-sm-4 text-muted">File Size</dt>
                    <dd class="col-sm-8"><?= $sizeFmt ?></dd>

                    <dt class="col-sm-4 text-muted">MIME Type</dt>
                    <dd class="col-sm-8 text-muted" style="font-size:.8rem"><?= e($doc['mime_type']) ?></dd>

                    <dt class="col-sm-4 text-muted">Version</dt>
                    <dd class="col-sm-8">
                        v<?= $doc['version'] ?>
                        <?php if ($doc['is_latest'] ?? true): ?>
                        <span class="badge bg-success ms-1">Latest</span>
                        <?php else: ?>
                        <span class="badge bg-secondary ms-1">Superseded</span>
                        <?php endif; ?>
                    </dd>

                    <?php if (!empty($doc['parent_title'])): ?>
                    <dt class="col-sm-4 text-muted">Previous Version</dt>
                    <dd class="col-sm-8">
                        <a href="?uuid=<?= urlencode($doc['parent_uuid'] ?? '') ?>">
                            <?= e($doc['parent_title']) ?> (v<?= $doc['parent_version'] ?? '' ?>)
                        </a>
                    </dd>
                    <?php endif; ?>

                    <dt class="col-sm-4 text-muted">Uploaded By</dt>
                    <dd class="col-sm-8"><?= e($doc['uploaded_by_name'] ?? '—') ?></dd>

                    <dt class="col-sm-4 text-muted">Uploaded On</dt>
                    <dd class="col-sm-8"><?= fmt_date($doc['created_at'], 'd M Y, H:i') ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Quick actions -->
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold py-2 small">Quick Actions</div>
            <div class="card-body d-grid gap-2">
                <a href="<?= e($downloadUrl) ?>" class="btn btn-success" download>
                    <i class="bi bi-download me-1"></i>Download File
                </a>
                <?php if ($canEdit && !($doc['is_deleted'] ?? false)): ?>
                <a href="?uuid=<?= urlencode($uuid) ?>&tab=edit" class="btn btn-outline-primary">
                    <i class="bi bi-pencil me-1"></i>Edit Metadata
                </a>
                <?php endif; ?>
                <?php if ($canVersion && !($doc['is_deleted'] ?? false)): ?>
                <a href="?uuid=<?= urlencode($uuid) ?>&tab=version" class="btn btn-outline-info">
                    <i class="bi bi-upload me-1"></i>Upload New Version
                </a>
                <?php endif; ?>
                <a href="?uuid=<?= urlencode($uuid) ?>&tab=versions" class="btn btn-outline-secondary">
                    <i class="bi bi-clock-history me-1"></i>Version History
                </a>
            </div>
        </div>

        <!-- UUID / reference -->
        <div class="card shadow-sm">
            <div class="card-header fw-semibold py-2 small">Reference</div>
            <div class="card-body">
                <label class="form-label small text-muted mb-1">Document UUID</label>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control font-monospace" style="font-size:.75rem"
                           value="<?= e($doc['uuid']) ?>" id="uuidInput" readonly>
                    <button class="btn btn-outline-secondary" type="button" onclick="copyUuid()" title="Copy">
                        <i class="bi bi-copy"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Edit Metadata ──────────────────────────────────────────── -->
<?php elseif ($activeTab === 'edit' && $canEdit): ?>
<div class="row">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold py-2">Edit Document Metadata</div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="edit">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" maxlength="200"
                               value="<?= e($_POST['title'] ?? $doc['title']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?= e($_POST['description'] ?? $doc['description'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category</label>
                        <input type="text" name="category" class="form-control" maxlength="100"
                               value="<?= e($_POST['category'] ?? $doc['category'] ?? '') ?>"
                               placeholder="e.g. signed_lease, id_copy">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Access Level</label>
                        <select name="access_level" class="form-select">
                            <?php foreach (['internal' => 'Internal (Staff only)', 'shared' => 'Shared (Tenant can view)', 'private' => 'Private (Uploader only)'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= ($doc['access_level'] === $val) ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Save Changes
                        </button>
                        <a href="?uuid=<?= urlencode($uuid) ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ── Versions ───────────────────────────────────────────────── -->
<?php elseif (in_array($activeTab, ['versions', 'version'])): ?>

<?php if ($activeTab === 'version' && $canVersion): ?>
<div class="row mb-4">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold py-2">
                <i class="bi bi-upload me-1 text-primary"></i>Upload New Version
            </div>
            <div class="card-body">
                <p class="text-muted small">The new file will replace this document as the latest version. The current file is preserved in version history.</p>
                <form method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="version">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New File <span class="text-danger">*</span></label>
                        <input type="file" name="file" class="form-control"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.gif,.txt,.csv" required>
                        <div class="form-text">Same file types as the original. Max 10 MB.</div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload me-1"></i>Upload Version
                    </button>
                    <a href="?uuid=<?= urlencode($uuid) ?>&tab=versions" class="btn btn-outline-secondary ms-2">View History</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($versions): ?>
<div class="card shadow-sm">
    <div class="card-header fw-semibold py-2">
        <i class="bi bi-clock-history me-1"></i>Version History
        <span class="badge bg-secondary ms-1"><?= count($versions) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Version</th>
                    <th>Title</th>
                    <th>File</th>
                    <th>Size</th>
                    <th>Uploaded By</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($versions as $v):
                    $vSizeKb  = round($v['file_size'] / 1024, 1);
                    $vSizeFmt = $vSizeKb > 1024 ? round($vSizeKb / 1024, 1) . ' MB' : $vSizeKb . ' KB';
                    $vDlUrl   = rtrim(env('APP_URL', BASE_URL), '/') . '/api/v1/documents/' . urlencode($v['uuid']) . '/download?api_token=' . urlencode($_SESSION['api_token'] ?? '');
                ?>
                <tr class="<?= $v['uuid'] === $uuid ? 'table-primary' : '' ?>">
                    <td class="fw-bold">v<?= $v['version'] ?></td>
                    <td>
                        <a href="?uuid=<?= urlencode($v['uuid']) ?>">
                            <?= e($v['title']) ?>
                        </a>
                    </td>
                    <td class="text-muted" style="font-size:.8rem"><?= e($v['file_name']) ?></td>
                    <td class="text-muted"><?= $vSizeFmt ?></td>
                    <td><?= e($v['uploaded_by_name'] ?? '—') ?></td>
                    <td><?= fmt_date($v['created_at'], 'd M Y') ?></td>
                    <td>
                        <?php if ($v['is_latest']): ?>
                        <span class="badge bg-success">Latest</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Old</span>
                        <?php endif; ?>
                        <?php if ($v['uuid'] === $uuid): ?>
                        <span class="badge bg-primary ms-1">Viewing</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= e($vDlUrl) ?>" class="btn btn-xs btn-sm btn-outline-success py-0 px-1" download title="Download v<?= $v['version'] ?>">
                            <i class="bi bi-download"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card shadow-sm">
    <div class="text-center text-muted py-4">
        <i class="bi bi-clock-history fs-2 d-block mb-2"></i>
        No version history available.
    </div>
</div>
<?php endif; ?>

<!-- ── Access Log ─────────────────────────────────────────────── -->
<?php elseif ($activeTab === 'log' && is_manager()): ?>
<?php if ($accessLog): ?>
<div class="card shadow-sm">
    <div class="card-header fw-semibold py-2">
        <i class="bi bi-shield-check me-1"></i>Access Audit Log
        <span class="badge bg-secondary ms-1"><?= count($accessLog) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Action</th>
                    <th>User</th>
                    <th>IP Address</th>
                    <th>User Agent</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accessLog as $entry):
                    $actionColor = match($entry['action']) {
                        'download' => 'success',
                        'delete'   => 'danger',
                        'upload'   => 'primary',
                        'version'  => 'info',
                        default    => 'secondary',
                    };
                ?>
                <tr>
                    <td><span class="badge bg-<?= $actionColor ?>"><?= ucfirst($entry['action']) ?></span></td>
                    <td><?= e($entry['user_name'] ?? $entry['user_id']) ?></td>
                    <td class="font-monospace" style="font-size:.8rem"><?= e($entry['ip_address'] ?? '—') ?></td>
                    <td class="text-muted" style="font-size:.75rem;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                        title="<?= e($entry['user_agent'] ?? '') ?>">
                        <?= e(substr($entry['user_agent'] ?? '—', 0, 60)) ?>
                    </td>
                    <td><?= fmt_date($entry['created_at'], 'd M Y, H:i') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card shadow-sm">
    <div class="text-center text-muted py-4">
        <i class="bi bi-shield-check fs-2 d-block mb-2"></i>
        No access log entries found.
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- ── Delete Modal ───────────────────────────────────────────── -->
<?php if ($canDelete && !($doc['is_deleted'] ?? false)): ?>
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-trash me-2"></i>Delete Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong><?= e($doc['title']) ?></strong>?</p>
                <p class="text-muted small mb-0">The file will be soft-deleted and can be recovered by an administrator.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="delete">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function copyUuid() {
    const el = document.getElementById('uuidInput');
    if (!el) return;
    navigator.clipboard.writeText(el.value).then(() => {
        const btn = el.nextElementSibling;
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check text-success"></i>';
        setTimeout(() => { btn.innerHTML = orig; }, 1500);
    });
}
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
