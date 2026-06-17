<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api = new ApiClient();
$id  = int_param('id');
if (!$id) { redirect(BASE_URL . '/tenants/index.php'); }

$res    = $api->get("tenants/$id");
$tenant = $res['data'] ?? null;
if (!$tenant) { set_flash('error', 'Tenant not found.'); redirect(BASE_URL . '/tenants/index.php'); }

$full_name = $tenant['full_name'] ?? trim(($tenant['first_name'] ?? '') . ' ' . ($tenant['last_name'] ?? ''));
$errors    = [];

$doc_types = [
    'national_id_front' => 'National ID (Front)',
    'national_id_back'  => 'National ID (Back)',
    'passport'          => 'Passport',
    'alien_id'          => 'Alien ID',
    'driving_license'   => 'Driving License',
    'payslip'           => 'Payslip',
    'bank_statement'    => 'Bank Statement',
    'lease_agreement'   => 'Lease Agreement',
    'other'             => 'Other',
];

// ── Delete document ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/tenants/documents.php?id=' . $id); }
    $doc_id = int_param('doc_id', 0, 'post');
    if ($doc_id) {
        $del = $api->delete("tenants/$id/kyc-documents/$doc_id");
        if (!empty($del['success'])) {
            // Delete local file
            $fp = $del['data']['file_path'] ?? null;
            if ($fp) {
                $full_path = UPLOAD_PATH . $fp;
                if (is_file($full_path)) unlink($full_path);
            }
            set_flash('success', 'Document deleted.');
        } else {
            set_flash('error', $del['message'] ?? 'Failed to delete document.');
        }
    }
    redirect(BASE_URL . '/tenants/documents.php?id=' . $id);
}

// ── Upload document ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'upload') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/tenants/documents.php?id=' . $id); }

    $doc_type = post('document_type');
    $notes    = post('notes');

    if (!$doc_type || !isset($doc_types[$doc_type])) $errors[] = 'Select a valid document type.';
    if (empty($_FILES['document']['name']))            $errors[] = 'Please select a file to upload.';

    if (!$errors) {
        $path = upload_document($_FILES['document'], 'kyc');
        if (!$path) {
            $errors[] = 'Upload failed. Allowed formats: PDF, JPEG, PNG. Max size: ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . ' MB.';
        } else {
            $res = $api->post("tenants/$id/kyc-documents", [
                'document_type' => $doc_type,
                'original_name' => $_FILES['document']['name'],
                'file_path'     => $path,
                'file_size'     => (int)$_FILES['document']['size'],
                'mime_type'     => $_FILES['document']['type'],
                'notes'         => $notes ?: null,
            ]);
            if (!empty($res['success'])) {
                set_flash('success', 'Document uploaded successfully.');
                redirect(BASE_URL . '/tenants/documents.php?id=' . $id);
            }
            // API failed — clean up the uploaded file
            if (is_file(UPLOAD_PATH . $path)) unlink(UPLOAD_PATH . $path);
            $errors[] = $res['message'] ?? 'Failed to save document record.';
        }
    }
}

// ── Fetch existing documents ───────────────────────────────────
$docs_res = $api->get("tenants/$id/kyc-documents");
$docs     = $docs_res['data'] ?? [];

$page_title = 'KYC Documents — ' . $full_name;
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex align-items-center mb-4 gap-2">
    <a href="<?= BASE_URL ?>/tenants/view.php?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div class="flex-grow-1">
        <h5 class="fw-bold mb-0">KYC Documents</h5>
        <small class="text-muted"><?= e($full_name) ?></small>
    </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger alert-dismissible">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">

    <!-- ── Upload form ────────────────────────────────────────── -->
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-cloud-upload me-2 text-primary"></i>Upload Document
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="upload">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Document Type <span class="text-danger">*</span></label>
                        <select name="document_type" class="form-select" required>
                            <option value="">— Select type —</option>
                            <?php foreach ($doc_types as $k => $label): ?>
                            <option value="<?= $k ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">File <span class="text-danger">*</span></label>
                        <input type="file" name="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                        <div class="form-text">PDF, JPEG or PNG · Max <?= MAX_UPLOAD_SIZE / 1024 / 1024 ?> MB</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional note">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-upload me-1"></i>Upload
                    </button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mt-3 border-0 bg-light">
            <div class="card-body small text-muted">
                <p class="fw-semibold mb-1"><i class="bi bi-info-circle me-1"></i>Accepted Documents</p>
                <ul class="mb-0 ps-3">
                    <?php foreach ($doc_types as $label): ?>
                    <li><?= $label ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- ── Document list ──────────────────────────────────────── -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex align-items-center">
                <span class="fw-semibold flex-grow-1"><i class="bi bi-folder2-open me-2 text-primary"></i>Uploaded Documents</span>
                <span class="badge bg-primary-subtle text-primary"><?= count($docs) ?></span>
            </div>
            <?php if ($docs): ?>
            <div class="list-group list-group-flush">
                <?php foreach ($docs as $doc):
                    $ext      = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                    $isPdf    = $ext === 'pdf';
                    $icon     = $isPdf ? 'bi-file-earmark-pdf text-danger' : 'bi-file-earmark-image text-primary';
                    $fileUrl  = BASE_URL . '/assets/uploads/' . $doc['file_path'];
                    $typeLabel = $doc_types[$doc['document_type']] ?? ucwords(str_replace('_', ' ', $doc['document_type']));
                    $size     = $doc['file_size'] ? round($doc['file_size'] / 1024, 1) . ' KB' : '';
                ?>
                <div class="list-group-item d-flex align-items-start gap-3 py-3">
                    <div class="fs-3 lh-1 mt-1"><i class="bi <?= $icon ?>"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold small"><?= e($typeLabel) ?></div>
                        <div class="text-muted small text-truncate"><?= e($doc['original_name']) ?></div>
                        <?php if ($doc['notes']): ?>
                        <div class="text-muted small fst-italic"><?= e($doc['notes']) ?></div>
                        <?php endif; ?>
                        <div class="text-muted small mt-1">
                            <?= $size ? $size . ' · ' : '' ?>
                            Uploaded <?= fmt_date($doc['created_at']) ?>
                            <?= $doc['uploaded_by_name'] ? ' by ' . e($doc['uploaded_by_name']) : '' ?>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-1 flex-shrink-0">
                        <a href="<?= $fileUrl ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="<?= $fileUrl ?>" download="<?= e($doc['original_name']) ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-download"></i>
                        </a>
                        <form method="POST" onsubmit="return confirm('Delete this document?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-folder2 d-block fs-1 mb-3 opacity-25"></i>
                <p class="mb-1 fw-semibold">No documents uploaded yet</p>
                <small>Upload the tenant's ID, payslip or other KYC documents using the form.</small>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
