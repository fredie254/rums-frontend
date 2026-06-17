<?php
if (defined('RUMS_CONFIG_LOADED')) return;
define('RUMS_CONFIG_LOADED', true);

/**
 * RUMS — Application Configuration
 * Reads from environment variables (.env or container env) with sensible defaults.
 */

// ── Load environment first ────────────────────────────────────
require_once __DIR__ . '/environment.php';

// ── Paths ─────────────────────────────────────────────────────
define('BASE_PATH', dirname(__DIR__));

// Build BASE_URL: prefer APP_URL env var (production/cloud) else auto-detect
$_app_url = env('APP_URL', '');
if ($_app_url) {
    define('BASE_URL', rtrim($_app_url, '/'));
} else {
    define('BASE_URL',
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') .
        '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') .
        rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\')
    );
}

// ── Application ───────────────────────────────────────────────
define('APP_NAME',       env('APP_NAME',    'RUMS'));
define('APP_FULL_NAME',  'Rental Unit Management System');
define('APP_VERSION',    env('APP_VERSION', '1.0.0'));
define('APP_ENV',        env('APP_ENV',     'production'));
define('APP_TIMEZONE',   env('APP_TIMEZONE','Africa/Nairobi'));
define('APP_KEY',        env('APP_KEY',     'changeme'));

// ── Session ───────────────────────────────────────────────────
define('SESSION_NAME',     env('SESSION_NAME',     'rums_session'));
define('SESSION_LIFETIME', (int)env('SESSION_LIFETIME', 7200));

// ── Uploads ───────────────────────────────────────────────────
define('UPLOAD_PATH',          BASE_PATH . '/assets/uploads/');
define('MAX_UPLOAD_SIZE',      (int)env('UPLOAD_MAX_SIZE', 5 * 1024 * 1024));
define('ALLOWED_IMAGE_TYPES',  ['image/jpeg','image/png','image/webp','image/gif']);

// ── Pagination ────────────────────────────────────────────────
define('ROWS_PER_PAGE', 20);

// ── Currency ──────────────────────────────────────────────────
define('CURRENCY_SYMBOL', env('CURRENCY_SYMBOL', 'Ksh'));
define('CURRENCY_CODE',   env('CURRENCY_CODE',   'KES'));

// ── API ───────────────────────────────────────────────────────
define('API_RATE_LIMIT',  (int)env('API_RATE_LIMIT',  120));
define('API_RATE_WINDOW', (int)env('API_RATE_WINDOW',  60));

// URL of the standalone RUMS REST API (no trailing slash).
// In production: https://api.rums.ultimatesolutions.co.ke
// In local dev:  http://localhost/Rental_api
define('API_URL', env('API_URL', 'http://localhost/Rental_api'));

// ── CORS ──────────────────────────────────────────────────────
define('CORS_ALLOWED_ORIGINS', env('CORS_ALLOWED_ORIGINS', '*'));

// ── Load database singleton ───────────────────────────────────
require_once __DIR__ . '/database.php';

// ── Start session (skip for API/CLI contexts) ─────────────────
if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => (bool)env('SESSION_SECURE', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => (bool)env('SESSION_HTTPONLY', true),
        'samesite' => env('SESSION_SAMESITE', 'Lax'),
    ]);
    session_start();
}

// ── Load helpers ─────────────────────────────────────────────
require_once BASE_PATH . '/includes/ApiClient.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';
