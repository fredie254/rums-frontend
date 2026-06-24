<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api = new ApiClient();

// ── Occupancy report ──────────────────────────────────────────
$occ_res = $api->get('reports/occupancy');
$occ     = $occ_res['data'] ?? [];

$totals      = $occ['totals']      ?? ['total' => 0, 'occupied' => 0, 'available' => 0, 'maintenance' => 0];
$by_property = $occ['by_property'] ?? [];
$by_type     = $occ['by_type']     ?? [];

$total_units    = (int)($totals['total']       ?? 0);
$occupied_cnt   = (int)($totals['occupied']    ?? 0);
$available      = (int)($totals['available']   ?? 0);
$maintenance_cnt= (int)($totals['maintenance'] ?? 0);
$occupancy_rate = $total_units > 0 ? round($occupied_cnt / $total_units * 100, 1) : 0;

// ── Leases expiring in 30 days ────────────────────────────────
$lease_res = $api->get('leases', ['status' => 'active', 'per_page' => 500]);
$all_leases = $lease_res['data'] ?? [];
$today      = new DateTime();
$limit_dt   = (new DateTime())->modify('+30 days');

$expiring_30 = array_filter($all_leases, function ($l) use ($today, $limit_dt) {
    if (empty($l['end_date'])) return false;
    try { $end = new DateTime($l['end_date']); } catch (Throwable $e) { return false; }
    return $end >= $today && $end <= $limit_dt;
});
usort($expiring_30, fn($a, $b) => strcmp($a['end_date'], $b['end_date']));

$page_title = 'Occupancy Report';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-grid-1x2 me-2 text-success"></i>Occupancy Report</h5>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="kpi-card kpi-blue"><div class="kpi-icon"><i class="bi bi-door-open"></i></div><div class="kpi-value"><?= $total_units ?></div><div class="kpi-label">Total Units</div></div></div>
    <div class="col-6 col-md-3"><div class="kpi-card kpi-green"><div class="kpi-icon"><i class="bi bi-person-check"></i></div><div class="kpi-value"><?= $occupied_cnt ?></div><div class="kpi-label">Occupied</div></div></div>
    <div class="col-6 col-md-3"><div class="kpi-card kpi-teal"><div class="kpi-icon"><i class="bi bi-door-closed"></i></div><div class="kpi-value"><?= $available ?></div><div class="kpi-label">Available</div></div></div>
    <div class="col-6 col-md-3"><div class="kpi-card kpi-purple"><div class="kpi-icon"><i class="bi bi-graph-up"></i></div><div class="kpi-value"><?= $occupancy_rate ?>%</div><div class="kpi-label">Occupancy Rate</div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Occupancy by Property</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Property</th><th>Total</th><th>Occupied</th><th>Available</th><th>Maintenance</th><th>Rate</th></tr></thead>
                    <tbody>
                    <?php foreach ($by_property as $p): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($p['property_name']) ?></td>
                            <td><?= $p['total_units'] ?></td>
                            <td class="text-success"><?= $p['occupied'] ?></td>
                            <td class="text-primary"><?= $p['available'] ?></td>
                            <td class="text-warning"><?= $p['maintenance'] ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height:6px">
                                        <div class="progress-bar bg-success" style="width:<?= $p['occupancy_rate'] ?>%"></div>
                                    </div>
                                    <small><?= $p['occupancy_rate'] ?>%</small>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">By Unit Type</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Type</th><th>Total</th><th>Occ.</th><th>Avail.</th></tr></thead>
                    <tbody>
                    <?php foreach ($by_type as $t): ?>
                        <tr><td><?= strtoupper($t['unit_type']) ?></td><td><?= $t['total'] ?></td><td class="text-success"><?= $t['occupied'] ?></td><td><?= $t['available'] ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Expiring Leases -->
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar-x text-danger me-1"></i>Leases Expiring in 30 Days (<?= count($expiring_30) ?>)</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Tenant</th><th>Property/Unit</th><th>End Date</th><th>Days Left</th><th></th></tr></thead>
            <tbody>
            <?php if ($expiring_30): foreach ($expiring_30 as $l):
                try { $end_dt = new DateTime($l['end_date']); $days = (int)$today->diff($end_dt)->days; } catch (Throwable $e) { $days = 0; }
            ?>
                <tr>
                    <td><?= e($l['tenant_name'] ?? '—') ?></td>
                    <td><?= e($l['property_name'] ?? '') ?>/<?= e($l['unit_number'] ?? '') ?></td>
                    <td><?= fmt_date($l['end_date']) ?></td>
                    <td><span class="badge <?= $days <= 7 ? 'bg-danger' : 'bg-warning' ?>"><?= $days ?> days</span></td>
                    <td><a href="<?= BASE_URL ?>/leases/view?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-1"><i class="bi bi-eye"></i></a></td>
                </tr>
            <?php endforeach; else: ?><tr><td colspan="5" class="text-center text-muted py-3">No leases expiring in 30 days.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
