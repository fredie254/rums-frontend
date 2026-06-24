<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'manager');

$api = new ApiClient();
$id  = int_param('id');
if (!$id) { set_flash('error', 'Invalid property.'); redirect(BASE_URL . '/properties/index'); }

$res  = $api->get("properties/$id");
$prop = $res['data'] ?? null;
if (!$prop) { set_flash('error', 'Property not found.'); redirect(BASE_URL . '/properties/index'); }

// Fetch landlords and managers for dropdowns
$ll_res    = $api->get('landlords', ['per_page' => 200]);
$landlords = $ll_res['data'] ?? [];
$usr_res   = $api->get('users', ['role' => 'admin', 'status' => 'all', 'per_page' => 200]);
$mgr_res   = $api->get('users', ['role' => 'manager', 'status' => 'all', 'per_page' => 200]);
$managers  = array_merge($usr_res['data'] ?? [], $mgr_res['data'] ?? []);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/properties/edit?id=' . $id); }

    $name        = post('name');
    $address     = post('address');
    $city        = post('city');
    $county      = post('county');
    $ptype       = post('property_type');
    $landlord_id = int_param('landlord_id', 0, 'post');
    $manager_id  = int_param('manager_id', 0, 'post') ?: null;
    $description = post('description');
    $status      = post('status');

    if (!$name)        $errors[] = 'Property name is required.';
    if (!$address)     $errors[] = 'Address is required.';
    if (!$landlord_id) $errors[] = 'Landlord is required.';

    $image_path = $prop['image'] ?? null;
    if (!empty($_FILES['image']['name'])) {
        $new_img = upload_image($_FILES['image'], 'properties');
        if ($new_img) $image_path = $new_img;
        else $errors[] = 'Invalid image.';
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
            'status'         => $status,
            'image'          => $image_path,
        ];
        $upd = $api->put("properties/$id", $payload);
        if (!empty($upd['success'])) {
            set_flash('success', 'Property updated successfully.');
            redirect(BASE_URL . '/properties/index');
        }
        $errors[] = $upd['message'] ?? 'Failed to update property.';
    }
    $prop = array_merge($prop, compact('name','ptype','address','city','county','landlord_id','manager_id','description','status'));
    $prop['property_type']  = $ptype;
    $prop['address_line1']  = $address;
    $prop['address_city']   = $city;
    $prop['address_county'] = $county;
}

$page_title = 'Edit Property';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3">
    <a href="<?= BASE_URL ?>/properties/index" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0">Edit Property — <?= e($prop['name']) ?></h5>
</div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Property Name *</label>
                    <input type="text" name="name" class="form-control" value="<?= e($prop['name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Property Type *</label>
                    <select name="property_type" class="form-select">
                        <?php foreach (['residential','commercial','mixed'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($prop['property_type'] ?? '')===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Address *</label>
                    <input type="text" name="address" class="form-control" value="<?= e($prop['address_line1'] ?? $prop['address'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">City *</label>
                    <input type="text" name="city" class="form-control" value="<?= e($prop['address_city'] ?? $prop['city'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">County</label>
                    <input type="text" name="county" class="form-control" value="<?= e($prop['address_county'] ?? $prop['county'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="active"   <?= ($prop['status'] ?? '')==='active'?'selected':'' ?>>Active</option>
                        <option value="inactive" <?= ($prop['status'] ?? '')==='inactive'?'selected':'' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Landlord *</label>
                    <select name="landlord_id" class="form-select" required>
                        <?php foreach ($landlords as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= ($prop['landlord_id'] ?? 0)==$l['id']?'selected':'' ?>><?= e($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Manager</label>
                    <select name="manager_id" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($managers as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= ($prop['manager_id'] ?? 0)==$m['id']?'selected':'' ?>><?= e($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= e($prop['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Property Image</label>
                    <?php if (!empty($prop['image'])): ?>
                    <div class="mb-1"><img src="<?= BASE_URL ?>/assets/uploads/<?= e($prop['image']) ?>" style="height:60px;border-radius:4px"></div>
                    <?php endif; ?>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Update Property</button>
                    <a href="<?= BASE_URL ?>/properties/index" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
