<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'accountant');

$api = new ApiClient();

$dateFrom = str_param('date_from') ?: date('Y-m-01');
$dateTo   = str_param('date_to')   ?: date('Y-m-d');
$propId   = int_param('property_id');
$tab      = get_param('tab') ?: 'summary';

$props = $api->get('properties?per_page=100')['data'] ?? [];

// Load reconciliation report
$qs      = "?date_from=$dateFrom&date_to=$dateTo" . ($propId ? "&property_id=$propId" : '');
$repRes  = $api->get("reconciliation$qs");
$report  = $repRes['data'] ?? null;

// Load statement entries (tab=entries)
$entries    = [];
$entryMeta  = [];
$entryPage  = max(1, int_param('epage'));
if ($tab === 'unmatched_bank') {
    $er        = $api->get("reconciliation/entries?date_from=$dateFrom&date_to=$dateTo&match_status=unmatched&page=$entryPage");
    $entries   = $er['data'] ?? [];
    $entryMeta = $er['meta'] ?? [];
}
if ($tab === 'matched') {
    $er        = $api->get("reconciliation/entries?date_from=$dateFrom&date_to=$dateTo&match_status=matched&page=$entryPage");
    $entries   = $er['data'] ?? [];
    $entryMeta = $er['meta'] ?? [];
}

// Load unmatched RUMS payments
$unmatchedRums = [];
if ($tab === 'unmatched_rums') {
    $ur            = $api->get("reconciliation/unmatched-rums?date_from=$dateFrom&date_to=$dateTo");
    $unmatchedRums = $ur['data'] ?? [];
}

// Load batches
$batches = [];
if ($tab === 'batches') {
    $br      = $api->get('reconciliation/batches');
    $batches = $br['data'] ?? [];
}

$page_title = 'Bank Reconciliation';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/payments/index" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
        <h5 class="fw-bold mb-0"><i class="bi bi-bank me-2 text-primary"></i>Bank Reconciliation</h5>
    </div>
    <button class="btn btn-sm btn-outline-primary d-print-none" data-bs-toggle="modal" data-bs-target="#importModal">
        <i class="bi bi-upload me-1"></i>Import Statement
    </button>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-3 d-print-none">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <div class="col-md-3">
                <label class="form-label fw-semibold small mb-1">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small mb-1">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small mb-1">Property</label>
                <select name="property_id" class="form-select form-select-sm">
                    <option value="">All Properties</option>
                    <?php foreach ($props as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $propId==$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto d-flex gap-1">
                <button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
                <button type="button" class="btn btn-sm btn-outline-warning" onclick="runAutoMatch()">
                    <i class="bi bi-magic me-1"></i>Auto-Match
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($report): ?>
<!-- Summary KPIs -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="text-muted small">Bank Credits (Statement)</div>
            <div class="fs-5 fw-bold"><?= money($report['bank_total_credits']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="text-muted small">RUMS Bank Payments</div>
            <div class="fs-5 fw-bold"><?= money($report['rums_total']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <?php $diff = $report['difference']; ?>
        <div class="card border-0 shadow-sm text-center py-3" style="background:<?= abs($diff) < 0.01 ? '#d1fae5' : '#fee2e2' ?>">
            <div class="text-muted small">Difference</div>
            <div class="fs-5 fw-bold <?= abs($diff) < 0.01 ? 'text-success' : 'text-danger' ?>"><?= money(abs($diff)) ?></div>
            <small class="text-muted"><?= abs($diff) < 0.01 ? 'balanced' : ($diff > 0 ? 'bank > RUMS' : 'RUMS > bank') ?></small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="text-muted small">Matched Entries</div>
            <div class="fs-5 fw-bold text-success"><?= number_format($report['matched_count']) ?></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Tab nav -->
<ul class="nav nav-tabs mb-0 d-print-none">
    <?php
    $tabs = [
        'summary'        => 'Summary',
        'unmatched_bank' => 'Unmatched Bank (' . ($report['unmatched_bank_count'] ?? 0) . ')',
        'unmatched_rums' => 'Unmatched RUMS (' . ($report['unmatched_rums_count'] ?? 0) . ')',
        'matched'        => 'Matched',
        'batches'        => 'Import Batches',
    ];
    foreach ($tabs as $key => $label):
    ?>
    <li class="nav-item">
        <a class="nav-link <?= $tab===$key?'active':'' ?>" href="?tab=<?= $key ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&property_id=<?= $propId ?>">
            <?= $label ?>
            <?php if ($key==='unmatched_bank' && ($report['unmatched_bank_count'] ?? 0) > 0): ?><span class="badge bg-danger ms-1"><?= $report['unmatched_bank_count'] ?></span><?php endif; ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<div class="card shadow-sm" style="border-radius:0 0 8px 8px">
<div class="card-body p-0">

<?php if ($tab === 'summary' && $report): ?>
<div class="p-3">
    <h6 class="fw-semibold mb-3">Period: <?= fmt_date($dateFrom) ?> — <?= fmt_date($dateTo) ?></h6>
    <table class="table table-sm" style="max-width:480px">
        <tbody>
            <tr><td class="text-muted">Bank Statement Credits</td><td class="text-end fw-semibold"><?= money($report['bank_total_credits']) ?></td></tr>
            <tr><td class="text-muted">Bank Statement Debits</td><td class="text-end text-danger">(<?= money($report['bank_total_debits']) ?>)</td></tr>
            <tr class="border-top"><td class="text-muted">RUMS Bank Payments Recorded</td><td class="text-end fw-semibold"><?= money($report['rums_total']) ?></td></tr>
            <tr class="border-top fw-bold <?= abs($report['difference']) < 0.01 ? 'text-success' : 'text-danger' ?>">
                <td>Difference</td><td class="text-end"><?= money($report['difference']) ?></td>
            </tr>
        </tbody>
    </table>
    <div class="row g-3 mt-2">
        <div class="col-sm-6">
            <div class="alert alert-warning small py-2 mb-0">
                <strong><?= $report['unmatched_bank_count'] ?></strong> unmatched bank entries
                totalling <strong><?= money($report['unmatched_bank_amount']) ?></strong>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="alert alert-info small py-2 mb-0">
                <strong><?= $report['unmatched_rums_count'] ?></strong> unmatched RUMS payments
                totalling <strong><?= money($report['unmatched_rums_amount']) ?></strong>
            </div>
        </div>
    </div>
</div>

<?php elseif ($tab === 'unmatched_bank'): ?>
<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
            <tr><th>Date</th><th>Description</th><th>Reference</th><th class="text-end">Credit</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php if ($entries): foreach ($entries as $e): ?>
        <tr id="entry-<?= $e['id'] ?>">
            <td class="small"><?= fmt_date($e['statement_date']) ?></td>
            <td class="small"><?= e($e['description'] ?? '—') ?></td>
            <td class="small text-muted"><?= e($e['reference'] ?? '—') ?></td>
            <td class="text-end fw-semibold text-success"><?= money($e['credit']) ?></td>
            <td>
                <button class="btn btn-sm btn-outline-primary py-0 px-1"
                        onclick="showMatchModal(<?= $e['id'] ?>, <?= $e['credit'] ?>)"
                        title="Match to payment">
                    <i class="bi bi-link-45deg"></i> Match
                </button>
                <a href="<?= BASE_URL ?>/payments/add?amount=<?= $e['credit'] ?>"
                   class="btn btn-sm btn-outline-success py-0 px-1" title="Create payment">
                    <i class="bi bi-plus"></i>
                </a>
            </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="5" class="text-center text-muted py-4">No unmatched bank entries in this period.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php elseif ($tab === 'unmatched_rums'): ?>
<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
            <tr><th>Ref</th><th>Tenant</th><th>Unit</th><th>Method</th><th class="text-end">Amount</th><th>Date</th><th>Notes</th><th></th></tr>
        </thead>
        <tbody>
        <?php if ($unmatchedRums): foreach ($unmatchedRums as $p): ?>
        <tr>
            <td><code class="small"><?= e($p['payment_ref']) ?></code></td>
            <td class="small"><?= e($p['tenant_name'] ?? '—') ?></td>
            <td class="small"><?= e($p['unit_number'] ?? '—') ?></td>
            <td class="small"><?= ucfirst(str_replace('_',' ',$p['payment_method'] ?? '')) ?></td>
            <td class="text-end fw-semibold"><?= money($p['amount']) ?></td>
            <td class="small"><?= fmt_date($p['payment_date']) ?></td>
            <td class="small text-muted"><?= e($p['notes'] ?? '') ?></td>
            <td><a href="<?= BASE_URL ?>/payments/view?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-1"><i class="bi bi-eye"></i></a></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="8" class="text-center text-muted py-4">No unmatched RUMS bank payments in this period.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php elseif ($tab === 'matched'): ?>
<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
            <tr><th>Statement Date</th><th>Description</th><th>Reference</th><th class="text-end">Credit</th><th>Matched Payment</th><th>Matched At</th><th></th></tr>
        </thead>
        <tbody>
        <?php if ($entries): foreach ($entries as $e): ?>
        <tr>
            <td class="small"><?= fmt_date($e['statement_date']) ?></td>
            <td class="small"><?= e($e['description'] ?? '—') ?></td>
            <td class="small text-muted"><?= e($e['reference'] ?? '—') ?></td>
            <td class="text-end text-success fw-semibold"><?= money($e['credit']) ?></td>
            <td>
                <?php if (!empty($e['payment_ref'])): ?>
                <a href="<?= BASE_URL ?>/payments/view?id=<?= $e['payment_id'] ?>" class="small text-decoration-none">
                    <code><?= e($e['payment_ref']) ?></code>
                </a>
                <?php if (!empty($e['tenant_name'])): ?>
                <small class="text-muted d-block"><?= e($e['tenant_name']) ?> / <?= e($e['unit_number'] ?? '') ?></small>
                <?php endif; ?>
                <?php endif; ?>
            </td>
            <td class="small text-muted"><?= fmt_date($e['matched_at'] ?? '', 'd M H:i') ?></td>
            <td>
                <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="unmatch(<?= $e['id'] ?>)" title="Remove match">
                    <i class="bi bi-unlink"></i>
                </button>
            </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No matched entries in this period.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php elseif ($tab === 'batches'): ?>
<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
            <tr><th>Batch</th><th>Entries</th><th class="text-end">Credits</th><th class="text-end">Debits</th><th>Date Range</th><th>Matched</th><th>Imported By</th><th>At</th></tr>
        </thead>
        <tbody>
        <?php if ($batches): foreach ($batches as $b): ?>
        <tr>
            <td><code class="small"><?= e($b['import_batch']) ?></code></td>
            <td><?= number_format($b['entries']) ?></td>
            <td class="text-end text-success"><?= money($b['total_credit']) ?></td>
            <td class="text-end text-danger"><?= money($b['total_debit']) ?></td>
            <td class="small text-muted"><?= fmt_date($b['from_date']) ?> – <?= fmt_date($b['to_date']) ?></td>
            <td>
                <span class="badge <?= $b['matched_count'] >= $b['entries'] ? 'bg-success' : 'bg-warning text-dark' ?>">
                    <?= $b['matched_count'] ?>/<?= $b['entries'] ?>
                </span>
            </td>
            <td class="small"><?= e($b['imported_by_name'] ?? '—') ?></td>
            <td class="small text-muted"><?= fmt_date($b['imported_at'] ?? '', 'd M H:i') ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="8" class="text-center text-muted py-4">No import batches yet. <a href="#" data-bs-toggle="modal" data-bs-target="#importModal">Import a statement</a>.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>
</div><!-- .card-body -->
</div><!-- .card -->

<!-- ── Import Modal ─────────────────────────────────────────────── -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-upload me-2 text-primary"></i>Import Bank Statement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small">
                    Paste or upload a CSV with columns: <code>date, description, debit, credit, balance, reference</code>.
                    The first row is treated as headers and skipped. Dates can be in YYYY-MM-DD or DD/MM/YYYY format.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Upload CSV File</label>
                    <input type="file" id="csvFile" class="form-control" accept=".csv,.txt">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Or Paste CSV Content</label>
                    <textarea id="csvPaste" class="form-control font-monospace small" rows="8"
                              placeholder="Date,Description,Debit,Credit,Balance,Reference&#10;2025-01-05,Rent payment,,45000.00,125000.00,REF001"></textarea>
                </div>
                <div id="importPreview" class="d-none">
                    <div class="alert alert-success small" id="importPreviewMsg"></div>
                </div>
                <div id="importAlert" class="d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="importStatement()">
                    <i class="bi bi-upload me-1"></i>Import
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Match Modal ──────────────────────────────────────────────── -->
<div class="modal fade" id="matchModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Match Entry to Payment</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="matchEntryId">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Payment ID</label>
                    <input type="number" id="matchPaymentId" class="form-control" placeholder="Enter payment ID">
                    <small class="text-muted">Enter the RUMS payment ID to link this bank entry to.</small>
                </div>
                <div id="matchAlert" class="d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="doMatch()">Link Payment</button>
            </div>
        </div>
    </div>
</div>

<script>
const API_TOKEN = '<?= $_SESSION['api_token'] ?? '' ?>';
const API_BASE  = '<?= rtrim(env('APP_URL',''), '/') ?>/api/v1';

function apiCall(method, path, data) {
    return fetch(API_BASE + path, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + API_TOKEN
        },
        body: data ? JSON.stringify(data) : undefined
    }).then(r => r.json());
}

// ── CSV parsing ────────────────────────────────────────────────
function parseCSV(text) {
    const lines = text.trim().split('\n').filter(l => l.trim());
    if (lines.length < 2) return [];
    const headers = lines[0].split(',').map(h => h.trim().toLowerCase().replace(/['"]/g,''));
    return lines.slice(1).map(line => {
        const cols  = line.match(/("(?:[^"]|"")*"|[^,]*),?/g)?.map(c => c.replace(/^"|"$/g,'').replace(/""/g,'"').replace(/,$/, '').trim()) ?? [];
        const row   = {};
        headers.forEach((h, i) => { row[h] = cols[i] ?? ''; });
        return row;
    });
}

// ── File upload reader ─────────────────────────────────────────
document.getElementById('csvFile')?.addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => { document.getElementById('csvPaste').value = e.target.result; };
    reader.readAsText(file);
});

async function importStatement() {
    const text = document.getElementById('csvPaste').value.trim();
    if (!text) { showImportAlert('danger', 'Please upload or paste CSV content.'); return; }

    const rows = parseCSV(text);
    if (!rows.length) { showImportAlert('danger', 'No valid rows parsed from CSV.'); return; }

    const res = await apiCall('POST', '/reconciliation/import', { rows });
    if (res.success) {
        showImportAlert('success', res.message || `${rows.length} rows imported.`);
        setTimeout(() => location.reload(), 1500);
    } else {
        showImportAlert('danger', res.message || 'Import failed.');
    }
}

function showImportAlert(type, msg) {
    const el = document.getElementById('importAlert');
    el.className = 'alert alert-' + type + ' small';
    el.textContent = msg;
    el.classList.remove('d-none');
}

// ── Auto-match ─────────────────────────────────────────────────
async function runAutoMatch() {
    if (!confirm('Run auto-matching for this period? This will match bank entries to RUMS bank payments by amount and date.')) return;
    const res = await apiCall('POST', '/reconciliation/auto-match', {
        date_from: '<?= $dateFrom ?>',
        date_to:   '<?= $dateTo ?>'
    });
    alert(res.message || (res.success ? 'Done.' : 'Failed.'));
    if (res.success) location.reload();
}

// ── Manual match ───────────────────────────────────────────────
function showMatchModal(entryId, credit) {
    document.getElementById('matchEntryId').value = entryId;
    document.getElementById('matchPaymentId').value = '';
    document.getElementById('matchAlert').className = 'd-none';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('matchModal')).show();
}

async function doMatch() {
    const entryId   = document.getElementById('matchEntryId').value;
    const paymentId = document.getElementById('matchPaymentId').value;
    if (!paymentId) { document.getElementById('matchAlert').className='alert alert-danger small'; document.getElementById('matchAlert').textContent='Payment ID required.'; return; }

    const res = await apiCall('PATCH', `/reconciliation/entries/${entryId}/match`, { payment_id: parseInt(paymentId) });
    if (res.success) {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('matchModal')).hide();
        location.reload();
    } else {
        document.getElementById('matchAlert').className='alert alert-danger small';
        document.getElementById('matchAlert').textContent=res.message||'Failed.';
    }
}

// ── Unmatch ────────────────────────────────────────────────────
async function unmatch(entryId) {
    if (!confirm('Remove match from this entry?')) return;
    const res = await apiCall('DELETE', `/reconciliation/entries/${entryId}/match`);
    if (res.success) location.reload();
    else alert(res.message || 'Failed.');
}
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
