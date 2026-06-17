<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'security');

$api = new ApiClient();
$page_title = 'Occupancy Log';
$me  = current_user();

/* ══ POST handler ══════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = post_param('action');

    if ($action === 'add') {
        $body = [
            'property_id'  => int_param('property_id') ?: null,
            'unit_id'      => int_param('unit_id') ?: null,
            'event_type'   => post_param('event_type'),
            'event_date'   => post_param('event_date'),
            'event_time'   => post_param('event_time') ?: null,
            'description'  => post_param('description') ?: null,
            'persons_count'=> (int)post_param('persons_count') ?: 1,
            'authorized_by'=> post_param('authorized_by') ?: null,
            'reference_no' => post_param('reference_no') ?: null,
        ];

        $res    = $api->post('occupancy-logs', $body);
        $new_id = (int)($res['data']['id'] ?? 0);
        audit_log('CREATE', 'occupancy', $new_id, 'Occupancy event: ' . post_param('event_type'));
        set_flash('success', 'Occupancy event logged.');
        redirect(BASE_URL . '/security/occupancy_log.php');
    }
}

/* ══ Filters ══════════════════════════════════════════════════ */
$filter_property   = int_param('property_id', 0);
$filter_event_type = get_param('event_type', '');
$filter_date_from  = get_param('date_from', date('Y-m-01'));
$filter_date_to    = get_param('date_to', date('Y-m-d'));

$event_types = ['move_in','move_out','overnight_guest','unit_inspection','tenant_absence','emergency','other'];

/* ── Properties dropdown ── */
$prop_res   = $api->get('properties', ['status' => 'active', 'per_page' => 200]);
$properties = $prop_res['data'] ?? [];

/* ── Units for selected property (for modal dropdown) ── */
$units = [];
if ($filter_property) {
    $u_res = $api->get('units', ['property_id' => $filter_property, 'per_page' => 200]);
    $units = $u_res['data'] ?? [];
}

/* ── Event log ── */
$log_params = [
    'date_from' => $filter_date_from,
    'date_to'   => $filter_date_to,
    'per_page'  => 200,
];
if ($filter_property)   $log_params['property_id'] = $filter_property;
if ($filter_event_type) $log_params['event_type']  = $filter_event_type;

$log_res = $api->get('occupancy-logs', $log_params);
$logs    = $log_res['data'] ?? [];

/* ── Event type counts for the period ── */
$type_counts = [];
foreach ($logs as $l) {
    $type_counts[$l['event_type']] = ($type_counts[$l['event_type']] ?? 0) + 1;
}

/* ── Unit occupancy snapshot ── */
$snap_res       = $api->get('units', ['per_page' => 100]);
$units_snapshot = $snap_res['data'] ?? [];

$event_icons = [
    'move_in'         => ['icon' => 'box-arrow-in-right', 'color' => 'success'],
    'move_out'        => ['icon' => 'box-arrow-right',    'color' => 'danger'],
    'overnight_guest' => ['icon' => 'moon',               'color' => 'info'],
    'unit_inspection' => ['icon' => 'search',             'color' => 'primary'],
    'tenant_absence'  => ['icon' => 'person-dash',        'color' => 'warning'],
    'emergency'       => ['icon' => 'exclamation-octagon','color' => 'danger'],
    'other'           => ['icon' => 'three-dots',         'color' => 'secondary'],
];

include BASE_PATH . '/includes/header.php';
?>

<?= flash_html() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0"><i class="bi bi-house-check me-2 text-success"></i>Occupancy Log</h5>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEventModal">
        <i class="bi bi-plus-lg me-1"></i>Log Event
    </button>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filter_date_from) ?>">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filter_date_to) ?>">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Property</label>
                <select name="property_id" class="form-select form-select-sm">
                    <option value="0">All</option>
                    <?php foreach ($properties as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $filter_property==$p['id']?'selected':''?>><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Event Type</label>
                <select name="event_type" class="form-select form-select-sm">
                    <option value="">All Events</option>
                    <?php foreach ($event_types as $et): ?>
                    <option value="<?= $et ?>" <?= $filter_event_type===$et?'selected':''?>><?= ucfirst(str_replace('_',' ',$et)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm">Filter</button>
                <a href="occupancy_log.php" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Event Type Summary -->
<div class="row g-2 mb-4">
    <?php foreach ($event_types as $et):
        $cnt = $type_counts[$et] ?? 0;
        if (!$cnt) continue;
        $ei = $event_icons[$et] ?? ['icon'=>'dot','color'=>'secondary'];
    ?>
    <div class="col-auto">
        <div class="card shadow-sm border-<?= $ei['color'] ?>">
            <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
                <i class="bi bi-<?= $ei['icon'] ?> text-<?= $ei['color'] ?>"></i>
                <div>
                    <div class="fw-bold"><?= $cnt ?></div>
                    <div class="small text-muted"><?= ucfirst(str_replace('_',' ',$et)) ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <!-- Event Log -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-2 d-flex justify-content-between">
                <span class="fw-semibold">Event Log <span class="badge bg-secondary"><?= count($logs) ?></span></span>
            </div>
            <div class="card-body p-0">
                <?php if (!$logs): ?>
                <div class="text-center text-muted py-5">No events found for the selected period.</div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($logs as $log):
                        $ei = $event_icons[$log['event_type']] ?? ['icon'=>'dot','color'=>'secondary'];
                    ?>
                    <div class="list-group-item py-2 px-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="mt-1">
                                <span class="badge bg-<?= $ei['color'] ?>-subtle border border-<?= $ei['color'] ?>-subtle p-2">
                                    <i class="bi bi-<?= $ei['icon'] ?> text-<?= $ei['color'] ?>"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-semibold"><?= ucfirst(str_replace('_',' ',$log['event_type'])) ?></span>
                                    <small class="text-muted">
                                        <?= fmt_date($log['event_date']) ?>
                                        <?= $log['event_time'] ? ' at '.date('H:i', strtotime($log['event_time'])) : '' ?>
                                    </small>
                                </div>
                                <div class="small text-muted">
                                    <i class="bi bi-building me-1"></i><?= e($log['property_name'] ?? '&mdash;') ?>
                                    <?= $log['unit_number'] ? ' / Unit '.$log['unit_number'] : '' ?>
                                    <?= !empty($log['tenant_name']) ? ' &middot; '.$log['tenant_name'] : '' ?>
                                </div>
                                <?php if ($log['description']): ?>
                                <div class="small mt-1"><?= e($log['description']) ?></div>
                                <?php endif; ?>
                                <div class="small text-muted mt-1">
                                    <?php if (($log['persons_count'] ?? 1) > 1): ?>
                                    <span class="me-2"><i class="bi bi-people me-1"></i><?= $log['persons_count'] ?> persons</span>
                                    <?php endif; ?>
                                    <?php if ($log['authorized_by']): ?>
                                    <span class="me-2"><i class="bi bi-check-circle me-1"></i>Auth: <?= e($log['authorized_by']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($log['reference_no']): ?>
                                    <span><i class="bi bi-hash me-1"></i><?= e($log['reference_no']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted">
                                    Logged by <?= e($log['logged_by_name'] ?? 'System') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Unit Occupancy Snapshot -->
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-2"><span class="fw-semibold">Unit Snapshot</span></div>
            <div class="card-body p-0" style="max-height:600px;overflow-y:auto">
                <div class="list-group list-group-flush">
                    <?php foreach ($units_snapshot as $u):
                        $sc = ['occupied'=>'success','available'=>'secondary','maintenance'=>'warning'];
                        $bc = $sc[$u['status']] ?? 'secondary';
                    ?>
                    <div class="list-group-item py-2 px-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-semibold"><?= e($u['unit_number']) ?></span>
                                <small class="text-muted ms-1"><?= e($u['property_name'] ?? '') ?></small>
                            </div>
                            <span class="badge bg-<?= $bc ?>"><?= ucfirst($u['status']) ?></span>
                        </div>
                        <?php if (!empty($u['tenant_name'])): ?>
                        <div class="small text-muted">
                            <i class="bi bi-person me-1"></i><?= e($u['tenant_name']) ?>
                            <?= !empty($u['tenant_phone']) ? ' &middot; ' . e($u['tenant_phone']) : '' ?>
                        </div>
                        <?php if (!empty($u['lease_end'])): ?>
                        <div class="small text-muted"><i class="bi bi-calendar-x me-1"></i>Lease ends <?= fmt_date($u['lease_end']) ?></div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Event Modal -->
<div class="modal fade" id="addEventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Log Occupancy Event</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Event Type <span class="text-danger">*</span></label>
                            <select name="event_type" class="form-select" required>
                                <?php foreach ($event_types as $et): ?>
                                <option value="<?= $et ?>"><?= ucfirst(str_replace('_',' ',$et)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" name="event_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Time</label>
                            <input type="time" name="event_time" class="form-control" value="<?= date('H:i') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Property <span class="text-danger">*</span></label>
                            <select name="property_id" id="evtProperty" class="form-select" required onchange="loadUnits(this.value)">
                                <option value="">Select Property</option>
                                <?php foreach ($properties as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Unit</label>
                            <select name="unit_id" id="evtUnit" class="form-select">
                                <option value="">Select Unit</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">No. of Persons</label>
                            <input type="number" name="persons_count" class="form-control" value="1" min="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Authorized By</label>
                            <input type="text" name="authorized_by" class="form-control" placeholder="Name / title">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reference No.</label>
                            <input type="text" name="reference_no" class="form-control" placeholder="Lease / work order #">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description / Notes</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Log Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function loadUnits(propertyId) {
    const sel = document.getElementById('evtUnit');
    sel.innerHTML = '<option value="">Loading...</option>';
    if (!propertyId) { sel.innerHTML = '<option value="">Select Unit</option>'; return; }
    fetch('<?= BASE_URL ?>/api/v1/units?property_id=' + propertyId + '&per_page=200', {
        headers: { 'Accept': 'application/json' }
    })
        .then(r => r.json())
        .then(resp => {
            const data = resp.data || [];
            sel.innerHTML = '<option value="">Select Unit</option>';
            data.forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.id;
                opt.textContent = u.unit_number + (u.tenant_name ? ' \u2014 ' + u.tenant_name : '');
                sel.appendChild(opt);
            });
        })
        .catch(() => { sel.innerHTML = '<option value="">Select Unit</option>'; });
}
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
