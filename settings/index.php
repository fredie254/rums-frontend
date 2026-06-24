<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$api = new ApiClient();
$res = $api->get('settings');
$s   = $res['data'] ?? [];

// Currency defaults
$cur_code     = ($s['currency_code']         ?? '') ?: 'KES';
$_raw_sym     = trim($s['currency_symbol'] ?? '');
$cur_symbol   = ($s['currency_symbol'] ?? '' ) ?: 'Ksh';
if ($_raw_sym !== '' && (is_numeric($_raw_sym) || strlen($_raw_sym) > 15)) $cur_symbol = 'Ksh';
$cur_position = ($s['currency_position']     ?? '') ?: 'before';
$cur_decimals = ($s['currency_decimals']     ?? '') ?: '2';
$cur_dec_sep  = ($s['currency_decimal_sep']  ?? '') ?: '.';
$cur_thou_sep = ($s['currency_thousand_sep'] ?? '') ?: ',';

$page_title = 'System Settings';
include BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-4">
    <div>
        <h5 class="fw-bold mb-1"><i class="bi bi-sliders me-2 text-primary"></i>System Settings</h5>
        <small class="text-muted">Manage company profile, currency, payments and integrations</small>
    </div>
</div>

<!-- Alert container for AJAX feedback -->
<div id="settingsAlert" class="d-none mb-3"></div>

<!-- Tab Nav -->
<ul class="nav nav-tabs mb-0" id="settingsTabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-general" type="button" data-tab="general">
            <i class="bi bi-building me-1"></i><span class="d-none d-sm-inline">Company</span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-currency" type="button" data-tab="currency">
            <i class="bi bi-currency-exchange me-1"></i><span class="d-none d-sm-inline">Currency</span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-payments" type="button" data-tab="payments">
            <i class="bi bi-cash-coin me-1"></i><span class="d-none d-sm-inline">Payments</span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-mpesa" type="button" data-tab="mpesa">
            <i class="bi bi-phone me-1"></i><span class="d-none d-sm-inline">M-Pesa</span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-notifications" type="button" data-tab="notifications">
            <i class="bi bi-bell me-1"></i><span class="d-none d-sm-inline">Notifications</span>
        </button>
    </li>
</ul>

<div class="card shadow-sm" style="border-radius:0 0 10px 10px">
    <div class="card-body p-3 p-md-4">
        <div class="tab-content">

            <!-- ══════════════════════════════════════════════════
                 COMPANY
            ══════════════════════════════════════════════════ -->
            <div class="tab-pane fade show active" id="tab-general">
                <div class="row g-3 align-items-start">
                    <div class="col-lg-7">
                        <p class="text-muted small mb-3">This information appears on invoices, statements and tenant communications.</p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Company / Business Name</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-building"></i></span>
                                    <input type="text" name="company_name" class="form-control" value="<?= e($s['company_name'] ?? '') ?>" placeholder="RUMS Property Management">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Business Email</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                                    <input type="email" name="company_email" class="form-control" value="<?= e($s['company_email'] ?? '') ?>" placeholder="info@company.co.ke">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-telephone"></i></span>
                                    <input type="text" name="company_phone" class="form-control" value="<?= e($s['company_phone'] ?? '') ?>" placeholder="+254 700 000 000">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">KRA PIN / Tax ID</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-file-earmark-text"></i></span>
                                    <input type="text" name="company_kra_pin" class="form-control" value="<?= e($s['company_kra_pin'] ?? '') ?>" placeholder="P0512345678X">
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Physical Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-geo-alt"></i></span>
                                    <input type="text" name="company_address" class="form-control" value="<?= e($s['company_address'] ?? '') ?>" placeholder="123 Main Street, Nairobi, Kenya">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Website</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-globe"></i></span>
                                    <input type="url" name="company_website" class="form-control" value="<?= e($s['company_website'] ?? '') ?>" placeholder="https://www.company.co.ke">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">System Timezone</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-clock"></i></span>
                                    <select name="timezone" class="form-select">
                                        <?php
                                        $tz = $s['timezone'] ?? 'Africa/Nairobi';
                                        $zones = ['Africa/Nairobi' => 'Africa/Nairobi (EAT, UTC+3)', 'Africa/Johannesburg' => 'Africa/Johannesburg (SAST, UTC+2)', 'Africa/Lagos' => 'Africa/Lagos (WAT, UTC+1)', 'Africa/Accra' => 'Africa/Accra (GMT, UTC+0)', 'Europe/London' => 'Europe/London (GMT/BST)', 'America/New_York' => 'America/New_York (EST/EDT)', 'UTC' => 'UTC'];
                                        foreach ($zones as $k => $v): ?>
                                        <option value="<?= $k ?>" <?= $tz === $k ? 'selected' : '' ?>><?= $v ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="card bg-light border-0 h-100">
                            <div class="card-body">
                                <h6 class="fw-semibold mb-3"><i class="bi bi-card-text me-2 text-primary"></i>Invoice Header Preview</h6>
                                <div class="bg-white rounded p-3 shadow-sm" style="font-size:.82rem;line-height:1.7">
                                    <div class="fw-bold fs-6" id="prevCompanyName"><?= e($s['company_name'] ?? 'RUMS Property Management') ?></div>
                                    <div class="text-muted" id="prevCompanyAddr"><?= e($s['company_address'] ?? '123 Main Street, Nairobi') ?></div>
                                    <div><i class="bi bi-telephone me-1 text-muted"></i><span id="prevCompanyPhone"><?= e($s['company_phone'] ?? '+254 700 000 000') ?></span></div>
                                    <div><i class="bi bi-envelope me-1 text-muted"></i><span id="prevCompanyEmail"><?= e($s['company_email'] ?? 'info@company.co.ke') ?></span></div>
                                    <?php if (!empty($s['company_kra_pin'])): ?>
                                    <div class="mt-1 text-muted">PIN: <span id="prevKraPin"><?= e($s['company_kra_pin']) ?></span></div>
                                    <?php else: ?>
                                    <div class="mt-1 text-muted" id="prevKraPin" style="display:none"></div>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted d-block mt-2"><i class="bi bi-info-circle me-1"></i>Updates live as you type</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                    <button type="button" class="btn btn-primary px-4" onclick="saveTab('general',['company_name','company_email','company_phone','company_kra_pin','company_address','company_website','timezone'])">
                        <i class="bi bi-check-circle me-1"></i>Save Company Settings
                    </button>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════
                 CURRENCY
            ══════════════════════════════════════════════════ -->
            <div class="tab-pane fade" id="tab-currency">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <p class="text-muted small mb-3">Currency settings apply globally to all financial figures across the system.</p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Currency <span class="text-danger">*</span></label>
                                <?php
                                $currencies = [
                                    'KES' => ['name' => 'Kenyan Shilling',       'symbol' => 'Ksh',  'pos' => 'before', 'dec' => 2, 'dsep' => '.', 'tsep' => ','],
                                    'USD' => ['name' => 'US Dollar',              'symbol' => '$',    'pos' => 'before', 'dec' => 2, 'dsep' => '.', 'tsep' => ','],
                                    'EUR' => ['name' => 'Euro',                   'symbol' => '€',    'pos' => 'after',  'dec' => 2, 'dsep' => ',', 'tsep' => '.'],
                                    'GBP' => ['name' => 'British Pound',          'symbol' => '£',    'pos' => 'before', 'dec' => 2, 'dsep' => '.', 'tsep' => ','],
                                    'TZS' => ['name' => 'Tanzanian Shilling',     'symbol' => 'TSh',  'pos' => 'before', 'dec' => 0, 'dsep' => '.', 'tsep' => ','],
                                    'UGX' => ['name' => 'Ugandan Shilling',       'symbol' => 'USh',  'pos' => 'before', 'dec' => 0, 'dsep' => '.', 'tsep' => ','],
                                    'ZAR' => ['name' => 'South African Rand',     'symbol' => 'R',    'pos' => 'before', 'dec' => 2, 'dsep' => '.', 'tsep' => ','],
                                    'NGN' => ['name' => 'Nigerian Naira',         'symbol' => '₦',    'pos' => 'before', 'dec' => 2, 'dsep' => '.', 'tsep' => ','],
                                    'GHS' => ['name' => 'Ghanaian Cedi',          'symbol' => 'GH₵',  'pos' => 'before', 'dec' => 2, 'dsep' => '.', 'tsep' => ','],
                                    'RWF' => ['name' => 'Rwandan Franc',          'symbol' => 'RF',   'pos' => 'before', 'dec' => 0, 'dsep' => '.', 'tsep' => ','],
                                    'ETB' => ['name' => 'Ethiopian Birr',         'symbol' => 'Br',   'pos' => 'before', 'dec' => 2, 'dsep' => '.', 'tsep' => ','],
                                    'ZMW' => ['name' => 'Zambian Kwacha',         'symbol' => 'ZK',   'pos' => 'before', 'dec' => 2, 'dsep' => '.', 'tsep' => ','],
                                    'BWP' => ['name' => 'Botswana Pula',          'symbol' => 'P',    'pos' => 'before', 'dec' => 2, 'dsep' => '.', 'tsep' => ','],
                                    'NAD' => ['name' => 'Namibian Dollar',        'symbol' => 'N$',   'pos' => 'before', 'dec' => 2, 'dsep' => '.', 'tsep' => ','],
                                    'MUR' => ['name' => 'Mauritian Rupee',        'symbol' => '₨',    'pos' => 'before', 'dec' => 2, 'dsep' => '.', 'tsep' => ','],
                                    'AUD' => ['name' => 'Australian Dollar',      'symbol' => 'A$',   'pos' => 'before', 'dec' => 2, 'dsep' => '.', 'tsep' => ','],
                                    'CAD' => ['name' => 'Canadian Dollar',        'symbol' => 'CA$',  'pos' => 'before', 'dec' => 2, 'dsep' => '.', 'tsep' => ','],
                                    'INR' => ['name' => 'Indian Rupee',           'symbol' => '₹',    'pos' => 'before', 'dec' => 2, 'dsep' => '.', 'tsep' => ','],
                                    'AED' => ['name' => 'UAE Dirham',             'symbol' => 'AED',  'pos' => 'before', 'dec' => 2, 'dsep' => '.', 'tsep' => ','],
                                ];
                                ?>
                                <select name="currency_code" id="currencyCode" class="form-select" onchange="applyCurrencyPreset(this.value)">
                                    <?php foreach ($currencies as $code => $c): ?>
                                    <option value="<?= $code ?>"
                                        data-symbol="<?= $c['symbol'] ?>"
                                        data-position="<?= $c['pos'] ?>"
                                        data-decimals="<?= $c['dec'] ?>"
                                        data-dsep="<?= $c['dsep'] ?>"
                                        data-tsep="<?= $c['tsep'] ?>"
                                        <?= $cur_code === $code ? 'selected' : '' ?>>
                                        <?= $code ?> — <?= $c['name'] ?> (<?= $c['symbol'] ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Selecting a currency auto-fills the fields below</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Symbol Override</label>
                                <input type="text" name="currency_symbol" id="currencySymbol" class="form-control" value="<?= e($cur_symbol) ?>" placeholder="Ksh">
                                <small class="text-muted">Leave as-is or type a custom symbol</small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Symbol Position</label>
                                <select name="currency_position" id="currencyPosition" class="form-select">
                                    <option value="before" <?= $cur_position === 'before' ? 'selected' : '' ?>>Before (Ksh 1,000)</option>
                                    <option value="after"  <?= $cur_position === 'after'  ? 'selected' : '' ?>>After (1.000 €)</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-semibold">Decimals</label>
                                <select name="currency_decimals" id="currencyDecimals" class="form-select">
                                    <option value="0" <?= $cur_decimals === '0' ? 'selected' : '' ?>>0</option>
                                    <option value="2" <?= $cur_decimals === '2' ? 'selected' : '' ?>>2</option>
                                    <option value="3" <?= $cur_decimals === '3' ? 'selected' : '' ?>>3</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Decimal Sep.</label>
                                <select name="currency_decimal_sep" id="currencyDecSep" class="form-select">
                                    <option value="." <?= $cur_dec_sep === '.'  ? 'selected' : '' ?>>Period  1,234<strong>.</strong>56</option>
                                    <option value="," <?= $cur_dec_sep === ','  ? 'selected' : '' ?>>Comma   1.234<strong>,</strong>56</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Thousands Sep.</label>
                                <select name="currency_thousand_sep" id="currencyThouSep" class="form-select">
                                    <option value=","  <?= $cur_thou_sep === ','  ? 'selected' : '' ?>>Comma 1<strong>,</strong>234</option>
                                    <option value="."  <?= $cur_thou_sep === '.'  ? 'selected' : '' ?>>Period 1<strong>.</strong>234</option>
                                    <option value=" "  <?= $cur_thou_sep === ' '  ? 'selected' : '' ?>>Space 1 234</option>
                                    <option value="'"  <?= $cur_thou_sep === "'"  ? 'selected' : '' ?>>Apostrophe 1'234</option>
                                    <option value=""   <?= $cur_thou_sep === ''   ? 'selected' : '' ?>>None 1234</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Live preview -->
                    <div class="col-lg-5">
                        <p class="text-muted small mb-3">How amounts will appear everywhere in the system.</p>
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white d-flex align-items-center gap-2">
                                <i class="bi bi-eye text-primary"></i>
                                <span class="fw-semibold small">Live Preview</span>
                                <span class="ms-auto badge bg-primary-soft text-primary" id="prevCode"><?= e($cur_code) ?></span>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted ps-3">Monthly rent</td>
                                            <td class="text-end pe-3 fw-semibold" id="prev1"></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted ps-3">Total collected</td>
                                            <td class="text-end pe-3 fw-semibold text-success" id="prev2"></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted ps-3">Outstanding</td>
                                            <td class="text-end pe-3 fw-semibold text-danger" id="prev3"></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted ps-3">Balance (negative)</td>
                                            <td class="text-end pe-3 fw-semibold text-danger" id="prev5"></td>
                                        </tr>
                                        <tr class="border-0">
                                            <td class="text-muted ps-3">Zero balance</td>
                                            <td class="text-end pe-3 text-muted" id="prev4"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer bg-light border-0 py-2 px-3">
                                <small class="text-muted">Symbol: <strong id="prevSymbol"><?= e($cur_symbol) ?></strong> &nbsp;·&nbsp; Position: <strong id="prevPos"><?= $cur_position ?></strong></small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                    <button type="button" class="btn btn-primary px-4" onclick="saveTab('currency',['currency_code','currency_symbol','currency_position','currency_decimals','currency_decimal_sep','currency_thousand_sep'])">
                        <i class="bi bi-check-circle me-1"></i>Save Currency Settings
                    </button>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════
                 PAYMENTS
            ══════════════════════════════════════════════════ -->
            <div class="tab-pane fade" id="tab-payments">
                <p class="text-muted small mb-4">Configure rent collection rules, late fees and invoice policies.</p>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="fw-semibold mb-3"><i class="bi bi-calendar-check me-2 text-primary"></i>Late Payment Policy</h6>
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold">Grace Period</label>
                                        <div class="input-group">
                                            <input type="number" name="grace_period_days" class="form-control" value="<?= e($s['grace_period_days'] ?? '5') ?>" min="0" max="30">
                                            <span class="input-group-text">days</span>
                                        </div>
                                        <small class="text-muted">Days before penalty kicks in</small>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold">Late Penalty</label>
                                        <div class="input-group">
                                            <input type="number" name="late_payment_penalty" class="form-control" value="<?= e($s['late_payment_penalty'] ?? '5') ?>" step="0.5" min="0" max="100">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <small class="text-muted">Applied on overdue invoices</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="fw-semibold mb-3"><i class="bi bi-receipt me-2 text-primary"></i>Invoice Settings</h6>
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold">Invoice Due (days)</label>
                                        <div class="input-group">
                                            <input type="number" name="invoice_due_days" class="form-control" value="<?= e($s['invoice_due_days'] ?? '7') ?>" min="1" max="60">
                                            <span class="input-group-text">days</span>
                                        </div>
                                        <small class="text-muted">After issue date</small>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold">Invoice Prefix</label>
                                        <input type="text" name="invoice_prefix" class="form-control" value="<?= e($s['invoice_prefix'] ?? 'INV') ?>" maxlength="6" placeholder="INV">
                                        <small class="text-muted">e.g. INV-2025-0001</small>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Payment Terms Note</label>
                                        <textarea name="payment_terms" class="form-control" rows="2" placeholder="e.g. Payment is due within 7 days of invoice date."><?= e($s['payment_terms'] ?? '') ?></textarea>
                                        <small class="text-muted">Printed at the bottom of invoices</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                    <button type="button" class="btn btn-primary px-4" onclick="saveTab('payments',['grace_period_days','late_payment_penalty','invoice_due_days','invoice_prefix','payment_terms'])">
                        <i class="bi bi-check-circle me-1"></i>Save Payment Settings
                    </button>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════
                 M-PESA
            ══════════════════════════════════════════════════ -->
            <div class="tab-pane fade" id="tab-mpesa">
                <div class="alert alert-info d-flex gap-2 small mb-4">
                    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                    <div>
                        Obtain API credentials from <strong>developer.safaricom.co.ke</strong>.
                        Your callback URL must be HTTPS and publicly reachable by Safaricom.
                        Recommended: point it directly to <code><?= e(rtrim(defined('API_URL') ? API_URL : '', '/')) ?>/api/v1/mpesa/callback</code>.
                    </div>
                </div>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="fw-semibold mb-3"><i class="bi bi-key me-2 text-success"></i>Daraja API Credentials</h6>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Consumer Key</label>
                                        <div class="input-group">
                                            <input type="text" name="mpesa_consumer_key" id="mpesaConsumerKey" class="form-control font-monospace" value="<?= e($s['mpesa_consumer_key'] ?? '') ?>" autocomplete="off" placeholder="Enter consumer key">
                                            <button type="button" class="btn btn-outline-secondary" onclick="toggleVis('mpesaConsumerKey')"><i class="bi bi-eye"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Consumer Secret</label>
                                        <div class="input-group">
                                            <input type="password" name="mpesa_consumer_secret" id="mpesaConsumerSecret" class="form-control font-monospace" value="<?= e($s['mpesa_consumer_secret'] ?? '') ?>" autocomplete="off" placeholder="Enter consumer secret">
                                            <button type="button" class="btn btn-outline-secondary" onclick="toggleVis('mpesaConsumerSecret')"><i class="bi bi-eye"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold">Shortcode</label>
                                        <input type="text" name="mpesa_shortcode" class="form-control font-monospace" value="<?= e($s['mpesa_shortcode'] ?? '') ?>" placeholder="174379">
                                        <small class="text-muted">PayBill or Till number</small>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold">Environment</label>
                                        <select name="mpesa_env" class="form-select">
                                            <option value="sandbox" <?= ($s['mpesa_env'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' ?>>Sandbox (Testing)</option>
                                            <option value="live"    <?= ($s['mpesa_env'] ?? '') === 'live'    ? 'selected' : '' ?>>Live / Production</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Lipa Na M-Pesa Passkey</label>
                                        <div class="input-group">
                                            <input type="password" name="mpesa_passkey" id="mpesaPasskey" class="form-control font-monospace" value="<?= e($s['mpesa_passkey'] ?? '') ?>" autocomplete="off" placeholder="Lipa Na M-Pesa online passkey">
                                            <button type="button" class="btn btn-outline-secondary" onclick="toggleVis('mpesaPasskey')"><i class="bi bi-eye"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="fw-semibold mb-3"><i class="bi bi-link-45deg me-2 text-success"></i>Callback Configuration</h6>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Callback URL</label>
                                        <input type="url" name="mpesa_callback_url" class="form-control font-monospace small" value="<?= e($s['mpesa_callback_url'] ?? '') ?>" placeholder="https://yourdomain.com/api/v1/mpesa/callback">
                                        <small class="text-muted">Must be registered on Safaricom Daraja portal</small>
                                    </div>
                                    <div class="col-12">
                                        <div class="alert alert-warning d-flex gap-2 small mb-0 py-2">
                                            <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                                            <div>
                                                <strong>Production checklist:</strong><br>
                                                ✓ Switch environment to <em>Live</em><br>
                                                ✓ Use live consumer key &amp; secret<br>
                                                ✓ Register callback URL on Daraja<br>
                                                ✓ Callback URL must use HTTPS
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                    <button type="button" class="btn btn-primary px-4" onclick="saveTab('mpesa',['mpesa_consumer_key','mpesa_consumer_secret','mpesa_shortcode','mpesa_passkey','mpesa_env','mpesa_callback_url'])">
                        <i class="bi bi-check-circle me-1"></i>Save M-Pesa Settings
                    </button>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════
                 NOTIFICATIONS
            ══════════════════════════════════════════════════ -->
            <div class="tab-pane fade" id="tab-notifications">
                <p class="text-muted small mb-4">Configure how the system sends emails and SMS to tenants and administrators.</p>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="fw-semibold mb-3"><i class="bi bi-envelope me-2 text-primary"></i>Email (SMTP)</h6>
                                <div class="row g-3">
                                    <div class="col-sm-8">
                                        <label class="form-label fw-semibold">SMTP Host</label>
                                        <input type="text" name="smtp_host" class="form-control" value="<?= e($s['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label fw-semibold">Port</label>
                                        <input type="number" name="smtp_port" class="form-control" value="<?= e($s['smtp_port'] ?? '587') ?>" placeholder="587">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold">Username</label>
                                        <input type="text" name="smtp_user" class="form-control" value="<?= e($s['smtp_user'] ?? '') ?>" placeholder="you@gmail.com" autocomplete="off">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold">Password / App Key</label>
                                        <div class="input-group">
                                            <input type="password" name="smtp_pass" id="smtpPass" class="form-control" value="<?= e($s['smtp_pass'] ?? '') ?>" autocomplete="off">
                                            <button type="button" class="btn btn-outline-secondary" onclick="toggleVis('smtpPass')"><i class="bi bi-eye"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Encryption</label>
                                        <select name="smtp_encryption" class="form-select">
                                            <?php $enc = $s['smtp_encryption'] ?? 'tls'; ?>
                                            <option value="tls"  <?= $enc === 'tls'  ? 'selected' : '' ?>>TLS (recommended, port 587)</option>
                                            <option value="ssl"  <?= $enc === 'ssl'  ? 'selected' : '' ?>>SSL (port 465)</option>
                                            <option value="none" <?= $enc === 'none' ? 'selected' : '' ?>>None (port 25, not recommended)</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">From Name</label>
                                        <input type="text" name="smtp_from_name" class="form-control" value="<?= e($s['smtp_from_name'] ?? ($s['company_name'] ?? 'RUMS')) ?>" placeholder="RUMS Property Management">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="fw-semibold mb-3"><i class="bi bi-chat-dots me-2 text-success"></i>SMS (Africa's Talking)</h6>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">API Key</label>
                                        <div class="input-group">
                                            <input type="password" name="sms_api_key" id="smsApiKey" class="form-control font-monospace" value="<?= e($s['sms_api_key'] ?? '') ?>" autocomplete="off" placeholder="Africa's Talking API key">
                                            <button type="button" class="btn btn-outline-secondary" onclick="toggleVis('smsApiKey')"><i class="bi bi-eye"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold">Sender ID</label>
                                        <input type="text" name="sms_sender" class="form-control" value="<?= e($s['sms_sender'] ?? 'RUMS') ?>" placeholder="RUMS" maxlength="11">
                                        <small class="text-muted">Max 11 chars, no spaces</small>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold">Username</label>
                                        <input type="text" name="sms_username" class="form-control" value="<?= e($s['sms_username'] ?? '') ?>" placeholder="sandbox" autocomplete="off">
                                        <small class="text-muted">Use "sandbox" for testing</small>
                                    </div>
                                </div>

                                <hr class="my-3">
                                <h6 class="fw-semibold mb-3"><i class="bi bi-toggle-on me-2 text-primary"></i>Notification Triggers</h6>
                                <div class="row g-2">
                                    <?php
                                    $triggers = [
                                        'notify_invoice_created' => 'Invoice created',
                                        'notify_payment_received' => 'Payment received',
                                        'notify_lease_expiry' => 'Lease expiry reminder',
                                        'notify_maintenance_update' => 'Maintenance status update',
                                    ];
                                    foreach ($triggers as $key => $label): ?>
                                    <div class="col-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="<?= $key ?>" id="<?= $key ?>" value="1" <?= !empty($s[$key]) ? 'checked' : '' ?>>
                                            <label class="form-check-label small" for="<?= $key ?>"><?= $label ?></label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                    <button type="button" class="btn btn-primary px-4" onclick="saveTab('notifications',['smtp_host','smtp_port','smtp_user','smtp_pass','smtp_encryption','smtp_from_name','sms_api_key','sms_sender','sms_username','notify_invoice_created','notify_payment_received','notify_lease_expiry','notify_maintenance_update'])">
                        <i class="bi bi-check-circle me-1"></i>Save Notification Settings
                    </button>
                </div>
            </div>

        </div><!-- .tab-content -->
    </div><!-- .card-body -->
</div><!-- .card -->

<script>
// ── Currency presets ────────────────────────────────────────────
const CURRENCIES = <?= json_encode(array_map(fn($c) => [
    'symbol'   => $c['symbol'],
    'position' => $c['pos'],
    'decimals' => (string)$c['dec'],
    'dsep'     => $c['dsep'],
    'tsep'     => $c['tsep'],
], $currencies)) ?>;

function applyCurrencyPreset(code) {
    const c = CURRENCIES[code];
    if (!c) return;
    document.getElementById('currencySymbol').value   = c.symbol;
    document.getElementById('currencyPosition').value = c.position;
    document.getElementById('currencyDecimals').value = c.decimals;
    document.getElementById('currencyDecSep').value   = c.dsep;
    document.getElementById('currencyThouSep').value  = c.tsep;
    updatePreview();
}

function fmtMoney(amount, sym, pos, dec, dsep, tsep) {
    let [intPart, decPart] = Math.abs(amount).toFixed(parseInt(dec)).split('.');
    if (tsep) intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, tsep);
    let num = parseInt(dec) > 0 ? intPart + dsep + decPart : intPart;
    let val = pos === 'after' ? num + '\u00a0' + sym : sym + '\u00a0' + num;
    return amount < 0 ? '(' + val + ')' : val;
}

function updatePreview() {
    const sym  = document.getElementById('currencySymbol').value   || 'Ksh';
    const pos  = document.getElementById('currencyPosition').value || 'before';
    const dec  = document.getElementById('currencyDecimals').value || '2';
    const dsep = document.getElementById('currencyDecSep').value   || '.';
    const tsep = document.getElementById('currencyThouSep').value;
    const code = document.getElementById('currencyCode').value;
    document.getElementById('prev1').textContent = fmtMoney(45000,    sym, pos, dec, dsep, tsep);
    document.getElementById('prev2').textContent = fmtMoney(1285750,  sym, pos, dec, dsep, tsep);
    document.getElementById('prev3').textContent = fmtMoney(3250.5,   sym, pos, dec, dsep, tsep);
    document.getElementById('prev4').textContent = fmtMoney(0,        sym, pos, dec, dsep, tsep);
    document.getElementById('prev5').textContent = fmtMoney(-12500,   sym, pos, dec, dsep, tsep);
    document.getElementById('prevCode').textContent   = code;
    document.getElementById('prevSymbol').textContent = sym;
    document.getElementById('prevPos').textContent    = pos;
}

['currencySymbol','currencyPosition','currencyDecimals','currencyDecSep','currencyThouSep'].forEach(id => {
    const el = document.getElementById(id);
    el.addEventListener('input',  updatePreview);
    el.addEventListener('change', updatePreview);
});
updatePreview();

// ── Company header live preview ─────────────────────────────────
function bindPreview(inputName, targetId) {
    const inp = document.querySelector(`[name="${inputName}"]`);
    if (!inp) return;
    inp.addEventListener('input', () => {
        const el = document.getElementById(targetId);
        if (el) el.textContent = inp.value || inp.placeholder;
    });
}
bindPreview('company_name',    'prevCompanyName');
bindPreview('company_address', 'prevCompanyAddr');
bindPreview('company_phone',   'prevCompanyPhone');
bindPreview('company_email',   'prevCompanyEmail');
bindPreview('company_kra_pin', 'prevKraPin');

// ── Show/hide password ──────────────────────────────────────────
function toggleVis(id) {
    const el  = document.getElementById(id);
    const btn = el.nextElementSibling;
    if (el.type === 'password') {
        el.type = 'text';
        btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
    } else {
        el.type = 'password';
        btn.innerHTML = '<i class="bi bi-eye"></i>';
    }
}

// ── Per-tab AJAX save ───────────────────────────────────────────
function saveTab(tabName, fields) {
    const payload = {};
    fields.forEach(name => {
        const el = document.querySelector(`[name="${name}"]`);
        if (!el) return;
        if (el.type === 'checkbox') {
            payload[name] = el.checked ? '1' : '0';
        } else {
            payload[name] = el.value;
        }
    });

    const btn = event.currentTarget;
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

    fetch('<?= BASE_URL ?>/settings/save', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>'},
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        showAlert(res.success, res.message || (res.success ? 'Settings saved.' : 'Save failed.'));
    })
    .catch(() => showAlert(false, 'Network error. Please try again.'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
    });
}

function showAlert(success, message) {
    const el = document.getElementById('settingsAlert');
    el.className = 'alert alert-' + (success ? 'success' : 'danger') + ' alert-dismissible fade show mb-3';
    el.innerHTML = '<i class="bi bi-' + (success ? 'check-circle' : 'exclamation-triangle') + ' me-2"></i>'
        + message
        + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    if (success) setTimeout(() => bootstrap.Alert.getOrCreateInstance(el)?.close(), 4000);
}

// ── Persist active tab in localStorage ─────────────────────────
const TAB_KEY = 'rums_settings_tab';
const savedTab = localStorage.getItem(TAB_KEY);
if (savedTab) {
    const btn = document.querySelector(`[data-bs-target="${savedTab}"]`);
    if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
}
document.querySelectorAll('#settingsTabs [data-bs-toggle="tab"]').forEach(btn => {
    btn.addEventListener('shown.bs.tab', e => {
        localStorage.setItem(TAB_KEY, e.target.getAttribute('data-bs-target'));
    });
});
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
