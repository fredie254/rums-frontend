# System Architecture Document
## Rental Unit Management System (RUMS)

**Version:** 1.0  
**Date:** 2026-06-17  
**Status:** Approved  

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Component Architecture](#2-component-architecture)
3. [Technology Stack](#3-technology-stack)
4. [Directory Structure](#4-directory-structure)
5. [Request Flow](#5-request-flow)
6. [Authentication Architecture](#6-authentication-architecture)
7. [Deployment Architecture](#7-deployment-architecture)
8. [Scalability Design](#8-scalability-design)

---

## 1. Architecture Overview

RUMS follows a **two-tier decoupled architecture**: a PHP server-rendered frontend that communicates exclusively with a REST API backend. Both tiers are PHP applications but are independently deployable.

```
┌─────────────────────────────────────────────────────────────┐
│                        CLIENT TIER                          │
│   Browser (Bootstrap 5 + Chart.js + Bootstrap Icons)        │
└────────────────────────┬────────────────────────────────────┘
                         │ HTTPS (HTML Pages)
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                     FRONTEND TIER                           │
│   PHP 8.x  ·  d:/Nexus/Rental/                              │
│   Session-based auth  ·  ApiClient (cURL)                   │
└────────────────────────┬────────────────────────────────────┘
                         │ HTTPS JSON  (Bearer Token)
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                      API TIER                               │
│   PHP 8.x  ·  d:/Nexus/Rental_api/                          │
│   Router  ·  Services  ·  ApiAuth  ·  ApiResponse           │
└────────────────────────┬────────────────────────────────────┘
                         │ PDO / MySQL Protocol
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                     DATA TIER                               │
│   MySQL 8.x  ·  utf8mb4_unicode_ci                          │
│   37 tables  ·  Full FK constraints  ·  InnoDB              │
└─────────────────────────────────────────────────────────────┘
```

### Key Architectural Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Frontend/API separation | Two repos | Independent deployability, clear API contract |
| API communication | REST + JSON | Stateless, cacheable, debuggable |
| Auth tokens | Bearer (DB-stored) | Revocable, scoped, auditable |
| Rendering | Server-side PHP | Fast initial load, no JS build toolchain |
| Database | MySQL + InnoDB | ACID compliance, FK enforcement, mature tooling |
| Encryption | AES-256-GCM | Authenticated encryption; detects tampering |

---

## 2. Component Architecture

### 2.1 Frontend Components

```
d:/Nexus/Rental/
├── config/
│   └── config.php          # Constants, env(), require_login(), role helpers
├── includes/
│   ├── header.php           # Bootstrap shell, sidebar include
│   ├── sidebar.php          # Role-aware navigation
│   ├── footer.php           # Scripts (Bootstrap JS, Chart.js)
│   └── auth.php             # login_user(), mfa_login(), logout_user()
├── assets/
│   ├── css/style.css        # Custom styles (kpi-cards, sidebar, etc.)
│   └── js/                  # Page-specific scripts (inline on each page)
└── [module directories]/    # One directory per module
```

**Core helpers in `config.php`:**

| Helper | Purpose |
|---|---|
| `require_login()` | Redirects to login if no session |
| `require_role(...$roles)` | 403 if user role not in list |
| `is_admin()` / `is_manager()` / `is_finance()` | Role boolean checks |
| `e($str)` | `htmlspecialchars()` XSS escape |
| `money($amount)` | Formats as `KES X,XXX.XX` |
| `fmt_date($dt)` | Formats datetime for display |
| `csrf_field()` / `verify_csrf()` | CSRF token generation/verification |
| `set_flash()` / `flash_html()` | One-shot flash messages |
| `redirect($url)` | Safe redirect with session write |
| `env($key, $default)` | `.env` value lookup |

### 2.2 API Components

```
d:/Nexus/Rental_api/
├── index.php               # Bootstrap: routes registered, request dispatched
├── src/
│   ├── Router.php          # Pattern-based URL routing (GET/POST/PATCH/DELETE)
│   ├── ApiAuth.php         # Token validation, scope checking, requireRole()
│   ├── ApiResponse.php     # ok(), created(), paginated(), error responses + headers
│   ├── Encryptor.php       # AES-256-GCM encrypt/decrypt, hash()
│   └── TOTP.php            # RFC 6238 TOTP — generateSecret, verify, getUri
├── services/
│   ├── BaseService.php     # fetchOne(), fetchAll(), fetchColumn(), execute(), insert(), paginatedQuery()
│   ├── ReportService.php   # All report data methods
│   ├── GdprService.php     # exportUserData(), anonymizeUser(), consent*, deletion*
│   └── [other services]
├── endpoints/
│   ├── auth.php            # Login, logout, password, MFA routes
│   ├── users.php           # User CRUD
│   ├── properties.php      # Properties + units
│   ├── tenants.php         # Tenant CRUD + KYC
│   ├── leases.php          # Leases + templates + renewals
│   ├── invoices.php        # Invoice CRUD + bulk generation
│   ├── payments.php        # Payments + M-Pesa + bank summary
│   ├── maintenance.php     # Work orders
│   ├── documents.php       # Document repository
│   ├── reports.php         # All report endpoints
│   ├── notifications.php   # In-app notifications
│   ├── audit_logs.php      # Audit trail + meta
│   ├── security.php        # Visitors + incidents + occupancy
│   ├── mfa.php             # MFA setup/confirm/challenge/disable
│   └── gdpr.php            # Consent + export + deletion
└── migrations/
    └── 001–011.sql         # Incremental schema migrations
```

### 2.3 ApiClient (Frontend → API)

The frontend uses a custom `ApiClient` class to communicate with the API:

```php
$api = new ApiClient();               // Uses $_SESSION['api_token'] automatically

$api->get('properties', ['per_page' => 50]);
$api->post('invoices', $payload);
$api->patch('leases/' . $id, $data);
$api->delete('documents/' . $uuid);
```

All requests are made via cURL with:
- `Authorization: Bearer {token}` header
- JSON Content-Type
- SSL verification enabled (production)

---

## 3. Technology Stack

### 3.1 Backend

| Component | Technology | Version |
|---|---|---|
| Language | PHP | 8.1+ |
| Web Server | Apache / Nginx | — |
| Database | MySQL | 8.0+ |
| DB Driver | PDO + PDO_MySQL | Bundled |
| TOTP | Custom PHP (pure) | RFC 6238 |
| HTTP Client | cURL | Bundled |
| Encryption | OpenSSL (AES-256-GCM) | Bundled |

### 3.2 Frontend

| Component | Technology | Version |
|---|---|---|
| CSS Framework | Bootstrap | 5.3.3 |
| Icons | Bootstrap Icons | 1.11.3 |
| Charts | Chart.js | 4.x |
| QR Codes | qrcode-generator | CDN |
| JavaScript | Vanilla ES6+ | — |

### 3.3 Infrastructure

| Component | Technology |
|---|---|
| SSL/TLS | Let's Encrypt / hosting provider |
| File Storage | Local filesystem (`/storage/`) |
| Session Storage | PHP native file sessions |
| Cron Jobs | System cron (invoice generation, scheduled reports) |

---

## 4. Directory Structure

### 4.1 Module-to-Directory Mapping (Frontend)

| Module | Directory | Key Files |
|---|---|---|
| Auth | `/auth/` | `login.php`, `logout.php`, `mfa_verify.php` |
| Dashboard | `/dashboard/` | `index.php` |
| Properties | `/properties/` | `index.php`, `add.php`, `edit.php`, `view.php` |
| Units | `/units/` | `index.php`, `add.php`, `edit.php` |
| Landlords | `/landlords/` | `index.php`, `add.php`, `edit.php`, `view.php` |
| Tenants | `/tenants/` | `index.php`, `add.php`, `edit.php`, `view.php` |
| Leases | `/leases/` | `index.php`, `add.php`, `edit.php`, `view.php` |
| Invoices | `/invoices/` | `index.php`, `add.php`, `view.php`, `generate.php` |
| Payments | `/payments/` | `index.php`, `add.php`, `view.php` |
| Maintenance | `/maintenance/` | `index.php`, `add.php`, `view.php` |
| Maintenance Staff | `/maintenance_staff/` | `dashboard.php`, `work_orders.php` |
| Reports | `/reports/` | `index.php`, `financial.php`, `occupancy.php`, `arrears.php`, `maintenance.php`, `rent_collection.php`, `aging.php`, `deposits.php`, `tenants.php`, `ledger.php`, `dashboard.php`, `scheduled.php` |
| Documents | `/documents/` | `index.php`, `add.php`, `view.php` |
| Security | `/security/` | `dashboard.php`, `visitors.php`, `incidents.php`, `occupancy_log.php` |
| Auditor | `/auditor/` | `dashboard.php`, `audit_trail.php`, `compliance.php` |
| Accountant | `/accountant/` | `dashboard.php`, `reconciliation.php`, `aging.php`, `expenses.php`, `statements.php` |
| Landlord Portal | `/landlord/` | `dashboard.php`, `statement.php` |
| Tenant Portal | `/tenant/` | `dashboard.php`, `lease.php`, `invoices.php`, `payments.php`, `maintenance.php` |
| Notifications | `/notifications/` | `index.php` |
| Settings | `/settings/` | `index.php`, `account.php` |
| Users | `/users/` | `index.php`, `add.php`, `edit.php` |
| GDPR | `/gdpr/` | `index.php` |
| Profile | `/profile/` | `security.php` |

---

## 5. Request Flow

### 5.1 Standard Page Request

```
1. Browser → GET /invoices/index.php
2. PHP: require_once config.php
3. config.php: require_login() — checks $_SESSION['api_token']
4. PHP: $api->get('invoices', $params) via cURL
5. API Router: matches GET 'invoices' → endpoint handler
6. ApiAuth::requireScope($db, 'read:invoices')
7. Service method executes prepared SQL
8. ApiResponse::paginated($data, $meta) → JSON
9. Frontend PHP: parse JSON → render HTML
10. Browser: display page
```

### 5.2 Form Submission (State Change)

```
1. Browser → POST /invoices/add.php
2. PHP: verify_csrf() — validates hidden token
3. PHP: $api->post('invoices', $_POST) via cURL
4. API: validates input → creates record → logs audit
5. ApiResponse::created($record) → JSON
6. Frontend: set_flash('success', ...) → redirect()
7. Browser: GET (redirect) → display flash message
```

### 5.3 MFA Login Flow

```
1. POST /auth/login → API validates credentials
2. If mfa_enabled: returns {mfa_required: true, pending_token}
3. Frontend: stores pending_token in $_SESSION → redirect /auth/mfa_verify.php
4. User enters 6-digit TOTP code
5. POST /auth/mfa/challenge {pending_token, code}
6. API: validates pending_token + verifies TOTP against encrypted secret
7. On success: issues full API token → frontend populates session → redirect /dashboard
```

---

## 6. Authentication Architecture

### 6.1 Token Model

```
api_tokens table:
  token       VARCHAR(255)  — 64-byte hex (random_bytes(32))
  user_id     FK → users
  scopes      TEXT          — space-separated: "read:invoices write:invoices ..."
  revoked     TINYINT(1)
  expires_at  DATETIME      — NULL = no expiry (session tokens); set for temp tokens
```

### 6.2 Scope Catalogue

| Scope | Granted To |
|---|---|
| `read:properties` | admin, manager, landlord, auditor |
| `write:properties` | admin, manager |
| `read:tenants` | admin, manager, auditor |
| `write:tenants` | admin, manager |
| `read:leases` | admin, manager, landlord, tenant, accountant, auditor |
| `write:leases` | admin, manager |
| `read:invoices` | admin, manager, tenant, accountant, auditor |
| `write:invoices` | admin, manager, accountant |
| `read:payments` | admin, manager, tenant, accountant, auditor |
| `write:payments` | admin, manager, accountant |
| `read:maintenance` | admin, manager, tenant, maintenance, auditor |
| `write:maintenance` | admin, manager, tenant, maintenance |
| `read:reports` | admin, manager, accountant, auditor, landlord |
| `read:documents` | admin, manager, tenant, accountant, auditor |
| `write:documents` | admin, manager, accountant |
| `admin:users` | admin |

---

## 7. Deployment Architecture

### 7.1 Single-Server (Standard)

```
┌────────────────────────────────────────────────┐
│              Linux Server (Ubuntu 22.04)        │
│                                                  │
│  ┌──────────────┐   ┌────────────────────────┐  │
│  │   Nginx       │   │    PHP-FPM 8.1         │  │
│  │  (port 443)   │──►│                        │  │
│  │  SSL/TLS      │   │  /var/www/rental       │  │
│  └──────────────┘   │  /var/www/rental_api    │  │
│                      └───────────┬────────────┘  │
│                                  │               │
│                      ┌───────────▼────────────┐  │
│                      │   MySQL 8.0             │  │
│                      │  (localhost:3306)        │  │
│                      └────────────────────────┘  │
│                                                  │
│  ┌──────────────────────────────────────────┐   │
│  │  Cron Jobs                                │   │
│  │  0 1 * * *  php /var/www/rental_api/     │   │
│  │              cron/generate_invoices.php   │   │
│  │  */5 * * * * php ... scheduled_reports   │   │
│  └──────────────────────────────────────────┘   │
└────────────────────────────────────────────────┘
```

### 7.2 High-Availability (Scale-Out)

```
                        ┌─────────────────┐
                        │   Load Balancer  │
                        │   (Nginx/HAProxy)│
                        └────────┬────────┘
               ┌─────────────────┴─────────────────┐
               ▼                                   ▼
    ┌──────────────────┐                ┌──────────────────┐
    │   App Server 1    │                │   App Server 2   │
    │  PHP-FPM          │                │  PHP-FPM         │
    │  Frontend + API   │                │  Frontend + API  │
    └────────┬─────────┘                └────────┬─────────┘
             │                                   │
             └─────────────┬─────────────────────┘
                           ▼
              ┌────────────────────────┐
              │   MySQL Primary        │
              │   + Read Replica       │
              └────────────────────────┘
```

---

## 8. Scalability Design

### 8.1 Horizontal Scaling Readiness

| Concern | Approach |
|---|---|
| Session state | Move `$_SESSION` to Redis/Memcached for shared session across nodes |
| File storage | Move `/storage/` to object storage (S3-compatible, e.g., MinIO or Cloudflare R2) |
| Cron jobs | Use a dedicated worker node or job queue (Laravel Queue-compatible structure) |
| DB read scaling | Add MySQL read replica; route SELECT queries to replica |
| Rate limiting | Migrate `api_rate_limits` table to Redis for atomic increments |

### 8.2 Performance Targets

| Metric | Target |
|---|---|
| Concurrent users | 10,000+ |
| API response time (p95) | < 3 seconds |
| Database query time (p95) | < 500ms |
| Page load time | < 2 seconds (cached assets) |
| System availability | 99.9% monthly |

---

*End of System Architecture Document*
