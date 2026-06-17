<?php
$current_path = $_SERVER['PHP_SELF'] ?? '';
$user_role    = $_SESSION['user_role'] ?? 'tenant';

function nav_link(string $url, string $icon, string $label, string $current_path): string {
    $base = parse_url($url, PHP_URL_PATH);
    $active = (strpos($current_path, dirname($base)) !== false && dirname($base) !== '/') ||
              $current_path === $base ? ' active' : '';
    return '<li class="nav-item"><a class="nav-link' . $active . '" href="' . $url . '">'
        . '<i class="bi bi-' . $icon . ' me-2"></i>' . $label . '</a></li>';
}
?>
<nav class="sidebar" id="sidebar" aria-label="Main navigation">
    <div class="sidebar-header py-3 px-3 d-flex align-items-center">
        <i class="bi bi-building-fill text-warning me-2 fs-5"></i>
        <span class="fw-bold text-white">Property Hub</span>
    </div>
    <ul class="nav flex-column px-2 py-2">

        <!-- Dashboard — role-aware home link -->
        <?php
        $home_url = match($user_role) {
            'landlord'    => BASE_URL . '/landlord/dashboard.php',
            'accountant'  => BASE_URL . '/accountant/dashboard.php',
            'maintenance' => BASE_URL . '/maintenance_staff/dashboard.php',
            'auditor'     => BASE_URL . '/auditor/dashboard.php',
            'security'    => BASE_URL . '/security/dashboard.php',
            'tenant'      => BASE_URL . '/tenant/dashboard.php',
            default       => BASE_URL . '/dashboard/index.php',
        };
        ?>
        <?= nav_link($home_url, 'speedometer2', 'Dashboard', $current_path) ?>

        <?php /* ══════════════════════════════════════════
               ADMIN
               ══════════════════════════════════════════ */
        if ($user_role === 'admin'): ?>

        <li class="nav-section-label">PROPERTIES</li>
        <?= nav_link(BASE_URL . '/properties/index.php', 'buildings', 'Properties', $current_path) ?>
        <?= nav_link(BASE_URL . '/units/index.php', 'door-open', 'Units', $current_path) ?>

        <li class="nav-section-label">PEOPLE</li>
        <?= nav_link(BASE_URL . '/landlords/index.php', 'person-badge', 'Landlords', $current_path) ?>
        <?= nav_link(BASE_URL . '/tenants/index.php', 'people', 'Tenants', $current_path) ?>

        <li class="nav-section-label">OPERATIONS</li>
        <?= nav_link(BASE_URL . '/leases/index.php', 'file-earmark-text', 'Leases', $current_path) ?>
        <?= nav_link(BASE_URL . '/invoices/index.php', 'receipt', 'Invoices', $current_path) ?>
        <?= nav_link(BASE_URL . '/payments/index.php', 'cash-coin', 'Payments', $current_path) ?>
        <?= nav_link(BASE_URL . '/maintenance/index.php', 'wrench', 'Maintenance', $current_path) ?>

        <li class="nav-section-label">ANALYTICS</li>
        <?= nav_link(BASE_URL . '/reports/index.php', 'bar-chart-line', 'Reports', $current_path) ?>
        <?= nav_link(BASE_URL . '/reports/unit_performance.php', 'graph-up-arrow', 'Unit Performance', $current_path) ?>
        <?= nav_link(BASE_URL . '/reports/financial.php', 'currency-exchange', 'Financial', $current_path) ?>
        <?= nav_link(BASE_URL . '/reports/occupancy.php', 'grid-1x2', 'Occupancy', $current_path) ?>
        <?= nav_link(BASE_URL . '/reports/maintenance.php', 'wrench-adjustable', 'Maintenance', $current_path) ?>

        <li class="nav-section-label">SECURITY</li>
        <?= nav_link(BASE_URL . '/security/dashboard.php', 'shield-lock', 'Security', $current_path) ?>
        <?= nav_link(BASE_URL . '/security/visitors.php', 'person-lines-fill', 'Visitors', $current_path) ?>
        <?= nav_link(BASE_URL . '/security/incidents.php', 'exclamation-triangle', 'Incidents', $current_path) ?>

        <li class="nav-section-label">DOCUMENTS</li>
        <?= nav_link(BASE_URL . '/documents/index.php', 'folder2-open', 'Document Repository', $current_path) ?>

        <li class="nav-section-label">SYSTEM</li>
        <?= nav_link(BASE_URL . '/users/index.php', 'person-gear', 'Users', $current_path) ?>
        <?= nav_link(BASE_URL . '/settings/index.php', 'sliders', 'Settings', $current_path) ?>
        <?= nav_link(BASE_URL . '/auditor/audit_trail.php', 'journal-text', 'Audit Log', $current_path) ?>

        <?php /* ══════════════════════════════════════════
               MANAGER
               ══════════════════════════════════════════ */
        elseif ($user_role === 'manager'): ?>

        <li class="nav-section-label">PROPERTIES</li>
        <?= nav_link(BASE_URL . '/properties/index.php', 'buildings', 'Properties', $current_path) ?>
        <?= nav_link(BASE_URL . '/units/index.php', 'door-open', 'Units', $current_path) ?>

        <li class="nav-section-label">PEOPLE</li>
        <?= nav_link(BASE_URL . '/landlords/index.php', 'person-badge', 'Landlords', $current_path) ?>
        <?= nav_link(BASE_URL . '/tenants/index.php', 'people', 'Tenants', $current_path) ?>

        <li class="nav-section-label">OPERATIONS</li>
        <?= nav_link(BASE_URL . '/leases/index.php', 'file-earmark-text', 'Leases', $current_path) ?>
        <?= nav_link(BASE_URL . '/invoices/index.php', 'receipt', 'Invoices', $current_path) ?>
        <?= nav_link(BASE_URL . '/payments/index.php', 'cash-coin', 'Payments', $current_path) ?>
        <?= nav_link(BASE_URL . '/maintenance/index.php', 'wrench', 'Maintenance', $current_path) ?>

        <li class="nav-section-label">ANALYTICS</li>
        <?= nav_link(BASE_URL . '/reports/index.php', 'bar-chart-line', 'Reports', $current_path) ?>
        <?= nav_link(BASE_URL . '/reports/unit_performance.php', 'graph-up-arrow', 'Unit Performance', $current_path) ?>
        <?= nav_link(BASE_URL . '/reports/financial.php', 'currency-exchange', 'Financial', $current_path) ?>
        <?= nav_link(BASE_URL . '/reports/occupancy.php', 'grid-1x2', 'Occupancy', $current_path) ?>
        <?= nav_link(BASE_URL . '/reports/maintenance.php', 'wrench-adjustable', 'Maintenance', $current_path) ?>

        <li class="nav-section-label">DOCUMENTS</li>
        <?= nav_link(BASE_URL . '/documents/index.php', 'folder2-open', 'Document Repository', $current_path) ?>

        <?php /* ══════════════════════════════════════════
               LANDLORD
               ══════════════════════════════════════════ */
        elseif ($user_role === 'landlord'): ?>

        <li class="nav-section-label">MY PORTFOLIO</li>
        <?= nav_link(BASE_URL . '/landlord/dashboard.php', 'building-fill', 'Portfolio Overview', $current_path) ?>
        <?= nav_link(BASE_URL . '/landlord/statement.php', 'file-earmark-bar-graph', 'Income Statement', $current_path) ?>

        <?php /* ══════════════════════════════════════════
               ACCOUNTANT
               ══════════════════════════════════════════ */
        elseif ($user_role === 'accountant'): ?>

        <li class="nav-section-label">FINANCIALS</li>
        <?= nav_link(BASE_URL . '/accountant/dashboard.php', 'graph-up-arrow', 'Finance Dashboard', $current_path) ?>
        <?= nav_link(BASE_URL . '/accountant/reconciliation.php', 'arrow-left-right', 'Reconciliation', $current_path) ?>
        <?= nav_link(BASE_URL . '/accountant/aging.php', 'hourglass-split', 'AR Aging', $current_path) ?>
        <?= nav_link(BASE_URL . '/accountant/expenses.php', 'receipt-cutoff', 'Expenses', $current_path) ?>
        <?= nav_link(BASE_URL . '/accountant/statements.php', 'file-earmark-bar-graph', 'Income Statement', $current_path) ?>

        <li class="nav-section-label">OPERATIONS</li>
        <?= nav_link(BASE_URL . '/payments/index.php', 'cash-coin', 'Payments', $current_path) ?>
        <?= nav_link(BASE_URL . '/invoices/index.php', 'receipt', 'Invoices', $current_path) ?>
        <?= nav_link(BASE_URL . '/leases/index.php', 'file-earmark-text', 'Leases', $current_path) ?>

        <li class="nav-section-label">REPORTS</li>
        <?= nav_link(BASE_URL . '/reports/financial.php', 'currency-exchange', 'Financial', $current_path) ?>
        <?= nav_link(BASE_URL . '/reports/rent_collection.php', 'cash-stack', 'Rent Collection', $current_path) ?>
        <?= nav_link(BASE_URL . '/landlord/statement.php', 'file-earmark-person', 'Landlord Statement', $current_path) ?>

        <li class="nav-section-label">DOCUMENTS</li>
        <?= nav_link(BASE_URL . '/documents/index.php', 'folder2-open', 'Document Repository', $current_path) ?>

        <?php /* ══════════════════════════════════════════
               MAINTENANCE STAFF
               ══════════════════════════════════════════ */
        elseif ($user_role === 'maintenance'): ?>

        <li class="nav-section-label">WORK ORDERS</li>
        <?= nav_link(BASE_URL . '/maintenance_staff/dashboard.php', 'tools', 'Maintenance Dashboard', $current_path) ?>
        <?= nav_link(BASE_URL . '/maintenance_staff/work_orders.php', 'clipboard-list', 'All Work Orders', $current_path) ?>
        <?= nav_link(BASE_URL . '/maintenance_staff/work_orders.php?assigned_to=' . ($_SESSION['user_id']??0), 'person-check', 'My Tasks', $current_path) ?>
        <?= nav_link(BASE_URL . '/maintenance/add.php', 'plus-circle', 'New Request', $current_path) ?>

        <?php /* ══════════════════════════════════════════
               SECURITY OFFICER
               ══════════════════════════════════════════ */
        elseif ($user_role === 'security'): ?>

        <li class="nav-section-label">SECURITY</li>
        <?= nav_link(BASE_URL . '/security/dashboard.php', 'shield-lock', 'Security Dashboard', $current_path) ?>
        <?= nav_link(BASE_URL . '/security/visitors.php', 'person-lines-fill', 'Visitor Log', $current_path) ?>
        <?= nav_link(BASE_URL . '/security/occupancy_log.php', 'house-check', 'Occupancy Log', $current_path) ?>
        <?= nav_link(BASE_URL . '/security/incidents.php', 'exclamation-triangle', 'Incidents', $current_path) ?>

        <?php /* ══════════════════════════════════════════
               AUDITOR
               ══════════════════════════════════════════ */
        elseif ($user_role === 'auditor'): ?>

        <li class="nav-section-label">AUDIT & COMPLIANCE</li>
        <?= nav_link(BASE_URL . '/auditor/dashboard.php', 'shield-check', 'Audit Dashboard', $current_path) ?>
        <?= nav_link(BASE_URL . '/auditor/audit_trail.php', 'journal-text', 'Audit Trail', $current_path) ?>
        <?= nav_link(BASE_URL . '/auditor/compliance.php', 'clipboard2-check', 'Compliance', $current_path) ?>

        <li class="nav-section-label">READ-ONLY VIEW</li>
        <?= nav_link(BASE_URL . '/reports/index.php', 'bar-chart-line', 'Reports', $current_path) ?>
        <?= nav_link(BASE_URL . '/reports/financial.php', 'currency-exchange', 'Financial', $current_path) ?>
        <?= nav_link(BASE_URL . '/reports/unit_performance.php', 'graph-up-arrow', 'Unit Performance', $current_path) ?>
        <?= nav_link(BASE_URL . '/payments/index.php', 'cash-coin', 'Payments', $current_path) ?>
        <?= nav_link(BASE_URL . '/invoices/index.php', 'receipt', 'Invoices', $current_path) ?>
        <?= nav_link(BASE_URL . '/accountant/aging.php', 'hourglass-split', 'AR Aging', $current_path) ?>
        <?= nav_link(BASE_URL . '/documents/index.php', 'folder2-open', 'Documents', $current_path) ?>

        <?php /* ══════════════════════════════════════════
               TENANT
               ══════════════════════════════════════════ */
        else: ?>

        <?= nav_link(BASE_URL . '/tenant/dashboard.php', 'house-fill', 'My Dashboard', $current_path) ?>

        <li class="nav-section-label">MY TENANCY</li>
        <?= nav_link(BASE_URL . '/tenant/lease.php', 'file-earmark-text', 'My Lease', $current_path) ?>
        <?= nav_link(BASE_URL . '/tenant/invoices.php', 'receipt', 'My Invoices', $current_path) ?>
        <?= nav_link(BASE_URL . '/tenant/payments.php', 'cash-coin', 'My Payments', $current_path) ?>
        <?= nav_link(BASE_URL . '/tenant/maintenance.php', 'wrench', 'Maintenance', $current_path) ?>
        <?= nav_link(BASE_URL . '/documents/index.php', 'folder2-open', 'My Documents', $current_path) ?>

        <?php endif; ?>

        <li class="nav-section-label"></li>
        <?= nav_link(BASE_URL . '/notifications/index.php', 'bell', 'Notifications', $current_path) ?>
        <?= nav_link(BASE_URL . '/settings/account.php', 'person-gear', 'Account Settings', $current_path) ?>
        <?= nav_link(BASE_URL . '/gdpr/index.php', 'person-lock', 'Privacy & Data', $current_path) ?>
        <li class="nav-item mt-2">
            <a class="nav-link text-danger" href="<?= BASE_URL ?>/auth/logout.php">
                <i class="bi bi-box-arrow-right me-2"></i>Logout
            </a>
        </li>
    </ul>
</nav>
