<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api    = new ApiClient();
$errors = [];

// Fetch landlords and managers for dropdowns
$ll_res    = $api->get('landlords', ['per_page' => 200]);
$landlords = $ll_res['data'] ?? [];
$usr_res   = $api->get('users', ['role' => 'admin', 'status' => 'all', 'per_page' => 200]);
$mgr_res   = $api->get('users', ['role' => 'manager', 'status' => 'all', 'per_page' => 200]);
$managers  = array_merge($usr_res['data'] ?? [], $mgr_res['data'] ?? []);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/properties/add'); }

    $name        = post('name');
    $address     = post('address');
    $city        = post('city');
    $county      = post('county');
    $ptype       = post('property_type');
    $landlord_id = int_param('landlord_id', 0, 'post');
    $manager_id  = int_param('manager_id', 0, 'post') ?: null;
    $description = post('description');

    if (!$name)        $errors[] = 'Property name is required.';
    if (!$address)     $errors[] = 'Address is required.';
    if (!$city)        $errors[] = 'City is required.';
    if (!$landlord_id) $errors[] = 'Landlord is required.';

    $image_path = null;
    if (!empty($_FILES['image']['name'])) {
        $image_path = upload_image($_FILES['image'], 'properties');
        if (!$image_path) $errors[] = 'Invalid image file. Max 5MB, JPEG/PNG/WebP only.';
    }

    if (!$errors) {
        $payload = [
            'name'           => $name,
            'property_type'  => $ptype,
            'address_line1'  => $address,
            'address_city'   => $city,
            'address_county' => $county,
            'landlord_id'    => $landlord_id,
            'manager_id'     => $manager_id,
            'description'    => $description,
            'image'          => $image_path,
        ];
        $res = $api->post('properties', $payload);
        if (!empty($res['success'])) {
            set_flash('success', "Property \"$name\" added successfully.");
            redirect(BASE_URL . '/properties/index');
        }
        $errors[] = $res['message'] ?? 'Failed to add property.';
    }
}

$page_title = 'Add Property';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3">
    <a href="<?= BASE_URL ?>/properties/index" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0">Add New Property</h5>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Property Name *</label>
                    <input type="text" name="name" class="form-control" value="<?= e(post('name')) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Property Type *</label>
                    <select name="property_type" class="form-select" required>
                        <option value="residential" <?= post('property_type')==='residential'?'selected':'' ?>>Residential</option>
                        <option value="commercial"  <?= post('property_type')==='commercial'?'selected':'' ?>>Commercial</option>
                        <option value="mixed"       <?= post('property_type')==='mixed'?'selected':'' ?>>Mixed</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Address *</label>
                    <input type="text" name="address" class="form-control" value="<?= e(post('address')) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">City *</label>
                    <input type="text" name="city" class="form-control" value="<?= e(post('city')) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">County</label>
                    <input type="text" name="county" class="form-control" value="<?= e(post('county')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Landlord *</label>
                    <select name="landlord_id" class="form-select" required>
                        <option value="">— Select Landlord —</option>
                        <?php foreach ($landlords as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= int_param('landlord_id',0,'post')==$l['id']?'selected':'' ?>><?= e($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Property Manager</label>
                    <select name="manager_id" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($managers as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= int_param('manager_id',0,'post')==$m['id']?'selected':'' ?>><?= e($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= e(post('description')) ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Property Image</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <small class="text-muted">Max 5MB. JPEG, PNG, WebP.</small>
                </div>
                <div class="col-12 mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save Property</button>
                    <a href="<?= BASE_URL ?>/properties/index" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
