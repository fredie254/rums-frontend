<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'security');

$api = new ApiClient();
$page_title = 'Security Dashboard';

$today = date('Y-m-d');
$me    = current_user();

/* ── Today's visitor KPIs ── */
$vis_res    = $api->get('visitors', ['date' => $today, 'per_page' => 200]);
$today_vis  = $vis_res['data'] ?? [];
$vis_stats  = [
    'currently_in'      => count(array_filter($today_vis, fn($v) => $v['status'] === 'in')),
    'today_total'       => count($today_vis),
    'today_checked_out' => count(array_filter($today_vis, fn($v) => $v['status'] === 'out')),
    'overstays'         => count(array_filter($today_vis, fn($v) => $v['status'] === 'overstay')),
    'long_stay'         => count(array_filter($today_vis, fn($v) => $v['status'] === 'in' && ($v['duration_mins'] ?? 0) >= 240)),
];

/* ── Occupancy events today ── */
$occ_res   = $api->get('occupancy-logs', ['date_from' => $today, 'date_to' => $today, 'per_page' => 1]);
$occ_today = (int)($occ_res['meta']['total'] ?? 0);

/* ── Unresolved incidents ── */
$inc_res           = $api->get('security-incidents', ['resolved' => 0, 'per_page' => 200]);
$open_incidents    = (int)($inc_res['meta']['total'] ?? count($inc_res['data'] ?? []));
$critical_incidents = count(array_filter($inc_res['data'] ?? [], fn($i) => $i['severity'] === 'critical'));

/* ── Visitors currently inside ── */
$inside = array_filter($today_vis, fn($v) => $v['status'] === 'in');
// Sort by check_in ASC (API returns DESC, so reverse)
usort($inside, fn($a, $b) => strcmp($a['check_in'], $b['check_in']));

/* ── Today's visitor timeline (first 30, already sorted DESC from API) ── */
$timeline = array_slice($today_vis, 0, 30);

/* ── 7-day visitor trend ── */
$trend = [];
for ($i = 6; $i >= 0; $i--) {
    $day  = date('Y-m-d', strtotime("-$i days"));
    $dr   = $api->get('visitors', ['date' => $day, 'per_page' => 1]);
    $trend[] = ['day' => $day, 'cnt' => (int)($dr['meta']['total'] ?? 0)];
}

/* ── Recent open incidents (up to 5) ── */
$incidents_all = $inc_res['data'] ?? [];
// Sort: critical first, then high, medium, low; then by date DESC
$sev_order = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
usort($incidents_all, function ($a, $b) use ($sev_order) {
    $sa = $sev_order[$a['severity']] ?? 9;
    $sb = $sev_order[$b['severity']] ?? 9;
    if ($sa !== $sb) return $sa - $sb;
    return strcmp($b['incident_date'], $a['incident_date']);
});
$incidents = array_slice($incidents_all, 0, 5);

/* ── Properties for quick check-in ── */
$prop_res   = $api->get('properties', ['status' => 'active', 'per_page' => 200]);
$properties = $prop_res['data'] ?? [];

include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0"><i class="bi bi-shield-lock me-2 text-primary"></i>Security Dashboard</h5>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#quickCheckinModal">
            <i class="bi bi-person-plus me-1"></i>Log Visitor
        </button>
        <a href="<?= BASE_URL ?>/security/incidents?action=add" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-exclamation-triangle me-1"></i>Report Incident
        </a>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <div class="card shadow-sm kpi-blue text-white">
            <div class="card-body py-3 text-center">
                <div class="fs-2 fw-bold"><?= $vis_stats['currently_in'] ?></div>
                <div class="small opacity-75">Inside Now</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card shadow-sm kpi-green text-white">
            <div class="card-body py-3 text-center">
                <div class="fs-2 fw-bold"><?= $vis_stats['today_total'] ?></div>
                <div class="small opacity-75">Visitors Today</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card shadow-sm kpi-teal text-white">
            <div class="card-body py-3 text-center">
                <div class="fs-2 fw-bold"><?= $occ_today ?></div>
                <div class="small opacity-75">Occupancy Events</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card shadow-sm <?= $vis_stats['overstays'] > 0 ? 'kpi-orange' : 'kpi-yellow' ?> text-white">
            <div class="card-body py-3 text-center">
                <div class="fs-2 fw-bold"><?= $vis_stats['overstays'] ?></div>
                <div class="small opacity-75">Overstays</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card shadow-sm <?= $open_incidents > 0 ? 'kpi-red' : 'kpi-green' ?> text-white">
            <div class="card-body py-3 text-center">
                <div class="fs-2 fw-bold"><?= $open_incidents ?></div>
                <div class="small opacity-75">Open Incidents</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card shadow-sm <?= $critical_incidents > 0 ? 'kpi-red' : 'kpi-purple' ?> text-white">
            <div class="card-body py-3 text-center">
                <div class="fs-2 fw-bold"><?= $critical_incidents ?></div>
                <div class="small opacity-75">Critical Alerts</div>
            </div>
        </div>
    </div>
</div>

<?php if ($critical_incidents > 0): ?>
<div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-exclamation-octagon-fill fs-4 me-3"></i>
    <div>
        <strong><?= $critical_incidents ?> CRITICAL incident(s) require immediate attention.</strong>
        <a href="<?= BASE_URL ?>/security/incidents" class="alert-link ms-2">View &rarr;</a>
    </div>
</div>
<?php endif; ?>

<!-- Visitor Trend + Currently Inside -->
<div class="row g-4 mb-4">
    <div class="col-md-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-2"><span class="fw-semibold">Visitor Trend &mdash; 7 Days</span></div>
            <div class="card-body"><canvas id="trendChart" height="160"></canvas></div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Currently Inside <span class="badge bg-primary"><?= count($inside) ?></span></span>
                <a href="<?= BASE_URL ?>/security/visitors?status=in" class="btn btn-xs btn-outline-primary">Full List</a>
            </div>
            <div class="card-body p-0">
                <?php if (!$inside): ?>
                <div class="text-center text-muted py-4"><i class="bi bi-check-circle text-success fs-3 d-block mb-2"></i>No visitors inside</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light"><tr><th>Name</th><th>Unit</th><th>Purpose</th><th>Time In</th><th>Duration</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($inside as $v):
                            $mins_in  = (int)($v['duration_mins'] ?? 0);
                            $hrs      = intdiv($mins_in, 60);
                            $mins     = $mins_in % 60;
                            $duration = ($hrs > 0 ? "{$hrs}h " : '') . "{$mins}m";
                            $warn     = $mins_in >= 240;
                        ?>
                        <tr class="<?= $warn ? 'table-warning' : '' ?>">
                            <td>
                                <div class="fw-semibold"><?= e($v['visitor_name']) ?></div>
                                <small class="text-muted"><?= e($v['visitor_phone'] ?? '') ?></small>
                            </td>
                            <td><?= e($v['property_name'] ?? '&mdash;') ?><?= $v['unit_number'] ? ' / '.$v['unit_number'] : '' ?></td>
                            <td class="small"><?= e($v['purpose'] ?? '&mdash;') ?></td>
                            <td class="small"><?= date('H:i', strtotime($v['check_in'])) ?></td>
                            <td class="small <?= $warn ? 'text-warning fw-bold' : '' ?>"><?= $duration ?><?= $warn ? ' &#9888;' : '' ?></td>
                            <td>
                                <form method="POST" action="<?= BASE_URL ?>/security/visitors">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="checkout">
                                    <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                    <button class="btn btn-xs btn-success">Check Out</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Open Incidents -->
<?php if ($incidents): ?>
<div class="card shadow-sm border-danger mb-4">
    <div class="card-header bg-danger-subtle py-2 d-flex justify-content-between align-items-center">
        <span class="fw-semibold text-danger-emphasis"><i class="bi bi-exclamation-triangle me-1"></i>Open Incidents</span>
        <a href="<?= BASE_URL ?>/security/incidents" class="btn btn-xs btn-outline-danger">View All</a>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Date</th><th>Type</th><th>Property</th><th>Severity</th><th>Description</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($incidents as $inc):
                $sev_colors = ['low'=>'secondary','medium'=>'warning','high'=>'danger','critical'=>'dark'];
                $sc = $sev_colors[$inc['severity']] ?? 'secondary';
            ?>
            <tr>
                <td><?= fmt_date($inc['incident_date'], 'd M Y, H:i') ?></td>
                <td><?= ucfirst(str_replace('_',' ',$inc['incident_type'])) ?></td>
                <td><?= e($inc['property_name'] ?? '&mdash;') ?></td>
                <td><span class="badge bg-<?= $sc ?>"><?= ucfirst($inc['severity']) ?></span></td>
                <td class="text-truncate" style="max-width:180px"><?= e($inc['description']) ?></td>
                <td><a href="<?= BASE_URL ?>/security/incidents?id=<?= $inc['id'] ?>" class="btn btn-xs btn-outline-danger">View</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Today's Visitor Log -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Today's Visitor Log</span>
        <a href="<?= BASE_URL ?>/security/visitors" class="btn btn-sm btn-outline-primary">Full Log</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Name</th><th>ID / Phone</th><th>Unit</th><th>Purpose</th><th>In</th><th>Out</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php if (!$timeline): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No visitors logged today.</td></tr>
                <?php else: ?>
                    <?php foreach ($timeline as $v):
                        $badge = ['in'=>'primary','out'=>'success','overstay'=>'warning'];
                        $bc = $badge[$v['status']] ?? 'secondary';
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= e($v['visitor_name']) ?></td>
                        <td>
                            <div class="small"><?= e($v['visitor_id_no'] ?? '&mdash;') ?></div>
                            <div class="small text-muted"><?= e($v['visitor_phone'] ?? '') ?></div>
                        </td>
                        <td><?= e($v['property_name'] ?? '&mdash;') ?><?= $v['unit_number'] ? ' / '.$v['unit_number'] : '' ?></td>
                        <td><?= e($v['purpose'] ?? '&mdash;') ?></td>
                        <td><?= date('H:i', strtotime($v['check_in'])) ?></td>
                        <td><?= $v['check_out'] ? date('H:i', strtotime($v['check_out'])) : '&mdash;' ?></td>
                        <td><span class="badge bg-<?= $bc ?>"><?= ucfirst($v['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Quick Check-in Modal -->
<div class="modal fade" id="quickCheckinModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= BASE_URL ?>/security/visitors">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="checkin">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Log Visitor Check-In</h5>
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
                            <input type="text" name="vehicle_reg" class="form-control" placeholder="e.g. KCA 123X">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Property <span class="text-danger">*</span></label>
                            <select name="property_id" class="form-select" id="modalProperty" required>
                                <option value="">Select Property</option>
                                <?php foreach ($properties as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Unit / Host</label>
                            <input type="text" name="host_name" class="form-control" placeholder="Unit number or host name">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Purpose of Visit <span class="text-danger">*</span></label>
                            <input type="text" name="purpose" class="form-control" placeholder="e.g. Personal visit, Delivery, Plumber" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Badge No.</label>
                            <input type="text" name="badge_no" class="form-control" placeholder="Optional">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Check-In Time</label>
                            <input type="datetime-local" name="check_in" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div class="text-muted small pt-2">Leave blank to use current time</div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('trendChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($trend, 'day')) ?>,
            datasets: [{
                label: 'Visitors',
                data: <?= json_encode(array_column($trend, 'cnt')) ?>,
                backgroundColor: 'rgba(13,110,253,0.65)',
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
});
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
