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
 * Return a validated currency symbol: falls back to the compile-time
 * constant if the DB value is blank, purely numeric, or suspiciously long.
 */
function _validated_currency_symbol(string $raw): string {
    $raw = trim($raw);
    if ($raw === '' || is_numeric($raw) || strlen($raw) > 15) {
        return CURRENCY_SYMBOL;
    }
    return $raw;
}

function money(float $amount, bool $accounting = false): string {
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
function money_formal(float $amount): string {
    $raw  = get_setting('currency_code', CURRENCY_CODE);
    $code = (trim($raw) === '' || is_numeric($raw) || strlen($raw) > 10) ? CURRENCY_CODE : trim($raw);
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

function pct(float $part, float $total): string {
    if ($total == 0) return '0%';
    return round(($part / $total) * 100, 1) . '%';
}

// ─── Dates ───────────────────────────────────────────────────
function fmt_date(?string $date, string $format = 'd M Y'): string {
    if (empty($date)) return '—';
    return (new DateTime($date))->format($format);
}

function month_name(int $month): string {
    return DateTime::createFromFormat('!m', $month)->format('F');
}

function days_overdue(string $due_date): int {
    $due = new DateTime($due_date);
    $now = new DateTime('today');
    return max(0, (int)$now->diff($due)->days * ($now > $due ? 1 : -1));
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
    $res = (new ApiClient())->get('notifications/unread-count');
    return (int)($res['data']['count'] ?? 0);
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

function upload_document(array $file, string $subfolder = 'kyc'): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > MAX_UPLOAD_SIZE) return null;
    $allowed = ['image/jpeg','image/png','image/webp','application/pdf'];
    if (!in_array($file['type'], $allowed)) return null;

    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid('kyc_', true) . '.' . $ext;
    $dir      = UPLOAD_PATH . $subfolder . '/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        return $subfolder . '/' . $filename;
    }
    return null;
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
    $val = ($source === 'post' ? $_POST[$key] : $_GET[$key]) ?? $default;
    return (int)filter_var($val, FILTER_SANITIZE_NUMBER_INT);
}
