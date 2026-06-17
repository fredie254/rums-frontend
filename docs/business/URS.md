# User Requirements Specification (URS)
## Rental Unit Management System (RUMS)

**Version:** 1.0  
**Date:** 2026-06-17  
**Status:** Approved  
**References:** BRD v1.0, FRS v1.0  

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [User Personas](#2-user-personas)
3. [User Stories](#3-user-stories)
4. [Acceptance Criteria](#4-acceptance-criteria)

---

## 1. Introduction

This document captures user requirements from the perspective of each system actor. Requirements are expressed as user stories in the format:

> **As a** [role], **I want to** [action], **so that** [benefit].

Each story has acceptance criteria (AC) that define when the story is complete.

---

## 2. User Personas

### 2.1 Alice — Property Manager (Admin)
Alice manages a portfolio of 3 apartment blocks with 120 units. She is technically proficient, works on a laptop, and needs real-time visibility into rent collection, occupancy, and maintenance.

### 2.2 James — Landlord
James owns two properties managed by Alice's company. He checks his financial statements monthly and wants transparent income reporting without having to call the office.

### 2.3 Mary — Tenant
Mary rents a 2-bedroom apartment. She wants to pay rent easily via M-Pesa, track her invoice history, and submit maintenance requests without phone calls.

### 2.4 David — Accountant
David handles reconciliation and financial reporting. He processes payments, reviews aging reports, and exports financial data for the company's accounting software.

### 2.5 Brian — Maintenance Technician
Brian is a handyman assigned work orders. He needs to see his task list, update job status, and record material costs from his phone.

### 2.6 Grace — Security Officer
Grace manages the entrance gate. She logs visitors, records check-ins/check-outs, and files incident reports on shift.

### 2.7 Peter — Auditor
Peter is an external auditor who reviews the company's financial and operational records periodically. He needs read-only access to all transactions and the audit trail.

---

## 3. User Stories

---

### 3.1 Admin / Manager Stories

#### US-ADM-001: Dashboard Overview
> **As an** admin, **I want to** see a real-time dashboard with occupancy rate, monthly revenue, outstanding AR, open maintenance, and expiring leases, **so that** I can make operational decisions at a glance.

#### US-ADM-002: User Management
> **As an** admin, **I want to** create, edit, and deactivate user accounts with specific roles, **so that** each team member has appropriate system access.

#### US-ADM-003: Add a Property
> **As an** admin, **I want to** add a new property with its address, landlord, and manager details, **so that** all subsequent units, leases, and payments are organised under it.

#### US-ADM-004: Add Units to a Property
> **As an** admin, **I want to** bulk-define units within a property, **so that** I can quickly configure a new building before assigning tenants.

#### US-ADM-005: Onboard a New Tenant
> **As an** admin, **I want to** create a complete tenant profile with KYC documents, **so that** the tenant record complies with our due diligence requirements.

#### US-ADM-006: Create a Lease
> **As an** admin, **I want to** create a lease linking a tenant to a unit with all financial terms, **so that** invoices are auto-generated from the start date.

#### US-ADM-007: Generate Invoices
> **As an** admin, **I want to** generate monthly invoices for all active leases in one action, **so that** tenants are billed correctly each month without manual work.

#### US-ADM-008: Record a Payment
> **As an** admin, **I want to** record a rent payment against an invoice with payment method and reference, **so that** the tenant's balance is updated immediately.

#### US-ADM-009: Scheduled Reports
> **As a** manager, **I want to** configure automated weekly rent collection reports emailed to the director, **so that** management is always informed without manual distribution.

#### US-ADM-010: Approve Lease Renewal
> **As a** manager, **I want to** review and approve or reject a proposed lease renewal, **so that** new terms are binding only after authorisation.

---

### 3.2 Landlord Stories

#### US-LL-001: Portfolio Overview
> **As a** landlord, **I want to** see a summary of my properties — occupancy rate, total units, current monthly income — **so that** I understand my portfolio performance at a glance.

#### US-LL-002: Income Statement
> **As a** landlord, **I want to** view a monthly and annual income statement for each of my properties, **so that** I can account for my rental income accurately.

#### US-LL-003: Occupancy by Property
> **As a** landlord, **I want to** see which specific units are vacant and which are occupied, **so that** I can follow up on vacancies with my manager.

---

### 3.3 Tenant Stories

#### US-TEN-001: View My Lease
> **As a** tenant, **I want to** view my current lease terms (rent amount, payment day, end date), **so that** I always have clarity on my obligations.

#### US-TEN-002: View My Invoices
> **As a** tenant, **I want to** see a list of all my invoices with their status (paid, unpaid, overdue), **so that** I know exactly what I owe.

#### US-TEN-003: Pay Rent via M-Pesa
> **As a** tenant, **I want to** initiate an M-Pesa STK Push from the portal, **so that** my payment is confirmed automatically without manual reconciliation.

#### US-TEN-004: View Payment History
> **As a** tenant, **I want to** see a history of all my payments with dates and references, **so that** I can resolve any payment disputes with evidence.

#### US-TEN-005: Submit a Maintenance Request
> **As a** tenant, **I want to** submit a maintenance request with a description of the issue, **so that** repairs are tracked and I receive updates on progress.

#### US-TEN-006: Track Maintenance Status
> **As a** tenant, **I want to** see the current status of my maintenance requests, **so that** I know when to expect the repair to be done.

#### US-TEN-007: Download My Documents
> **As a** tenant, **I want to** access and download documents shared with me (lease, receipts), **so that** I have copies for my own records.

#### US-TEN-008: Update My Account
> **As a** tenant, **I want to** update my phone number and enable MFA, **so that** my account is kept current and secure.

---

### 3.4 Accountant Stories

#### US-ACC-001: View AR Aging
> **As an** accountant, **I want to** view accounts receivable grouped into aging buckets (0–30, 31–60, 61–90, 90+ days), **so that** I can prioritise debt collection.

#### US-ACC-002: Reconcile Bank Statements
> **As an** accountant, **I want to** import a bank statement CSV and match entries to recorded payments, **so that** I can identify unreconciled deposits.

#### US-ACC-003: Record Bank/Cash Payments
> **As an** accountant, **I want to** manually record offline payments (cash, cheque, bank transfer), **so that** the system reflects all income regardless of channel.

#### US-ACC-004: Export Financial Report
> **As an** accountant, **I want to** export the monthly rent collection report as CSV, **so that** I can import it into the company's accounting software.

#### US-ACC-005: View Expense Ledger
> **As an** accountant, **I want to** record and categorise property expenses, **so that** the financial reports accurately show net income.

#### US-ACC-006: Landlord Statement
> **As an** accountant, **I want to** generate an income statement for a specific landlord and period, **so that** I can remit their net proceeds with a clear statement.

---

### 3.5 Maintenance Staff Stories

#### US-MNT-001: View My Work Orders
> **As a** maintenance technician, **I want to** see all work orders assigned to me, **so that** I can plan my day efficiently.

#### US-MNT-002: Update Work Order Status
> **As a** maintenance technician, **I want to** mark a work order as in-progress or completed, **so that** tenants and managers are kept informed.

#### US-MNT-003: Record Job Costs
> **As a** maintenance technician, **I want to** enter the materials cost and hours worked on a job, **so that** the company can track maintenance expenses accurately.

#### US-MNT-004: Create a Maintenance Request
> **As a** maintenance technician, **I want to** log a new maintenance issue I discovered during inspection, **so that** it is tracked in the system.

---

### 3.6 Security Officer Stories

#### US-SEC-001: Log a Visitor
> **As a** security officer, **I want to** record a visitor's name, ID, host, purpose, and check-in time, **so that** a complete access log is maintained.

#### US-SEC-002: Check Out a Visitor
> **As a** security officer, **I want to** record a visitor's check-out time, **so that** the gate log accurately reflects who is on the premises.

#### US-SEC-003: Report an Incident
> **As a** security officer, **I want to** file a security incident report with description and severity, **so that** management is informed and records are maintained.

#### US-SEC-004: View Occupancy Status
> **As a** security officer, **I want to** check which units are currently occupied, **so that** I can verify residents' claims at the gate.

---

### 3.7 Auditor Stories

#### US-AUD-001: Filter Audit Trail
> **As an** auditor, **I want to** filter the audit trail by user, module, action, and date range, **so that** I can trace specific transactions during a review.

#### US-AUD-002: Export Audit Log
> **As an** auditor, **I want to** export the audit trail to CSV, **so that** I can perform offline analysis or submit to external regulators.

#### US-AUD-003: View Financial Reports
> **As an** auditor, **I want to** view occupancy, revenue, and AR aging reports, **so that** I can verify the financial health of the portfolio.

#### US-AUD-004: Review Change History
> **As an** auditor, **I want to** see before/after values for any data change, **so that** I can confirm no unauthorised modifications were made.

---

### 3.8 All Users (Self-Service)

#### US-ALL-001: Enable MFA
> **As any** user, **I want to** optionally enable TOTP-based two-factor authentication, **so that** my account is protected even if my password is compromised.

#### US-ALL-002: Change My Password
> **As any** user, **I want to** change my password from the account settings page, **so that** I can maintain account security.

#### US-ALL-003: Download My Data
> **As any** user, **I want to** download all personal data held about me as a JSON file, **so that** I can exercise my right to data portability.

#### US-ALL-004: Request Account Deletion
> **As any** user, **I want to** submit a request to delete my account data, **so that** I can exercise my right to erasure under GDPR.

#### US-ALL-005: Manage Consent
> **As any** user, **I want to** view and update my consent preferences for marketing communications, **so that** I only receive communications I have agreed to.

---

## 4. Acceptance Criteria

### AC for US-ADM-007 (Generate Invoices)
- [ ] Clicking "Generate Invoices" generates exactly one invoice per active lease for the selected period.
- [ ] No duplicate invoice is created if one already exists for that lease/period.
- [ ] Invoice total = rent_amount + utility_amount + penalty_amount − discount_amount.
- [ ] Invoice status is `unpaid` on creation.
- [ ] An in-app notification is sent to the tenant.

### AC for US-TEN-003 (Pay via M-Pesa)
- [ ] STK Push is sent to the tenant's registered M-Pesa number within 5 seconds.
- [ ] On successful Safaricom callback, invoice status updates to `paid` or `partial`.
- [ ] Payment reference (M-Pesa receipt) is stored and visible on the payment record.
- [ ] Failed transactions are logged with the result description.

### AC for US-TEN-005 (Submit Maintenance Request)
- [ ] Request form requires issue_title and unit; priority defaults to `medium`.
- [ ] Request number (MR-YYYYMM-XXXXX) is auto-generated and displayed.
- [ ] Assigned maintenance staff receives an in-app notification.
- [ ] Tenant can view the request status from `/tenant/maintenance.php`.

### AC for US-AUD-001 (Filter Audit Trail)
- [ ] Audit trail loads within 3 seconds for a 30-day window.
- [ ] Filtering by user returns only events for that user.
- [ ] Filtering by action returns only events with that exact action code.
- [ ] Date range filter is inclusive on both ends.
- [ ] Pagination shows 50 records per page with correct total count.

### AC for US-ALL-001 (Enable MFA)
- [ ] MFA is off by default (is_enabled = 0) for all new users.
- [ ] QR code is rendered on the setup screen within 2 seconds of clicking "Enable".
- [ ] Setup requires confirmation with a valid TOTP code before MFA is activated.
- [ ] 8 backup codes are shown exactly once after setup.
- [ ] Once enabled, subsequent logins present the MFA challenge screen.

### AC for US-GDPR (Data Export)
- [ ] Export request generates a download link valid for 1 hour.
- [ ] Downloaded JSON contains all 9 data categories (profile, tenant, leases, payments, invoices, maintenance, documents, notifications, audit_log).
- [ ] A second click of the same token returns 401 (token already used).

---

*End of User Requirements Specification*
