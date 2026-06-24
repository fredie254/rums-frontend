<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title ?? APP_FULL_NAME) ?> — <?= APP_NAME ?></title>
    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<!-- Top Navbar -->
<nav class="navbar navbar-dark rums-navbar px-3 py-2 fixed-top">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-outline-light sidebar-toggle me-1" id="sidebarToggle">
            <i class="bi bi-list fs-5"></i>
        </button>
        <a class="navbar-brand mb-0 fw-bold" href="<?= BASE_URL ?>/dashboard/index">
            <i class="bi bi-building-fill text-warning me-1"></i><?= APP_NAME ?>
        </a>
    </div>
    <div class="d-flex align-items-center gap-3">
        <!-- Notifications -->
        <?php
        $notif_count = is_logged_in() ? unread_notification_count((int)$_SESSION['user_id']) : 0;
        ?>
        <div class="dropdown">
            <a href="#" class="nav-link text-white position-relative" data-bs-toggle="dropdown">
                <i class="bi bi-bell-fill fs-5"></i>
                <?php if ($notif_count > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.6rem">
                    <?= $notif_count > 9 ? '9+' : $notif_count ?>
                </span>
                <?php endif; ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width:300px">
                <li><h6 class="dropdown-header">Notifications</h6></li>
                <?php
                if (is_logged_in()) {
                    $notifs = (new ApiClient())->get('notifications', ['per_page' => 5])['data'] ?? [];
                    if ($notifs):
                        foreach ($notifs as $n): ?>
                        <li>
                            <a class="dropdown-item <?= $n['is_read'] ? '' : 'fw-semibold bg-light' ?>" href="<?= BASE_URL ?>/notifications/index?mark=<?= $n['id'] ?>">
                                <small class="text-<?= $n['type'] ?>"><?= e($n['title']) ?></small><br>
                                <small class="text-muted"><?= e(substr($n['message'], 0, 60)) ?>...</small>
                            </a>
                        </li>
                        <?php endforeach;
                    else: ?>
                        <li><span class="dropdown-item text-muted small">No notifications</span></li>
                    <?php endif;
                } ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-center small" href="<?= BASE_URL ?>/notifications/index">View all</a></li>
            </ul>
        </div>
        <!-- User menu -->
        <?php $u = current_user(); ?>
        <div class="dropdown">
            <a href="#" class="nav-link text-white d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <div class="avatar-sm bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center fw-bold">
                    <?= strtoupper(substr($u['name'] ?? 'U', 0, 1)) ?>
                </div>
                <span class="d-none d-md-inline small"><?= e($u['name'] ?? '') ?></span>
                <i class="bi bi-chevron-down small"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li><h6 class="dropdown-header"><?= e($u['name'] ?? '') ?><br><small class="text-muted"><?= ucfirst($u['role'] ?? '') ?></small></h6></li>
                <li><a class="dropdown-item" href="<?= BASE_URL ?>/users/profile"><i class="bi bi-person me-2"></i>My Profile</a></li>
                <li><a class="dropdown-item" href="<?= BASE_URL ?>/users/change_password"><i class="bi bi-key me-2"></i>Change Password</a></li>
                <?php if (is_admin()): ?>
                <li><a class="dropdown-item" href="<?= BASE_URL ?>/settings/index"><i class="bi bi-gear me-2"></i>Settings</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/auth/logout"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</nav>
<!-- End Navbar -->
<div class="wrapper d-flex">
    <?php include BASE_PATH . '/includes/sidebar.php'; ?>
    <div class="main-content flex-grow-1">
        <div class="container-fluid py-3 py-lg-4 px-3 px-lg-4">
            <?= flash_html() ?>
