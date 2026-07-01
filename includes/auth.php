<?php
/**
 * RUMS — Authentication helpers
 *
 * All authentication goes through the REST API.
 * Session stores: api_token, user_id, user_role, user_name, user_data.
 */

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']) && !empty($_SESSION['api_token']);
}

// ── Current user ──────────────────────────────────────────────

/**
 * Returns the authenticated user array from session cache.
 * Falls back to GET /auth/me if session cache is absent (e.g. after session restore).
 */
function current_user(): ?array
{
    if (!is_logged_in()) return null;

    // Validate cached user_data has a proper user structure (id + role).
    // Old sessions stored the full API envelope {user:{...},token:{...}} by mistake.
    $cached = $_SESSION['user_data'] ?? null;
    if (!empty($cached['id']) && !empty($cached['role'])) {
        return $cached;
    }

    // Cache is missing or has wrong structure — re-fetch and store correctly.
    unset($_SESSION['user_data']);

    $res = (new ApiClient())->get('auth/me');
    if (empty($res['success'])) return null;

    $_SESSION['user_data'] = $res['data']['user'] ?? $res['data'];
    return $_SESSION['user_data'];
}

// ── Access control ────────────────────────────────────────────

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Please login to continue.');
        redirect(BASE_URL . '/index');
    }
    $user = current_user();
    if (!$user || ($user['status'] ?? '') !== 'active') {
        session_destroy();
        redirect(BASE_URL . '/index?err=suspended');
    }
}

function require_role(string ...$roles): void
{
    require_login();
    $user = current_user();
    if (!in_array($user['role'], $roles, true)) {
        abort(403);
    }
}

// ── Role helpers ──────────────────────────────────────────────

function is_admin(): bool
{
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function is_manager(): bool
{
    $u = current_user();
    return $u && in_array($u['role'], ['admin', 'manager'], true);
}

/** Can view financial data (admin, manager, accountant, auditor) */
function is_finance(): bool
{
    $u = current_user();
    return $u && in_array($u['role'], ['admin', 'manager', 'accountant', 'auditor'], true);
}

/** Has read-only access — auditor sees all but cannot edit */
function is_auditor(): bool
{
    $u = current_user();
    return $u && $u['role'] === 'auditor';
}

/** Accountant role — financial operations */
function is_accountant(): bool
{
    $u = current_user();
    return $u && in_array($u['role'], ['admin', 'accountant'], true);
}

/** Maintenance staff */
function is_maintenance(): bool
{
    $u = current_user();
    return $u && in_array($u['role'], ['admin', 'manager', 'maintenance'], true);
}

/** Can view property data (owner/landlord and above) */
function is_landlord(): bool
{
    $u = current_user();
    return $u && in_array($u['role'], ['admin', 'manager', 'landlord', 'accountant', 'auditor'], true);
}

/** Security Officer — visitor and occupancy log access */
function is_security(): bool
{
    $u = current_user();
    return $u && in_array($u['role'], ['admin', 'manager', 'security'], true);
}

// ── Login / Logout ────────────────────────────────────────────

/**
 * Authenticate via POST /api/v1/auth/login.
 * Stores the returned Bearer token and user profile in session.
 */
function login_user(string $email, string $password): bool
{
    // Pass null so no Authorization header is sent
    $res = (new ApiClient(null))->post('auth/login', [
        'email'    => strtolower(trim($email)),
        'password' => $password,
    ]);

    if (empty($res['success'])) return false;

    // ── MFA challenge ─────────────────────────────────────────
    if ($res['data']['mfa_required'] ?? false) {
        $_SESSION['mfa_pending_token'] = $res['data']['pending_token'] ?? '';
        $_SESSION['mfa_pending_email'] = strtolower(trim($email));
        redirect(BASE_URL . '/auth/mfa_verify');
        return false; // unreachable
    }
    // ─────────────────────────────────────────────────────────

    $token = $res['data']['token'] ?? null;
    $user  = $res['data']['user']  ?? null;
    if (!$token || !$user) return false;

    session_regenerate_id(true);
    $_SESSION['api_token'] = $token;
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_data'] = $user;

    audit_log('LOGIN', 'auth', null, 'User logged in');

    return true;
}

/**
 * Complete MFA login: exchange pending_token + TOTP code for a full API token.
 * Returns true on success (session populated), false on failure.
 */
function mfa_login(string $pendingToken, string $code): bool|string
{
    $res = (new ApiClient(null))->post('auth/mfa/challenge', [
        'pending_token' => $pendingToken,
        'code'          => $code,
    ]);

    if (empty($res['success'])) {
        return $res['message'] ?? 'Invalid code.';
    }

    $token = $res['data']['token'] ?? null;
    $user  = $res['data']['user']  ?? null;
    if (!$token || !$user) return 'Unexpected response from server.';

    // Clear MFA session state
    unset($_SESSION['mfa_pending_token'], $_SESSION['mfa_pending_email']);

    session_regenerate_id(true);
    $_SESSION['api_token'] = $token;
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_data'] = $user;

    return true;
}

/**
 * Revoke token on the API then destroy the local session.
 */
function logout_user(): void
{
    audit_log('LOGOUT', 'auth', null, 'User logged out');

    if (!empty($_SESSION['api_token'])) {
        // Best-effort: ignore failure (network down, token already expired, etc.)
        (new ApiClient())->post('auth/logout');
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ── Audit Logging ─────────────────────────────────────────────

/**
 * No-op stub — audit logging is handled server-side by the REST API.
 * Kept here so page-level calls don't need to be removed one by one.
 */
function audit_log(string $action, string $module, ?int $record_id = null,
                   string $description = '', string $old_value = '', string $new_value = ''): void
{
    // intentionally empty
}
