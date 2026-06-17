<?php
declare(strict_types=1);

/**
 * RUMS — Unified Error Page
 *
 * Apache sets REDIRECT_STATUS when serving an ErrorDocument.
 * Completely self-contained: no config includes, no DB calls.
 * Works even when the application itself is broken.
 */

// ── Detect status code ────────────────────────────────────────
$code = (int)(
    $_SERVER['REDIRECT_STATUS']   // Apache ErrorDocument
    ?? $_SERVER['HTTP_STATUS']    // some proxy setups
    ?? $_GET['code']              // manual override for testing
    ?? 500
);
if ($code < 100 || $code > 599) $code = 500;
http_response_code($code);

// ── Error catalogue ───────────────────────────────────────────
$catalogue = [
    400 => [
        'title' => 'Bad Request',
        'desc'  => 'The server could not understand the request due to invalid syntax or missing parameters.',
        'icon'  => 'bi-x-circle',
        'hex'   => '#e02424',
    ],
    403 => [
        'title' => 'Access Forbidden',
        'desc'  => "You don't have permission to view this page. Please log in with an authorised account or contact your administrator.",
        'icon'  => 'bi-shield-x',
        'hex'   => '#c05621',
    ],
    404 => [
        'title' => 'Page Not Found',
        'desc'  => "The page you're looking for doesn't exist or may have been moved. Check the URL and try again.",
        'icon'  => 'bi-compass',
        'hex'   => '#1a56db',
    ],
    405 => [
        'title' => 'Method Not Allowed',
        'desc'  => 'The HTTP method used is not supported for this resource. Please check your request and try again.',
        'icon'  => 'bi-slash-circle',
        'hex'   => '#6c29b3',
    ],
    419 => [
        'title' => 'Session Expired',
        'desc'  => 'Your session has expired or the security token is invalid. Please refresh the page and try again.',
        'icon'  => 'bi-clock-history',
        'hex'   => '#b57d00',
    ],
    429 => [
        'title' => 'Too Many Requests',
        'desc'  => "You've sent too many requests in a short time. Please wait a moment before trying again.",
        'icon'  => 'bi-hourglass-split',
        'hex'   => '#b57d00',
    ],
    500 => [
        'title' => 'Internal Server Error',
        'desc'  => 'Something went wrong on our end. Our team has been notified. Please try again or contact support if the problem persists.',
        'icon'  => 'bi-exclamation-triangle',
        'hex'   => '#9b1c1c',
    ],
    502 => [
        'title' => 'Bad Gateway',
        'desc'  => 'The server received an invalid response from an upstream service. Please try again in a moment.',
        'icon'  => 'bi-wifi-off',
        'hex'   => '#076980',
    ],
    503 => [
        'title' => 'Service Unavailable',
        'desc'  => 'The service is temporarily down, possibly for scheduled maintenance. Please try again shortly.',
        'icon'  => 'bi-tools',
        'hex'   => '#076980',
    ],
];

$err   = $catalogue[$code] ?? $catalogue[500];
$title = $err['title'];
$desc  = $err['desc'];
$icon  = $err['icon'];
$hex   = $err['hex'];

// ── Build home URL without relying on app config ──────────────
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost', ENT_QUOTES);
$homeUrl = $scheme . '://' . $host . '/';

// Convert hex to very-light background tint (alpha 10%)
// We do this inline so no external dep is needed.
$hexRgb  = ltrim($hex, '#');
$r = hexdec(substr($hexRgb, 0, 2));
$g = hexdec(substr($hexRgb, 2, 2));
$b = hexdec(substr($hexRgb, 4, 2));
$iconBg  = "rgba($r,$g,$b,.12)";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $code ?> · <?= htmlspecialchars($title) ?> — RUMS</title>
    <meta name="robots" content="noindex, nofollow">

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        /* ── Base ───────────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; }

        body {
            background: linear-gradient(135deg, #1e2a3b 0%, #1a56db 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            padding: 1.5rem;
            margin: 0;
        }

        /* ── Brand strip ────────────────────────────────────────── */
        .rums-brand {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-bottom: 1.75rem;
            font-size: .85rem;
            color: rgba(255,255,255,.65);
            font-weight: 500;
            letter-spacing: .01em;
        }
        .rums-brand i       { color: #f6b400; font-size: 1.2rem; }
        .rums-brand strong  { color: #fff; font-size: .95rem; }

        /* ── Error card ─────────────────────────────────────────── */
        .error-card {
            background: #fff;
            border-radius: 20px;
            padding: 3rem 2.5rem 2.5rem;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 24px 64px rgba(0,0,0,.35);
        }

        /* ── Icon ───────────────────────────────────────────────── */
        .error-icon {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            background: <?= $iconBg ?>;
        }
        .error-icon i { font-size: 2.4rem; color: <?= $hex ?>; }

        /* ── Code & text ────────────────────────────────────────── */
        .error-code {
            font-size: 5.5rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -4px;
            color: <?= $hex ?>;
            margin-bottom: .4rem;
        }
        .error-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1e2a3b;
            margin-bottom: .75rem;
        }
        .error-desc {
            color: #6b7280;
            font-size: .9rem;
            line-height: 1.65;
            margin-bottom: 2rem;
        }

        /* ── Divider ────────────────────────────────────────────── */
        .error-divider {
            border: none;
            border-top: 1px solid #f0f0f0;
            margin: 0 0 1.75rem;
        }

        /* ── Buttons ────────────────────────────────────────────── */
        .actions { display: flex; gap: .75rem; justify-content: center; flex-wrap: wrap; }

        .btn-primary-action {
            background: #1a56db;
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: .65rem 1.6rem;
            font-size: .9rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            transition: background .15s, transform .1s, box-shadow .15s;
            box-shadow: 0 2px 8px rgba(26,86,219,.3);
        }
        .btn-primary-action:hover {
            background: #1648c0;
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(26,86,219,.4);
        }

        .btn-secondary-action {
            color: #6b7280;
            background: #fff;
            border: 1.5px solid #e5e7eb;
            border-radius: 9px;
            padding: .65rem 1.4rem;
            font-size: .9rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            transition: border-color .15s, color .15s, background .15s;
        }
        .btn-secondary-action:hover {
            border-color: #9ca3af;
            color: #374151;
            background: #f9fafb;
        }

        /* ── Footer note ────────────────────────────────────────── */
        .error-ref {
            margin-top: 1.5rem;
            font-size: .75rem;
            color: #9ca3af;
        }
        .error-ref code {
            background: #f3f4f6;
            padding: .1em .4em;
            border-radius: 4px;
            font-size: .75em;
            color: #6b7280;
        }

        /* ── Responsive ─────────────────────────────────────────── */
        @media (max-width: 479px) {
            .error-card   { padding: 2rem 1.25rem 1.75rem; border-radius: 14px; }
            .error-code   { font-size: 4.5rem; letter-spacing: -3px; }
            .error-title  { font-size: 1.15rem; }
            .actions      { flex-direction: column; }
            .btn-primary-action,
            .btn-secondary-action { justify-content: center; width: 100%; }
        }
    </style>
</head>
<body>

    <!-- Brand -->
    <div class="rums-brand">
        <i class="bi bi-building-fill"></i>
        <strong>RUMS</strong>
        <span>Rental Unit Management System</span>
    </div>

    <!-- Error card -->
    <div class="error-card">

        <!-- Icon -->
        <div class="error-icon">
            <i class="bi <?= htmlspecialchars($icon) ?>"></i>
        </div>

        <!-- Code -->
        <div class="error-code"><?= $code ?></div>

        <!-- Title & description -->
        <div class="error-title"><?= htmlspecialchars($title) ?></div>
        <p class="error-desc"><?= htmlspecialchars($desc) ?></p>

        <hr class="error-divider">

        <!-- Action buttons -->
        <div class="actions">
            <a href="<?= $homeUrl ?>" class="btn-primary-action">
                <i class="bi bi-speedometer2"></i>
                Go to Dashboard
            </a>
            <button class="btn-secondary-action" onclick="history.length > 1 ? history.back() : window.location.href='<?= $homeUrl ?>'">
                <i class="bi bi-arrow-left"></i>
                Go Back
            </button>
        </div>

        <!-- Reference -->
        <div class="error-ref">
            HTTP <code><?= $code ?></code>
            &nbsp;·&nbsp; <?= date('D, d M Y H:i:s T') ?>
            <?php if (!empty($_SERVER['REQUEST_URI'])): ?>
                &nbsp;·&nbsp; <code><?= htmlspecialchars(substr($_SERVER['REQUEST_URI'], 0, 80)) ?></code>
            <?php endif; ?>
        </div>

    </div>

</body>
</html>
