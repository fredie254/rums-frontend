<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api  = new ApiClient();
$page = max(1, int_param('page'));

$res        = $api->get('broadcasts', ['page' => $page, 'per_page' => ROWS_PER_PAGE]);
$broadcasts = $res['data'] ?? [];
$meta       = $res['meta'] ?? [];
$total      = $meta['total'] ?? 0;
$pg         = ['total' => $total, 'per_page' => $meta['per_page'] ?? ROWS_PER_PAGE, 'page' => $meta['current_page'] ?? 1, 'total_pages' => $meta['total_pages'] ?? 1, 'offset' => (($meta['current_page'] ?? 1) - 1) * ($meta['per_page'] ?? ROWS_PER_PAGE)];

$page_title = 'Broadcast Messages';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/notifications/index.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h5 class="fw-bold mb-0"><i class="bi bi-broadcast me-2 text-primary"></i>Broadcast Messages</h5>
    </div>
    <a href="<?= BASE_URL ?>/notifications/broadcasts/add.php" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg me-1"></i>New Broadcast
    </a>
</div>

<?= flash_html() ?>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Title</th>
                    <th>Channel</th>
                    <th>Status</th>
                    <th>Recipients</th>
                    <th>Sent</th>
                    <th>Failed</th>
                    <th>Created</th>
                    <th>By</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($broadcasts): foreach ($broadcasts as $b):
                    $statusMap = ['draft' => 'secondary', 'sending' => 'warning', 'sent' => 'success', 'failed' => 'danger', 'cancelled' => 'dark'];
                    $badge     = $statusMap[$b['status']] ?? 'secondary';
                ?>
                <tr>
                    <td class="fw-semibold small"><?= e($b['title']) ?></td>
                    <td><span class="badge bg-light text-dark border"><?= e(strtoupper($b['channel'])) ?></span></td>
                    <td><span class="badge bg-<?= $badge ?>"><?= ucfirst($b['status']) ?></span></td>
                    <td class="small text-center"><?= $b['total_recipients'] ?></td>
                    <td class="small text-center text-success"><?= $b['sent_count'] ?></td>
                    <td class="small text-center <?= $b['failed_count'] > 0 ? 'text-danger' : '' ?>"><?= $b['failed_count'] ?></td>
                    <td class="small text-muted"><?= fmt_date($b['created_at'], 'd M y') ?></td>
                    <td class="small"><?= e($b['created_by_name'] ?? '—') ?></td>
                    <td class="text-end">
                        <?php if ($b['status'] === 'draft'): ?>
                        <button class="btn btn-xs btn-sm btn-success py-0 px-2" onclick="sendBroadcast(<?= $b['id'] ?>, this)">
                            <i class="bi bi-send me-1"></i>Send
                        </button>
                        <button class="btn btn-xs btn-sm btn-outline-danger py-0 px-2" onclick="cancelBroadcast(<?= $b['id'] ?>, this)">
                            <i class="bi bi-x"></i>
                        </button>
                        <?php elseif ($b['status'] === 'sent'): ?>
                        <a href="<?= BASE_URL ?>/notifications/logs.php?<?= http_build_query(['date_from' => date('Y-m-d', strtotime($b['created_at'])), 'date_to' => date('Y-m-d', strtotime($b['completed_at'] ?? $b['created_at']))]) ?>" class="btn btn-xs btn-sm btn-outline-secondary py-0 px-2">
                            <i class="bi bi-journal me-1"></i>Logs
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="9" class="text-center text-muted py-5">
                    <i class="bi bi-broadcast fs-2 d-block mb-2"></i>No broadcasts yet.
                    <a href="<?= BASE_URL ?>/notifications/broadcasts/add.php" class="btn btn-sm btn-primary mt-2">Create First Broadcast</a>
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total > $pg['per_page']): ?>
    <div class="card-footer d-flex justify-content-end">
        <?= pagination_links($pg, BASE_URL . '/notifications/broadcasts.php') ?>
    </div>
    <?php endif; ?>
</div>

<!-- Result toast area -->
<div id="toastArea" class="position-fixed bottom-0 end-0 p-3" style="z-index:9999"></div>

<script>
const API_BASE = '<?= rtrim(env('APP_URL', BASE_URL), '/') ?>/api/v1';
const TOKEN    = '<?= e($_SESSION['api_token'] ?? '') ?>';

async function sendBroadcast(id, btn) {
    if (!confirm('Send this broadcast to all recipients now?')) return;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    try {
        const r = await fetch(API_BASE + '/broadcasts/' + id + '/send', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + TOKEN, 'Content-Type': 'application/json' },
        });
        const res = await r.json();
        if (res.success) {
            showToast('success', `Sent: ${res.data?.sent ?? 0} / ${res.data?.total ?? 0}`);
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('danger', res.message || 'Send failed.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send me-1"></i>Send';
        }
    } catch (e) {
        showToast('danger', e.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-1"></i>Send';
    }
}

async function cancelBroadcast(id, btn) {
    if (!confirm('Cancel this broadcast?')) return;
    btn.disabled = true;
    const r = await fetch(API_BASE + '/broadcasts/' + id + '/cancel', {
        method: 'PATCH',
        headers: { 'Authorization': 'Bearer ' + TOKEN },
    });
    const res = await r.json();
    if (res.success) { showToast('success', 'Cancelled.'); setTimeout(() => location.reload(), 1000); }
    else { showToast('danger', res.message || 'Failed.'); btn.disabled = false; }
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
