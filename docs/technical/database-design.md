# Database Design Document
## Rental Unit Management System (RUMS)

**Version:** 1.0  
**Date:** 2026-06-17  
**Database:** MySQL 8.0+ · InnoDB · utf8mb4_unicode_ci  

---

## Table of Contents

1. [Overview](#1-overview)
2. [Entity Relationship Summary](#2-entity-relationship-summary)
3. [Table Catalogue](#3-table-catalogue)
4. [Key Relationships](#4-key-relationships)
5. [Indexes & Performance](#5-indexes--performance)
6. [Data Encryption Strategy](#6-data-encryption-strategy)
7. [Audit & Compliance Tables](#7-audit--compliance-tables)

---

## 1. Overview

The RUMS database contains **37 tables** across six functional domains:

| Domain | Tables | Description |
|---|---|---|
| **Core** | users, api_tokens, api_rate_limits, api_request_logs | Auth and API infrastructure |
| **Property** | landlords, properties, units | Physical asset hierarchy |
| **Tenancy** | tenants, lease_templates, leases, lease_renewals, lease_documents, kyc_documents | Tenant and lease lifecycle |
| **Finance** | invoices, payments, expenses, mpesa_transactions, bank_statement_entries | Billing and payments |
| **Operations** | maintenance_requests, documents, document_access_logs, notifications, settings | Day-to-day operations |
| **Security & Compliance** | visitor_logs, security_incidents, occupancy_logs, audit_logs, mfa_secrets, mfa_backup_codes, mfa_pending, consent_records, data_export_requests, data_deletion_requests | Security, MFA, and GDPR |
| **Communication** | message_templates, communication_logs, broadcast_messages, report_schedules | Messaging and reporting |

---

## 2. Entity Relationship Summary

```
users ──────────────────────────── api_tokens
  │                                    │
  ├── landlords ──── properties ──── units
  │        │              │            │
  │        │              │            ├── leases ──── invoices ──── payments
  │        │              │            │        │
  │        │              │            │        ├── lease_renewals
  │        │              │            │        └── lease_documents
  │        │              │            │
  │        │              │            └── maintenance_requests
  │        │              │
  │        │              └── visitor_logs / security_incidents / occupancy_logs
  │        │
  │        └── (via leases) tenants ──── kyc_documents
  │
  ├── documents ──── document_access_logs
  │
  ├── mfa_secrets / mfa_backup_codes / mfa_pending
  │
  ├── consent_records / data_export_requests / data_deletion_requests
  │
  ├── notifications
  │
  └── audit_logs
```

---

## 3. Table Catalogue

### 3.1 Core Tables

#### `users`
Central identity table for all system actors.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | Auto-increment |
| name | VARCHAR(150) | Display name |
| email | VARCHAR(150) UNIQUE | Login credential |
| phone | VARCHAR(30) | Contact number |
| role | ENUM | admin, manager, landlord, tenant, accountant, maintenance, auditor, security |
| password | VARCHAR(255) | bcrypt hash |
| status | ENUM | active, inactive, suspended |
| data_anonymized | TINYINT(1) DEFAULT 0 | GDPR anonymization flag |
| anonymized_at | DATETIME | Timestamp of anonymization |
| last_login | DATETIME | Last successful login |
| created_at | DATETIME | Record creation |

#### `api_tokens`
Bearer tokens issued at login, scoped per role.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | |
| user_id | FK → users | CASCADE delete |
| token | VARCHAR(255) UNIQUE | 64-byte hex |
| name | VARCHAR(100) | e.g. "Session Token" |
| scopes | TEXT | Space-separated permission list |
| revoked | TINYINT(1) DEFAULT 0 | |
| last_used | DATETIME | |
| expires_at | DATETIME | NULL = no expiry |

---

### 3.2 Property Tables

#### `landlords`
Extended profile for property owner users.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | |
| user_id | FK → users | CASCADE |
| id_number | TEXT | AES-256-GCM encrypted |
| id_number_hash | CHAR(64) UNIQUE | SHA-256 for uniqueness |
| company_name | VARCHAR(150) | Optional |
| kra_pin | TEXT | Encrypted |
| bank_name | VARCHAR(100) | |
| bank_account | TEXT | Encrypted |
| bank_branch | VARCHAR(100) | |
| mpesa_number | TEXT | Encrypted |
| commission_rate | DECIMAL(5,2) DEFAULT 0 | Management commission % |

#### `properties`

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | |
| name | VARCHAR(150) | |
| property_type | VARCHAR(50) | Residential, Commercial, Mixed |
| address_* | VARCHAR(100-150) | line1, line2, city, county, country |
| total_units | SMALLINT UNSIGNED | Updated on unit add/remove |
| landlord_id | FK → landlords | SET NULL on delete |
| manager_id | FK → users | SET NULL on delete |
| image | VARCHAR(255) | Relative file path |
| status | ENUM | active, inactive, deleted |

#### `units`

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | |
| property_id | FK → properties | CASCADE |
| unit_number | VARCHAR(30) | Unique per property |
| unit_type | VARCHAR(50) | Bedsitter, 1BR, 2BR, etc. |
| floor | TINYINT | |
| block_number | VARCHAR(30) | |
| bedrooms/bathrooms | TINYINT UNSIGNED | |
| size_sqft | DECIMAL(8,2) | |
| rent_amount | DECIMAL(12,2) | Advertised rent |
| deposit_amount | DECIMAL(12,2) | |
| furnished | TINYINT(1) | |
| water_included | TINYINT(1) | |
| electricity_included | TINYINT(1) | |
| utility_charge | DECIMAL(12,2) | |
| status | ENUM | available, occupied, maintenance, inactive, reserved |

---

### 3.3 Tenancy Tables

#### `tenants`
All PII fields encrypted at rest.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | |
| user_id | FK → users | SET NULL (optional account) |
| first_name / last_name | VARCHAR(80) | |
| email | VARCHAR(150) UNIQUE | Plain text (login identifier) |
| phone | TEXT | AES-256-GCM encrypted |
| id_number | TEXT | Encrypted |
| id_number_hash | CHAR(64) UNIQUE | SHA-256 for uniqueness |
| id_type | VARCHAR(20) | national_id, passport |
| dob | TEXT | Encrypted |
| gender | ENUM | male, female, other |
| nationality | VARCHAR(50) | |
| emergency_contact_* | TEXT | Encrypted |
| next_of_kin_* | TEXT | Encrypted |
| occupation/employer | TEXT | Encrypted |
| monthly_income | TEXT | Encrypted |
| status | ENUM | active, inactive, blacklisted |

#### `leases`
Core financial contract between tenant and unit.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | |
| lease_number | VARCHAR(30) UNIQUE | Auto: LSE-YYYY-XXXXX |
| lease_type | ENUM | fixed-term, periodic, commercial, furnished |
| template_id | FK → lease_templates | SET NULL |
| renewed_from_id | INT UNSIGNED | Self-reference to prior lease |
| unit_id | FK → units | |
| tenant_id | FK → tenants | |
| start_date / end_date | DATE | |
| monthly_rent | DECIMAL(12,2) | |
| deposit_amount | DECIMAL(12,2) | |
| payment_day | TINYINT UNSIGNED | Day of month (1–28) |
| grace_period_days | TINYINT UNSIGNED | Days after payment_day before penalty |
| penalty_rate | DECIMAL(5,2) | % per day |
| notice_period_days | SMALLINT UNSIGNED | |
| escalation_type | ENUM | none, fixed, percentage |
| escalation_rate | DECIMAL(5,2) | |
| escalation_frequency | ENUM | annually, biannually, quarterly |
| next_escalation_date | DATE | |
| status | ENUM | active, expired, terminated |

---

### 3.4 Finance Tables

#### `invoices`

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | |
| invoice_number | VARCHAR(30) UNIQUE | Auto: INV-YYYYMM-XXXXX |
| lease_id | FK → leases | |
| tenant_id | FK → tenants | |
| invoice_date / due_date | DATE | |
| rent_amount | DECIMAL(12,2) | |
| utility_amount | DECIMAL(12,2) | |
| penalty_amount | DECIMAL(12,2) | |
| discount_amount | DECIMAL(12,2) DEFAULT 0 | |
| total_amount | DECIMAL(12,2) | rent + utility + penalty − discount |
| amount_paid | DECIMAL(12,2) DEFAULT 0 | Running total of payments |
| period_month | TINYINT UNSIGNED | Billing month (1–12) |
| period_year | SMALLINT UNSIGNED | Billing year |
| status | ENUM | unpaid, partial, paid, overdue, cancelled |

**Unique constraint:** `(lease_id, period_year, period_month)` prevents duplicate billing.

#### `payments`

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | |
| payment_ref | VARCHAR(30) UNIQUE | Auto: PAY-YYYYMM-XXXXX |
| lease_id | FK → leases | |
| invoice_id | FK → invoices | SET NULL |
| tenant_id | FK → tenants | |
| unit_id | FK → units | SET NULL |
| amount | DECIMAL(12,2) | |
| payment_date | DATE | |
| payment_method | ENUM | cash, mpesa, bank, bank_transfer, cheque, card, other |
| payment_type | VARCHAR(50) | rent, deposit, utility, penalty |
| period_month/year | TINYINT / SMALLINT | |
| mpesa_transaction_id | VARCHAR(50) | Daraja transaction ID |
| mpesa_receipt | VARCHAR(50) | Safaricom receipt number |
| status | ENUM | completed, pending, reversed, failed |

#### `mpesa_transactions`
Raw Daraja API callback data before reconciliation.

| Column | Type | Notes |
|---|---|---|
| checkout_request_id | VARCHAR(100) UNIQUE | STK Push request ID |
| payment_id | FK → payments | SET NULL until matched |
| result_code | TINYINT | 0 = success |
| raw_response | MEDIUMTEXT | Full JSON callback |
| status | ENUM | pending, completed, failed |

---

### 3.5 Operations Tables

#### `maintenance_requests`

| Column | Type | Notes |
|---|---|---|
| request_number | VARCHAR(30) UNIQUE | MR-YYYYMM-XXXXX |
| unit_id | FK → units | |
| tenant_id | FK → tenants | SET NULL |
| issue_title | VARCHAR(200) | |
| priority | ENUM | low, medium, high, urgent |
| status | ENUM | open, in_progress, completed, resolved, cancelled |
| assigned_to | FK → users | Maintenance staff |
| work_started / work_completed | DATETIME | |
| materials_cost / labour_cost | DECIMAL(12,2) | |
| labour_hours | DECIMAL(6,2) | |
| is_recurring | TINYINT(1) | |
| next_due_date | DATE | |

#### `documents`

| Column | Type | Notes |
|---|---|---|
| uuid | CHAR(36) UNIQUE | Public v4 UUID reference |
| title | VARCHAR(200) | |
| document_type | ENUM | lease, tenant, property, certificate, financial, maintenance, other |
| entity_type | ENUM | lease, tenant, property, unit, general |
| entity_id | INT UNSIGNED | FK to entity (polymorphic) |
| file_name / stored_name | VARCHAR(255) | Original vs disk name |
| file_path | VARCHAR(500) | Relative to DOCUMENT_STORAGE |
| file_size | INT UNSIGNED | Bytes |
| version | TINYINT UNSIGNED | Starts at 1 |
| parent_id | FK → documents | Previous version |
| is_latest | TINYINT(1) DEFAULT 1 | |
| access_level | ENUM | private, internal, shared |
| is_deleted / deleted_at / deleted_by | — | Soft delete |
| uploaded_by | FK → users | RESTRICT |

---

## 4. Key Relationships

```
properties (1) ──────────── (N) units
units      (1) ──────────── (N) leases       [one active at a time]
tenants    (1) ──────────── (N) leases
leases     (1) ──────────── (N) invoices     [one per period]
invoices   (1) ──────────── (N) payments
leases     (1) ──────────── (N) payments     [direct link]
users      (1) ──────────── (1) mfa_secrets  [UNIQUE user_id]
users      (1) ──────────── (N) api_tokens
users      (1) ──────────── (N) consent_records
documents  (1) ──────────── (N) document_access_logs
documents  (1) ──────────── (N) documents    [parent_id self-reference]
```

---

## 5. Indexes & Performance

### Critical Indexes

| Table | Index | Type | Purpose |
|---|---|---|---|
| `invoices` | `(lease_id, period_year, period_month)` | UNIQUE | Prevent duplicate billing; fast period lookup |
| `invoices` | `(tenant_id)` | FK | Tenant invoice history |
| `payments` | `(lease_id)`, `(tenant_id)`, `(invoice_id)` | FK | Multi-join queries |
| `audit_logs` | `(user_id)`, `(action)`, `(module)`, `(created_at)` | INDEX | Filtered audit trail queries |
| `notifications` | `(user_id, is_read)` | INDEX | Unread count badge |
| `documents` | `(entity_type, entity_id)` | INDEX | Entity document lists |
| `documents` | `(uuid, is_latest, is_deleted)` | INDEX | UUID resolution |
| `mfa_pending` | `(pending_token)`, `(expires_at)` | INDEX | Fast token lookup + expiry cleanup |
| `api_rate_limits` | `(identifier, window_start)` | PK | Rate limit check |
| `tenants` | `(id_number_hash)` | UNIQUE | Fast ID lookup after encryption |

---

## 6. Data Encryption Strategy

### 6.1 Encrypted Fields

All PII fields use AES-256-GCM encryption via `Encryptor::encrypt()`:

| Table | Encrypted Columns |
|---|---|
| tenants | phone, id_number, dob, monthly_income, occupation, employer, emergency_contact_name, emergency_contact_phone, next_of_kin_name, next_of_kin_phone |
| landlords | id_number, kra_pin, bank_account, mpesa_number |
| mfa_secrets | secret (TOTP base32 key) |

### 6.2 Encryption Format

```
Ciphertext format: enc1:{base64(iv + tag + ciphertext)}

Where:
  enc1:     prefix identifying encrypted values
  iv        12-byte random initialisation vector
  tag       16-byte GCM authentication tag
  ciphertext AES-256-GCM encrypted payload
```

### 6.3 Hash Strategy

For fields requiring uniqueness (id_number), a separate `*_hash` column stores:

```
SHA-256(LOWER(TRIM(plaintext)))
```

This allows UNIQUE constraint enforcement without decrypting.

### 6.4 Encryption Key

- Stored in `.env` as `APP_ENCRYPTION_KEY` (32-byte hex).
- Must be rotated if compromised; companion script re-encrypts all rows.
- Never stored in the database or version control.

---

## 7. Audit & Compliance Tables

### `audit_logs`
Immutable event log — no UPDATE or DELETE operations permitted.

| Column | Notes |
|---|---|
| id | BIGINT — high volume expected |
| user_id | FK → users, SET NULL on delete |
| action | LOGIN, LOGOUT, CREATE, UPDATE, DELETE, VIEW |
| module | users, properties, leases, invoices, payments, etc. |
| entity_id | ID of the affected record |
| description | Human-readable summary |
| ip_address | Client IP |
| user_agent | Browser/client UA |

### `consent_records`
Append-only consent history (never updated, only new records added).

### `data_deletion_requests`
Tracks GDPR Art. 17 requests from submission through admin review to completion.

### `mfa_pending`
Short-lived challenge tokens cleaned up by cron after `expires_at`.

---

*End of Database Design Document*
