<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api      = new ApiClient();
$lease_id = int_param('lease_id');
$errors   = [];

if (!$lease_id) { redirect(BASE_URL . '/leases/index'); }

$res   = $api->get("leases/$lease_id");
$lease = $res['data'] ?? null;
if (!$lease) { set_flash('error', 'Lease not found.'); redirect(BASE_URL . '/leases/index'); }

// ── Delete ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('_action') === 'delete') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/leases/documents?lease_id=' . $lease_id); }
    $doc_id = int_param('doc_id', 0, 'post');
    if ($doc_id) {
        $del = $api->delete("leases/$lease_id/documents/$doc_id");
        if (!empty($del['success'])) {
            $file_path = $del['data']['file_path'] ?? null;
            if ($file_path) {
                $full = UPLOAD_PATH . ltrim($file_path, '/');
                if (file_exists($full)) @unlink($full);
            }
            set_flash('success', 'Document removed.');
        } else {
            set_flash('error', $del['message'] ?? 'Failed to remove document.');
        }
    }
    redirect(BASE_URL . '/leases/documents?lease_id=' . $lease_id);
}

// ── Upload ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('_action') === 'upload') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/leases/documents?lease_id=' . $lease_id); }

    $doc_type = post('document_type') ?: 'contract';
    $notes    = post('notes');

    if (empty($_FILES['document']) || $_FILES['document']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Please select a file to upload.';
    } else {
        $path = upload_document($_FILES['document'], 'leases');
        if (!$path) {
            $errors[] = 'Upload failed. Only PDF, JPEG, and PNG files up to ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . ' MB are allowed.';
        } else {
            $res2 = $api->post("leases/$lease_id/documents", [
                'document_type' => $doc_type,
                'original_name' => $_FILES['document']['name'],
                'file_path'     => $path,
                'file_size'     => $_FILES['document']['size'],
                'mime_type'     => $_FILES['document']['type'],
                'notes'         => $notes ?: null,
            ]);
            if (!empty($res2['success'])) {
                set_flash('success', 'Document uploaded.');
                redirect(BASE_URL . '/leases/documents?lease_id=' . $lease_id);
            }
            // Cleanup orphaned file
            @unlink(UPLOAD_PATH . $path);
            $errors[] = $res2['message'] ?? 'Failed to save document record.';
        }
    }
}

// ── Load document list ───────────────────────────────────────
$doc_res   = $api->get("leases/$lease_id/documents");
$documents = $doc_res['data'] ?? [];

$doc_types = [
    'contract'    => 'Lease Contract',
    'addendum'    => 'Addendum',
    'renewal'     => 'Renewal Agreement',
    'termination' => 'Termination Notice',
    'inspection'  => 'Property Inspection',
    'id_copy'     => 'Tenant ID Copy',
    'other'       => 'Other',
];

$page_title = 'Lease Documents';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3">
    <a href="<?= BASE_URL ?>/leases/view?id=<?= $lease_id ?>" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0">Documents — <code><?= e($lease['lease_number']) ?></code></h5>
    <span class="ms-2"><?= lease_badge($lease['status']) ?></span>
</div>

<?php if ($errors): ?><div class="alert alert-danger small"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="row g-3">
    <!-- Upload form -->
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-cloud-upload me-1 text-primary"></i>Upload Document</div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="upload">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Document Type</label>
                        <select name="document_type" class="form-select">
                            <?php foreach ($doc_types as $val => $label): ?>
                            <option value="<?= $val ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">File *</label>
                        <input type="file" name="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                        <div class="form-text">PDF, JPEG, PNG — max <?= MAX_UPLOAD_SIZE / 1024 / 1024 ?> MB</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes…"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-upload me-1"></i>Upload
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Document list -->
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-files me-1"></i>Attached Documents (<?= count($documents) ?>)</div>
            <?php if ($documents): ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>File</th><th>Type</th><th>Size</th><th>Uploaded By</th><th>Date</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($documents as $doc): ?>
                    <tr>
                        <td>
                            <i class="bi bi-<?= str_contains($doc['mime_type'] ?? '', 'pdf') ? 'file-earmark-pdf text-danger' : 'file-earmark-image text-info' ?> me-1"></i>
                            <?= e($doc['original_name']) ?>
                            <?php if (!empty($doc['notes'])): ?><br><small class="text-muted"><?= e($doc['notes']) ?></small><?php endif; ?>
                        </td>
                        <td><span class="badge bg-light text-dark"><?= e($doc_types[$doc['document_type']] ?? ucfirst($doc['document_type'])) ?></span></td>
                        <td class="text-muted small"><?= $doc['file_size'] ? round($doc['file_size']/1024, 1) . ' KB' : '—' ?></td>
                        <td class="small"><?= e($doc['uploaded_by_name'] ?? '—') ?></td>
                        <td class="small"><?= fmt_date($doc['created_at']) ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>/uploads/<?= e($doc['file_path']) ?>" target="_blank"
                               class="btn btn-sm btn-outline-secondary py-0 px-1" title="View/Download">
                                <i class="bi bi-download"></i>
                            </a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Remove this document?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_action" value="delete">
                                <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card-body text-center text-muted py-4">
                <i class="bi bi-folder2-open fs-3 d-block mb-2 opacity-25"></i>
                No documents attached to this lease yet.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
