# Security Architecture Document
## Rental Unit Management System (RUMS)

**Version:** 1.0  
**Date:** 2026-06-17  
**Classification:** Internal — Restricted  

---

## Table of Contents

1. [Security Principles](#1-security-principles)
2. [Authentication](#2-authentication)
3. [Authorisation (RBAC)](#3-authorisation-rbac)
4. [Data Protection](#4-data-protection)
5. [Transport Security](#5-transport-security)
6. [Input Validation & Output Encoding](#6-input-validation--output-encoding)
7. [CSRF Protection](#7-csrf-protection)
8. [Rate Limiting & Abuse Prevention](#8-rate-limiting--abuse-prevention)
9. [Security Headers](#9-security-headers)
10. [GDPR Compliance](#10-gdpr-compliance)
11. [Audit Logging](#11-audit-logging)
12. [Vulnerability Management](#12-vulnerability-management)
13. [Incident Response](#13-incident-response)

---

## 1. Security Principles

RUMS is designed around the following security principles:

| Principle | Implementation |
|---|---|
| **Defence in Depth** | Multiple layers: TLS → RBAC → token scopes → field-level encryption |
| **Least Privilege** | Each role token scoped to minimum required permissions |
| **Zero Trust Internal** | All API calls require a valid Bearer token — no IP-based trust |
| **Fail Secure** | Unknown errors return 500 (never expose stack traces in production) |
| **Privacy by Design** | PII encrypted at rest; data minimisation enforced |
| **Auditability** | All state-changing operations logged with user + timestamp + before/after |

---

## 2. Authentication

### 2.1 Password Storage

```
Storage: bcrypt with cost factor 12
Function: password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])
Verify:   password_verify($input, $hash)
```

Passwords are never stored in plain text, never logged, and never returned in API responses.

### 2.2 API Token Generation

```
Token: bin2hex(random_bytes(32))  → 64-character hex string
Storage: SHA-256 hash stored in api_tokens (plaintext also stored for current impl)
Binding: user_id + scopes + expires_at
Revocation: revoked flag set to 1; checked on every request
```

### 2.3 Session Security (Frontend)

```php
session_start();
// Session fixation prevention
session_regenerate_id(true);

// Session cookies
ini_set('session.cookie_httponly', 1);      // No JS access
ini_set('session.cookie_secure', 1);        // HTTPS only
ini_set('session.cookie_samesite', 'Lax');  // CSRF mitigation
ini_set('session.gc_maxlifetime', 7200);    // 2-hour TTL
```

### 2.4 Multi-Factor Authentication (TOTP)

**Algorithm:** RFC 6238 (TOTP)  
**Hash:** HMAC-SHA1  
**Period:** 30 seconds  
**Digits:** 6  
**Window:** ±1 period (allows for clock drift)  
**Backup codes:** 8 × 8-character codes, stored as bcrypt hashes

**MFA Flow:**

```
Login → credentials valid? → MFA enabled?
  NO  → Issue full token
  YES → Issue pending_token (10 min TTL)
        → Frontend: redirect to /auth/mfa_verify.php
        → User enters TOTP code
        → POST /auth/mfa/challenge {pending_token, code}
        → API: verify TOTP against encrypted secret
        → On success: mark pending_token used; issue full token
        → On failure: return 401 (log attempt)
```

**TOTP Secret Storage:**  
- Secret is AES-256-GCM encrypted before storage in `mfa_secrets.secret`
- The encryption key is the application `APP_ENCRYPTION_KEY`
- Secret is never returned in API responses after setup

### 2.5 Account Lockout

- Failed login attempts are rate-limited at the IP level (120 req/min).
- Suspended accounts (`status = 'suspended'`) are blocked at the API auth layer.
- Anonymized accounts (`data_anonymized = 1`) cannot authenticate.

---

## 3. Authorisation (RBAC)

### 3.1 Scope-Based Access Control

Every API token carries a space-separated list of scopes. Each endpoint calls:

```php
ApiAuth::requireScope($db, 'write:invoices');
// or
ApiAuth::requireRole($db, 'admin', 'manager');
```

If the token lacks the required scope, the API returns HTTP 403 — the token is valid but insufficiently privileged.

### 3.2 Row-Level Security

Beyond scope checks, certain endpoints enforce row-level isolation:

- **Tenant users** can only access their own leases, invoices, and payments (filtered by `tenant_id = auth_user.tenant_id`).
- **Landlord users** can only access properties linked to their `landlord_id`.
- **Maintenance users** can only update work orders assigned to their `user_id`.

### 3.3 Admin Privileges

Admin tokens have full scope but are still subject to:
- CSRF token validation on all POST/PATCH/DELETE form submissions
- Rate limiting
- Audit logging
- They cannot bypass row-level checks without explicit admin scope

---

## 4. Data Protection

### 4.1 AES-256-GCM Encryption

All sensitive PII fields are encrypted at rest using **AES-256-GCM** — an authenticated encryption scheme that both encrypts data and provides an integrity check.

```
Key:  32-byte random key from APP_ENCRYPTION_KEY env variable
IV:   12-byte random value generated per encryption operation
Tag:  16-byte GCM authentication tag
Format: "enc1:" + base64(iv + tag + ciphertext)
```

**Encrypted fields:**

| Table | Fields |
|---|---|
| tenants | phone, id_number, dob, monthly_income, occupation, employer, emergency_contact_name/phone, next_of_kin_name/phone |
| landlords | id_number, kra_pin, bank_account, mpesa_number |
| mfa_secrets | secret |

### 4.2 Deterministic Hashing for Uniqueness

Encrypted fields cannot be directly searched or have UNIQUE constraints. For ID numbers, a shadow hash column stores:

```
id_number_hash = SHA-256(LOWER(TRIM(plaintext_id_number)))
```

This allows uniqueness enforcement and exact-match lookups without storing plaintext.

### 4.3 Key Management

| Concern | Approach |
|---|---|
| Key storage | `.env` file only — never in DB or code |
| Key rotation | Companion CLI script re-encrypts all rows with new key |
| Key backup | Stored in secure password manager + HSM (production) |
| Dev/prod separation | Different keys for each environment |

### 4.4 Database Security

- MySQL user for the application has only `SELECT, INSERT, UPDATE, DELETE` on the RUMS database — no `DROP`, `ALTER`, or `GRANT`.
- Database port (3306) is not exposed externally — localhost/VPC only.
- All queries use **PDO prepared statements** — no string interpolation in SQL.

---

## 5. Transport Security

### 5.1 TLS Configuration

| Setting | Value |
|---|---|
| Minimum protocol | TLS 1.2 (TLS 1.3 preferred) |
| Certificate | Let's Encrypt (auto-renew via Certbot) |
| HSTS | `Strict-Transport-Security: max-age=31536000; includeSubDomains; preload` |
| Cipher suites | ECDHE-RSA-AES256-GCM-SHA384, ECDHE-RSA-CHACHA20-POLY1305 |

### 5.2 Internal API Communication

- Frontend-to-API communication goes over HTTPS even in internal deployments.
- cURL SSL verification is enabled in production (`CURLOPT_SSL_VERIFYPEER = true`).

---

## 6. Input Validation & Output Encoding

### 6.1 Input Validation

All API inputs are validated at the endpoint layer before any database operations:

```php
$amount = filter_var($body['amount'], FILTER_VALIDATE_FLOAT);
if ($amount === false || $amount <= 0) {
    ApiResponse::unprocessable(['amount' => 'Must be a positive number.']);
}
```

Validation rules:
- **Required fields** — checked for presence and non-empty.
- **Type validation** — integers, floats, dates, emails validated before use.
- **Range checks** — page numbers, month values, amounts capped at reasonable limits.
- **Enum validation** — role, status, payment_method values checked against allowed list.
- **SQL injection** — all queries use PDO prepared statements with bound parameters.

### 6.2 Output Encoding

All PHP values rendered in HTML use the `e()` helper:

```php
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
```

Used on every user-supplied value rendered in templates. JSON API responses are handled by `json_encode()` which escapes HTML characters by default.

### 6.3 File Upload Security

- Uploaded files are validated for MIME type (server-side via `finfo`).
- Files stored with UUID-based names (no user-controlled filename on disk).
- Upload directory is outside the web root — files served only via the API download endpoint.
- Maximum file size enforced in both PHP (`upload_max_filesize`) and the endpoint.

---

## 7. CSRF Protection

All state-changing form submissions require a CSRF token:

```php
// Generate (stored in session)
function csrf_field(): string {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

// Verify
function verify_csrf(): bool {
    return hash_equals(
        $_SESSION['csrf_token'] ?? '',
        $_POST['csrf_token'] ?? ''
    );
}
```

- Uses `hash_equals()` for timing-safe comparison.
- Token is regenerated after each successful form submission.
- AJAX calls from the same origin include the token in a custom header.

---

## 8. Rate Limiting & Abuse Prevention

### 8.1 API Rate Limiting

Implemented as a sliding-window counter in the `api_rate_limits` table:

```
Key: {ip}:{token_id}
Window: 60 seconds
Limit: 120 requests
On exceed: HTTP 429 + Retry-After header
```

### 8.2 Login Brute Force Protection

- Login endpoint is rate-limited per IP (120 req/min shared with all endpoints).
- Consistent response time for valid/invalid credentials (constant-time compare).
- No user enumeration: "Invalid email or password" message for all failures.

### 8.3 MFA Brute Force

- TOTP codes are single-use within a 30-second window.
- Pending tokens expire after 10 minutes regardless of attempts.
- Failed MFA attempts logged in `audit_logs`.

---

## 9. Security Headers

All API responses include:

```
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), camera=(), microphone=()
Content-Security-Policy: default-src 'none'
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload  (HTTPS only)
```

Frontend pages extend the CSP to allow:
```
default-src 'self';
script-src 'self' cdn.jsdelivr.net 'nonce-{random}';
style-src 'self' cdn.jsdelivr.net;
img-src 'self' data:;
connect-src 'self';
frame-ancestors 'none';
```

---

## 10. GDPR Compliance

### 10.1 Legal Basis

RUMS processes personal data under:
- **Contractual necessity** — tenant profile, lease, invoices (cannot opt out while tenancy is active)
- **Legitimate interest** — audit logs, security incident records
- **Consent** — marketing communications (optional, withdrawable)

### 10.2 Rights Implementation

| GDPR Right | Article | Implementation |
|---|---|---|
| Right of Access | Art. 15 | User can view own data in portal |
| Right to Rectification | Art. 16 | Profile edit in `/settings/account.php` |
| Right to Erasure | Art. 17 | Deletion request → admin anonymization workflow |
| Right to Portability | Art. 20 | JSON export via `/gdpr/export/request` |
| Right to Object | Art. 21 | Marketing consent withdrawal |

### 10.3 Anonymization Process

On admin approval of a deletion request:

```
1. users: name → "Deleted User #{id}", email → "deleted_{id}@anonymized.local",
          phone → NULL, password → random bcrypt, data_anonymized = 1, anonymized_at = NOW()
2. tenants: first_name/last_name/phone/id_number/dob/income → NULL or placeholder
3. api_tokens: all revoked (revoked = 1)
4. mfa_secrets: deleted
5. notifications: deleted
6. documents: soft-deleted (is_deleted = 1)
7. consent_records: RETAINED (legal record of consent history)
8. audit_logs: RETAINED (legal compliance — user_id SET NULL on delete)
9. invoices/payments: RETAINED (financial records — 7-year legal requirement)
```

### 10.4 Data Retention Schedule

| Data Type | Retention Period | Basis |
|---|---|---|
| Account & profile | Active + 30 days post-anonymization | Contractual |
| Financial records (invoices, payments) | 7 years | Kenya Tax Act / legal |
| Audit logs | 2 years | Regulatory |
| Login history (api_request_logs) | 90 days | Security |
| Maintenance records | 5 years | Property management |
| Consent records | Duration of relationship + 3 years | Legal evidence |

---

## 11. Audit Logging

### 11.1 What Is Logged

Every CREATE, UPDATE, DELETE, LOGIN, LOGOUT, and significant VIEW operation is logged:

```json
{
  "user_id": 1,
  "action": "UPDATE",
  "module": "leases",
  "entity_id": 42,
  "description": "Updated monthly_rent from 25000 to 27500",
  "ip_address": "197.248.x.x",
  "user_agent": "Mozilla/5.0 ...",
  "created_at": "2026-06-17T09:30:00Z"
}
```

### 11.2 Immutability

- `audit_logs` table has no UPDATE or DELETE endpoints — append-only by design.
- The application DB user has no `DELETE` privilege on `audit_logs`.
- Row-level access: only admins and auditors can read; no one can modify.

### 11.3 Log Storage

- Primary: MySQL `audit_logs` table (queryable, filterable).
- Recommended: Periodic export to immutable object storage (S3/R2) for long-term archival.

---

## 12. Vulnerability Management

### 12.1 Known Mitigations

| Threat | Mitigation |
|---|---|
| SQL Injection | All queries use PDO prepared statements |
| XSS | All output HTML-encoded via `e()`; CSP header |
| CSRF | Synchronised token pattern; SameSite cookies |
| Clickjacking | `X-Frame-Options: DENY` |
| Path Traversal | Files stored with UUID names; no user path control |
| Mass Assignment | All API inputs explicitly whitelisted |
| Insecure Deserialization | No serialized objects used; JSON only |
| Brute Force | Rate limiting; constant-time password compare |
| Session Fixation | Session ID regenerated on login |

### 12.2 Dependency Security

- Minimal external PHP dependencies (no Composer framework — custom lightweight stack).
- CDN assets (Bootstrap, Chart.js) pinned to specific versions with SRI hashes recommended.
- Regular review of CDN library versions against CVE databases.

---

## 13. Incident Response

### 13.1 Severity Levels

| Level | Description | Response Time |
|---|---|---|
| P1 — Critical | Data breach, auth bypass, production down | 1 hour |
| P2 — High | Privilege escalation, payment processing failure | 4 hours |
| P3 — Medium | Feature unavailable, minor data exposure | 24 hours |
| P4 — Low | UI bugs, non-critical performance | Next sprint |

### 13.2 Response Steps

1. **Contain** — Revoke affected tokens; suspend compromised accounts.
2. **Assess** — Query `audit_logs` to determine scope and timeline.
3. **Notify** — Alert affected users within 72 hours if PII is involved (GDPR requirement).
4. **Remediate** — Patch vulnerability; rotate encryption keys if necessary.
5. **Document** — Post-incident report within 7 days.

---

*End of Security Architecture Document*
