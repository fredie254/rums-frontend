# Wireframes
## Rental Unit Management System (RUMS)

**Version:** 1.0  
**Date:** 2026-06-17  
**Tool:** ASCII wireframes (low-fidelity)  

> These low-fidelity wireframes define layout, hierarchy, and content zones for key screens.
> Annotations describe behaviour and interactions.

---

## WF-01: Login Screen

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│                    ┌─────────────────┐                     │
│                    │  🏢 Property Hub │                     │
│                    │                 │                     │
│                    │  Rental Unit    │                     │
│                    │  Management     │                     │
│                    │  System         │                     │
│                    │                 │                     │
│                    │ ┌─────────────┐ │                     │
│                    │ │ Email       │ │                     │
│                    │ └─────────────┘ │                     │
│                    │                 │                     │
│                    │ ┌─────────────┐ │                     │
│                    │ │ Password  👁 │ │                     │
│                    │ └─────────────┘ │                     │
│                    │                 │                     │
│                    │ [  Sign In  ]   │  ← Primary CTA      │
│                    │                 │                     │
│                    │ Forgot password │  ← Text link        │
│                    └─────────────────┘                     │
│                                                             │
│                     © 2026 RUMS v1.0                       │
└─────────────────────────────────────────────────────────────┘

Annotations:
- Error state: red alert banner above email field
- Loading state: button disabled + spinner "Signing in..."
- MFA: on submit → redirect to WF-02
```

---

## WF-02: MFA Verification Screen

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│                    ┌─────────────────┐                     │
│                    │  🔒 Two-Factor   │                     │
│                    │  Authentication │                     │
│                    │                 │                     │
│                    │  Enter the 6-digit code               │
│                    │  from your authenticator app          │
│                    │  for user@email.com                   │
│                    │                 │                     │
│                    │ ┌─────────────┐ │                     │
│                    │ │  0 0 0 0 0 0│ │  ← Large mono input │
│                    │ └─────────────┘ │                     │
│                    │                 │                     │
│                    │  ⏱ Code refresh in [30]s              │
│                    │  [●●●●●●●●●●●●●●●●●●●●●●●] ← SVG arc │
│                    │                 │                     │
│                    │ [  ✓ Verify  ]  │  ← Auto-submits at 6│
│                    │                 │                     │
│                    │ ▶ Use a backup code instead           │
│                    │   ← <details> collapsible            │
│                    │                 │                     │
│                    │ ← Back to Login │                     │
│                    └─────────────────┘                     │
└─────────────────────────────────────────────────────────────┘

Annotations:
- Input: numeric keypad on mobile (inputmode="numeric")
- Auto-submit when 6 digits entered
- Timer arc turns red when ≤ 5 seconds remain
- Submit button disabled during API call
```

---

## WF-03: Admin Dashboard

```
┌──────────┬──────────────────────────────────────────────────┐
│          │  ≡  RUMS     🔔 3    👤 Alice ▾                  │
│ SIDEBAR  ├──────────────────────────────────────────────────┤
│          │  Dashboard                                       │
│Dashboard │                                                  │
│─────────│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────┐│
│PROPERTIES│  │ 🏠 Total │ │💰Monthly │ │📋 Open   │ │ 📊   ││
│Properties│  │  Units   │ │ Revenue  │ │ Maint.   │ │Occ.% ││
│Units     │  │   120    │ │KES 450K  │ │    8     │ │ 87%  ││
│          │  └──────────┘ └──────────┘ └──────────┘ └──────┘│
│PEOPLE    │                                                  │
│Landlords │  ┌───────────────────────┐ ┌────────────────────┐│
│Tenants   │  │  Monthly Revenue      │ │  Unit Status       ││
│          │  │  [Bar chart 12 months]│ │  [Doughnut chart]  ││
│OPERATIONS│  │                       │ │  ■ Occupied  105   ││
│Leases    │  │                       │ │  ■ Available  10   ││
│Invoices  │  └───────────────────────┘ │  ■ Maint.     5   ││
│Payments  │                            └────────────────────┘│
│          │  ┌────────────────────────────────────────────┐  │
│ANALYTICS │  │  Expiring Leases (30 days)         ↗ View  │  │
│Reports   │  │  Tenant        Unit        End Date  Days   │  │
│          │  │  John Doe      A101        Jul 5     18d ⚠  │  │
│          │  │  Jane Smith    B205        Jul 12    25d     │  │
│SYSTEM    │  └────────────────────────────────────────────┘  │
│Users     │                                                  │
│Settings  │  ┌────────────────────────────────────────────┐  │
│          │  │  Outstanding AR                  KES 125K  │  │
│🔔 Notifs │  │  [Progress bar: 72% collection rate]       │  │
│⚙ Account │  └────────────────────────────────────────────┘  │
│Privacy   │                                                  │
│Logout    │                                                  │
└──────────┴──────────────────────────────────────────────────┘

Annotations:
- KPI cards: hover shows tooltip with period detail
- Revenue chart: click bar → drill to that month's payments
- Sidebar: collapsible on mobile (hamburger toggle)
- Role-aware sidebar: only shows sections relevant to logged-in role
```

---

## WF-04: Tenant Portal — My Invoices

```
┌──────────┬──────────────────────────────────────────────────┐
│          │  My Invoices                              🔔  👤 │
│ SIDEBAR  ├──────────────────────────────────────────────────┤
│          │                                                  │
│My Dash   │  ┌──────────┐ ┌──────────┐ ┌──────────┐         │
│          │  │📋 Total  │ │✓ Paid    │ │⚠ Unpaid  │         │
│MY TENANCY│  │    12    │ │    10    │ │    2     │         │
│My Lease  │  └──────────┘ └──────────┘ └──────────┘         │
│My Invoice│                                                  │
│My Payment│  ┌────────────────────────────────────────────┐  │
│Maintenanc│  │ # │ Invoice     │ Period  │ Amount  │ Status│  │
│Documents │  ├───┼─────────────┼─────────┼─────────┼───────┤  │
│          │  │ 1 │ INV-2026-001│ Jun '26 │KES 25K  │[PAID] │  │
│          │  │ 2 │ INV-2026-002│ May '26 │KES 25K  │[PAID] │  │
│🔔 Notifs │  │ 3 │ INV-2026-003│ Apr '26 │KES 25K  │[UNPD] │  │
│⚙ Account │  │   │             │         │         │[Pay ▶]│  │
│Privacy   │  └────────────────────────────────────────────┘  │
│Logout    │                                                  │
│          │  Outstanding Balance: KES 25,000.00              │
│          │  [  💳 Pay Now via M-Pesa  ]  ← Primary CTA      │
└──────────┴──────────────────────────────────────────────────┘

Annotations:
- "Pay Now" opens payment modal (phone input + confirm)
- [PAID] badge: green; [UNPD] badge: red; [OVRD] badge: dark red
- Row click → invoice detail view (WF-05)
```

---

## WF-05: Invoice Detail View

```
┌──────────────────────────────────────────────────────────────┐
│  ← Back   Invoice #INV-202606-001              [Download PDF]│
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌────────────────────────────────────────────────────────┐  │
│  │                   INVOICE                              │  │
│  │  Property Hub     To: John Doe                        │  │
│  │  Nairobi, Kenya   Unit A-101, Westgate Apts           │  │
│  │                   Invoice: INV-202606-001              │  │
│  │                   Date: Jun 1, 2026                   │  │
│  │                   Due:  Jun 5, 2026                   │  │
│  │  ─────────────────────────────────────────────────    │  │
│  │  Rent (June 2026)                       KES 25,000   │  │
│  │  Utility Charge                          KES  1,500   │  │
│  │  Late Penalty                            KES      0   │  │
│  │  Discount                              (KES      0)   │  │
│  │  ─────────────────────────────────────────────────    │  │
│  │  TOTAL                                  KES 26,500   │  │
│  │  Paid                                   KES 26,500   │  │
│  │  Balance                                KES      0   │  │
│  │                      ✓ PAID              │  │
│  └────────────────────────────────────────────────────────┘  │
│                                                              │
│  Payment History                                             │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ Date       │ Method │ Reference    │ Amount           │   │
│  │ Jun 3 2026 │ M-Pesa │ QGH7823KLM  │ KES 26,500      │   │
│  └──────────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────────┘
```

---

## WF-06: Maintenance Request Form

```
┌──────────────────────────────────────────────────────────────┐
│  ← Back    New Maintenance Request                           │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  Unit *              [Unit A-101 ▾]  ← pre-selected tenant  │
│                                                              │
│  Issue Title *                                               │
│  [Leaking kitchen tap                               ]        │
│                                                              │
│  Category             [Plumbing ▾]                          │
│                                                              │
│  Priority             ○ Low  ● Medium  ○ High  ○ Urgent     │
│                                                              │
│  Description                                                 │
│  ┌───────────────────────────────────────────────────────┐  │
│  │ The kitchen tap has been dripping for 3 days. Water  │  │
│  │ pooling under the sink.                              │  │
│  └───────────────────────────────────────────────────────┘  │
│                                                              │
│  Attach Photo  [Choose File]  No file chosen                 │
│                                                              │
│  [Cancel]                         [Submit Request ▶]        │
└──────────────────────────────────────────────────────────────┘

Annotations:
- On submit: request number displayed in success message
- Priority defaults to "Medium"
- Photo upload optional (max 5MB)
- Tenant: unit pre-selected from their tenancy
```

---

## WF-07: Reports — Arrears Analysis

```
┌──────────┬──────────────────────────────────────────────────┐
│ SIDEBAR  │  ← Back   Arrears Analysis          [Export CSV] │
│          ├──────────────────────────────────────────────────┤
│          │  Filter: [All Properties ▾] [12 months ▾] [Apply]│
│          │                                                  │
│          │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────┐│
│          │  │⚠ Outstand│ │📊 Billed │ │✓ Collected│ │ 78%  ││
│          │  │KES 125K  │ │KES 540K  │ │KES 415K  │ │Effec. ││
│          │  └──────────┘ └──────────┘ └──────────┘ └──────┘│
│          │                                                  │
│          │  ┌──────────────────────────────────┐ ┌────────┐│
│          │  │  Billed vs Collected (bar+line)  │ │By Prop ││
│          │  │  [12-month chart]                │ │[H.bar] ││
│          │  └──────────────────────────────────┘ └────────┘│
│          │                                                  │
│          │  Worst Arrears Offenders                        │
│          │  ┌──────────────────────────────────────────────┐│
│          │  │ # │ Tenant     │ Unit  │ Owed   │Days│Ledger ││
│          │  │ 1 │ Peter K.   │ C302  │KES 75K │127d│[View] ││
│          │  │ 2 │ Susan M.   │ A104  │KES 50K │ 89d│[View] ││
│          │  │ 3 │ Tom O.     │ B201  │KES 25K │ 45d│[View] ││
│          │  └──────────────────────────────────────────────┘│
└──────────┴──────────────────────────────────────────────────┘
```

---

## WF-08: Account Settings — Security Tab (MFA)

```
┌──────────────────────────────────────────────────────────────┐
│  Account Settings                                            │
│  [Security] [Password] [Profile]  ← Tab navigation          │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌────────────────────────────────────────────────────────┐  │
│  │  Two-Factor Authentication          [Disabled] [Optl]  │  │
│  │  Add an extra layer of security to your account        │  │
│  │  Off by default — enable for stronger protection       │  │
│  │                                                        │  │
│  │  [ 📱 Enable Two-Factor Authentication ]               │  │
│  └────────────────────────────────────────────────────────┘  │
│                                                              │
│  ── ENABLED STATE ─────────────────────────────────────────  │
│                                                              │
│  ┌────────────────────────────────────────────────────────┐  │
│  │  Two-Factor Authentication          [Enabled] ✓         │  │
│  │  Enabled on 17 Jun 2026                                │  │
│  │                                                        │  │
│  │  Backup Codes: 7 remaining  [Regenerate]               │  │
│  │                                                        │  │
│  │  [ 🔴 Disable Two-Factor Authentication ]              │  │
│  └────────────────────────────────────────────────────────┘  │
│                                                              │
│  ── SETUP STATE (after clicking Enable) ───────────────────  │
│                                                              │
│  ┌────────────────────────────────────────────────────────┐  │
│  │  1. Scan QR code in your authenticator app             │  │
│  │  ┌───────────┐   Or enter manual key:                  │  │
│  │  │ [QR CODE] │   JBSWY3DPEHPK3PXP                     │  │
│  │  └───────────┘                                         │  │
│  │  2. Save backup codes (shown once):                    │  │
│  │  ABC12345  DEF67890  GHI11111  JKL22222                │  │
│  │  MNO33333  PQR44444  STU55555  VWX66666                │  │
│  │  3. Enter first code to confirm:                       │  │
│  │  [          ]  [ Confirm & Enable ]                    │  │
│  └────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────┘
```

---

## WF-09: Document Repository

```
┌──────────┬──────────────────────────────────────────────────┐
│ SIDEBAR  │  Document Repository            [+ Upload Doc]   │
│          ├──────────────────────────────────────────────────┤
│          │  Search: [                  🔍]                  │
│          │  Filter: [All Types ▾] [All Access ▾] [Apply]    │
│          │                                                  │
│          │  ┌───┬──────────────────┬──────┬────────┬──────┐ │
│          │  │ ⬜│ Document         │ Type │ Entity │ Date │ │
│          │  ├───┼──────────────────┼──────┼────────┼──────┤ │
│          │  │ 📄│ Lease_A101.pdf   │Lease │A-101   │Jun 1 │ │
│          │  │   │ v2 • 245 KB     │      │        │[⬇][⋮]│ │
│          │  ├───┼──────────────────┼──────┼────────┼──────┤ │
│          │  │ 📄│ TenantID_JD.jpg │Tenant│John D. │May 15│ │
│          │  │   │ v1 • 88 KB      │      │        │[⬇][⋮]│ │
│          │  ├───┼──────────────────┼──────┼────────┼──────┤ │
│          │  │ 📄│ Insurance_2026  │Cert. │Westgate│Apr 3 │ │
│          │  │   │ v1 • 1.2 MB     │      │        │[⬇][⋮]│ │
│          │  └───┴──────────────────┴──────┴────────┴──────┘ │
│          │                                                  │
│          │  Showing 3 of 47 documents      [◀ 1 2 3 … ▶]   │
└──────────┴──────────────────────────────────────────────────┘

Annotations:
- [⬇] = Download button
- [⋮] = Context menu: View | Edit | New Version | Delete
- Row click → document detail (overview tab)
- Badge "v2" indicates versioned document
```

---

## WF-10: Security Dashboard

```
┌──────────┬──────────────────────────────────────────────────┐
│SECURITY  │  Security Dashboard          [Log Visitor]       │
│          ├──────────────────────────────────────────────────┤
│Security  │  ┌──────────┐ ┌──────────┐ ┌──────────┐         │
│Visitor   │  │👥 On Site│ │📋 Today  │ │⚠ Overstay│         │
│Incidents │  │    12    │ │    34    │ │    2     │         │
│Occupancy │  └──────────┘ └──────────┘ └──────────┘         │
│          │                                                  │
│🔔 Notifs │  Currently On Site                               │
│⚙ Account │  ┌────────────────────────────────────────────┐  │
│          │  │ Visitor       │ Unit  │ In    │ Duration   │  │
│          │  │ James Njoroge │ B201  │ 09:15 │ 2h 15m     │  │
│          │  │ Sarah Kamau   │ A104  │ 10:00 │ 1h 30m     │  │
│          │  │ ⚠ Tom Omondi  │ C302  │ 07:30 │ 4h 00m ⚠  │  │
│          │  │               │       │       │[Check Out] │  │
│          │  └────────────────────────────────────────────┘  │
│          │                                                  │
│          │  Recent Incidents              [View All]        │
│          │  ┌────────────────────────────────────────────┐  │
│          │  │ Jun 17 │ HIGH  │ Suspicious Vehicle        │  │
│          │  │ Jun 15 │ MED   │ Noise Complaint — B Block │  │
│          │  └────────────────────────────────────────────┘  │
└──────────┴──────────────────────────────────────────────────┘
```

---

*End of Wireframes*
