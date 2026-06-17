<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$api  = new ApiClient();
$user = current_user();

// Mark single notification as read
$mark = int_param('mark');
if ($mark) {
    $api->patch("notifications/$mark/read", []);
}

// Mark all as read
if (isset($_GET['mark_all'])) {
    $api->post('notifications/read-all', []);
    set_flash('success', 'All notifications marked as read.');
    redirect(BASE_URL . '/notifications/index.php');
}

$page    = max(1, int_param('page'));
$res     = $api->get('notifications', ['page' => $page, 'per_page' => ROWS_PER_PAGE]);
$notifs  = $res['data'] ?? [];
$meta    = $res['meta'] ?? ['total' => 0, 'total_pages' => 1, 'current_page' => 1, 'per_page' => ROWS_PER_PAGE];
$total   = $meta['total'] ?? 0;
$pg      = ['total' => $total, 'per_page' => $meta['per_page'], 'page' => $meta['current_page'], 'total_pages' => $meta['total_pages'], 'offset' => ($meta['current_page'] - 1) * $meta['per_page']];

$page_title = 'Notifications';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="fw-bold mb-0"><i class="bi bi-bell me-2 text-primary"></i>Notifications & Communication</h5>
    <div class="d-flex gap-2">
        <?php if (is_manager()): ?>
        <a href="<?= BASE_URL ?>/notifications/compose.php" class="btn btn-sm btn-primary">
            <i class="bi bi-send me-1"></i>Compose
        </a>
        <a href="<?= BASE_URL ?>/notifications/broadcasts.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-broadcast me-1"></i>Broadcasts
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/notifications/logs.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-journal-text me-1"></i>Comm Logs
        </a>
        <?php if (is_manager()): ?>
        <a href="<?= BASE_URL ?>/notifications/templates.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-file-text me-1"></i>Templates
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3" id="notifTabs">
    <li class="nav-item">
        <a class="nav-link active" href="#in-app" data-bs-toggle="tab">
            <i class="bi bi-bell me-1"></i>In-App
            <?php if ($total > 0): ?><span class="badge bg-primary ms-1"><?= $total ?></span><?php endif; ?>
        </a>
    </li>
    <?php if (is_manager()): ?>
    <li class="nav-item">
        <a class="nav-link" href="<?= BASE_URL ?>/notifications/compose.php"><i class="bi bi-send me-1"></i>Compose</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= BASE_URL ?>/notifications/logs.php"><i class="bi bi-journal-text me-1"></i>Comm Logs</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= BASE_URL ?>/notifications/broadcasts.php"><i class="bi bi-broadcast me-1"></i>Broadcasts</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= BASE_URL ?>/notifications/templates.php"><i class="bi bi-file-text me-1"></i>Templates</a>
    </li>
    <?php endif; ?>
</ul>

<?= flash_html() ?>

<!-- In-App Notifications -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span class="fw-semibold small">In-App Notifications (<?= $total ?>)</span>
        <?php if ($total > 0): ?>
        <a href="?mark_all=1" class="btn btn-xs btn-sm btn-outline-secondary py-0 px-2">
            <i class="bi bi-check-all me-1"></i>Mark All Read
        </a>
        <?php endif; ?>
    </div>
    <?php if ($notifs): foreach ($notifs as $n):
        $iconMap  = ['info' => 'bi-info-circle text-info', 'warning' => 'bi-exclamation-triangle text-warning', 'success' => 'bi-check-circle text-success', 'danger' => 'bi-x-circle text-danger'];
        $iconClass = $iconMap[$n['type']] ?? 'bi-bell text-secondary';
    ?>
    <div class="notification-item d-flex align-items-start p-3 border-bottom <?= $n['is_read'] ? '' : 'bg-light' ?>">
        <i class="bi <?= $iconClass ?> fs-5 me-3 mt-1 flex-shrink-0"></i>
        <div class="flex-grow-1">
            <div class="fw-semibold small"><?= e($n['title']) ?></div>
            <div class="text-muted small"><?= e($n['message']) ?></div>
            <?php if (!empty($n['link'])): ?>
            <a href="<?= e($n['link']) ?>" class="small text-primary">View &rarr;</a>
            <?php endif; ?>
            <div class="text-muted" style="font-size:.7rem"><?= fmt_date($n['created_at'], 'd M Y H:i') ?></div>
        </div>
        <?php if (!$n['is_read']): ?>
        <a href="?mark=<?= $n['id'] ?>&page=<?= $page ?>" class="btn btn-xs btn-sm btn-outline-secondary py-0 px-1 ms-2 flex-shrink-0" title="Mark read">
            <i class="bi bi-check"></i>
        </a>
        <?php else: ?>
        <span class="badge bg-light text-muted ms-2 flex-shrink-0" style="font-size:.65rem">Read</span>
        <?php endif; ?>
    </div>
    <?php endforeach; else: ?>
    <div class="text-center text-muted py-5">
        <i class="bi bi-bell-slash fs-2"></i>
        <p class="mt-2">No notifications.</p>
    </div>
    <?php endif; ?>
    <?php if ($total > $pg['per_page']): ?>
    <div class="card-footer d-flex justify-content-end">
        <?= pagination_links($pg, BASE_URL . '/notifications/index.php') ?>
    </div>
    <?php endif; ?>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
