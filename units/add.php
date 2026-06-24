<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api        = new ApiClient();
$propRes    = $api->get('properties', ['status' => 'active', 'per_page' => 200]);
$properties = $propRes['data'] ?? [];
$prop_id    = int_param('property_id');
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/units/add'); }

    $data = [
        'property_id'           => int_param('property_id', 0, 'post'),
        'unit_number'           => post('unit_number'),
        'floor'                 => post('floor'),
        'block_number'          => post('block_number') ?: null,
        'unit_type'             => post('unit_type'),
        'bedrooms'              => (int)post('bedrooms') ?: 1,
        'bathrooms'             => (int)post('bathrooms') ?: 1,
        'size_sqft'             => post('size_sqft') ?: null,
        'rent_amount'           => (float)post('rent_amount'),
        'deposit_amount'        => (float)post('deposit_amount'),
        'water_included'        => isset($_POST['water_included']) ? 1 : 0,
        'electricity_included'  => isset($_POST['electricity_included']) ? 1 : 0,
        'utility_charge'        => (float)post('utility_charge') ?: 0,
        'amenities'             => post('amenities'),
        'description'           => post('description'),
    ];

    if (!$data['property_id'])    $errors[] = 'Property is required.';
    if (!$data['unit_number'])    $errors[] = 'Unit number is required.';
    if ($data['rent_amount'] <= 0) $errors[] = 'Valid rent amount is required.';
    if ($data['deposit_amount'] <= 0) $errors[] = 'Valid deposit amount is required.';

    if (!$errors) {
        $res = $api->post('units', $data);
        if (!empty($res['success'])) {
            set_flash('success', "Unit {$data['unit_number']} added successfully.");
            redirect(BASE_URL . '/units/index?property_id=' . $data['property_id']);
        }
        $errors[] = $res['message'] ?? 'Failed to create unit.';
        foreach ($res['errors'] ?? [] as $e) $errors[] = $e;
    }
}

$unit_types = ['bedsitter','studio','1br','2br','3br','4br','penthouse','office','shop','warehouse'];
$page_title = 'Add Unit';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3">
    <a href="<?= BASE_URL ?>/units/index" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0">Add New Unit</h5>
</div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Property *</label>
                    <select name="property_id" class="form-select" required>
                        <option value="">— Select Property —</option>
                        <?php foreach ($properties as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= (int_param('property_id',0,'post')||$prop_id)==$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Unit Number *</label>
                    <input type="text" name="unit_number" class="form-control" value="<?= e(post('unit_number')) ?>" placeholder="e.g. A1, 101" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Floor</label>
                    <input type="text" name="floor" class="form-control" value="<?= e(post('floor')) ?>" placeholder="e.g. G, 1, 2">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Block</label>
                    <input type="text" name="block_number" class="form-control" value="<?= e(post('block_number')) ?>" placeholder="e.g. A, B, East">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Unit Type *</label>
                    <select name="unit_type" class="form-select" required>
                        <?php foreach ($unit_types as $t): ?>
                        <option value="<?= $t ?>" <?= post('unit_type')===$t?'selected':'' ?>><?= strtoupper($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2"><label class="form-label fw-semibold">Bedrooms</label><input type="number" name="bedrooms" class="form-control" value="<?= e(post('bedrooms') ?: 1) ?>" min="0" max="10"></div>
                <div class="col-md-2"><label class="form-label fw-semibold">Bathrooms</label><input type="number" name="bathrooms" class="form-control" value="<?= e(post('bathrooms') ?: 1) ?>" min="1" max="10"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Size (sq ft)</label><input type="number" step="0.01" name="size_sqft" class="form-control" value="<?= e(post('size_sqft')) ?>"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Monthly Rent (<?= get_setting('currency_symbol', CURRENCY_SYMBOL) ?>) *</label><input type="number" step="0.01" name="rent_amount" class="form-control" value="<?= e(post('rent_amount')) ?>" required min="0"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Deposit Amount (<?= get_setting('currency_symbol', CURRENCY_SYMBOL) ?>) *</label><input type="number" step="0.01" name="deposit_amount" class="form-control" value="<?= e(post('deposit_amount')) ?>" required min="0"></div>
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Utilities</label>
                    <div class="d-flex gap-3 align-items-center mt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="water_included" id="water_inc" <?= isset($_POST['water_included'])?'checked':'' ?>>
                            <label class="form-check-label" for="water_inc">Water Included</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="electricity_included" id="elec_inc" <?= isset($_POST['electricity_included'])?'checked':'' ?>>
                            <label class="form-check-label" for="elec_inc">Electricity Included</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Utility Charge (<?= get_setting('currency_symbol', CURRENCY_SYMBOL) ?>)</label>
                    <input type="number" step="0.01" name="utility_charge" class="form-control" value="<?= e(post('utility_charge') ?: '0') ?>" min="0" placeholder="0.00">
                    <div class="form-text">Flat monthly charge for non-included utilities</div>
                </div>
                <div class="col-12"><label class="form-label fw-semibold">Amenities</label><input type="text" name="amenities" class="form-control" value="<?= e(post('amenities')) ?>" placeholder="e.g. Parking, Balcony, Garden..."></div>
                <div class="col-12"><label class="form-label fw-semibold">Description</label><textarea name="description" class="form-control" rows="2"><?= e(post('description')) ?></textarea></div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save Unit</button>
                    <a href="<?= BASE_URL ?>/units/index" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
