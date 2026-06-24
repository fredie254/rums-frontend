<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'security');

$api = new ApiClient();
$page_title = 'Security Incidents';
$me = current_user();

/* ══ POST handler ══════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = post('action');

    if ($action === 'add') {
        $incident_date = post('incident_date') ?: date('Y-m-d H:i:s');
        // Convert datetime-local (YYYY-MM-DDTHH:MM) to full datetime if needed
        $incident_date = date('Y-m-d H:i:s', strtotime($incident_date));

        $body = [
            'property_id'      => int_param('property_id', 0, 'post') ?: null,
            'incident_type'    => post('incident_type'),
            'severity'         => post('severity'),
            'incident_date'    => $incident_date,
            'description'      => post('description'),
            'persons_involved' => post('persons_involved') ?: null,
            'action_taken'     => post('action_taken') ?: null,
            'police_ref'       => post('police_ref') ?: null,
        ];

        $res    = $api->post('security-incidents', $body);
        $new_id = (int)($res['data']['id'] ?? 0);
        audit_log('CREATE', 'security_incidents', $new_id, 'Incident reported: ' . post('incident_type'));
        if (!empty($res['success'])) {
            set_flash('success', 'Incident reported.');
        } else {
            set_flash('error', $res['message'] ?? 'Failed to report incident.');
        }
        redirect(BASE_URL . '/security/incidents');
    }

    if ($action === 'resolve') {
        $id = int_param('id', 0, 'post');
        $res = $api->post("security-incidents/$id/resolve", ['resolution_notes' => post('resolution_notes')]);
        audit_log('UPDATE', 'security_incidents', $id, 'Incident resolved');
        set_flash(!empty($res['success']) ? 'success' : 'error',
                  !empty($res['success']) ? 'Incident marked as resolved.' : ($res['message'] ?? 'Failed to resolve incident.'));
        redirect(BASE_URL . '/security/incidents');
    }

    if ($action === 'update_notes') {
        $id = int_param('id', 0, 'post');
        $api->patch("security-incidents/$id", [
            'action_taken' => post('action_taken'),
            'police_ref'   => post('police_ref') ?: null,
        ]);
        set_flash('success', 'Incident updated.');
        redirect(BASE_URL . '/security/incidents?id=' . $id);
    }
}

/* ══ View single incident ══════════════════════════════════════ */
$view_id  = int_param('id', 0, 'GET');
$incident = null;
if ($view_id) {
    $inc_res  = $api->get("security-incidents/$view_id");
    $incident = $inc_res['data'] ?? null;
}

/* ══ Filters ══════════════════════════════════════════════════ */
$filter_resolved  = get_param('resolved', '0');
$filter_severity  = get_param('severity', '');
$filter_property  = int_param('property_id', 0);
$filter_date_from = get_param('date_from', date('Y-m-01'));
$filter_date_to   = get_param('date_to', date('Y-m-d'));

/* ── Properties dropdown ── */
$prop_res      = $api->get('properties', ['status' => 'active', 'per_page' => 200]);
$properties    = $prop_res['data'] ?? [];
$incident_types= ['theft','vandalism','trespass','noise_complaint','fire','flooding','suspicious_activity','medical','other'];

/* ── Incident list ── */
$list_params = [
    'date_from' => $filter_date_from,
    'date_to'   => $filter_date_to,
    'per_page'  => 200,
];
if ($filter_resolved !== 'all') $list_params['resolved']    = (int)$filter_resolved;
if ($filter_severity)           $list_params['severity']    = $filter_severity;
if ($filter_property)           $list_params['property_id'] = $filter_property;

$inc_list_res = $api->get('security-incidents', $list_params);
$incidents    = $inc_list_res['data'] ?? [];

$sev_colors = ['low'=>'secondary','medium'=>'warning','high'=>'danger','critical'=>'dark'];

include BASE_PATH . '/includes/header.php';
?>

<?= flash_html() ?>

<?php if ($incident): ?>
<!-- ══ Single Incident View ══════════════════════════════════ -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>
        Incident #<?= $incident['id'] ?> &mdash; <?= ucfirst(str_replace('_',' ',$incident['incident_type'])) ?>
    </h5>
    <a href="incidents" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>All Incidents</a>
</div>

<div class="row g-4">
    <div class="col-md-8">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white py-2 d-flex justify-content-between">
                <span class="fw-semibold">Incident Details</span>
                <div>
                    <span class="badge bg-<?= $sev_colors[$incident['severity']] ?>"><?= ucfirst($incident['severity']) ?></span>
                    <span class="badge <?= $incident['resolved'] ? 'bg-success' : 'bg-danger' ?> ms-1"><?= $incident['resolved'] ? 'Resolved' : 'Open' ?></span>
                </div>
            </div>
            <div class="card-body">
                <dl class="row mb-3">
                    <dt class="col-sm-3">Type</dt>
                    <dd class="col-sm-9"><?= ucfirst(str_replace('_',' ',$incident['incident_type'])) ?></dd>
                    <dt class="col-sm-3">Date/Time</dt>
                    <dd class="col-sm-9"><?= fmt_date($incident['incident_date'], 'd M Y, H:i') ?></dd>
                    <dt class="col-sm-3">Property</dt>
                    <dd class="col-sm-9"><?= e($incident['property_name'] ?? '&mdash;') ?><?= !empty($incident['unit_number']) ? ' / Unit '.$incident['unit_number'] : '' ?></dd>
                    <dt class="col-sm-3">Logged By</dt>
                    <dd class="col-sm-9"><?= e($incident['logged_by_name'] ?? '&mdash;') ?> &middot; <?= fmt_date($incident['created_at'], 'd M Y, H:i') ?></dd>
                    <?php if ($incident['police_ref']): ?>
                    <dt class="col-sm-3">Police Ref</dt>
                    <dd class="col-sm-9"><span class="font-monospace"><?= e($incident['police_ref']) ?></span></dd>
                    <?php endif; ?>
                </dl>
                <h6 class="text-muted">Description</h6>
                <p><?= nl2br(e($incident['description'])) ?></p>
                <?php if ($incident['persons_involved']): ?>
                <h6 class="text-muted">Persons Involved</h6>
                <p><?= nl2br(e($incident['persons_involved'])) ?></p>
                <?php endif; ?>
                <?php if ($incident['action_taken']): ?>
                <h6 class="text-muted">Action Taken</h6>
                <p class="mb-0"><?= nl2br(e($incident['action_taken'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Update Form -->
        <?php if (!$incident['resolved']): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white py-2"><span class="fw-semibold">Update Actions</span></div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $incident['id'] ?>">
                    <div class="mb-3">
                        <label class="form-label">Action Taken / Notes</label>
                        <textarea name="action_taken" class="form-control" rows="3"><?= e($incident['action_taken'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Police Reference Number</label>
                        <input type="text" name="police_ref" class="form-control" value="<?= e($incident['police_ref'] ?? '') ?>">
                    </div>
                    <button name="action" value="update_notes" class="btn btn-primary btn-sm">Save Updates</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-md-4">
        <?php if (!$incident['resolved']): ?>
        <div class="card shadow-sm border-success mb-3">
            <div class="card-header bg-success-subtle py-2"><span class="fw-semibold text-success-emphasis">Resolve Incident</span></div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $incident['id'] ?>">
                    <label class="form-label">Resolution Notes</label>
                    <textarea name="resolution_notes" class="form-control mb-3" rows="3" placeholder="Describe how the incident was resolved..."></textarea>
                    <button name="action" value="resolve" class="btn btn-success w-100">
                        <i class="bi bi-check2-circle me-1"></i>Mark as Resolved
                    </button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>Resolved</strong><br>
            <small><?= fmt_date($incident['resolved_at'], 'd M Y, H:i') ?></small>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<!-- ══ Incidents List ═══════════════════════════════════════ -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0"><i class="bi bi-shield-exclamation me-2 text-danger"></i>Security Incidents</h5>
    <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#addIncidentModal">
        <i class="bi bi-plus-lg me-1"></i>Report Incident
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
                <label class="form-label small mb-1">Status</label>
                <select name="resolved" class="form-select form-select-sm">
                    <option value="0"   <?= $filter_resolved==='0'  ?'selected':''?>>Open</option>
                    <option value="1"   <?= $filter_resolved==='1'  ?'selected':''?>>Resolved</option>
                    <option value="all" <?= $filter_resolved==='all'?'selected':''?>>All</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Severity</label>
                <select name="severity" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="critical" <?= $filter_severity==='critical'?'selected':''?>>Critical</option>
                    <option value="high"     <?= $filter_severity==='high'    ?'selected':''?>>High</option>
                    <option value="medium"   <?= $filter_severity==='medium'  ?'selected':''?>>Medium</option>
                    <option value="low"      <?= $filter_severity==='low'     ?'selected':''?>>Low</option>
                </select>
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
                <button class="btn btn-primary btn-sm">Filter</button>
                <a href="incidents" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Date</th><th>Type</th><th>Property</th><th>Severity</th><th>Description</th><th>Police Ref</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php if (!$incidents): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No incidents found.</td></tr>
                <?php else: ?>
                    <?php foreach ($incidents as $inc): ?>
                    <tr class="<?= !$inc['resolved'] && $inc['severity']==='critical' ? 'table-danger' : '' ?>">
                        <td class="text-nowrap small"><?= fmt_date($inc['incident_date'], 'd M Y, H:i') ?></td>
                        <td><?= ucfirst(str_replace('_',' ',$inc['incident_type'])) ?></td>
                        <td><?= e($inc['property_name'] ?? '&mdash;') ?><?= !empty($inc['unit_number']) ? ' / '.$inc['unit_number'] : '' ?></td>
                        <td><span class="badge bg-<?= $sev_colors[$inc['severity']] ?>"><?= ucfirst($inc['severity']) ?></span></td>
                        <td class="text-truncate" style="max-width:200px"><?= e($inc['description']) ?></td>
                        <td class="font-monospace small"><?= e($inc['police_ref'] ?? '&mdash;') ?></td>
                        <td>
                            <?php if ($inc['resolved']): ?>
                            <span class="badge bg-success">Resolved</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Open</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?id=<?= $inc['id'] ?>" class="btn btn-xs btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Incident Modal -->
<div class="modal fade" id="addIncidentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Report Security Incident</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Incident Type <span class="text-danger">*</span></label>
                            <select name="incident_type" class="form-select" required>
                                <?php foreach ($incident_types as $it): ?>
                                <option value="<?= $it ?>"><?= ucfirst(str_replace('_',' ',$it)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Severity <span class="text-danger">*</span></label>
                            <select name="severity" class="form-select" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date/Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="incident_date" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Property</label>
                            <select name="property_id" class="form-select">
                                <option value="">Select Property</option>
                                <?php foreach ($properties as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Police Reference</label>
                            <input type="text" name="police_ref" class="form-control" placeholder="OB number or case ref">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="3" required placeholder="Describe the incident in detail..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Persons Involved</label>
                            <textarea name="persons_involved" class="form-control" rows="2" placeholder="Names, descriptions, plate numbers..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Immediate Action Taken</label>
                            <textarea name="action_taken" class="form-control" rows="2" placeholder="What was done immediately..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-exclamation-triangle me-1"></i>Submit Report</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/footer.php'; ?>
