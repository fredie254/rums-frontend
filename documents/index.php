<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$api    = new ApiClient();
$user   = current_user();

// ── Filters ───────────────────────────────────────────────────
$entityType   = get_param('entity_type');
$docType      = get_param('document_type');
$search       = get_param('search');
$page         = max(1, int_param('page'));

$query = array_filter([
    'entity_type'   => $entityType  ?: null,
    'document_type' => $docType     ?: null,
    'search'        => $search      ?: null,
    'page'          => $page,
    'per_page'      => ROWS_PER_PAGE,
], fn($v) => $v !== null && $v !== '');

$res   = $api->get('documents', $query);
$docs  = $res['data'] ?? [];
$meta  = $res['meta'] ?? [];
$total = $meta['total'] ?? 0;
$pg    = ['total' => $total, 'per_page' => $meta['per_page'] ?? ROWS_PER_PAGE, 'page' => $meta['current_page'] ?? 1, 'total_pages' => $meta['total_pages'] ?? 1, 'offset' => (($meta['current_page'] ?? 1) - 1) * ($meta['per_page'] ?? ROWS_PER_PAGE)];

// Stats (managers only)
$stats = [];
if (is_manager()) {
    $stats = $api->get('documents/stats')['data'] ?? [];
}

$baseQ = http_build_query(array_filter(['entity_type' => $entityType, 'document_type' => $docType, 'search' => $search], fn($v) => $v !== null && $v !== ''));

$page_title = 'Document Repository';
include BASE_PATH . '/includes/header.php';

// Document type icon map
$typeIcons = [
    'lease'       => ['bi-file-earmark-text',  'primary'],
    'tenant'      => ['bi-person-vcard',        'info'],
    'property'    => ['bi-building',            'success'],
    'certificate' => ['bi-patch-check',         'warning'],
    'financial'   => ['bi-receipt',             'teal'],
    'maintenance' => ['bi-wrench',              'orange'],
    'other'       => ['bi-file-earmark',        'secondary'],
];

// MIME icon map
function mime_icon(string $mime): string {
    if (str_contains($mime, 'pdf'))   return 'bi-file-earmark-pdf text-danger';
    if (str_contains($mime, 'word'))  return 'bi-file-earmark-word text-primary';
    if (str_contains($mime, 'excel') || str_contains($mime, 'spreadsheet')) return 'bi-file-earmark-excel text-success';
    if (str_contains($mime, 'image')) return 'bi-file-earmark-image text-info';
    return 'bi-file-earmark text-secondary';
}
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="fw-bold mb-0"><i class="bi bi-folder2-open me-2 text-primary"></i>Document Repository</h5>
    <?php if (is_manager()): ?>
    <a href="<?= BASE_URL ?>/documents/upload" class="btn btn-sm btn-primary">
        <i class="bi bi-upload me-1"></i>Upload Document
    </a>
    <?php endif; ?>
</div>

<?= flash_html() ?>

<!-- Stats (managers/admins) -->
<?php if ($stats): ?>
<div class="row row-cols-2 row-cols-md-4 g-2 mb-3">
    <div class="col">
        <div class="card text-center py-2 border-0 shadow-sm">
            <div class="fs-4 fw-bold text-primary"><?= number_format((int)($stats['active'] ?? 0)) ?></div>
            <div class="small text-muted">Total Documents</div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center py-2 border-0 shadow-sm">
            <div class="fs-4 fw-bold text-success"><?= number_format((int)($stats['lease_docs'] ?? 0)) ?></div>
            <div class="small text-muted">Lease Docs</div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center py-2 border-0 shadow-sm">
            <div class="fs-4 fw-bold text-info"><?= number_format((int)($stats['tenant_docs'] ?? 0)) ?></div>
            <div class="small text-muted">Tenant Docs</div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center py-2 border-0 shadow-sm">
            <div class="fs-4 fw-bold text-warning"><?= round((float)($stats['total_size_mb'] ?? 0), 1) ?> MB</div>
            <div class="small text-muted">Storage Used</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" value="<?= e($search) ?>" placeholder="Title, filename…">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Document Type</label>
                <select name="document_type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <?php foreach (array_keys($typeIcons) as $t): ?>
                    <option value="<?= $t ?>" <?= $docType === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Entity</label>
                <select name="entity_type" class="form-select form-select-sm">
                    <option value="">All Entities</option>
                    <?php foreach (['lease', 'tenant', 'property', 'unit', 'general'] as $e): ?>
                    <option value="<?= $e ?>" <?= $entityType === $e ? 'selected' : '' ?>><?= ucfirst($e) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filter</button>
                <a href="?" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Document Grid -->
<?php if ($docs): ?>
<div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3 mb-3">
    <?php foreach ($docs as $doc):
        [$icon, $color] = $typeIcons[$doc['document_type']] ?? ['bi-file-earmark', 'secondary'];
        $sizeKb = round($doc['file_size'] / 1024, 1);
        $sizeFmt = $sizeKb > 1024 ? round($sizeKb / 1024, 1) . ' MB' : $sizeKb . ' KB';
        $downloadUrl = rtrim(env('APP_URL', BASE_URL), '/') . '/api/v1/documents/' . urlencode($doc['uuid']) . '/download?api_token=' . urlencode($_SESSION['api_token'] ?? '');
    ?>
    <div class="col">
        <div class="card shadow-sm h-100 doc-card" style="border-left: 3px solid var(--bs-<?= $color ?>)">
            <div class="card-body pb-2">
                <div class="d-flex align-items-start gap-2 mb-2">
                    <i class="bi <?= mime_icon($doc['mime_type']) ?> fs-3 flex-shrink-0"></i>
                    <div class="flex-grow-1 min-w-0">
                        <a href="<?= BASE_URL ?>/documents/view?uuid=<?= urlencode($doc['uuid']) ?>"
                           class="fw-semibold text-body text-decoration-none d-block text-truncate" title="<?= e($doc['title']) ?>">
                            <?= e($doc['title']) ?>
                        </a>
                        <div class="text-muted" style="font-size:.75rem"><?= e($doc['file_name']) ?></div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-1 mb-2">
                    <span class="badge bg-<?= $color ?> bg-opacity-75"><?= ucfirst($doc['document_type']) ?></span>
                    <?php if ($doc['category']): ?>
                    <span class="badge bg-light text-dark border"><?= e($doc['category']) ?></span>
                    <?php endif; ?>
                    <?php if ($doc['version'] > 1): ?>
                    <span class="badge bg-secondary">v<?= $doc['version'] ?></span>
                    <?php endif; ?>
                    <span class="badge bg-<?= $doc['access_level'] === 'private' ? 'danger' : ($doc['access_level'] === 'shared' ? 'success' : 'secondary') ?>">
                        <?= ucfirst($doc['access_level']) ?>
                    </span>
                </div>
                <div class="text-muted d-flex justify-content-between" style="font-size:.72rem">
                    <span><i class="bi bi-hdd me-1"></i><?= $sizeFmt ?></span>
                    <span><i class="bi bi-calendar3 me-1"></i><?= fmt_date($doc['created_at'], 'd M Y') ?></span>
                </div>
            </div>
            <div class="card-footer py-1 bg-transparent d-flex justify-content-between align-items-center">
                <small class="text-muted"><?= e($doc['uploaded_by_name'] ?? '—') ?></small>
                <div class="d-flex gap-1">
                    <a href="<?= BASE_URL ?>/documents/view?uuid=<?= urlencode($doc['uuid']) ?>"
                       class="btn btn-xs btn-sm btn-outline-primary py-0 px-1" title="View">
                        <i class="bi bi-eye"></i>
                    </a>
                    <a href="<?= e($downloadUrl) ?>"
                       class="btn btn-xs btn-sm btn-outline-success py-0 px-1" title="Download" download>
                        <i class="bi bi-download"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card shadow-sm">
    <div class="text-center text-muted py-5">
        <i class="bi bi-folder-x fs-2 d-block mb-2"></i>
        No documents found.
        <?php if (is_manager()): ?>
        <div class="mt-2">
            <a href="<?= BASE_URL ?>/documents/upload" class="btn btn-sm btn-primary">Upload First Document</a>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($total > $pg['per_page']): ?>
<div class="d-flex justify-content-end">
    <?= pagination_links($pg, BASE_URL . '/documents/index?' . $baseQ) ?>
</div>
<?php endif; ?>

<style>
.doc-card:hover { transform: translateY(-1px); transition: transform .15s, box-shadow .15s; box-shadow: 0 .25rem .75rem rgba(0,0,0,.1) !important; }
</style>
<?php include BASE_PATH . '/includes/footer.php'; ?>
