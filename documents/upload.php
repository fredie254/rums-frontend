<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$api    = new ApiClient();
$user   = current_user();
$errors = [];
$success = false;

// Fetch entity options (managers/admin)
$properties = is_manager() ? ($api->get('properties', ['per_page' => 100])['data'] ?? []) : [];
$tenants    = is_manager() ? ($api->get('tenants', ['per_page' => 500, 'status' => 'active'])['data'] ?? []) : [];

// Tenant self-upload: pre-fill entity
$selfTenantId = null;
if ($user['role'] === 'tenant') {
    $tenantRes     = $api->get('tenants', ['per_page' => 1]); // will be filtered server-side
    $selfTenantId  = null; // resolved server-side from user_id
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Invalid CSRF token.';
    } elseif (empty($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Please select a file to upload.';
    } else {
        $fields = [
            'title'         => trim($_POST['title']         ?? ''),
            'description'   => trim($_POST['description']   ?? ''),
            'document_type' => trim($_POST['document_type'] ?? 'other'),
            'category'      => trim($_POST['category']      ?? ''),
            'entity_type'   => trim($_POST['entity_type']   ?? 'general'),
            'entity_id'     => trim($_POST['entity_id']     ?? ''),
            'access_level'  => trim($_POST['access_level']  ?? 'internal'),
        ];

        // Remove empty optional fields
        foreach (['description', 'category', 'entity_id'] as $k) {
            if ($fields[$k] === '') unset($fields[$k]);
        }

        $res = $api->upload('documents/upload', $_FILES['file'], $fields);

        if ($res['success'] ?? false) {
            $success = true;
            $newUuid = $res['data']['uuid'] ?? null;
            set_flash('success', 'Document uploaded successfully.');
            redirect($newUuid
                ? BASE_URL . '/documents/view?uuid=' . urlencode($newUuid)
                : BASE_URL . '/documents/index'
            );
        } else {
            $errors[] = $res['message'] ?? 'Upload failed. Please try again.';
        }
    }
}

$page_title = 'Upload Document';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3 gap-2">
    <a href="<?= BASE_URL ?>/documents/index" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h5 class="fw-bold mb-0"><i class="bi bi-upload me-2 text-primary"></i>Upload Document</h5>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold py-2">Document Details</div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <!-- Drag & drop zone -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">File <span class="text-danger">*</span></label>
                        <div id="dropZone" class="border-2 border-dashed rounded p-4 text-center"
                             style="border-style:dashed !important;cursor:pointer;transition:background .2s"
                             onclick="document.getElementById('fileInput').click()"
                             ondragover="event.preventDefault();this.classList.add('bg-primary-subtle')"
                             ondragleave="this.classList.remove('bg-primary-subtle')"
                             ondrop="handleDrop(event)">
                            <i class="bi bi-cloud-upload fs-2 text-primary d-block mb-2"></i>
                            <div class="text-muted small">Drag &amp; drop a file here, or <span class="text-primary">click to browse</span></div>
                            <div class="text-muted" style="font-size:.7rem">PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, WEBP, TXT, CSV — max 10 MB</div>
                            <div id="fileInfo" class="mt-2 fw-semibold text-success d-none"></div>
                        </div>
                        <input type="file" id="fileInput" name="file" class="d-none"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.gif,.txt,.csv"
                               onchange="showFileInfo(this)">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" id="titleInput"
                               value="<?= e($_POST['title'] ?? '') ?>" maxlength="200" placeholder="Document title">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Optional description"><?= e($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Document Type <span class="text-danger">*</span></label>
                            <select name="document_type" class="form-select" id="docTypeSelect" onchange="updateCategoryHints()">
                                <?php foreach (['lease','tenant','property','certificate','financial','maintenance','other'] as $t): ?>
                                <option value="<?= $t ?>" <?= ($_POST['document_type'] ?? 'other') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Category <small class="text-muted">(optional)</small></label>
                            <input type="text" name="category" class="form-control" id="categoryInput"
                                   value="<?= e($_POST['category'] ?? '') ?>" maxlength="100" list="categoryHints" placeholder="e.g. signed_lease, id_copy">
                            <datalist id="categoryHints"></datalist>
                        </div>
                    </div>

                    <?php if (is_manager()): ?>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Entity Type</label>
                            <select name="entity_type" class="form-select form-select-sm" id="entityTypeSelect" onchange="updateEntityIdOptions()">
                                <?php foreach (['general','lease','tenant','property','unit'] as $et): ?>
                                <option value="<?= $et ?>" <?= ($_POST['entity_type'] ?? 'general') === $et ? 'selected' : '' ?>><?= ucfirst($et) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8" id="entityIdCol">
                            <label class="form-label fw-semibold">Linked To</label>
                            <select name="entity_id" class="form-select form-select-sm" id="entityIdSelect">
                                <option value="">— Select —</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Access Level</label>
                        <select name="access_level" class="form-select">
                            <option value="internal" <?= ($_POST['access_level'] ?? 'internal') === 'internal' ? 'selected' : '' ?>>Internal (Staff only)</option>
                            <option value="shared"   <?= ($_POST['access_level'] ?? '') === 'shared'   ? 'selected' : '' ?>>Shared (Tenant can view)</option>
                            <option value="private"  <?= ($_POST['access_level'] ?? '') === 'private'  ? 'selected' : '' ?>>Private (Uploader only)</option>
                        </select>
                    </div>
                    <?php else: ?>
                    <!-- Tenant: entity and access level are forced server-side -->
                    <input type="hidden" name="entity_type" value="tenant">
                    <input type="hidden" name="access_level" value="private">
                    <?php endif; ?>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="bi bi-upload me-1"></i>Upload
                        </button>
                        <a href="<?= BASE_URL ?>/documents/index" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <!-- Upload guidelines -->
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold py-2 small"><i class="bi bi-info-circle me-1"></i>Upload Guidelines</div>
            <div class="card-body small text-muted">
                <ul class="mb-2">
                    <li>Max file size: <strong>10 MB</strong></li>
                    <li>Allowed formats: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, WEBP, TXT, CSV</li>
                    <li>Files are stored securely and are not publicly accessible</li>
                    <li>Access is logged for audit purposes</li>
                </ul>
                <hr class="my-2">
                <strong>Access Levels:</strong>
                <ul class="mb-0">
                    <li><strong>Private</strong> — only you can see it</li>
                    <li><strong>Internal</strong> — all staff, not tenants</li>
                    <li><strong>Shared</strong> — tenants can view/download</li>
                </ul>
            </div>
        </div>

        <!-- Document types guide -->
        <div class="card shadow-sm">
            <div class="card-header fw-semibold py-2 small"><i class="bi bi-tags me-1"></i>Category Suggestions</div>
            <div class="card-body p-2">
                <table class="table table-sm mb-0" style="font-size:.75rem">
                    <thead class="table-light"><tr><th>Type</th><th>Categories</th></tr></thead>
                    <tbody>
                        <tr><td>Lease</td><td class="text-muted">signed_lease, addendum, notice, renewal, termination</td></tr>
                        <tr><td>Tenant</td><td class="text-muted">id_copy, passport, kra_pin, employment_letter, reference_letter</td></tr>
                        <tr><td>Property</td><td class="text-muted">title_deed, survey, insurance, valuation</td></tr>
                        <tr><td>Certificate</td><td class="text-muted">fire_safety, nema, nca, county</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// ── File picker ──────────────────────────────────────────────
function showFileInfo(input) {
    if (!input.files.length) return;
    const f   = input.files[0];
    const mb  = (f.size / 1024 / 1024).toFixed(2);
    const info = document.getElementById('fileInfo');
    info.textContent = f.name + ' (' + mb + ' MB)';
    info.classList.remove('d-none');
    // Auto-populate title if empty
    const titleInput = document.getElementById('titleInput');
    if (!titleInput.value.trim()) {
        titleInput.value = f.name.replace(/\.[^.]+$/, '').replace(/[-_]/g, ' ');
    }
}

function handleDrop(event) {
    event.preventDefault();
    document.getElementById('dropZone').classList.remove('bg-primary-subtle');
    const files = event.dataTransfer.files;
    if (!files.length) return;
    const input = document.getElementById('fileInput');
    const dt = new DataTransfer();
    dt.items.add(files[0]);
    input.files = dt.files;
    showFileInfo(input);
}

// ── Category hints ───────────────────────────────────────────
const CATEGORIES = {
    lease:       ['signed_lease','addendum','notice','renewal','termination','other'],
    tenant:      ['id_copy','passport','kra_pin','employment_letter','reference_letter','photo','other'],
    property:    ['title_deed','survey','insurance','valuation','floor_plan','other'],
    certificate: ['fire_safety','nema','nca','county','water_quality','other'],
    financial:   ['invoice','receipt','statement','tax','other'],
    maintenance: ['quote','photo','report','completion_certificate','other'],
    other:       [],
};
function updateCategoryHints() {
    const type  = document.getElementById('docTypeSelect').value;
    const cats  = CATEGORIES[type] || [];
    const dl    = document.getElementById('categoryHints');
    dl.innerHTML = cats.map(c => `<option value="${c}">`).join('');
}
updateCategoryHints();

// ── Entity ID loader ──────────────────────────────────────────
const TENANTS    = <?= json_encode(array_map(fn($t) => ['id' => $t['id'], 'label' => trim($t['first_name'].' '.$t['last_name']).' ('.$t['unit_number'].')'], $tenants)) ?>;
const PROPERTIES = <?= json_encode(array_map(fn($p) => ['id' => $p['id'], 'label' => $p['name']], $properties)) ?>;

function updateEntityIdOptions() {
    const et  = document.getElementById('entityTypeSelect')?.value;
    const sel = document.getElementById('entityIdSelect');
    const col = document.getElementById('entityIdCol');
    if (!sel) return;

    sel.innerHTML = '<option value="">— Select —</option>';

    if (et === 'general') { col.classList.add('d-none'); return; }
    col.classList.remove('d-none');

    let items = [];
    if (et === 'tenant')    items = TENANTS;
    if (et === 'property')  items = PROPERTIES;

    if (items.length) {
        items.forEach(item => {
            sel.insertAdjacentHTML('beforeend', `<option value="${item.id}">${item.label}</option>`);
        });
    } else if (et !== 'general') {
        sel.insertAdjacentHTML('beforeend', '<option disabled>— enter ID manually —</option>');
    }
}
updateEntityIdOptions();

// Disable submit while uploading
document.querySelector('form').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Uploading…';
});
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
