<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin', 'accountant');

$api        = new ApiClient();
$page_title = 'Expense Management';

$categories = ['repairs','utilities','insurance','taxes','management_fee','advertising','legal','office','salaries','other'];

/* ── handle form actions ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = post_param('action', '');

    if ($action === 'add') {
        $res = $api->post('expenses', [
            'property_id'  => post_param('property_id') ?: null,
            'category'     => post_param('category'),
            'description'  => post_param('description'),
            'amount'       => (float)post_param('amount'),
            'expense_date' => post_param('expense_date'),
            'vendor'       => post_param('vendor') ?: null,
            'receipt_ref'  => post_param('receipt_ref') ?: null,
            'notes'        => post_param('notes') ?: null,
        ]);
        set_flash($res['success'] ? 'success' : 'danger', $res['message'] ?? 'Error saving expense.');
        redirect(BASE_URL . '/accountant/expenses.php');
    }

    if ($action === 'approve' && is_admin()) {
        $id  = int_param('id');
        $res = $api->patch("expenses/$id/approve", []);
        set_flash($res['success'] ? 'success' : 'danger', $res['message'] ?? 'Error.');
        redirect(BASE_URL . '/accountant/expenses.php');
    }

    if ($action === 'reject' && is_admin()) {
        $id  = int_param('id');
        $res = $api->patch("expenses/$id/reject", []);
        set_flash($res['success'] ? 'success' : 'danger', $res['message'] ?? 'Error.');
        redirect(BASE_URL . '/accountant/expenses.php');
    }

    if ($action === 'mark_paid') {
        $id  = int_param('id');
        $res = $api->patch("expenses/$id/mark-paid", []);
        set_flash($res['success'] ? 'success' : 'danger', $res['message'] ?? 'Error.');
        redirect(BASE_URL . '/accountant/expenses.php');
    }
}

/* ── filters ── */
$filter_status   = get_param('status', 'all');
$filter_category = get_param('category', '');
$filter_property = int_param('property_id', 0);
$filter_period   = get_param('period', date('Y-m'));

[$yr, $mo] = explode('-', $filter_period);
$date_from = "$yr-$mo-01";
$date_to   = date('Y-m-t', strtotime($date_from));

/* ── Property dropdown ── */
$prop_res   = $api->get('properties', ['per_page' => 500]);
$properties = $prop_res['data'] ?? [];

/* ── Fetch expenses via API ── */
$query = array_filter([
    'date_from'   => $date_from,
    'date_to'     => $date_to,
    'status'      => $filter_status !== 'all' ? $filter_status : null,
    'category'    => $filter_category ?: null,
    'property_id' => $filter_property ?: null,
    'per_page'    => 500,
], fn($v) => $v !== null && $v !== '');

$exp_res  = $api->get('expenses', $query);
$expenses = $exp_res['data'] ?? [];

/* ── Summary ── */
$total_all      = array_sum(array_column($expenses, 'amount'));
$total_pending  = array_sum(array_column(array_filter($expenses, fn($r) => $r['status']==='pending'),  'amount'));
$total_approved = array_sum(array_column(array_filter($expenses, fn($r) => $r['status']==='approved'), 'amount'));
$total_paid     = array_sum(array_column(array_filter($expenses, fn($r) => $r['status']==='paid'),     'amount'));

/* ── Category totals ── */
$cat_totals = [];
foreach ($expenses as $ex) {
    $cat_totals[$ex['category']] = ($cat_totals[$ex['category']] ?? 0) + (float)$ex['amount'];
}
arsort($cat_totals);

include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0"><i class="bi bi-receipt-cutoff me-2 text-danger"></i>Expense Management</h5>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
        <i class="bi bi-plus-lg me-1"></i>Record Expense
    </button>
</div>

<?= flash_html() ?>

<!-- Filters -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">Period</label>
                <input type="month" name="period" class="form-control form-control-sm" value="<?= e($filter_period) ?>">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Property</label>
                <select name="property_id" class="form-select form-select-sm">
                    <option value="0">All Properties</option>
                    <?php foreach ($properties as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $filter_property==$p['id']?'selected':''?>><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Category</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= $c ?>" <?= $filter_category===$c?'selected':''?>><?= ucfirst(str_replace('_',' ',$c)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="all"      <?= $filter_status==='all'     ?'selected':''?>>All</option>
                    <option value="pending"  <?= $filter_status==='pending' ?'selected':''?>>Pending</option>
                    <option value="approved" <?= $filter_status==='approved'?'selected':''?>>Approved</option>
                    <option value="paid"     <?= $filter_status==='paid'    ?'selected':''?>>Paid</option>
                    <option value="rejected" <?= $filter_status==='rejected'?'selected':''?>>Rejected</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm">Filter</button>
                <a href="expenses.php" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm kpi-blue text-white"><div class="card-body py-3">
            <div class="small opacity-75">Total Expenses</div>
            <div class="fs-5 fw-bold"><?= money($total_all) ?></div>
            <div class="small opacity-75"><?= count($expenses) ?> records</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm kpi-yellow text-white"><div class="card-body py-3">
            <div class="small opacity-75">Pending Approval</div>
            <div class="fs-5 fw-bold"><?= money($total_pending) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm kpi-orange text-white"><div class="card-body py-3">
            <div class="small opacity-75">Approved (Unpaid)</div>
            <div class="fs-5 fw-bold"><?= money($total_approved) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm kpi-green text-white"><div class="card-body py-3">
            <div class="small opacity-75">Paid</div>
            <div class="fs-5 fw-bold"><?= money($total_paid) ?></div>
        </div></div>
    </div>
</div>

<!-- Chart + Category Breakdown -->
<div class="row g-4 mb-4">
    <div class="col-md-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-2"><span class="fw-semibold">By Category</span></div>
            <div class="card-body"><canvas id="catChart" height="200"></canvas></div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-2"><span class="fw-semibold">Category Breakdown</span></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Category</th><th class="text-end">Amount</th><th>Share</th></tr></thead>
                    <tbody>
                    <?php foreach ($cat_totals as $cat => $amt): $pct = $total_all > 0 ? round($amt/$total_all*100) : 0; ?>
                    <tr>
                        <td><?= ucfirst(str_replace('_',' ', $cat)) ?></td>
                        <td class="text-end"><?= money($amt) ?></td>
                        <td style="width:120px">
                            <div class="progress" style="height:6px">
                                <div class="progress-bar" style="width:<?= $pct ?>%"></div>
                            </div>
                            <small class="text-muted"><?= $pct ?>%</small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Expenses Table -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-2">
        <span class="fw-semibold">Expense Records — <?= date('F Y', strtotime($date_from)) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Property/Unit</th>
                        <th>Vendor</th>
                        <th class="text-end">Amount</th>
                        <th>Status</th>
                        <?php if (is_admin()): ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$expenses): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No expenses found.</td></tr>
                <?php else: ?>
                    <?php foreach ($expenses as $ex): ?>
                    <tr>
                        <td><?= fmt_date($ex['expense_date']) ?></td>
                        <td>
                            <div class="fw-semibold"><?= e($ex['description']) ?></div>
                            <?php if (!empty($ex['receipt_ref'])): ?>
                            <small class="text-muted">Ref: <?= e($ex['receipt_ref']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-secondary"><?= ucfirst(str_replace('_',' ',$ex['category'])) ?></span></td>
                        <td><?= e($ex['property_name'] ?? '—') ?><?= !empty($ex['unit_number']) ? ' / '.$ex['unit_number'] : '' ?></td>
                        <td><?= e($ex['vendor'] ?: '—') ?></td>
                        <td class="text-end fw-semibold"><?= money($ex['amount']) ?></td>
                        <td>
                            <?php
                            $sc = ['pending'=>'warning','approved'=>'info','paid'=>'success','rejected'=>'danger'];
                            echo '<span class="badge bg-'.($sc[$ex['status']]??'secondary').'">'.ucfirst($ex['status']).'</span>';
                            ?>
                        </td>
                        <?php if (is_admin()): ?>
                        <td>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $ex['id'] ?>">
                                <?php if ($ex['status'] === 'pending'): ?>
                                <button name="action" value="approve" class="btn btn-xs btn-success me-1" title="Approve">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                                <button name="action" value="reject" class="btn btn-xs btn-danger" title="Reject">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                                <?php elseif ($ex['status'] === 'approved'): ?>
                                <button name="action" value="mark_paid" class="btn btn-xs btn-primary" title="Mark Paid">
                                    <i class="bi bi-currency-dollar"></i> Paid
                                </button>
                                <?php else: ?>
                                <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Record Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <input type="text" name="description" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select" required>
                                <?php foreach ($categories as $c): ?>
                                <option value="<?= $c ?>"><?= ucfirst(str_replace('_',' ',$c)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Amount (<?= get_setting('currency_symbol', CURRENCY_SYMBOL) ?>) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" step="0.01" min="0" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expense Date <span class="text-danger">*</span></label>
                            <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Vendor</label>
                            <input type="text" name="vendor" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Property</label>
                            <select name="property_id" class="form-select">
                                <option value="">Select Property</option>
                                <?php foreach ($properties as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Receipt / Reference No.</label>
                            <input type="text" name="receipt_ref" class="form-control" placeholder="Optional">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const catCtx = document.getElementById('catChart').getContext('2d');
    new Chart(catCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_map(fn($c) => ucfirst(str_replace('_',' ',$c)), array_keys($cat_totals))) ?>,
            datasets: [{
                data: <?= json_encode(array_values($cat_totals)) ?>,
                backgroundColor: ['#0d6efd','#198754','#ffc107','#dc3545','#6f42c1','#0dcaf0','#fd7e14','#20c997','#6c757d','#e83e8c'],
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
            }
        }
    });
});
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
