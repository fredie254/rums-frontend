<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'security');

$api = new ApiClient();
$page_title = 'Visitor Log';
$me  = current_user();

/* ══ Handle POST actions ══════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');

    /* ── Check In ── */
    if ($action === 'checkin') {
        $check_in_val = post('check_in') ?: date('Y-m-d H:i:s');
        $check_in_dt  = date('Y-m-d H:i:s', strtotime($check_in_val));
        $visitor_name = post('visitor_name');

        $body = [
            'property_id'     => int_param('property_id', 0, 'post') ?: null,
            'visitor_name'    => $visitor_name,
            'visitor_phone'   => post('visitor_phone') ?: null,
            'visitor_id_no'   => post('visitor_id_no') ?: null,
            'visitor_id_type' => post('visitor_id_type') ?: 'national_id',
            'vehicle_reg'     => post('vehicle_reg') ?: null,
            'host_name'       => post('host_name') ?: null,
            'purpose'         => post('purpose'),
            'badge_no'        => post('badge_no') ?: null,
            'check_in'        => $check_in_dt,
            'notes'           => post('notes') ?: null,
        ];

        $res    = $api->post('visitors', $body);
        $new_id = (int)($res['data']['id'] ?? 0);
        if (!empty($res['success'])) {
            audit_log('CREATE', 'visitors', $new_id, 'Visitor checked in: ' . $visitor_name);
            set_flash('success', 'Visitor ' . $visitor_name . ' checked in successfully.');
        } else {
            set_flash('error', $res['message'] ?? 'Failed to check in visitor.');
        }
        redirect(BASE_URL . '/security/visitors');
    }

    /* ── Check Out ── */
    if ($action === 'checkout') {
        $id  = int_param('id', 0, 'post');
        $res = $api->patch("visitors/$id/checkout", []);
        if (!empty($res['success'])) {
            audit_log('UPDATE', 'visitors', $id, 'Visitor checked out');
            set_flash('success', 'Visitor checked out.');
        } else {
            set_flash('error', $res['message'] ?? 'Failed to check out visitor.');
        }
        redirect(BASE_URL . '/security/visitors');
    }

    /* ── Mark overstay ── */
    if ($action === 'overstay') {
        $id  = int_param('id', 0, 'post');
        $res = $api->patch("visitors/$id/overstay", []);
        set_flash(!empty($res['success']) ? 'warning' : 'error',
            $res['message'] ?? (!empty($res['success']) ? 'Visitor flagged as overstay.' : 'Action failed.'));
        redirect(BASE_URL . '/security/visitors');
    }
}

/* ══ Filters ══════════════════════════════════════════════════════ */
$filter_status   = get_param('status', 'all');
$filter_property = int_param('property_id', 0);
$filter_date     = get_param('date', date('Y-m-d'));
$filter_search   = get_param('search', '');

/* ── Properties dropdown ── */
$prop_res   = $api->get('properties', ['status' => 'active', 'per_page' => 200]);
$properties = $prop_res['data'] ?? [];

/* ── Visitor list ── */
$vis_params = ['per_page' => 200];
if ($filter_date)                    $vis_params['date']        = $filter_date;
if ($filter_property)                $vis_params['property_id'] = $filter_property;
if ($filter_status !== 'all')        $vis_params['status']      = $filter_status;
if ($filter_search !== '')           $vis_params['search']      = $filter_search;

$vis_res  = $api->get('visitors', $vis_params);
$visitors = $vis_res['data'] ?? [];

/* ── Summary counts ── */
$currently_in = count(array_filter($visitors, fn($r) => $r['status'] === 'in'));
$checked_out  = count(array_filter($visitors, fn($r) => $r['status'] === 'out'));
$overstays    = count(array_filter($visitors, fn($r) => $r['status'] === 'overstay'));

include BASE_PATH . '/includes/header.php';
?>

<?= flash_html() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Visitor Log</h5>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#checkinModal">
        <i class="bi bi-person-plus me-1"></i>Log Check-In
    </button>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">Date</label>
                <input type="date" name="date" class="form-control form-control-sm" value="<?= e($filter_date) ?>">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Property</label>
                <select name="property_id" class="form-select form-select-sm">
                    <option value="0">All Properties</option>
                    <?php foreach ($properties as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $filter_property==$p['id']?'selected':''?>><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="all"      <?= $filter_status==='all'     ?'selected':''?>>All</option>
                    <option value="in"       <?= $filter_status==='in'      ?'selected':''?>>Inside</option>
                    <option value="out"      <?= $filter_status==='out'     ?'selected':''?>>Checked Out</option>
                    <option value="overstay" <?= $filter_status==='overstay'?'selected':''?>>Overstay</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Name / Phone / ID" value="<?= e($filter_search) ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm">Filter</button>
                <a href="visitors" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Status Badges -->
<div class="d-flex gap-3 mb-3">
    <span class="badge bg-primary fs-6 px-3 py-2">Inside: <?= $currently_in ?></span>
    <span class="badge bg-success fs-6 px-3 py-2">Out: <?= $checked_out ?></span>
    <?php if ($overstays): ?><span class="badge bg-warning text-dark fs-6 px-3 py-2">Overstay: <?= $overstays ?></span><?php endif; ?>
    <span class="badge bg-secondary fs-6 px-3 py-2">Total: <?= count($visitors) ?></span>
</div>

<!-- Visitor Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Visitor</th>
                        <th>ID</th>
                        <th>Vehicle</th>
                        <th>Property / Unit</th>
                        <th>Purpose</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$visitors): ?>
                    <tr><td colspan="11" class="text-center text-muted py-4">No visitor records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($visitors as $v):
                        $dur_mins = (int)($v['duration_mins'] ?? 0);
                        $d_hrs    = intdiv($dur_mins, 60);
                        $d_mins   = $dur_mins % 60;
                        $dur      = ($d_hrs > 0 ? "{$d_hrs}h " : '') . "{$d_mins}m";
                        $status_badge = ['in'=>'primary','out'=>'success','overstay'=>'warning text-dark'];
                    ?>
                    <tr class="<?= $v['status']==='overstay' ? 'table-warning' : '' ?>">
                        <td class="small text-muted"><?= $v['id'] ?></td>
                        <td>
                            <div class="fw-semibold"><?= e($v['visitor_name']) ?></div>
                            <small class="text-muted"><?= e($v['visitor_phone'] ?? '') ?></small>
                        </td>
                        <td>
                            <div class="small"><?= e($v['visitor_id_no'] ?? '&mdash;') ?></div>
                            <small class="text-muted"><?= ucfirst(str_replace('_',' ',$v['visitor_id_type'] ?? '')) ?></small>
                        </td>
                        <td class="small"><?= e($v['vehicle_reg'] ?? '&mdash;') ?></td>
                        <td>
                            <div><?= e($v['property_name'] ?? '&mdash;') ?></div>
                            <?php if ($v['unit_number']): ?>
                            <small class="text-muted">Unit: <?= e($v['unit_number']) ?></small>
                            <?php elseif ($v['host_name']): ?>
                            <small class="text-muted">Host: <?= e($v['host_name']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= e($v['purpose'] ?? '&mdash;') ?></td>
                        <td class="text-nowrap small"><?= fmt_date($v['check_in'], 'd M Y, H:i') ?></td>
                        <td class="text-nowrap small"><?= $v['check_out'] ? fmt_date($v['check_out'], 'd M Y, H:i') : '&mdash;' ?></td>
                        <td class="small"><?= $dur ?></td>
                        <td><span class="badge bg-<?= $status_badge[$v['status']] ?? 'secondary' ?>"><?= ucfirst($v['status']) ?></span></td>
                        <td>
                            <?php if ($v['status'] === 'in'): ?>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                <button name="action" value="checkout" class="btn btn-xs btn-success me-1" title="Check Out">
                                    <i class="bi bi-box-arrow-right"></i>
                                </button>
                                <button name="action" value="overstay" class="btn btn-xs btn-warning" title="Flag Overstay">
                                    <i class="bi bi-flag"></i>
                                </button>
                            </form>
                            <?php elseif ($v['status'] === 'overstay'): ?>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                <button name="action" value="checkout" class="btn btn-xs btn-success" title="Check Out">
                                    <i class="bi bi-box-arrow-right"></i> Out
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="text-muted small">&mdash;</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Check-In Modal -->
<div class="modal fade" id="checkinModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="checkin">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Visitor Check-In</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Visitor Name <span class="text-danger">*</span></label>
                            <input type="text" name="visitor_name" class="form-control" required autofocus>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="visitor_phone" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ID Type</label>
                            <select name="visitor_id_type" class="form-select">
                                <option value="national_id">National ID</option>
                                <option value="passport">Passport</option>
                                <option value="driving_license">Driving License</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ID Number</label>
                            <input type="text" name="visitor_id_no" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Vehicle Reg.</label>
                            <input type="text" name="vehicle_reg" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Property <span class="text-danger">*</span></label>
                            <select name="property_id" class="form-select" required>
                                <option value="">Select Property</option>
                                <?php foreach ($properties as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Visiting Unit / Host Name</label>
                            <input type="text" name="host_name" class="form-control" placeholder="e.g. A12 or John Doe">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Purpose <span class="text-danger">*</span></label>
                            <input type="text" name="purpose" class="form-control" required placeholder="Personal visit, Delivery, Contractor, etc.">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Badge No.</label>
                            <input type="text" name="badge_no" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Check-In Time</label>
                            <input type="datetime-local" name="check_in" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-1"></i>Check In</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
