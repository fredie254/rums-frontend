<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api = new ApiClient();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf()) { set_flash('error', 'Invalid CSRF token.'); redirect(BASE_URL . '/reports/scheduled.php'); }
    $id = int_param('id', 0, $_POST);
    if ($_POST['action'] === 'delete') {
        $api->delete("report-schedules/$id");
        set_flash('success', 'Schedule deleted.');
    }
    redirect(BASE_URL . '/reports/scheduled.php');
}

$res       = $api->get('report-schedules');
$schedules = $res['data'] ?? [];

$page_title = 'Scheduled Reports';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/reports/index.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h5 class="fw-bold mb-0"><i class="bi bi-calendar-check me-2 text-primary"></i>Scheduled Reports</h5>
    </div>
    <a href="<?= BASE_URL ?>/reports/scheduled/add.php" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg me-1"></i>New Schedule
    </a>
</div>

<?= flash_html() ?>

<div class="card shadow-sm">
    <?php if ($schedules): ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Report Type</th>
                    <th>Format</th>
                    <th>Frequency</th>
                    <th>Recipients</th>
                    <th>Status</th>
                    <th>Next Run</th>
                    <th>Last Run</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($schedules as $s):
                    $recips   = json_decode($s['recipients'] ?? '[]', true);
                    $freqMap  = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];
                    $typeMap  = ['financial' => 'Financial', 'occupancy' => 'Occupancy', 'rent_collection' => 'Rent Collection', 'arrears' => 'Arrears', 'tenant_analytics' => 'Tenant Analytics', 'maintenance' => 'Maintenance', 'aging' => 'AR Aging', 'deposits' => 'Deposits', 'dashboard' => 'Dashboard'];
                ?>
                <tr>
                    <td class="fw-semibold small"><?= e($s['name']) ?></td>
                    <td><span class="badge bg-light text-dark border"><?= e($typeMap[$s['report_type']] ?? $s['report_type']) ?></span></td>
                    <td><span class="badge bg-success"><?= strtoupper($s['format']) ?></span></td>
                    <td class="small"><?= e($freqMap[$s['frequency']] ?? $s['frequency']) ?>
                        <?php if ($s['frequency'] === 'monthly'): ?>
                        <span class="text-muted">(day <?= $s['run_day'] ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td class="small">
                        <?php if ($recips): ?>
                        <span title="<?= e(implode(', ', $recips)) ?>"><?= count($recips) ?> recipient<?= count($recips) !== 1 ? 's' : '' ?></span>
                        <?php else: ?><span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-<?= $s['is_active'] ? 'success' : 'secondary' ?>">
                            <?= $s['is_active'] ? 'Active' : 'Paused' ?>
                        </span>
                    </td>
                    <td class="small text-muted"><?= $s['next_run_at'] ? fmt_date($s['next_run_at'], 'd M y H:i') : '—' ?></td>
                    <td class="small text-muted"><?= $s['last_run_at'] ? fmt_date($s['last_run_at'], 'd M y H:i') : 'Never' ?></td>
                    <td class="text-end">
                        <button class="btn btn-xs btn-sm btn-success py-0 px-2" onclick="runNow(<?= $s['id'] ?>, this)"
                                title="Run now & email">
                            <i class="bi bi-play-fill"></i>
                        </button>
                        <a href="<?= BASE_URL ?>/reports/scheduled/add.php?id=<?= $s['id'] ?>"
                           class="btn btn-xs btn-sm btn-outline-primary py-0 px-1" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this schedule?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn btn-xs btn-sm btn-outline-danger py-0 px-1" title="Delete">
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
    <div class="text-center text-muted py-5">
        <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
        No scheduled reports yet.
        <div class="mt-2">
            <a href="<?= BASE_URL ?>/reports/scheduled/add.php" class="btn btn-sm btn-primary">
                Create First Schedule
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<div id="toastArea" class="position-fixed bottom-0 end-0 p-3" style="z-index:9999"></div>

<script>
const API_BASE = '<?= rtrim(env('APP_URL', BASE_URL), '/') ?>/api/v1';
const TOKEN    = '<?= e($_SESSION['api_token'] ?? '') ?>';

async function runNow(id, btn) {
    if (!confirm('Run this report now and email recipients?')) return;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    try {
        const r   = await fetch(API_BASE + '/report-schedules/' + id + '/run', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + TOKEN },
        });
        const res = await r.json();
        if (res.success) {
            const d = res.data || {};
            showToast('success', `Sent to ${d.sent ?? 0}/${d.total ?? 0} recipients.`);
            setTimeout(() => location.reload(), 2000);
        } else {
            showToast('danger', res.message || 'Run failed.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-play-fill"></i>';
        }
    } catch (e) {
        showToast('danger', e.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-play-fill"></i>';
    }
}

function showToast(type, msg) {
    const id = 'toast' + Date.now();
    document.getElementById('toastArea').insertAdjacentHTML('beforeend',
        `<div id="${id}" class="toast align-items-center text-bg-${type} border-0 show mb-2" role="alert">
           <div class="d-flex"><div class="toast-body">${msg}</div>
           <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>
         </div>`);
    setTimeout(() => document.getElementById(id)?.remove(), 5000);
}
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
