# Functional Requirements Specification (FRS)
## Rental Unit Management System (RUMS)

**Version:** 1.0  
**Date:** 2026-06-17  
**Status:** Approved  
**References:** BRD v1.0  

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [System Overview](#2-system-overview)
3. [User Roles & Permissions](#3-user-roles--permissions)
4. [Module Specifications](#4-module-specifications)
5. [Business Rules](#5-business-rules)
6. [Non-Functional Requirements](#6-non-functional-requirements)

---

## 1. Introduction

### 1.1 Purpose

This document specifies the functional requirements for RUMS. It defines what the system must do, covering all modules, workflows, and user interactions. It serves as the contract between business stakeholders and the development team.

### 1.2 Definitions

| Term | Definition |
|---|---|
| Active Lease | A lease with status `active` and end_date in the future |
| Overdue Invoice | An invoice past its due_date with status `unpaid` or `partial` |
| Pending Token | A short-lived (10 min) MFA challenge token issued at login |
| UUID | Universally Unique Identifier used as the public document reference |
| KYC | Know Your Customer — identity verification documents |

---

## 2. System Overview

RUMS is a two-tier web application:

```
Browser (PHP Frontend)  ──────►  REST API (PHP)  ──────►  MySQL 8.x
  d:/Nexus/Rental/                d:/Nexus/Rental_api/
```

- All data access goes through the REST API at `/api/v1/`.
- The frontend authenticates using a Bearer token stored in `$_SESSION['api_token']`.
- The API enforces RBAC via token scopes.

---

## 3. User Roles & Permissions

### 3.1 Role Definitions

| Role | Description |
|---|---|
| `admin` | Full system access; manages users, properties, all data |
| `manager` | Operational access; no user management; can view all reports |
| `landlord` | Read-only view of own properties, income, and statements |
| `tenant` | Self-service portal; own lease, invoices, payments, maintenance |
| `accountant` | Financial modules; invoices, payments, reconciliation, reports |
| `maintenance` | Work orders assigned to them; no financial data access |
| `auditor` | Read-only across all modules; cannot modify any data |
| `security` | Visitor log, occupancy log, incidents only |

### 3.2 Permission Matrix

| Feature | admin | manager | landlord | tenant | accountant | maintenance | auditor | security |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| User Management | ✓ | — | — | — | — | — | — | — |
| Properties (CRUD) | ✓ | ✓ | R | — | — | — | R | — |
| Units (CRUD) | ✓ | ✓ | R | — | — | — | R | — |
| Tenants (CRUD) | ✓ | ✓ | — | Self | — | — | R | — |
| Leases (CRUD) | ✓ | ✓ | R | R | R | — | R | — |
| Invoices (CRUD) | ✓ | ✓ | — | R | ✓ | — | R | — |
| Payments (CRUD) | ✓ | ✓ | — | R | ✓ | — | R | — |
| Maintenance (CRUD) | ✓ | ✓ | — | Create/R | — | Update | R | — |
| Documents | ✓ | ✓ | — | Own | ✓ | — | R | — |
| Reports | ✓ | ✓ | Own | — | ✓ | — | R | — |
| Security Logs | ✓ | ✓ | — | — | — | — | R | ✓ |
| Audit Trail | ✓ | — | — | — | — | — | ✓ | — |
| Settings | ✓ | — | — | — | — | — | — | — |
| GDPR / Account | ✓ | Self | Self | Self | Self | Self | Self | Self |

---

## 4. Module Specifications

---

### 4.1 Authentication Module

#### FR-AUTH-01: Standard Login
- User submits email + password via `POST /auth/login`.
- System validates credentials against bcrypt hash.
- On success, a time-limited API token is issued with role-based scopes.
- Session stores: `api_token`, `user_id`, `user_role`, `user_name`.

#### FR-AUTH-02: MFA Challenge Flow
- If user has `is_enabled = 1` in `mfa_secrets`, login returns `mfa_required: true` + `pending_token`.
- Frontend stores `pending_token` in session and redirects to `/auth/mfa_verify.php`.
- User enters 6-digit TOTP code (or 8-character backup code).
- `POST /auth/mfa/challenge` validates token + code; issues full API token on success.
- Pending tokens expire after 10 minutes.

#### FR-AUTH-03: MFA Setup
- Any authenticated user may set up MFA via `/settings/account.php`.
- `POST /auth/mfa/setup` generates an encrypted TOTP secret + 8 one-time backup codes.
- User scans QR code in authenticator app and confirms with first TOTP code.
- `POST /auth/mfa/confirm` enables MFA by setting `is_enabled = 1`.

#### FR-AUTH-04: Password Change
- Users change password via `PATCH /users/{id}/password` with `current_password` + `new_password` fields.
- New password must be ≥ 8 characters.

#### FR-AUTH-05: Token Revocation
- All tokens for a user are revoked when: password is changed, MFA is disabled, account is suspended, or anonymization is performed.

---

### 4.2 Property Management Module

#### FR-PROP-01: Property CRUD
- Admin/Manager can create, read, update, delete properties.
- Fields: name, type, address (line1, line2, city, county, country), total_units, year_built, landlord_id, manager_id, description, amenities, image, status.
- Status transitions: `active` → `inactive` → `deleted`.

#### FR-PROP-02: Unit CRUD
- Each property contains units with: unit_number, type, floor, block, bedrooms, bathrooms, size_sqft, rent_amount, deposit_amount, furnished, water_included, electricity_included, utility_charge, status.
- Unit status: `available`, `occupied`, `maintenance`, `inactive`, `reserved`.
- Unit occupancy status updates automatically when a lease becomes active or terminates.

#### FR-PROP-03: Property Image Upload
- Property images uploaded via `POST /properties/{id}/image` (multipart/form-data).
- Stored in `/storage/images/properties/`.

---

### 4.3 Tenant Management Module

#### FR-TEN-01: Tenant Profile
- Profile fields: first_name, last_name, email, phone, id_number, id_type, dob, gender, nationality, emergency contacts, next of kin, occupation, employer, monthly_income, notes, status.
- All PII fields encrypted at rest using AES-256-GCM (`enc1:` prefix).
- `id_number_hash` stores SHA-256 of the normalised ID for uniqueness enforcement.

#### FR-TEN-02: Tenant User Account
- Optionally linked to a system user account (role: `tenant`).
- Account created via `POST /tenants/{id}/create-account`.

#### FR-TEN-03: KYC Documents
- Tenant may have multiple KYC documents (national ID scan, passport, employment letter, payslip).
- Uploaded via `POST /tenants/{id}/kyc`.
- Stored in `/storage/kyc/`.

#### FR-TEN-04: Tenant Status
- Status values: `active`, `inactive`, `blacklisted`.
- Blacklisted tenants cannot be assigned new leases.

---

### 4.4 Lease Management Module

#### FR-LEASE-01: Lease Creation
- Lease fields: lease_number (auto), type, unit, tenant, start_date, end_date, monthly_rent, deposit_amount, payment_day, grace_period_days, penalty_rate, notice_period_days, escalation config, signed_at, signed_by.
- Unit status changes to `occupied` on lease activation.
- One active lease per unit enforced at the API level.

#### FR-LEASE-02: Lease Templates
- Reusable templates with variable placeholders: `{{TENANT_NAME}}`, `{{UNIT_NUMBER}}`, `{{MONTHLY_RENT}}`, `{{START_DATE}}`, `{{END_DATE}}`.
- Templates scoped to lease type (fixed-term, periodic, commercial, furnished).

#### FR-LEASE-03: Rent Escalation
- Types: `none`, `fixed` (absolute KES amount), `percentage`.
- Frequencies: `annually`, `biannually`, `quarterly`.
- `next_escalation_date` recalculates monthly_rent when reached.

#### FR-LEASE-04: Lease Renewal
- Renewal generates a `lease_renewals` record with old and new terms.
- Status workflow: `pending` → `approved`/`rejected`.
- On approval, a new lease is created with `renewed_from_id` pointing to the original.

#### FR-LEASE-05: Lease Termination
- Admin/Manager can terminate a lease with `termination_reason` and `terminated_at`.
- Unit status reverts to `available` on termination.
- Outstanding invoices remain due; no new invoices generated.

---

### 4.5 Billing Module

#### FR-BILL-01: Invoice Generation
- Invoices generated monthly for each active lease on its `payment_day`.
- Invoice fields: invoice_number (auto), lease_id, tenant_id, invoice_date, due_date, rent_amount, utility_amount, penalty_amount, discount_amount, total_amount, amount_paid, period_month, period_year.
- Duplicate prevention: only one invoice per lease per period.

#### FR-BILL-02: Penalty Application
- Penalty = `monthly_rent × penalty_rate / 100` per day after grace period.
- Applied to the next invoice or as a standalone penalty invoice.

#### FR-BILL-03: Invoice Status Machine

```
unpaid → partial (payment received but < total)
unpaid/partial → paid (full payment received)
unpaid/partial → overdue (past due_date)
any → cancelled (manual admin action)
```

#### FR-BILL-04: Payment Recording
- Payments recorded with: amount, payment_date, payment_method, payment_type, period_month, period_year, reference fields.
- On save, linked invoice `amount_paid` updated; status recalculated.

#### FR-BILL-05: M-Pesa STK Push
- `POST /payments/mpesa/stkpush` initiates a Daraja C2B STK Push.
- Safaricom callback to `/api/v1/payments/mpesa/callback` confirms transaction.
- On confirmed `ResultCode=0`, a payment record is created automatically.

---

### 4.6 Maintenance Module

#### FR-MAINT-01: Request Submission
- Tenants and staff can submit requests with: unit, title, description, priority, category.
- Request number auto-generated (`MR-YYYYMM-XXXXX`).

#### FR-MAINT-02: Work Order Assignment
- Manager assigns request to maintenance staff member.
- Staff member receives in-app notification.

#### FR-MAINT-03: Status Workflow

```
open → in_progress (work started; work_started timestamp set)
in_progress → completed (work_completed + cost fields required)
completed → resolved (tenant confirmation)
any → cancelled
```

#### FR-MAINT-04: Cost Tracking
- Fields: labour_hours, materials_cost, labour_cost, contractor_name, contractor_phone.
- Total cost = materials_cost + labour_cost.

#### FR-MAINT-05: Recurring Maintenance
- `is_recurring = 1` + `next_due_date` creates new requests automatically.

---

### 4.7 Document Management Module

#### FR-DOC-01: Document Upload
- Upload via `POST /documents` (multipart/form-data).
- Stored as `{uuid}.ext` in `/storage/documents/`.
- Metadata: title, description, document_type, category, entity_type, entity_id, access_level.

#### FR-DOC-02: Access Levels
- `private` — uploader only.
- `internal` — all staff.
- `shared` — staff + associated tenant.

#### FR-DOC-03: Versioning
- New version via `POST /documents/{uuid}/version`.
- Previous `is_latest` set to 0; new version becomes `is_latest = 1`.
- All versions accessible via `GET /documents/{uuid}/versions`.

#### FR-DOC-04: Access Logging
- Every view, download, upload, or delete action recorded in `document_access_logs`.

#### FR-DOC-05: Download
- `GET /documents/{uuid}/download` — streams file with Content-Disposition header.
- Access token validated; access_level checked against user role.

---

### 4.8 Security Module

#### FR-SEC-01: Visitor Log
- Fields: visitor_name, visitor_phone, visitor_id_no, visitor_id_type, vehicle_reg, purpose, host_name, check_in, check_out, badge_no, status (`in`, `out`, `overstay`).
- Check-out time recorded on departure.
- Overstay auto-flagged if check_in > 12 hours with no check_out.

#### FR-SEC-02: Security Incidents
- Fields: incident_type, severity (`critical`, `high`, `medium`, `low`), incident_date, description, persons_involved, action_taken, police_ref, resolved.

#### FR-SEC-03: Occupancy Log
- Records tenant move-ins, move-outs, and access events per unit.

---

### 4.9 Reporting Module

#### FR-RPT-01: Occupancy Report
- Totals: total units, occupied, available, maintenance, occupancy rate %.
- Breakdown by property and by unit type.
- Leases expiring within 30 days.

#### FR-RPT-02: Arrears Report
- Monthly trend: billed vs collected vs outstanding (configurable 3–24 months).
- Worst offenders: tenant name, outstanding amount, overdue invoices, max days overdue.
- Breakdown by property.
- Collection effectiveness rate %.

#### FR-RPT-03: Revenue Report (Financial)
- Annual totals: collected, outstanding, collection rate %.
- Monthly revenue chart.
- Breakdown by payment type and payment method.
- Top 10 paying tenants.

#### FR-RPT-04: Maintenance Report
- Annual totals: total requests, open, total cost.
- Breakdown by status, priority, category.
- Per-property breakdown.

#### FR-RPT-05: Audit Report
- Filterable by date range, action, module, user, IP address.
- Paginated (50 per page).
- Shows: timestamp, user name/email/role, action, module, record ID, description, IP.
- Before/after diff viewer for UPDATE actions.
- CSV export with full history.

#### FR-RPT-06: CSV Export
- All tabular reports exportable as UTF-8 CSV with BOM (Excel-compatible).
- Export available via `GET /api/v1/reports/export?report={type}&format=csv`.

#### FR-RPT-07: Scheduled Reports
- Admin/Manager can define scheduled reports with: name, report_type, format, filters, frequency (daily/weekly/monthly), run_day, run_hour, recipients (email list).
- System emails reports automatically on schedule.

---

### 4.10 GDPR Module

#### FR-GDPR-01: Consent Management
- Three consent types: `terms` (required), `privacy` (required), `marketing` (optional).
- Each consent record stores: user_id, type, version, consented (1/0), ip_address, user_agent, created_at.

#### FR-GDPR-02: Data Export (Art. 20)
- `POST /gdpr/export/request` generates a one-time download token (1-hour TTL).
- `GET /gdpr/export/download?token=` streams a JSON file with all user data.
- Exported data includes: profile, tenant record, leases, payments, invoices, maintenance, documents, notifications, consents, audit_log.

#### FR-GDPR-03: Right to Erasure (Art. 17)
- `POST /gdpr/deletion/request` submits a deletion request with optional reason.
- Admins review requests in the Privacy & Data page.
- On approval: PII fields anonymized, tokens revoked, MFA deleted, notifications deleted, documents soft-deleted.
- Financial records and audit logs retained per legal requirements.

---

## 5. Business Rules

| ID | Rule |
|---|---|
| BR-R01 | A unit cannot have more than one active lease at a time |
| BR-R02 | A tenant in `blacklisted` status cannot be assigned to a new lease |
| BR-R03 | Only one invoice may be generated per lease per billing period |
| BR-R04 | Payments cannot be recorded against a cancelled invoice |
| BR-R05 | Lease end_date must be after start_date |
| BR-R06 | Monthly rent on invoices must match the lease monthly_rent at time of generation |
| BR-R07 | Audit log entries are immutable — no UPDATE or DELETE permitted |
| BR-R08 | Anonymized users (`data_anonymized=1`) cannot log in |
| BR-R09 | Admin users cannot anonymize themselves |
| BR-R10 | Documents with `access_level=private` are visible only to the uploading user |

---

## 6. Non-Functional Requirements

| ID | Category | Requirement |
|---|---|---|
| NFR-01 | Performance | API response time < 3 seconds for all endpoints under normal load |
| NFR-02 | Scalability | Architecture supports 10,000+ concurrent users via horizontal scaling |
| NFR-03 | Availability | 99.9% uptime (< 8.7 hours downtime/year) |
| NFR-04 | Security | All passwords bcrypt-hashed; PII encrypted AES-256-GCM at rest |
| NFR-05 | Security | HTTPS enforced; HSTS header sent on all responses over TLS |
| NFR-06 | Security | CSRF tokens required on all state-changing form submissions |
| NFR-07 | Security | API rate limiting: 120 requests/minute per IP/token |
| NFR-08 | Compliance | GDPR Art. 15, 16, 17, 20, 21 rights supported |
| NFR-09 | Usability | Responsive design supporting mobile (≥ 375px) and desktop |
| NFR-10 | Maintainability | All API changes version-namespaced under `/api/v1/` |

---

*End of Functional Requirements Specification*
