# API Documentation
## RUMS REST API — v1

**Base URL:** `https://{domain}/api/v1/`  
**Format:** JSON  
**Auth:** Bearer Token (`Authorization: Bearer {token}`)  
**Version:** 1.0 · 2026-06-17  

---

## Table of Contents

1. [Authentication](#1-authentication)
2. [Request & Response Format](#2-request--response-format)
3. [Error Codes](#3-error-codes)
4. [Rate Limiting](#4-rate-limiting)
5. [Endpoints Reference](#5-endpoints-reference)

---

## 1. Authentication

### 1.1 Login

```http
POST /api/v1/auth/login
Content-Type: application/json

{ "email": "admin@example.com", "password": "secret" }
```

**Response — Standard Login (200):**
```json
{
  "success": true,
  "data": {
    "token": "abc123...",
    "user": { "id": 1, "name": "Alice", "role": "admin", "email": "admin@example.com" }
  }
}
```

**Response — MFA Required (200):**
```json
{
  "success": true,
  "message": "MFA required. Submit code to /auth/mfa/challenge.",
  "data": {
    "mfa_required": true,
    "pending_token": "def456...",
    "expires_in": 600
  }
}
```

### 1.2 MFA Challenge

```http
POST /api/v1/auth/mfa/challenge
Content-Type: application/json

{ "pending_token": "def456...", "code": "123456" }
```

**Response (200):** Same as standard login — returns full `token`.

### 1.3 Logout

```http
POST /api/v1/auth/logout
Authorization: Bearer {token}
```

Revokes the current token. Returns `{ "success": true }`.

### 1.4 Using the Token

Include the token in every authenticated request:

```http
GET /api/v1/properties
Authorization: Bearer {token}
```

---

## 2. Request & Response Format

### 2.1 Standard Response Envelope

```json
{
  "success": true | false,
  "message": "Optional message",
  "data": { ... }
}
```

### 2.2 Paginated Response

```json
{
  "success": true,
  "data": [ ... ],
  "meta": {
    "total": 150,
    "per_page": 20,
    "current_page": 1,
    "total_pages": 8
  }
}
```

### 2.3 Error Response

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": "Email is required.",
    "monthly_rent": "Must be a positive number."
  }
}
```

### 2.4 Common Query Parameters

| Parameter | Type | Description |
|---|---|---|
| `page` | int | Page number (default: 1) |
| `per_page` | int | Records per page (default: 20, max: 500) |
| `search` | string | Full-text search (where supported) |
| `status` | string | Filter by status field |
| `property_id` | int | Filter by property |
| `date_from` | date | Start of date range (YYYY-MM-DD) |
| `date_to` | date | End of date range (YYYY-MM-DD) |

---

## 3. Error Codes

| HTTP Code | Meaning | When |
|---|---|---|
| 200 | OK | Successful GET / action |
| 201 | Created | Successful POST |
| 400 | Bad Request | Validation failed or missing required field |
| 401 | Unauthorized | Missing or invalid token |
| 403 | Forbidden | Token valid but insufficient scope/role |
| 404 | Not Found | Resource does not exist |
| 409 | Conflict | Duplicate record (unique constraint) |
| 422 | Unprocessable | Business rule violation |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Server Error | Unhandled exception |

---

## 4. Rate Limiting

- **Limit:** 120 requests per minute per IP or token
- **Headers returned:**
  ```
  X-RateLimit-Limit: 120
  X-RateLimit-Remaining: 87
  X-RateLimit-Reset: 1718620800
  ```
- Exceeding the limit returns HTTP 429.

---

## 5. Endpoints Reference

---

### 5.1 Authentication

| Method | Endpoint | Description | Auth |
|---|---|---|---|
| POST | `/auth/login` | Login with email + password | No |
| POST | `/auth/logout` | Revoke current token | Yes |
| POST | `/auth/mfa/setup` | Begin MFA setup — returns secret + QR URI + backup codes | Yes |
| POST | `/auth/mfa/confirm` | Confirm setup with first TOTP code | Yes |
| POST | `/auth/mfa/challenge` | Complete login with TOTP/backup code | No (pending_token) |
| POST | `/auth/mfa/disable` | Disable MFA (requires password) | Yes |
| GET | `/auth/mfa/status` | Get MFA enabled status + backup code count | Yes |
| POST | `/auth/mfa/backup-codes/regenerate` | Regenerate 8 backup codes | Yes |

---

### 5.2 Users

| Method | Endpoint | Description | Role |
|---|---|---|---|
| GET | `/users` | List all users (paginated) | admin |
| POST | `/users` | Create user | admin |
| GET | `/users/{id}` | Get user by ID | admin / self |
| PATCH | `/users/{id}` | Update user profile | admin / self |
| DELETE | `/users/{id}` | Deactivate user | admin |
| PATCH | `/users/{id}/password` | Change password | self |

**POST /users body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "securepass",
  "role": "tenant",
  "phone": "+254700000000"
}
```

---

### 5.3 Properties

| Method | Endpoint | Description | Role |
|---|---|---|---|
| GET | `/properties` | List properties (paginated) | admin, manager, landlord |
| POST | `/properties` | Create property | admin, manager |
| GET | `/properties/{id}` | Get property detail | admin, manager, landlord |
| PATCH | `/properties/{id}` | Update property | admin, manager |
| DELETE | `/properties/{id}` | Soft-delete property | admin |
| POST | `/properties/{id}/image` | Upload property image | admin, manager |

---

### 5.4 Units

| Method | Endpoint | Description | Role |
|---|---|---|---|
| GET | `/units` | List units (filterable by property_id, status) | admin, manager, landlord |
| POST | `/units` | Create unit | admin, manager |
| GET | `/units/{id}` | Get unit detail | admin, manager, landlord |
| PATCH | `/units/{id}` | Update unit | admin, manager |
| DELETE | `/units/{id}` | Deactivate unit | admin |

---

### 5.5 Tenants

| Method | Endpoint | Description | Role |
|---|---|---|---|
| GET | `/tenants` | List tenants | admin, manager |
| POST | `/tenants` | Create tenant | admin, manager |
| GET | `/tenants/{id}` | Get tenant (decrypted PII) | admin, manager |
| PATCH | `/tenants/{id}` | Update tenant | admin, manager |
| DELETE | `/tenants/{id}` | Deactivate tenant | admin |
| POST | `/tenants/{id}/create-account` | Create linked user account | admin |
| POST | `/tenants/{id}/kyc` | Upload KYC document | admin, manager |
| GET | `/tenants/{id}/kyc` | List KYC documents | admin, manager |

---

### 5.6 Leases

| Method | Endpoint | Description | Role |
|---|---|---|---|
| GET | `/leases` | List leases (filterable by status, unit_id, tenant_id) | admin, manager, accountant, auditor |
| POST | `/leases` | Create lease | admin, manager |
| GET | `/leases/{id}` | Get lease detail | admin, manager, accountant, tenant (own) |
| PATCH | `/leases/{id}` | Update lease | admin, manager |
| POST | `/leases/{id}/terminate` | Terminate lease | admin, manager |
| GET | `/lease-templates` | List templates | admin, manager |
| POST | `/lease-templates` | Create template | admin, manager |
| GET | `/leases/{id}/renewals` | List renewal proposals | admin, manager |
| POST | `/leases/{id}/renewals` | Propose renewal | admin, manager |
| POST | `/lease-renewals/{id}/approve` | Approve renewal | admin, manager |
| POST | `/lease-renewals/{id}/reject` | Reject renewal | admin, manager |

---

### 5.7 Invoices

| Method | Endpoint | Description | Role |
|---|---|---|---|
| GET | `/invoices` | List invoices (filterable by tenant, lease, status, period) | admin, manager, accountant, auditor |
| POST | `/invoices` | Create single invoice | admin, manager, accountant |
| GET | `/invoices/{id}` | Get invoice | admin, manager, accountant, tenant (own) |
| PATCH | `/invoices/{id}` | Update invoice (notes, discount) | admin, manager, accountant |
| DELETE | `/invoices/{id}` | Cancel invoice | admin |
| POST | `/invoices/generate` | Bulk generate for active leases | admin, manager, accountant |

**POST /invoices/generate body:**
```json
{
  "month": 6,
  "year": 2026,
  "property_id": 1
}
```

---

### 5.8 Payments

| Method | Endpoint | Description | Role |
|---|---|---|---|
| GET | `/payments` | List payments | admin, manager, accountant, auditor |
| POST | `/payments` | Record payment manually | admin, manager, accountant |
| GET | `/payments/{id}` | Get payment detail | admin, manager, accountant, tenant (own) |
| DELETE | `/payments/{id}` | Reverse payment | admin |
| POST | `/payments/mpesa/stkpush` | Initiate M-Pesa STK Push | tenant |
| POST | `/payments/mpesa/callback` | Daraja callback receiver | Public (IP-restricted) |
| GET | `/payments/summary` | Summary by method for a period | admin, manager, accountant |

**POST /payments body:**
```json
{
  "invoice_id": 42,
  "lease_id": 7,
  "tenant_id": 3,
  "amount": 25000.00,
  "payment_date": "2026-06-05",
  "payment_method": "mpesa",
  "mpesa_receipt": "QGH7823KLM",
  "payment_type": "rent"
}
```

---

### 5.9 Maintenance

| Method | Endpoint | Description | Role |
|---|---|---|---|
| GET | `/maintenance` | List requests (filterable by status, priority, unit_id, assigned_to) | admin, manager, maintenance, auditor |
| POST | `/maintenance` | Create request | admin, manager, tenant, maintenance |
| GET | `/maintenance/{id}` | Get request detail | admin, manager, maintenance, tenant (own) |
| PATCH | `/maintenance/{id}` | Update status / assign / record costs | admin, manager, maintenance |
| DELETE | `/maintenance/{id}` | Cancel request | admin, manager |

---

### 5.10 Documents

| Method | Endpoint | Description | Role |
|---|---|---|---|
| GET | `/documents` | List documents (filterable by entity, type, access_level) | admin, manager, accountant, auditor |
| POST | `/documents` | Upload document (multipart/form-data) | admin, manager, accountant |
| GET | `/documents/{uuid}` | Get document metadata | role + access_level check |
| PATCH | `/documents/{uuid}` | Update metadata | admin, manager |
| DELETE | `/documents/{uuid}` | Soft-delete | admin, manager |
| GET | `/documents/{uuid}/download` | Stream file download | role + access_level check |
| GET | `/documents/{uuid}/versions` | List all versions | admin, manager |
| POST | `/documents/{uuid}/version` | Upload new version | admin, manager, accountant |
| GET | `/documents/{uuid}/access-log` | View access history | admin, manager |

---

### 5.11 Reports

| Method | Endpoint | Description | Role |
|---|---|---|---|
| GET | `/reports/financial` | Income vs expenses by period | admin, manager, accountant, auditor, landlord |
| GET | `/reports/occupancy` | Unit occupancy analysis | admin, manager, auditor |
| GET | `/reports/maintenance` | Repair analytics | admin, manager, auditor |
| GET | `/reports/rent-collection` | Monthly collection vs expected | admin, manager, accountant |
| GET | `/reports/arrears` | Outstanding balances trend | admin, manager, accountant, auditor |
| GET | `/reports/aging` | AR aging buckets | admin, manager, accountant, auditor |
| GET | `/reports/deposits` | Security deposit management | admin, manager, accountant |
| GET | `/reports/ledger` | Tenant debit/credit ledger | admin, manager, accountant, auditor |
| GET | `/reports/tenant-analytics` | Tenant retention + expiry analysis | admin, manager |
| GET | `/reports/dashboard` | KPI summary (units, revenue, AR, maintenance, leases) | admin, manager, accountant |
| GET | `/reports/export` | CSV download (`?report=financial&format=csv`) | admin, manager, accountant, auditor |

**GET /reports/financial params:**

| Param | Type | Default |
|---|---|---|
| date_from | date | Start of current year |
| date_to | date | Today |
| property_id | int | All properties |

---

### 5.12 GDPR

| Method | Endpoint | Description | Role |
|---|---|---|---|
| POST | `/gdpr/consent` | Record consent | Any |
| GET | `/gdpr/consents` | Get current consents for user | Any (own) |
| POST | `/gdpr/export/request` | Generate data export token | Any |
| GET | `/gdpr/export/download` | Download data JSON (`?token=`) | Public (token-gated) |
| POST | `/gdpr/deletion/request` | Submit deletion request | Any |
| GET | `/gdpr/deletion/status` | Get own deletion request status | Any |
| GET | `/gdpr/deletion/requests` | List pending requests | admin |
| POST | `/gdpr/deletion/{id}/process` | Approve or reject request | admin |

---

### 5.13 Security

| Method | Endpoint | Description | Role |
|---|---|---|---|
| GET | `/visitors` | List visitor logs | admin, manager, security |
| POST | `/visitors` | Log visitor check-in | security, admin, manager |
| PATCH | `/visitors/{id}` | Update (check-out, notes) | security, admin, manager |
| GET | `/incidents` | List security incidents | admin, manager, security, auditor |
| POST | `/incidents` | Report incident | security, admin, manager |
| PATCH | `/incidents/{id}` | Update incident | security, admin, manager |
| GET | `/occupancy-logs` | List occupancy events | admin, manager, security, auditor |
| POST | `/occupancy-logs` | Log occupancy event | security, admin, manager |

---

### 5.14 Audit Logs

| Method | Endpoint | Description | Role |
|---|---|---|---|
| GET | `/audit-logs` | Query audit trail (filterable, paginated) | admin, auditor |
| GET | `/audit-logs/meta` | Get distinct actions, modules, users for filter dropdowns | admin, auditor |

---

*End of API Documentation*
