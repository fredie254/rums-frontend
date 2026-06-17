<?php
require_once __DIR__ . '/../../config/config.php';
require_role('admin', 'manager');

$api    = new ApiClient();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { set_flash('error', 'Invalid request.'); redirect(BASE_URL . '/leases/templates/add.php'); }

    $name       = trim(post('name'));
    $lease_type = post('lease_type') ?: 'fixed-term';
    $body       = post('body');
    $is_default = !empty($_POST['is_default']) ? 1 : 0;

    if (!$name) $errors[] = 'Template name is required.';
    if (!$body) $errors[] = 'Template body is required.';

    if (!$errors) {
        $res = $api->post('lease-templates', [
            'name'       => $name,
            'lease_type' => $lease_type,
            'body'       => $body,
            'is_default' => $is_default,
        ]);
        if (!empty($res['success'])) {
            set_flash('success', 'Template "' . $name . '" created.');
            redirect(BASE_URL . '/leases/templates/index.php');
        }
        $errors[] = $res['message'] ?? 'Failed to create template.';
    }
}

$page_title = 'New Lease Template';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3">
    <a href="<?= BASE_URL ?>/leases/templates/index.php" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0">New Lease Template</h5>
</div>
<?php if ($errors): ?><div class="alert alert-danger small"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="row g-3">
    <div class="col-md-8">
        <form method="POST">
            <?= csrf_field() ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold small"><i class="bi bi-info-circle me-1 text-primary"></i>Template Details</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Template Name *</label>
                            <input type="text" name="name" class="form-control" value="<?= e(post('name')) ?>"
                                   placeholder="e.g. Standard Residential Lease" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Lease Type</label>
                            <select name="lease_type" class="form-select">
                                <?php foreach (['fixed-term','periodic','commercial','furnished'] as $lt): ?>
                                <option value="<?= $lt ?>" <?= (post('lease_type') ?: 'fixed-term') === $lt ? 'selected':'' ?>>
                                    <?= ucfirst(str_replace('-',' ',$lt)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="is_default" id="isDefault"
                                       <?= !empty($_POST['is_default']) ? 'checked':'' ?>>
                                <label class="form-check-label fw-semibold" for="isDefault">Set as Default</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold small"><i class="bi bi-journal-text me-1 text-primary"></i>Template Body</div>
                <div class="card-body p-0">
                    <textarea name="body" id="templateBody" class="form-control border-0 rounded-0 font-monospace"
                              rows="24" style="resize:vertical"
                              placeholder="Type your lease terms here. Use placeholders like {{TENANT_NAME}}, {{MONTHLY_RENT}} etc."><?= e(post('body')) ?></textarea>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save Template</button>
                <a href="<?= BASE_URL ?>/leases/templates/index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <!-- Placeholder reference panel -->
    <div class="col-md-4">
        <div class="card shadow-sm sticky-top" style="top:1rem">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-braces me-1 text-warning"></i>Available Placeholders</div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush small">
                    <?php
                    $placeholders = [
                        '{{TENANT_NAME}}'        => 'Full name of the tenant',
                        '{{UNIT_NUMBER}}'         => 'Unit / house number',
                        '{{PROPERTY_NAME}}'       => 'Property / estate name',
                        '{{START_DATE}}'          => 'Lease start date',
                        '{{END_DATE}}'            => 'Lease end date',
                        '{{MONTHLY_RENT}}'        => 'Monthly rent amount',
                        '{{DEPOSIT_AMOUNT}}'      => 'Security deposit amount',
                        '{{PAYMENT_DAY}}'         => 'Day of month rent is due',
                        '{{GRACE_PERIOD_DAYS}}'   => 'Grace period in days',
                        '{{PENALTY_RATE}}'        => 'Late payment penalty %',
                        '{{NOTICE_PERIOD_DAYS}}'  => 'Notice period in days',
                        '{{LEASE_TYPE}}'          => 'Type of lease agreement',
                        '{{LANDLORD_NAME}}'       => 'Landlord full name',
                        '{{LEASE_NUMBER}}'        => 'Auto-generated lease reference',
                        '{{TODAY}}'               => 'Today\'s date (signing date)',
                    ];
                    foreach ($placeholders as $ph => $desc):
                    ?>
                    <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start py-2 px-3"
                            onclick="insertPlaceholder('<?= $ph ?>')" title="Click to insert">
                        <code class="text-primary"><?= $ph ?></code>
                        <small class="text-muted ms-2 text-end"><?= $desc ?></small>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function insertPlaceholder(ph) {
    const ta  = document.getElementById('templateBody');
    const st  = ta.selectionStart;
    const en  = ta.selectionEnd;
    ta.value  = ta.value.substring(0, st) + ph + ta.value.substring(en);
    ta.selectionStart = ta.selectionEnd = st + ph.length;
    ta.focus();
}
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
