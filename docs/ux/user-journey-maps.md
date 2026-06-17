# User Journey Maps
## Rental Unit Management System (RUMS)

**Version:** 1.0  
**Date:** 2026-06-17  

---

## Journey 1: Tenant — Monthly Rent Payment via M-Pesa

**Persona:** Mary, Tenant  
**Goal:** Pay her monthly rent quickly and get a confirmation  
**Channel:** Web browser (mobile)

```
STAGE         │ AWARENESS      │ ACCESS          │ PAYMENT          │ CONFIRMATION      │ RESOLUTION
──────────────┼────────────────┼─────────────────┼──────────────────┼───────────────────┼──────────────────
TOUCHPOINT    │ SMS reminder   │ Login page      │ Invoices page    │ M-Pesa prompt     │ Dashboard
              │ Email reminder │                 │ Pay button       │ (on phone)        │ Invoice status
──────────────┼────────────────┼─────────────────┼──────────────────┼───────────────────┼──────────────────
USER ACTION   │ Receives "Rent │ Navigates to    │ Sees invoice     │ Receives STK Push │ Returns to portal
              │ due in 3 days" │ portal, logs in │ for June 2026,  │ on phone, enters  │ sees "PAID"
              │ notification   │ with email+pass │ clicks "Pay Now" │ M-Pesa PIN        │ badge on invoice
──────────────┼────────────────┼─────────────────┼──────────────────┼───────────────────┼──────────────────
SYSTEM ACTION │ Cron sends SMS │ Session created │ STK Push init   │ Daraja callback   │ Invoice status →
              │ 3 days before  │ API token issued│ Pending record  │ received          │ paid; notification
              │ due_date        │                 │ created         │ Payment recorded  │ sent to tenant
──────────────┼────────────────┼─────────────────┼──────────────────┼───────────────────┼──────────────────
EMOTION       │ 😟 Stressed    │ 😐 Neutral      │ 😊 Hopeful      │ 😮 Waiting        │ 😄 Relieved
──────────────┼────────────────┼─────────────────┼──────────────────┼───────────────────┼──────────────────
PAIN POINTS   │ May miss       │ Forgot password │ Unsure if       │ STK Push may      │ Wants receipt
              │ reminder       │                 │ amount is right │ take a moment     │ to save offline
──────────────┼────────────────┼─────────────────┼──────────────────┼───────────────────┼──────────────────
OPPORTUNITIES │ Push + email   │ "Remember me"   │ Pre-fill amount │ Show countdown    │ Download PDF
              │ reminders      │ option          │ from invoice    │ spinner           │ receipt button
```

**Success Metric:** Invoice status = `paid` within 5 minutes of initiating STK Push.

---

## Journey 2: Property Manager — New Tenant Onboarding

**Persona:** Alice, Admin/Manager  
**Goal:** Onboard a new tenant and have their first invoice generated  
**Channel:** Desktop browser

```
STAGE         │ ENQUIRY         │ TENANT PROFILE   │ LEASE CREATION    │ INVOICE GEN       │ NOTIFICATION
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
TOUCHPOINT    │ Phone / walk-in │ Tenants > Add    │ Leases > Add      │ Invoices > Gen    │ System notifs
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
USER ACTION   │ Receives tenant │ Creates tenant   │ Links tenant to   │ Runs bulk invoice │ Confirms tenant
              │ application     │ profile + uploads │ unit, sets rent   │ generation for    │ received welcome
              │                 │ KYC documents    │ and payment terms │ current month     │ SMS/email
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
SYSTEM ACTION │ —               │ Encrypts PII,    │ Unit status →     │ Creates invoice   │ Sends welcome
              │                 │ hashes ID number │ occupied;         │ with correct      │ template via
              │                 │ Saves KYC files  │ auto lease number │ amounts           │ SMS + email
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
EMOTION       │ 😊 Motivated    │ 😐 Data-entry    │ 😊 Organised      │ 😌 Satisfied      │ 😄 Done
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
PAIN POINTS   │ Paper forms     │ Many fields to   │ Needs to double-  │ Timing: invoice   │ Did tenant
              │ get lost        │ fill in          │ check rent amount │ for partial month │ receive it?
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
OPPORTUNITIES │ Digital enquiry │ Save draft,      │ Template defaults │ Prorated invoice  │ Delivery
              │ form (Phase 2)  │ resume later     │ from unit record  │ calculator        │ status tracking
```

---

## Journey 3: Maintenance Staff — Resolving a Work Order

**Persona:** Brian, Maintenance Technician  
**Goal:** Complete an assigned work order and record costs  
**Channel:** Mobile browser

```
STAGE         │ NOTIFICATION    │ VIEW DETAILS     │ START WORK        │ COMPLETE JOB      │ CLOSE OUT
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
TOUCHPOINT    │ In-app notif    │ Work Orders list │ Work order detail │ Work order form   │ Dashboard
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
USER ACTION   │ Sees "New work  │ Opens work order │ Taps "Start Work" │ Enters materials  │ Marks "Resolved"
              │ order assigned" │ — reads issue,   │ button            │ cost, hours, and  │ after tenant
              │ notification    │ checks unit/photo│                   │ "Mark Completed"  │ confirms
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
SYSTEM ACTION │ Notification    │ Shows unit,      │ Status →          │ Status →          │ Status →
              │ sent on assign  │ tenant contact,  │ in_progress;      │ completed;        │ resolved;
              │                 │ priority badge   │ work_started set  │ costs recorded    │ tenant notified
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
EMOTION       │ 😐 Informed     │ 😊 Clear picture │ 💪 Engaged        │ 😌 Done           │ 😄 Satisfying
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
PAIN POINTS   │ Missed if phone │ No offline       │ Needs internet    │ Typing costs on   │ Needs tenant
              │ on silent       │ mode             │ to update         │ mobile is tedious │ to confirm
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
OPPORTUNITIES │ Push notifs     │ Cache work order │ Optimistic UI     │ Voice/photo input │ Auto-resolve
              │ (Phase 2 app)   │ for offline use  │ update            │ for costs         │ after N days
```

---

## Journey 4: Accountant — Monthly Reconciliation

**Persona:** David, Accountant  
**Goal:** Reconcile bank credits with recorded payments  
**Channel:** Desktop browser

```
STAGE         │ EXPORT BANK     │ IMPORT CSV       │ MATCH PAYMENTS    │ REVIEW GAPS       │ REPORT
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
TOUCHPOINT    │ Bank portal     │ Reconciliation   │ Reconciliation    │ AR Aging report   │ Financial report
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
USER ACTION   │ Downloads bank  │ Uploads CSV to   │ Reviews auto-     │ Manually matches  │ Exports financial
              │ statement CSV   │ RUMS             │ matched entries   │ remaining items;  │ report as CSV
              │                 │                  │                   │ records cash pays │ for director
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
SYSTEM ACTION │ —               │ Parses CSV;      │ Shows matched /   │ Saves manual      │ Generates report
              │                 │ auto-matches by  │ unmatched entries │ match; logs audit │ with all payments
              │                 │ amount + date    │ with colour codes │ trail             │ reconciled
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
EMOTION       │ 😐 Routine      │ 😊 Efficient     │ 😊 Most done      │ 😤 Manual effort  │ 😄 Clean books
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
PAIN POINTS   │ Different banks │ CSV format may   │ Auto-match may    │ Multiple windows  │ Format not
              │ use diff formats│ have issues      │ miss some entries │ to cross-check    │ matching software
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
OPPORTUNITIES │ Format guide    │ Format validator │ Fuzzy matching    │ Side-by-side view │ Direct QuickBooks
              │ in upload page  │ with preview     │ by reference no   │ both entries      │ export (Phase 2)
```

---

## Journey 5: Auditor — Quarterly Compliance Review

**Persona:** Peter, External Auditor  
**Goal:** Review transaction history and verify data integrity  
**Channel:** Desktop browser (read-only access)

```
STAGE         │ LOGIN           │ AUDIT TRAIL      │ FINANCIAL REVIEW  │ SPOT CHECKS       │ EXPORT
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
TOUCHPOINT    │ Login page      │ Audit Trail page │ Reports section   │ Invoices / Leases │ CSV exports
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
USER ACTION   │ Logs in with    │ Filters by date  │ Reviews financial │ Opens individual  │ Exports audit log
              │ auditor account │ range, module,   │ and AR aging      │ records to verify │ + financial CSVs
              │                 │ user for period  │ reports           │ changes made      │ for offline work
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
SYSTEM ACTION │ Issues read-    │ Returns filtered │ Renders report    │ Shows audit trail │ Generates CSV
              │ only token;     │ paginated log    │ data              │ diff for each     │ with BOM for
              │ auditor scopes  │                  │                   │ changed record    │ Excel compat.
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
EMOTION       │ 😐 Professional │ 😊 Comprehensive │ 😌 Informative    │ 😊 Transparent    │ 😄 Thorough
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
PAIN POINTS   │ Needs access    │ Very large log   │ Needs YoY         │ Cannot see all    │ CSVs need
              │ provisioned     │ — filtering slow │ comparison        │ change details    │ further formatting
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
OPPORTUNITIES │ Temporary       │ Saved filter     │ YoY comparison    │ Diff modal        │ PDF export
              │ access tokens   │ presets          │ in reports        │ on all updates    │ for reports
```

---

## Journey 6: Landlord — Monthly Portfolio Review

**Persona:** James, Landlord  
**Goal:** Check his properties' performance and income this month  
**Channel:** Mobile browser

```
STAGE         │ ACCESS          │ PORTFOLIO VIEW   │ INCOME DETAILS    │ QUERIES           │ DONE
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
TOUCHPOINT    │ Login page      │ Landlord         │ Income statement  │ Manager contact   │ —
              │                 │ dashboard        │                   │ (offline)         │
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
USER ACTION   │ Logs in         │ Sees all his     │ Views monthly     │ Notes vacancy     │ Satisfied with
              │                 │ properties with  │ income breakdown, │ in Property B —   │ transparency
              │                 │ occupancy rates  │ net after         │ calls manager     │
              │                 │                  │ commission        │                   │
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
EMOTION       │ 😊 Curious      │ 😌 Informed      │ 😄 Pleased        │ 😟 Concerned      │ 😊 Empowered
              │                 │                  │ (positive result) │ about vacancy     │
──────────────┼─────────────────┼──────────────────┼───────────────────┼───────────────────┼──────────────────
OPPORTUNITIES │ Email digest    │ Vacancy           │ Statement PDF     │ Direct message    │
              │ monthly summary │ alert by email   │ download          │ to manager in app │
```

---

*End of User Journey Maps*
