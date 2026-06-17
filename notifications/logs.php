<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'accountant');

$api = new ApiClient();

$channel  = get_param('channel');
$status   = get_param('status');
$tenantId = int_param('tenant_id');
$dateFrom = str_param('date_from') ?: date('Y-m-01');
$dateTo   = str_param('date_to')   ?: date('Y-m-d');
$page     = max(1, int_param('page'));

$query = array_filter([
    'channel'   => $channel  ?: null,
    'status'    => $status   ?: null,
    'tenant_id' => $tenantId ?: null,
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
    'page'      => $page,
    'per_page'  => ROWS_PER_PAGE,
], fn($v) => $v !== null && $v !== '');

$res    = $api->get('communication-logs', $query);
$logs   = $res['data'] ?? [];
$meta   = $res['meta'] ?? [];
$total  = $meta['total'] ?? 0;
$pg     = ['total' => $total, 'per_page' => $meta['per_page'] ?? ROWS_PER_PAGE, 'page' => $meta['current_page'] ?? 1, 'total_pages' => $meta['total_pages'] ?? 1, 'offset' => (($meta['current_page'] ?? 1) - 1) * ($meta['per_page'] ?? ROWS_PER_PAGE)];

// Stats for the date range
$statsRes = $api->get('communication-logs/stats', ['date_from' => $dateFrom, 'date_to' => $dateTo]);
$stats    = $statsRes['data'] ?? [];

$baseQ = http_build_query(array_filter(['channel' => $channel, 'status' => $status, 'tenant_id' => $tenantId ?: null, 'date_from' => $dateFrom, 'date_to' => $dateTo], fn($v) => $v !== null && $v !== ''));

$page_title = 'Communication Logs';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3 gap-2">
    <a href="<?= BASE_URL ?>/notifications/index.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h5 class="fw-bold mb-0"><i class="bi bi-journal-text me-2 text-primary"></i>Communication Logs</h5>
</div>

<!-- KPI cards -->
<div class="row row-cols-2 row-cols-md-4 g-2 mb-3">
    <div class="col">
        <div class="card text-center py-2">
            <div class="fs-4 fw-bold"><?= number_format((int)($stats['total'] ?? 0)) ?></div>
            <div class="text-muted small">Total Sent</div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center py-2">
            <div class="fs-4 fw-bold text-primary"><?= number_format((int)($stats['sms_count'] ?? 0)) ?></div>
            <div class="text-muted small">SMS</div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center py-2">
            <div class="fs-4 fw-bold text-info"><?= number_format((int)($stats['email_count'] ?? 0)) ?></div>
            <div class="text-muted small">Email</div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center py-2">
            <div class="fs-4 fw-bold text-danger"><?= number_format((int)($stats['failed_count'] ?? 0)) ?></div>
            <div class="text-muted small">Failed</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1">Date From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Date To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Channel</label>
                <select name="channel" class="form-select form-select-sm">
                    <option value="">All Channels</option>
                    <?php foreach (['sms', 'email', 'in_app'] as $c): ?>
                    <option value="<?= $c ?>" <?= $channel === $c ? 'selected' : '' ?>><?= ucfirst(str_replace('_', '-', $c)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <?php foreach (['queued', 'sent', 'delivered', 'failed'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Tenant ID</label>
                <input type="number" name="tenant_id" class="form-control form-control-sm" value="<?= $tenantId ?: '' ?>" placeholder="All">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filter</button>
                <a href="?" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Logs table -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span class="fw-semibold small">Logs (<?= number_format($total) ?>)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Tenant</th>
                    <th>Channel</th>
                    <th>Recipient</th>
                    <th>Template</th>
                    <th>Subject / Excerpt</th>
                    <th>Status</th>
                    <th>Provider</th>
                    <th>Sent By</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs): foreach ($logs as $l):
                    $statusMap = ['sent' => 'success', 'delivered' => 'primary', 'failed' => 'danger', 'queued' => 'warning'];
                    $badge     = $statusMap[$l['status']] ?? 'secondary';
                    $chIcon    = ['sms' => 'bi-chat-text text-success', 'email' => 'bi-envelope text-info', 'in_app' => 'bi-bell text-primary'];
                    $icon      = $chIcon[$l['channel']] ?? 'bi-question-circle';
                    $excerpt   = $l['subject'] ?: mb_strimwidth(strip_tags($l['body']), 0, 60, '...');
                ?>
                <tr>
                    <td class="text-nowrap small"><?= fmt_date($l['created_at'], 'd M y H:i') ?></td>
                    <td class="small"><?= $l['tenant_name'] ? e($l['tenant_name']) : '<span class="text-muted">—</span>' ?></td>
                    <td><i class="bi <?= $icon ?>"></i> <span class="small"><?= e($l['channel']) ?></span></td>
                    <td class="small text-muted" style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($l['recipient']) ?></td>
                    <td class="small"><?= $l['template_name'] ? e($l['template_name']) : '<span class="text-muted">—</span>' ?></td>
                    <td class="small" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= e($excerpt) ?>"><?= e($excerpt) ?></td>
                    <td><span class="badge bg-<?= $badge ?>"><?= ucfirst($l['status']) ?></span>
                        <?php if ($l['status'] === 'failed' && $l['error_message']): ?>
                        <i class="bi bi-info-circle text-danger ms-1" title="<?= e($l['error_message']) ?>" data-bs-toggle="tooltip"></i>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= e($l['provider'] ?? '—') ?></td>
                    <td class="small"><?= $l['sent_by_name'] ? e($l['sent_by_name']) : '<span class="text-muted">System</span>' ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No logs found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total > $pg['per_page']): ?>
    <div class="card-footer d-flex justify-content-end">
        <?= pagination_links($pg, BASE_URL . '/notifications/logs.php?' . $baseQ) ?>
    </div>
    <?php endif; ?>
</div>

<script>
// Init tooltips for error messages
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
