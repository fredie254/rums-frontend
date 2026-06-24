<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'accountant');

$api = new ApiClient();

$dateFrom = str_param('date_from') ?: date('Y-m-01');
$dateTo   = str_param('date_to')   ?: date('Y-m-d');
$status   = get_param('status');
$page     = max(1, int_param('page'));

$query = array_filter([
    'date_from'  => $dateFrom,
    'date_to'    => $dateTo,
    'status'     => $status   ?: null,
    'page'       => $page,
    'per_page'   => ROWS_PER_PAGE,
], fn($v) => $v !== null && $v !== '');

$res   = $api->get('mpesa-transactions', $query);
$txns  = $res['data'] ?? [];
$meta  = $res['meta'] ?? [];
$total = $meta['total'] ?? 0;
$pg    = [
    'total'       => $total,
    'per_page'    => $meta['per_page'] ?? ROWS_PER_PAGE,
    'page'        => $meta['current_page'] ?? 1,
    'total_pages' => $meta['total_pages'] ?? 1,
    'offset'      => (($meta['current_page'] ?? 1) - 1) * ($meta['per_page'] ?? ROWS_PER_PAGE),
];

$statusColors = [
    'completed' => 'success',
    'pending'   => 'warning',
    'failed'    => 'danger',
];

$page_title = 'M-Pesa Transactions';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/payments/index" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
        <h5 class="fw-bold mb-0"><i class="bi bi-phone me-2 text-success"></i>M-Pesa Transactions</h5>
    </div>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold small mb-1">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small mb-1">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="completed" <?= $status==='completed'?'selected':'' ?>>Completed</option>
                    <option value="pending"   <?= $status==='pending'  ?'selected':'' ?>>Pending</option>
                    <option value="failed"    <?= $status==='failed'   ?'selected':'' ?>>Failed</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-1">
                <button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>/payments/mpesa_transactions" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Checkout ID</th>
                    <th>Phone</th>
                    <th class="text-end">Amount</th>
                    <th>Account Ref</th>
                    <th>Receipt</th>
                    <th>Linked Payment</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($txns): $sn = $pg['offset'] + 1; foreach ($txns as $tx): ?>
            <tr>
                <td><?= $sn++ ?></td>
                <td class="small"><code><?= e(substr($tx['checkout_request_id'] ?? '', 0, 20)) ?>…</code></td>
                <td class="small"><?= e($tx['msisdn'] ?? $tx['phone'] ?? '—') ?></td>
                <td class="text-end fw-semibold"><?= money($tx['amount'] ?? 0) ?></td>
                <td class="small text-muted"><?= e($tx['account_reference'] ?? '—') ?></td>
                <td><?= !empty($tx['mpesa_receipt']) ? '<code class="small text-success">' . e($tx['mpesa_receipt']) . '</code>' : '<span class="text-muted small">—</span>' ?></td>
                <td>
                    <?php if (!empty($tx['payment_id'])): ?>
                    <a href="<?= BASE_URL ?>/payments/view?id=<?= $tx['payment_id'] ?>" class="small text-decoration-none">
                        <i class="bi bi-receipt me-1"></i>View
                    </a>
                    <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                </td>
                <td class="small"><?= fmt_date($tx['created_at'] ?? '', 'd M H:i') ?></td>
                <td>
                    <?php $sc = $statusColors[$tx['status'] ?? ''] ?? 'secondary'; ?>
                    <span class="badge bg-<?= $sc ?>"><?= ucfirst($tx['status'] ?? '') ?></span>
                </td>
                <td>
                    <?php if (($tx['status'] ?? '') === 'pending' && !empty($tx['checkout_request_id'])): ?>
                    <button class="btn btn-sm btn-outline-info py-0 px-1"
                            onclick="querySTK('<?= e($tx['checkout_request_id']) ?>', this)"
                            title="Query status">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="10" class="text-center text-muted py-4">No M-Pesa transactions found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted"><?= count($txns) ?> of <?= $total ?></small>
        <?= pagination_links($pg, BASE_URL . '/payments/mpesa_transactions?' . http_build_query(['date_from'=>$dateFrom,'date_to'=>$dateTo,'status'=>$status])) ?>
    </div>
</div>

<script>
async function querySTK(checkoutId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    try {
        const res  = await fetch('<?= rtrim(env('APP_URL',''), '/') ?>/api/v1/mpesa/stk-query', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer <?= $_SESSION['api_token'] ?? '' ?>'
            },
            body: JSON.stringify({ checkout_request_id: checkoutId })
        });
        const data = await res.json();
        const code = data.data?.ResultCode ?? data.data?.errorCode;
        if (code === 0 || code === '0') {
            alert('Payment completed successfully!');
            location.reload();
        } else if (code === 1032) {
            alert('Transaction was cancelled by the user.');
        } else {
            alert(data.data?.ResultDesc || data.message || 'Still pending or query failed.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-repeat"></i>';
        }
    } catch(e) {
        alert('Network error.');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-repeat"></i>';
    }
}
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
