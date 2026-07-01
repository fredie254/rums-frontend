<?php
/**
 * RUMS - Helper Functions
 */

// ─── Security ────────────────────────────────────────────────
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): bool {
    $token = $_POST['csrf_token'] ?? '';
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// ─── Flash Messages ───────────────────────────────────────────
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function flash_html(): string {
    $flash = get_flash();
    if (!$flash) return '';
    $map = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'];
    $cls = $map[$flash['type']] ?? 'info';
    return '<div class="alert alert-' . $cls . ' alert-dismissible fade show" role="alert">'
        . e($flash['message'])
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

// ─── Redirect ─────────────────────────────────────────────────
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function redirect_back(string $fallback = '/'): void {
    redirect($_SERVER['HTTP_REFERER'] ?? $fallback);
}

// ─── Error pages ──────────────────────────────────────────────

/**
 * Render the unified error page and halt execution.
 *
 * Usage:  abort(403);   abort(404);   abort(503);
 *
 * Works even if output has already started — cleans the buffer first.
 * Can be called from any page after config is loaded.
 */
function abort(int $code = 500): never
{
    if (!headers_sent()) http_response_code($code);
    while (ob_get_level()) ob_end_clean();
    $_GET['code'] = $code;
    include BASE_PATH . '/errors/error.php';
    exit;
}

/**
 * Verify the CSRF token on the current POST request.
 * Renders the 419 (session expired / invalid token) error page on failure.
 *
 * Call at the top of every POST handler:
 *   csrf_check();
 */
function csrf_check(): void
{
    if (!verify_csrf()) {
        abort(419);
    }
}

// ─── Number / Currency ────────────────────────────────────────

/**
 * Format a monetary amount using the system currency settings.
 *
 * Reads from settings (cached per request):
 *   currency_symbol, currency_position, currency_decimals,
 *   currency_decimal_sep, currency_thousand_sep
 *
 * @param float $amount      The amount to format.
 * @param bool  $accounting  If true, negatives are shown as (1,234.56) instead of -1,234.56
 */
/**
 * Return a validated currency symbol.
 * Tries (in order): DB value → CURRENCY_SYMBOL constant → hardcoded 'Ksh'.
 * Rejects any value that is blank, purely numeric, or longer than 15 chars
 * (catches corrupted values like '262145' stored in the DB or .env).
 */
function _validated_currency_symbol(string $raw): string {
    $isValid = fn(string $v): bool => $v !== '' && !is_numeric($v) && strlen($v) <= 15;

    if ($isValid(trim($raw))) return trim($raw);

    // Constant may also be corrupted (e.g. .env has CURRENCY_SYMBOL=262145)
    $const = defined('CURRENCY_SYMBOL') ? trim((string)CURRENCY_SYMBOL) : '';
    if ($isValid($const)) return $const;

    return 'Ksh'; // hardcoded last resort
}

function money(int|float|string|null $amount, bool $accounting = false): string {
    // API responses can legitimately surface NULL for a monetary value — e.g. a SQL
    // SUM()/AVG() aggregate over zero matching rows is NULL, not 0. money() used to be
    // typed `float $amount` (non-nullable, no default): PHP does NOT coerce null to a
    // scalar type even outside strict_types, so money(null) threw an uncaught TypeError,
    // which the production exception handler turns into a hard HTTP 500. Coercing here
    // makes every call site safe without auditing every `money($x['key'])` call individually.
    $amount = is_numeric($amount) ? (float)$amount : 0.0;

    static $cfg = null;
    if ($cfg === null) {
        $cfg = [
            'symbol'   => _validated_currency_symbol(get_setting('currency_symbol', CURRENCY_SYMBOL)),
            'position' => get_setting('currency_position',     'before'),
            'decimals' => (int)get_setting('currency_decimals', '2'),
            'dec_sep'  => get_setting('currency_decimal_sep',  '.'),
            'thou_sep' => get_setting('currency_thousand_sep', ','),
        ];
    }

    $negative = $amount < 0;
    $abs      = abs($amount);

    // Format the number using correct separators
    $formatted = number_format($abs, $cfg['decimals'], $cfg['dec_sep'], $cfg['thou_sep']);

    // Apply symbol position
    $value = $cfg['position'] === 'after'
        ? $formatted . "\u{00A0}" . $cfg['symbol']
        : $cfg['symbol'] . "\u{00A0}" . $formatted;

    // Handle negative: parentheses (accounting) or minus sign
    if ($negative) {
        return $accounting ? '(' . $value . ')' : '−' . $value;
    }

    return $value;
}

/**
 * Format a currency code + amount for formal documents (invoices, statements).
 * E.g.  KES 45,000.00
 */
function money_formal(int|float|string|null $amount): string {
    $amount = is_numeric($amount) ? (float)$amount : 0.0;
    $raw  = trim(get_setting('currency_code', ''));
    $isValidCode = $raw !== '' && !is_numeric($raw) && strlen($raw) <= 10;
    $constCode   = defined('CURRENCY_CODE') ? trim((string)CURRENCY_CODE) : '';
    $code = $isValidCode ? $raw
          : (($constCode !== '' && !is_numeric($constCode) && strlen($constCode) <= 10) ? $constCode : 'KES');
    // Strip symbol from money() output and prepend ISO code
    static $cfg = null;
    if ($cfg === null) {
        $cfg = [
            'decimals' => (int)get_setting('currency_decimals', '2'),
            'dec_sep'  => get_setting('currency_decimal_sep',  '.'),
            'thou_sep' => get_setting('currency_thousand_sep', ','),
        ];
    }
    $negative  = $amount < 0;
    $formatted = number_format(abs($amount), $cfg['decimals'], $cfg['dec_sep'], $cfg['thou_sep']);
    $value     = $code . "\u{00A0}" . $formatted;
    return $negative ? '(' . $value . ')' : $value;
}

function pct(int|float|string|null $part, int|float|string|null $total): string {
    $part  = is_numeric($part)  ? (float)$part  : 0.0;
    $total = is_numeric($total) ? (float)$total : 0.0;
    if ($total == 0) return '0%';
    return round(($part / $total) * 100, 1) . '%';
}

// ─── Dates ───────────────────────────────────────────────────
function fmt_date(?string $date, string $format = 'd M Y'): string {
    if (empty($date)) return '—';
    try {
        return (new DateTime($date))->format($format);
    } catch (Throwable $e) {
        return '—';
    }
}

function month_name(int $month): string {
    return DateTime::createFromFormat('!m', $month)->format('F');
}

function days_overdue(string $due_date): int {
    if (empty($due_date)) return 0;
    try {
        $due = new DateTime($due_date);
        $now = new DateTime('today');
        return max(0, (int)$now->diff($due)->days * ($now > $due ? 1 : -1));
    } catch (Throwable $e) {
        return 0;
    }
}

// ─── Reference Generators ─────────────────────────────────────
function gen_lease_number(): string {
    return 'LSE-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function gen_invoice_number(): string {
    return 'INV-' . date('Ym') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function gen_payment_ref(): string {
    return 'PAY-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function gen_maintenance_number(): string {
    return 'MNT-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

// ─── Pagination ───────────────────────────────────────────────
function paginate(int $total, int $page, int $per_page = ROWS_PER_PAGE): array {
    $total_pages = (int)ceil($total / $per_page);
    $page = max(1, min($page, $total_pages));
    return [
        'total'       => $total,
        'per_page'    => $per_page,
        'page'        => $page,
        'total_pages' => $total_pages,
        'offset'      => ($page - 1) * $per_page,
    ];
}

function pagination_links(array $pg, string $url): string {
    if ($pg['total_pages'] <= 1) return '';
    $sep = strpos($url, '?') !== false ? '&' : '?';
    $html = '<nav><ul class="pagination pagination-sm justify-content-end mb-0">';
    $html .= '<li class="page-item' . ($pg['page'] <= 1 ? ' disabled' : '') . '">'
        . '<a class="page-link" href="' . $url . $sep . 'page=' . ($pg['page'] - 1) . '">&laquo;</a></li>';
    for ($i = max(1, $pg['page'] - 2); $i <= min($pg['total_pages'], $pg['page'] + 2); $i++) {
        $html .= '<li class="page-item' . ($i === $pg['page'] ? ' active' : '') . '">'
            . '<a class="page-link" href="' . $url . $sep . 'page=' . $i . '">' . $i . '</a></li>';
    }
    $html .= '<li class="page-item' . ($pg['page'] >= $pg['total_pages'] ? ' disabled' : '') . '">'
        . '<a class="page-link" href="' . $url . $sep . 'page=' . ($pg['page'] + 1) . '">&raquo;</a></li>';
    $html .= '</ul></nav>';
    return $html;
}

// ─── Status Badges ────────────────────────────────────────────
function unit_badge(string $status): string {
    $map = ['available' => 'success', 'occupied' => 'primary', 'maintenance' => 'warning', 'reserved' => 'info'];
    $cls = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $cls . '">' . ucfirst($status) . '</span>';
}

function lease_badge(string $status): string {
    $map = ['active' => 'success', 'expired' => 'secondary', 'terminated' => 'danger', 'pending' => 'warning'];
    $cls = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $cls . '">' . ucfirst($status) . '</span>';
}

function payment_badge(string $status): string {
    $map = ['completed' => 'success', 'pending' => 'warning', 'failed' => 'danger', 'reversed' => 'secondary'];
    $cls = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $cls . '">' . ucfirst($status) . '</span>';
}

function invoice_badge(string $status): string {
    $map = ['paid' => 'success', 'partial' => 'info', 'overdue' => 'danger', 'sent' => 'primary', 'draft' => 'secondary', 'cancelled' => 'dark'];
    $cls = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $cls . '">' . ucfirst($status) . '</span>';
}

function maintenance_badge(string $status): string {
    $map = ['open' => 'danger', 'assigned' => 'warning', 'in_progress' => 'info', 'completed' => 'success', 'closed' => 'secondary', 'cancelled' => 'dark'];
    $cls = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $cls . '">' . ucfirst(str_replace('_', ' ', $status)) . '</span>';
}

function priority_badge(string $priority): string {
    $map = ['low' => 'success', 'medium' => 'warning', 'high' => 'orange', 'urgent' => 'danger'];
    $cls = $map[$priority] ?? 'secondary';
    return '<span class="badge bg-' . $cls . '">' . ucfirst($priority) . '</span>';
}

// ─── Settings Retrieval ───────────────────────────────────────
function get_setting(string $key, string $default = ''): string {
    static $settings = null;
    if ($settings === null) {
        try {
            $res      = (new ApiClient())->get('settings');
            $settings = is_array($res['data'] ?? null) ? $res['data'] : [];
        } catch (Throwable $e) {
            $settings = [];
        }
    }
    $val = $settings[$key] ?? null;
    return ($val !== null && $val !== '') ? (string)$val : $default;
}

// ─── Notifications ────────────────────────────────────────────
function push_notification(int $user_id, string $title, string $message, string $type = 'info', string $link = ''): void {
    try {
        (new ApiClient())->post('notifications', compact('user_id', 'title', 'message', 'type', 'link'));
    } catch (Throwable $e) { /* non-fatal */ }
}

function unread_notification_count(int $user_id): int {
    try {
        $res = (new ApiClient())->get('notifications/unread-count');
        return (int)($res['data']['count'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

// ─── File Upload ──────────────────────────────────────────────
function upload_image(array $file, string $subfolder = 'general'): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > MAX_UPLOAD_SIZE) return null;
    if (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) return null;

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_', true) . '.' . strtolower($ext);
    $dir = UPLOAD_PATH . $subfolder . '/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        return $subfolder . '/' . $filename;
    }
    return null;
}

/**
 * Upload a KYC document. Returns the relative path on success, or sets
 * $error to a human-readable reason and returns null on failure.
 *
 * Uses server-side finfo MIME detection — never trusts the browser-supplied
 * Content-Type which is unreliable across iOS, Android, and desktop clients.
 */
function upload_document(array $file, string $subfolder = 'kyc', string &$error = ''): ?string
{
    // ── PHP-level upload errors ───────────────────────────────
    $phpErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload limit (' . ini_get('upload_max_filesize') . '). Ask your administrator to raise upload_max_filesize.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form size limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded. Please try again.',
        UPLOAD_ERR_NO_FILE    => 'No file was received by the server.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temporary directory is missing.',
        UPLOAD_ERR_CANT_WRITE => 'Server could not write the file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
    ];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = $phpErrors[$file['error']] ?? 'Upload error code ' . $file['error'] . '.';
        return null;
    }

    // ── Application size cap ──────────────────────────────────
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        $mb    = round(MAX_UPLOAD_SIZE / 1024 / 1024, 0);
        $error = 'File is too large (' . round($file['size'] / 1024 / 1024, 1) . ' MB). Maximum allowed size is ' . $mb . ' MB.';
        return null;
    }

    // ── Server-side MIME detection (never trust the browser) ─
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed = [
        'image/jpeg'      => 'jpg',
        'image/jpg'       => 'jpg',   // non-standard but common
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'application/pdf' => 'pdf',
        'application/x-pdf' => 'pdf', // some servers/clients
    ];

    if (!isset($allowed[$realMime])) {
        $error = 'Invalid file type (' . $realMime . '). Only PDF, JPEG, PNG and WebP files are accepted.';
        return null;
    }

    // ── Use the safe extension from the MIME map, not the filename ──
    $ext      = $allowed[$realMime];
    $filename = uniqid('kyc_', true) . '.' . $ext;
    $dir      = UPLOAD_PATH . $subfolder . '/';

    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        $error = 'Server could not create the upload directory. Contact support.';
        return null;
    }

    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        $error = 'Server could not save the file. Check directory permissions.';
        return null;
    }

    return $subfolder . '/' . $filename;
}

// ─── Input Sanitize ───────────────────────────────────────────
function clean(string $str): string {
    return trim(strip_tags($str));
}

function post(string $key, string $default = ''): string {
    return clean($_POST[$key] ?? $default);
}

function get_param(string $key, string $default = ''): string {
    return clean($_GET[$key] ?? $default);
}

function str_param(string $key, string $default = ''): string {
    return clean($_GET[$key] ?? $default);
}

function int_param(string $key, int $default = 0, string $source = 'get'): int {
    $val = $source === 'post' ? ($_POST[$key] ?? $default) : ($_GET[$key] ?? $default);
    return (int)filter_var($val, FILTER_SANITIZE_NUMBER_INT);
}
