<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api = new ApiClient();
$id  = int_param('id');
$res = $api->get("landlords/$id");
$ll  = $res['data'] ?? null;
if (!$ll) { set_flash('error', 'Landlord not found.'); redirect(BASE_URL . '/landlords/index'); }

$props = $ll['properties'] ?? [];

$page_title = 'Landlord — ' . $ll['name'];
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3 gap-2">
    <a href="<?= BASE_URL ?>/landlords/index" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0 flex-grow-1"><?= e($ll['name']) ?></h5>
    <a href="<?= BASE_URL ?>/landlords/edit?id=<?= $id ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-pencil me-1"></i>Edit</a>
</div>
<div class="row g-3">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-body text-center py-4">
                <div class="avatar-lg bg-warning text-dark rounded-circle d-inline-flex align-items-center justify-content-center fw-bold fs-3 mb-3"><?= strtoupper(substr($ll['name'],0,1)) ?></div>
                <h6 class="fw-bold mb-1"><?= e($ll['name']) ?></h6>
                <p class="text-muted small mb-0"><?= e($ll['email']) ?></p>
                <p class="text-muted small mb-2"><?= e($ll['phone']) ?></p>
            </div>
            <hr class="my-0">
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted">ID Number</dt><dd class="col-7"><code><?= e($ll['id_number']) ?></code></dd>
                    <dt class="col-5 text-muted">KRA PIN</dt><dd class="col-7"><?= e($ll['kra_pin'] ?: '—') ?></dd>
                    <dt class="col-5 text-muted">M-Pesa</dt><dd class="col-7"><?= e($ll['mpesa_number'] ?: '—') ?></dd>
                    <dt class="col-5 text-muted">Commission</dt><dd class="col-7"><?= $ll['commission_rate'] ?>%</dd>
                    <dt class="col-5 text-muted">Bank</dt><dd class="col-7"><?= e($ll['bank_name'] ?: '—') ?></dd>
                    <dt class="col-5 text-muted">Account</dt><dd class="col-7"><?= e($ll['bank_account'] ?: '—') ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-buildings me-1 text-primary"></i>Properties (<?= count($props) ?>)</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Property</th><th>Type</th><th>Units</th><th>Occupied</th><th></th></tr></thead>
                    <tbody>
                    <?php if ($props): foreach ($props as $p): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($p['name']) ?></td>
                            <td><?= ucfirst($p['property_type'] ?? '') ?></td>
                            <td><?= $p['total_units'] ?? 0 ?></td>
                            <td><?= $p['occupied_units'] ?? 0 ?></td>
                            <td><a href="<?= BASE_URL ?>/properties/view?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-1"><i class="bi bi-eye"></i></a></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No properties.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
