<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager', 'accountant', 'auditor');

$page_title = 'Reports & Analytics';
include BASE_PATH . '/includes/header.php';

$apiBase = rtrim(env('APP_URL', BASE_URL), '/') . '/api/v1';
$token   = $_SESSION['api_token'] ?? '';
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h5 class="fw-bold mb-0"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Reports &amp; Analytics</h5>
    <?php if (is_manager()): ?>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/reports/scheduled.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-calendar-check me-1"></i>Scheduled Reports
        </a>
        <a href="<?= BASE_URL ?>/reports/dashboard.php" class="btn btn-sm btn-primary">
            <i class="bi bi-speedometer2 me-1"></i>Executive Dashboard
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- ── BI Dashboards ────────────────────────────────────────── -->
<h6 class="text-uppercase text-muted small fw-bold mb-2 mt-1">BI Dashboards</h6>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <a href="<?= BASE_URL ?>/reports/dashboard.php" class="text-decoration-none">
            <div class="card shadow-sm card-hover border-primary border-2 h-100">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="report-icon bg-primary-soft"><i class="bi bi-speedometer2 text-primary fs-3"></i></div>
                    <div class="flex-grow-1">
                        <h6 class="fw-bold mb-1">Executive Dashboard <span class="badge bg-primary ms-1" style="font-size:.65rem">NEW</span></h6>
                        <p class="text-muted small mb-0">KPIs, revenue, occupancy, arrears, maintenance — at a glance</p>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= BASE_URL ?>/reports/unit_performance.php" class="text-decoration-none">
            <div class="card shadow-sm card-hover h-100">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="report-icon bg-primary-soft"><i class="bi bi-graph-up-arrow text-primary fs-3"></i></div>
                    <div class="flex-grow-1">
                        <h6 class="fw-bold mb-1">Unit Performance</h6>
                        <p class="text-muted small mb-0">7 charts: occupancy, revenue, collection, maintenance</p>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= BASE_URL ?>/reports/arrears.php" class="text-decoration-none">
            <div class="card shadow-sm card-hover h-100">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="report-icon bg-danger-soft"><i class="bi bi-graph-down-arrow text-danger fs-3"></i></div>
                    <div class="flex-grow-1">
                        <h6 class="fw-bold mb-1">Arrears Analysis <span class="badge bg-danger ms-1" style="font-size:.65rem">NEW</span></h6>
                        <p class="text-muted small mb-0">Trend, worst offenders, collection effectiveness</p>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- ── Standard Reports ────────────────────────────────────── -->
<h6 class="text-uppercase text-muted small fw-bold mb-2">Standard Reports</h6>
<div class="row g-3 mb-4">
    <?php
    $reports = [
        ['url' => 'financial.php',     'icon' => 'bi-currency-exchange', 'color' => 'primary',  'title' => 'Financial Report',   'desc' => 'Revenue, collections, outstanding amounts', 'export' => 'financial'],
        ['url' => 'occupancy.php',     'icon' => 'bi-grid-1x2',          'color' => 'success',  'title' => 'Occupancy Report',   'desc' => 'Unit status, vacancy trends',               'export' => 'occupancy'],
        ['url' => 'rent_collection.php','icon'=> 'bi-cash-stack',        'color' => 'info',     'title' => 'Rent Collection',    'desc' => 'Monthly collection vs. expected',           'export' => 'rent_collection'],
        ['url' => 'maintenance.php',   'icon' => 'bi-wrench',            'color' => 'warning',  'title' => 'Maintenance Report', 'desc' => 'Request status, costs, turnaround',         'export' => 'maintenance'],
        ['url' => 'tenants.php',       'icon' => 'bi-people',            'color' => 'purple',   'title' => 'Tenant Report',      'desc' => 'Tenant statistics, analytics, retention',   'export' => 'tenant_analytics'],
        ['url' => 'ledger.php',        'icon' => 'bi-journal-text',      'color' => 'info',     'title' => 'Tenant Ledger',      'desc' => 'Debit/credit statement with running balance','export' => null],
        ['url' => 'aging.php',         'icon' => 'bi-layers',            'color' => 'warning',  'title' => 'AR Aging',           'desc' => 'Outstanding invoices by aging bucket',      'export' => 'aging'],
        ['url' => 'deposits.php',      'icon' => 'bi-safe',              'color' => 'primary',  'title' => 'Deposit Management', 'desc' => 'Security deposits held, collected, refunded','export' => 'deposits'],
    ];
    foreach ($reports as $r):
        $exportUrl = $r['export'] ? $apiBase . '/reports/export?report=' . $r['export'] . '&format=csv&token=' . urlencode($token) : null;
    ?>
    <div class="col-md-4">
        <div class="card shadow-sm card-hover h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <a href="<?= BASE_URL ?>/reports/<?= $r['url'] ?>" class="text-decoration-none d-flex align-items-center gap-3 flex-grow-1">
                    <div class="report-icon bg-<?= $r['color'] ?>-soft"><i class="bi <?= $r['icon'] ?> text-<?= $r['color'] ?> fs-3"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1"><?= $r['title'] ?></h6>
                        <p class="text-muted small mb-0"><?= $r['desc'] ?></p>
                    </div>
                </a>
                <?php if ($exportUrl): ?>
                <a href="<?= e($exportUrl) ?>" class="btn btn-xs btn-sm btn-outline-success py-0 px-1 flex-shrink-0"
                   title="Export CSV" download>
                    <i class="bi bi-download"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Scheduled Reports ──────────────────────────────────── -->
<?php if (is_manager()): ?>
<h6 class="text-uppercase text-muted small fw-bold mb-2">Automation</h6>
<div class="row g-3">
    <div class="col-md-4">
        <a href="<?= BASE_URL ?>/reports/scheduled.php" class="text-decoration-none">
            <div class="card shadow-sm card-hover h-100">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="report-icon bg-success-soft"><i class="bi bi-calendar-check text-success fs-3"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1">Scheduled Reports</h6>
                        <p class="text-muted small mb-0">Auto-email reports daily, weekly or monthly</p>
                    </div>
                    <i class="bi bi-chevron-right text-muted ms-auto"></i>
                </div>
            </div>
        </a>
    </div>
</div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/footer.php'; ?>
