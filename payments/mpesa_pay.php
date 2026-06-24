<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$lease_id   = int_param('lease_id');
$invoice_id = int_param('invoice_id');

if (!$lease_id) { set_flash('error', 'Lease not specified.'); redirect(BASE_URL . '/leases/index'); }

$api       = new ApiClient();
$lease_res = $api->get("leases/$lease_id");
$lease     = $lease_res['data'] ?? null;
if (!$lease || $lease['status'] !== 'active') {
    set_flash('error', 'Active lease not found.');
    redirect(BASE_URL . '/leases/index');
}
// Map tenant_phone to 'phone' for template compatibility
$lease['phone'] = $lease['tenant_phone'] ?? '';

$page_title = 'Pay via M-Pesa';
include BASE_PATH . '/includes/header.php';
?>
<div class="d-flex align-items-center mb-3">
    <a href="<?= BASE_URL ?>/leases/view?id=<?= $lease_id ?>" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0">Pay via M-Pesa &mdash; <?= e($lease['unit_number']) ?></h5>
</div>
<div class="card shadow-sm" style="max-width:480px;margin:auto">
    <div class="card-body p-4">
        <div class="text-center mb-4">
            <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width:64px;height:64px">
                <i class="bi bi-phone fs-2"></i>
            </div>
            <h6 class="fw-bold">M-Pesa STK Push</h6>
            <p class="text-muted small"><?= e($lease['property_name']) ?> / <?= e($lease['unit_number']) ?></p>
        </div>

        <div id="stkForm">
            <div class="mb-3">
                <label class="form-label fw-semibold">Phone Number</label>
                <input type="tel" id="mpesaPhone" class="form-control" value="<?= e($lease['phone']) ?>" placeholder="07XXXXXXXX">
                <small class="text-muted">Enter the M-Pesa registered number</small>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Amount (<?= get_setting('currency_symbol', CURRENCY_SYMBOL) ?>)</label>
                <input type="number" id="mpesaAmount" class="form-control" value="<?= $lease['rent_amount'] ?>" min="1">
            </div>
            <div id="stkAlert" class="alert d-none" role="alert"></div>
            <button id="stkBtn" class="btn btn-success w-100 fw-bold py-2" onclick="initiateSTK()">
                <i class="bi bi-phone me-2"></i>Send STK Push
            </button>
        </div>

        <div id="stkPending" class="text-center d-none">
            <div class="spinner-border text-success mb-3"></div>
            <h6>Check your phone</h6>
            <p class="text-muted small">Enter your M-Pesa PIN when prompted.</p>
            <div id="pollCountdown" class="text-muted small mb-2">Checking status in <span id="pollSecs">5</span>s…</div>
            <div id="pollStatus" class="alert d-none small py-2"></div>
            <button class="btn btn-outline-secondary btn-sm" onclick="cancelPolling()">Cancel</button>
        </div>
    </div>
</div>

<script>
const LEASE_ID   = <?= $lease_id ?>;
const INVOICE_ID = <?= $invoice_id ?: 'null' ?>;
const CSRF       = '<?= csrf_token() ?>';
const BASE_URL   = '<?= BASE_URL ?>';
const API_TOKEN  = '<?= $_SESSION['api_token'] ?? '' ?>';
const API_BASE   = '<?= rtrim(env('APP_URL',''), '/') ?>/api/v1';

let pollInterval  = null;
let pollAttempts  = 0;
let checkoutId    = null;
let paymentId     = null;
const MAX_POLLS   = 12; // 12 × 5s = 60s timeout

function showAlert(type, msg) {
    const el = document.getElementById('stkAlert');
    el.className = 'alert alert-' + type;
    el.textContent = msg;
    el.classList.remove('d-none');
}

function showPollStatus(type, msg) {
    const el = document.getElementById('pollStatus');
    el.className = 'alert alert-' + type + ' small py-2';
    el.textContent = msg;
    el.classList.remove('d-none');
}

async function initiateSTK() {
    const phone  = document.getElementById('mpesaPhone').value.trim();
    const amount = document.getElementById('mpesaAmount').value;
    if (!phone || !amount) return showAlert('warning', 'Phone and amount are required.');

    document.getElementById('stkBtn').disabled = true;
    document.getElementById('stkAlert').classList.add('d-none');

    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('phone', phone);
    fd.append('amount', amount);
    fd.append('lease_id', LEASE_ID);
    if (INVOICE_ID) fd.append('invoice_id', INVOICE_ID);
    fd.append('account', 'RENT-' + LEASE_ID);

    try {
        const res  = await fetch(BASE_URL + '/api/mpesa_stk', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            checkoutId = data.checkout_request_id;
            paymentId  = data.payment_id;
            document.getElementById('stkForm').classList.add('d-none');
            document.getElementById('stkPending').classList.remove('d-none');
            startPolling();
        } else {
            showAlert('danger', data.message || 'Failed to initiate payment.');
            document.getElementById('stkBtn').disabled = false;
        }
    } catch (e) {
        showAlert('danger', 'Network error. Please try again.');
        document.getElementById('stkBtn').disabled = false;
    }
}

function startPolling() {
    pollAttempts = 0;
    scheduleNextPoll(5);
}

function scheduleNextPoll(delaySecs) {
    let secs = delaySecs;
    document.getElementById('pollSecs').textContent = secs;
    const ticker = setInterval(() => {
        secs--;
        document.getElementById('pollSecs').textContent = secs;
        if (secs <= 0) clearInterval(ticker);
    }, 1000);

    pollInterval = setTimeout(async () => {
        pollAttempts++;
        showPollStatus('info', `Checking status (attempt ${pollAttempts}/${MAX_POLLS})…`);

        try {
            const res  = await fetch(API_BASE + '/mpesa/stk-query', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + API_TOKEN },
                body: JSON.stringify({ checkout_request_id: checkoutId })
            });
            const data = await res.json();
            const code = data.data?.ResultCode;

            if (code === 0 || code === '0') {
                // Success
                showPollStatus('success', 'Payment completed!');
                document.getElementById('pollCountdown').textContent = 'Redirecting…';
                setTimeout(() => {
                    window.location.href = paymentId
                        ? BASE_URL + '/payments/view?id=' + paymentId
                        : BASE_URL + '/payments/index';
                }, 1500);
                return;
            }

            if (code === 1032) {
                // User cancelled
                showPollStatus('warning', 'Payment cancelled. You dismissed the prompt on your phone.');
                document.getElementById('pollCountdown').classList.add('d-none');
                return;
            }

            if (code !== undefined && code !== null && code !== 1037) {
                // Definitive failure (1037 = DS timeout — keep polling)
                showPollStatus('danger', data.data?.ResultDesc || 'Payment failed.');
                document.getElementById('pollCountdown').classList.add('d-none');
                return;
            }

            // Still pending
            if (pollAttempts >= MAX_POLLS) {
                showPollStatus('warning', 'Timed out. Check your M-Pesa messages. If paid, it will appear in your payment history.');
                document.getElementById('pollCountdown').classList.add('d-none');
                return;
            }

            scheduleNextPoll(5);
        } catch (err) {
            if (pollAttempts < MAX_POLLS) scheduleNextPoll(5);
            else showPollStatus('danger', 'Network error. Check your payment history.');
        }
    }, delaySecs * 1000);
}

function cancelPolling() {
    clearTimeout(pollInterval);
    clearInterval(pollInterval);
    document.getElementById('stkForm').classList.remove('d-none');
    document.getElementById('stkPending').classList.add('d-none');
    document.getElementById('stkBtn').disabled = false;
    document.getElementById('pollStatus').classList.add('d-none');
}

function resetForm() { cancelPolling(); }
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
