<?php
/**
 * RUMS — Environment loader
 *
 * Reads .env file if present (local/staging), falls back to system
 * environment variables (production containers / cloud).
 *
 * Include ONCE before config.php constants are defined.
 */

/**
 * Load a .env file into $_ENV / getenv().
 * Supports: KEY=value, KEY="quoted value", # comments, empty lines.
 */
function load_dotenv(string $path): void
{
    if (!file_exists($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Strip inline comments
        if (str_contains($value, ' #')) {
            $value = trim(explode(' #', $value, 2)[0]);
        }

        // Strip surrounding quotes
        if (strlen($value) >= 2 && in_array($value[0], ['"', "'"])
            && $value[0] === $value[strlen($value) - 1]) {
            $value = substr($value, 1, -1);
        }

        if (!isset($_ENV[$key]) && getenv($key) === false) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

/**
 * Read an environment variable with a default.
 */
function env(string $key, mixed $default = null): mixed
{
    $val = $_ENV[$key] ?? getenv($key);
    if ($val === false || $val === null || $val === '') return $default;

    // Cast booleans
    if (is_string($val)) {
        $lower = strtolower($val);
        if ($lower === 'true')  return true;
        if ($lower === 'false') return false;
        if ($lower === 'null')  return null;
    }
    return $val;
}

// ── Load .env from project root ───────────────────────────────
$_env_path = dirname(__DIR__) . '/.env';
load_dotenv($_env_path);

// ── Set PHP timezone ─────────────────────────────────────────
$tz = env('APP_TIMEZONE', 'Africa/Nairobi');
date_default_timezone_set($tz);

// ── Error reporting based on environment ─────────────────────
$app_env   = env('APP_ENV', 'production');
$app_debug = env('APP_DEBUG', false);

if ($app_debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
}

// ── Log path ─────────────────────────────────────────────────
$log_channel = env('LOG_CHANNEL', 'file');
if ($log_channel === 'stderr') {
    ini_set('error_log', 'php://stderr');
} else {
    $log_path = env('LOG_PATH', dirname(__DIR__) . '/storage/logs/app.log');
    $log_dir  = dirname($log_path);
    if (!is_dir($log_dir)) @mkdir($log_dir, 0775, true);
    ini_set('error_log', $log_path);
}
