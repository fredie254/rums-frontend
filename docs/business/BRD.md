# Business Requirements Document (BRD)
## Rental Unit Management System (RUMS)

**Version:** 1.0  
**Date:** 2026-06-17  
**Status:** Approved  
**Prepared by:** RUMS Project Team  

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Business Context](#2-business-context)
3. [Stakeholders](#3-stakeholders)
4. [Business Objectives](#4-business-objectives)
5. [Business Requirements](#5-business-requirements)
6. [Scope](#6-scope)
7. [Constraints & Assumptions](#7-constraints--assumptions)
8. [Success Criteria](#8-success-criteria)
9. [Risk Summary](#9-risk-summary)

---

## 1. Executive Summary

The Rental Unit Management System (RUMS) is a comprehensive, web-based property management platform designed to digitise and streamline the end-to-end operations of residential and commercial rental properties in Kenya. The system replaces manual, spreadsheet-based workflows with a centralised, role-aware platform covering property management, tenant lifecycle, lease administration, billing, payments, maintenance, security, and compliance.

RUMS serves multiple user roles — from property administrators and landlords to tenants, accountants, maintenance staff, security officers, and auditors — through a single, integrated application backed by a RESTful API.

---

## 2. Business Context

### 2.1 Problem Statement

Property management companies in Kenya face a common set of operational challenges:

| Problem | Business Impact |
|---|---|
| Manual rent tracking in spreadsheets | Payment errors, missed collections, disputes |
| No centralised tenant records | Duplicate data, lost documents, KYC gaps |
| Paper-based lease agreements | Version control issues, no audit trail |
| Ad hoc maintenance management | Delayed repairs, poor contractor oversight |
| No landlord financial reporting | Reduced trust, manual reconciliation |
| Fragmented communication | Missed notices, rent reminder inefficiencies |
| No access control by role | Data leakage, compliance exposure |

### 2.2 Business Opportunity

The Kenyan rental property market manages an estimated 3+ million urban housing units. A well-designed SaaS property management system presents a significant opportunity to:

- Reduce rent collection time by automating invoicing and M-Pesa integration.
- Improve occupancy rates through proactive lease renewal management.
- Build landlord trust through transparent, real-time financial reporting.
- Ensure legal compliance with GDPR-aligned data protection practices.

---

## 3. Stakeholders

| Stakeholder | Role | Interest |
|---|---|---|
| **Property Owner / Landlord** | Business Owner | Revenue collection, portfolio performance, compliance |
| **Property Manager / Admin** | Platform Administrator | Full operational control, user management |
| **Tenant** | End User | Rent payment, lease access, maintenance requests |
| **Accountant** | Finance User | Invoicing, reconciliation, financial reports |
| **Maintenance Staff** | Operations User | Work order management, cost tracking |
| **Security Officer** | Safety User | Visitor logs, incident reporting, occupancy |
| **Auditor** | Compliance User | Read-only access to transactions and audit trail |
| **System Administrator** | Technical Owner | Deployment, uptime, database management |

---

## 4. Business Objectives

### 4.1 Primary Objectives

**BO-01 — Automated Revenue Collection**  
Reduce manual rent collection effort by 80% through automated invoice generation, M-Pesa payment integration, and real-time receipt confirmation.

**BO-02 — Centralised Tenant Management**  
Maintain a single, encrypted, KYC-compliant tenant record with full tenancy history, accessible to authorised staff only.

**BO-03 — Lease Lifecycle Automation**  
Automate lease creation, escalation schedules, renewal workflows, and expiry notifications to reduce vacancy periods.

**BO-04 — Real-Time Financial Visibility**  
Provide landlords and managers with live dashboards showing income, outstanding balances, occupancy rates, and expense summaries.

**BO-05 — Regulatory Compliance**  
Implement GDPR-aligned data processing, consent management, and right-to-erasure workflows to protect tenant data and business reputation.

**BO-06 — Maintenance Efficiency**  
Reduce average maintenance turnaround time by providing structured work order management with priority tracking, staff assignment, and cost recording.

### 4.2 Secondary Objectives

- **BO-07** — Provide multi-property portfolio management for landlords with multiple assets.
- **BO-08** — Enable secure document storage with versioning and role-based access.
- **BO-09** — Support scheduled automated reporting via email to reduce manual report generation.
- **BO-10** — Ensure system availability of 99.9% uptime to support business-critical payment flows.

---

## 5. Business Requirements

### 5.1 Property & Unit Management

| ID | Requirement |
|---|---|
| BR-001 | The system shall support multiple properties under a single account |
| BR-002 | Each property shall support unlimited rental units |
| BR-003 | Units shall track occupancy status in real time |
| BR-004 | Properties shall be assignable to a landlord and a property manager |
| BR-005 | The system shall support unit types: studio, 1BR, 2BR, 3BR, commercial, bedsitter |

### 5.2 Tenant Management

| ID | Requirement |
|---|---|
| BR-010 | The system shall maintain a complete KYC profile for each tenant |
| BR-011 | Sensitive tenant data (ID numbers, income) shall be encrypted at rest |
| BR-012 | Each tenant shall have a unique system-generated reference |
| BR-013 | Tenant records shall include emergency contacts and next-of-kin |
| BR-014 | The system shall support tenant blacklisting with reason tracking |

### 5.3 Lease Management

| ID | Requirement |
|---|---|
| BR-020 | The system shall support fixed-term, periodic, commercial, and furnished lease types |
| BR-021 | Leases shall auto-generate invoices on the configured billing day each month |
| BR-022 | The system shall support annual/quarterly rent escalation with configurable rates |
| BR-023 | Lease templates shall be reusable with variable substitution |
| BR-024 | Lease renewal shall trigger an approval workflow before a new lease is activated |

### 5.4 Billing & Payments

| ID | Requirement |
|---|---|
| BR-030 | Invoices shall itemise rent, utility charges, penalties, and discounts |
| BR-031 | The system shall support payment via M-Pesa, bank transfer, cash, cheque, and card |
| BR-032 | M-Pesa STK Push payments shall auto-reconcile via Daraja API callback |
| BR-033 | Late payment penalties shall accrue automatically after the grace period |
| BR-034 | The system shall generate payment receipts downloadable as PDF |

### 5.5 Maintenance

| ID | Requirement |
|---|---|
| BR-040 | Tenants shall be able to submit maintenance requests with photo attachments |
| BR-041 | Requests shall be prioritised as low, medium, high, or urgent |
| BR-042 | Work orders shall be assignable to named maintenance staff |
| BR-043 | The system shall track materials cost, labour cost, and contractor details |
| BR-044 | Recurring maintenance schedules shall be supported |

### 5.6 Reporting

| ID | Requirement |
|---|---|
| BR-050 | The system shall produce occupancy reports by property and unit type |
| BR-051 | Financial reports shall show income, expenses, and outstanding balances |
| BR-052 | Arrears reports shall rank tenants by overdue balance with aging buckets |
| BR-053 | Maintenance reports shall show category breakdowns, costs, and turnaround times |
| BR-054 | Audit reports shall provide a tamper-evident log of all user actions |
| BR-055 | Reports shall be exportable as CSV for offline analysis |
| BR-056 | Scheduled reports shall be automatically emailed on configurable intervals |

### 5.7 Security & Compliance

| ID | Requirement |
|---|---|
| BR-060 | Role-Based Access Control shall restrict features to authorised roles |
| BR-061 | Multi-Factor Authentication shall be available (optional) for all users |
| BR-062 | All sensitive data fields shall be encrypted using AES-256-GCM at rest |
| BR-063 | Users shall be able to download their personal data (GDPR Art. 20) |
| BR-064 | Users shall be able to request account deletion (GDPR Art. 17) |
| BR-065 | Consent to terms, privacy policy, and marketing shall be tracked per user |

---

## 6. Scope

### 6.1 In Scope

- Multi-property, multi-unit management
- Full tenant lifecycle (onboarding → offboarding)
- Lease creation, templating, renewals, and escalation
- Automated monthly invoice generation
- M-Pesa STK Push integration
- Bank reconciliation import (CSV statement upload)
- Maintenance work order management
- Document repository with versioning
- Security visitor and incident logging
- Scheduled and on-demand reporting (9 report types)
- GDPR consent, export, and deletion workflows
- Multi-factor authentication (TOTP)
- Audit trail with full change history

### 6.2 Out of Scope (Phase 1)

- Mobile native applications (iOS/Android)
- Property marketplace / listing portal
- Online tenant application portal
- Third-party accounting software integration (QuickBooks, Sage)
- Utility billing integration (Kenya Power, water utilities)
- Automated lease renewal signatures (e-signature)

---

## 7. Constraints & Assumptions

### 7.1 Constraints

| Constraint | Description |
|---|---|
| Technology | PHP 8.x backend; MySQL 8.x; Bootstrap 5 frontend |
| Currency | Primary currency is Kenyan Shilling (KES) |
| Connectivity | System requires internet access; no offline mode in Phase 1 |
| Regulatory | Must comply with Kenya Data Protection Act 2019 and GDPR principles |
| M-Pesa | Requires Daraja API credentials from Safaricom (Business Paybill/Till) |

### 7.2 Assumptions

- The business has a valid Safaricom Daraja API business account.
- At least one admin user will be pre-seeded by the deployment team.
- SSL/TLS certificates will be provided by the hosting environment.
- Email (SMTP) credentials will be configured before communication features are used.
- All users will have a modern browser (Chrome 90+, Firefox 88+, Edge 88+).

---

## 8. Success Criteria

| Criterion | Measurement |
|---|---|
| Rent collection rate improves | ≥ 90% of monthly invoices paid within grace period |
| Invoice generation automated | 100% of active leases generate invoices automatically each month |
| Maintenance turnaround | Average open-to-resolved time ≤ 7 days for high-priority requests |
| Audit coverage | 100% of create/update/delete actions logged in audit trail |
| System availability | 99.9% uptime measured monthly |
| Data security | Zero data breaches; AES-256 encryption verified for all PII fields |
| User adoption | All 8 user roles actively using system within 60 days of go-live |

---

## 9. Risk Summary

| Risk | Probability | Impact | Mitigation |
|---|---|---|---|
| M-Pesa API downtime | Medium | High | Payment fallback (cash/bank) + retry queue |
| Poor internet connectivity for tenants | High | Medium | Mobile-responsive design; SMS notifications |
| Data migration from legacy systems | Medium | High | Data import scripts + validation checks |
| User resistance to adoption | Medium | Medium | Role-based training + intuitive UI design |
| Regulatory changes (data protection) | Low | High | GDPR-compliant architecture; modular consent system |

---

*End of Business Requirements Document*
