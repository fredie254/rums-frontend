# UI Mockups
## Rental Unit Management System (RUMS)

**Version:** 1.0  
**Date:** 2026-06-17  
**Framework:** Bootstrap 5.3 · Bootstrap Icons 1.11 · Chart.js 4  

> Mockups describe the high-fidelity visual design using the actual component library.
> Each mockup documents colour, typography, component choices, and states.

---

## Design System

### Colour Palette

| Token | Hex | Bootstrap Class | Usage |
|---|---|---|---|
| Primary Blue | `#0d6efd` | `bg-primary` | CTAs, active nav, charts |
| Success Green | `#198754` | `bg-success` | Paid status, positive KPIs |
| Danger Red | `#dc3545` | `bg-danger` | Overdue, alerts, delete |
| Warning Amber | `#ffc107` | `bg-warning` | Caution, pending states |
| Info Teal | `#0dcaf0` | `bg-info` | Info cards, download |
| Sidebar Dark | `#1a1d23` | Custom | Sidebar background |
| Sidebar Hover | `#2d3139` | Custom | Nav item hover |
| Card BG | `#ffffff` | `bg-white` | All cards |
| Page BG | `#f0f2f5` | Custom `body-bg` | Page background |

### Typography

| Element | Font | Size | Weight |
|---|---|---|---|
| Page headings `h5` | System sans-serif | 1.1rem | 700 `fw-bold` |
| Section labels | System sans-serif | 0.75rem | 600 uppercase |
| Body text | System sans-serif | 0.875rem `small` | 400 |
| KPI values | System sans-serif | 1.5rem | 700 |
| Code/refs | Monospace | 0.8rem | 400 |

### KPI Card Variants

```css
/* Defined in assets/css/style.css */
.kpi-card         { border-radius:12px; padding:1.25rem; display:flex; align-items:center; gap:1rem }
.kpi-card.kpi-blue   { background: linear-gradient(135deg,#0d6efd,#0a58ca); color:white }
.kpi-card.kpi-green  { background: linear-gradient(135deg,#198754,#146c43); color:white }
.kpi-card.kpi-red    { background: linear-gradient(135deg,#dc3545,#b02a37); color:white }
.kpi-card.kpi-teal   { background: linear-gradient(135deg,#0dcaf0,#0aa2c0); color:white }
.kpi-card.kpi-orange { background: linear-gradient(135deg,#fd7e14,#ca6510); color:white }
.kpi-card.kpi-purple { background: linear-gradient(135deg,#6f42c1,#59359a); color:white }
.kpi-value { font-size:1.6rem; font-weight:700; line-height:1 }
.kpi-label { font-size:0.75rem; opacity:0.85; text-transform:uppercase; letter-spacing:.05em }
.kpi-icon  { font-size:2rem; opacity:0.7 }
```

### Status Badge Colours

| Status | Class | Appearance |
|---|---|---|
| Paid | `badge bg-success` | Green pill |
| Unpaid | `badge bg-danger` | Red pill |
| Partial | `badge bg-warning text-dark` | Amber pill |
| Overdue | `badge bg-danger` | Red pill |
| Active (lease) | `badge bg-success` | Green pill |
| Expired | `badge bg-secondary` | Grey pill |
| Terminated | `badge bg-dark` | Dark pill |
| Open (maint.) | `badge bg-primary` | Blue pill |
| In Progress | `badge bg-warning text-dark` | Amber pill |
| Completed | `badge bg-success` | Green pill |
| Urgent (priority) | `badge bg-danger` | Red pill |
| High | `badge bg-warning text-dark` | Amber pill |
| Medium | `badge bg-info text-dark` | Teal pill |
| Low | `badge bg-secondary` | Grey pill |

---

## MK-01: Login Page

**Component:** Custom standalone page (no sidebar)  
**Layout:** Centred card on gradient background

```
Background: linear-gradient(135deg, #667eea 0%, #764ba2 100%)

┌──────────────────────────────────────┐
│  .login-card (shadow-lg, rounded-3)  │
│                                      │
│  🏢  ← bi-building-fill text-warning │
│  Property Hub                        │
│  ── fs-5 text-muted ─────────────── │
│                                      │
│  [Flash alert if error]              │
│                                      │
│  Email address             label     │
│  ┌──────────────────────┐            │
│  │ 📧                   │            │
│  └──────────────────────┘            │
│                                      │
│  Password                  label     │
│  ┌──────────────────────┐            │
│  │ 🔒               👁  │ ← toggle  │
│  └──────────────────────┘            │
│                                      │
│  ┌──────────────────────────────┐   │
│  │  Sign In →                   │   │
│  └──────────────────────────────┘   │
│  btn-warning w-100 fw-bold py-2      │
│                                      │
│  Forgot your password? (link)        │
│                                      │
│  © 2026 RUMS — v1.0                 │
└──────────────────────────────────────┘
```

**States:**
- **Default:** Empty form, no error
- **Error:** `alert-danger` with icon + message above form
- **Loading:** Button disabled, spinner inside: "Signing in..."
- **MFA redirect:** Brief flash "Redirecting to verification..."

---

## MK-02: Sidebar Navigation

**Component:** `nav.sidebar` fixed left, 250px wide  
**Behaviour:** Collapses to icons-only on mobile (toggled by hamburger)

```
┌────────────────────────┐
│ 🏢 Property Hub        │  ← sidebar-header
│   (fw-bold text-white) │
│                         │
│  ─ PROPERTIES ─────── │  ← .nav-section-label (uppercase, grey)
│  🏛 Properties         │  ← .nav-link (hover: bg #2d3139)
│  🚪 Units              │
│                         │
│  ─ PEOPLE ──────────  │
│  👤 Landlords          │
│  👥 Tenants            │
│                         │
│  ─ OPERATIONS ──────  │
│  📄 Leases             │
│  🧾 Invoices           │
│  💰 Payments           │
│  🔧 Maintenance        │
│                         │
│  [active link]          │  ← .nav-link.active (bg #0d6efd)
│                         │
│  ─ (bottom section) ─  │
│  🔔 Notifications  [3] │  ← badge count
│  ⚙  Account Settings  │
│  🔒 Privacy & Data     │
│  ← Logout (text-danger)│
└────────────────────────┘
```

**Active state:** `background: #0d6efd; color: white; border-radius: 8px`  
**Section labels:** `font-size: 0.65rem; letter-spacing: .1em; color: #adb5bd`

---

## MK-03: Tenant Dashboard

**Layout:** 12-column Bootstrap grid

```
Header row: "My Dashboard" h5 + current date (text-muted)

Row 1 — KPI Cards (4 × col-6 col-md-3):
┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐
│ kpi-blue    │ │ kpi-green   │ │ kpi-red     │ │ kpi-teal    │
│ 🏠 Lease    │ │ ✓ Paid      │ │ ⚠ Balance  │ │ 🔧 Open     │
│ Active      │ │ This Month  │ │ Outstanding │ │ Requests    │
│             │ │ KES 25,000  │ │ KES 0       │ │      1      │
└─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘

Row 2 — Two columns:
┌───────────────────────────────┐ ┌────────────────────────────┐
│ card shadow-sm                │ │ card shadow-sm              │
│ My Lease Summary              │ │ Recent Invoices             │
│                               │ │                             │
│ Unit: A-101                   │ │ Jun 2026  KES 26,500  PAID  │
│ Property: Westgate Apts       │ │ May 2026  KES 26,500  PAID  │
│ Monthly Rent: KES 25,000      │ │ Apr 2026  KES 26,500  PAID  │
│ Due Day: 5th of month         │ │                             │
│ Lease End: Dec 31 2026        │ │ [View All Invoices →]       │
│ [View Full Lease →]           │ │                             │
└───────────────────────────────┘ └────────────────────────────┘

Row 3 — Full width:
┌──────────────────────────────────────────────────────────────┐
│ card shadow-sm                                               │
│ My Maintenance Requests               [+ New Request]       │
│ ┌──────┬──────────────┬──────────┬────────────┬──────────┐  │
│ │ Ref# │ Title        │ Priority │ Status     │ Date     │  │
│ │ MR-01│ Leaking tap  │ Medium   │ In Progress│ Jun 10   │  │
│ └──────┴──────────────┴──────────┴────────────┴──────────┘  │
└──────────────────────────────────────────────────────────────┘
```

---

## MK-04: Admin Invoice List

**Component:** Full-page table with filters  
**Toolbar:** Search + status filter + date range + [Generate Invoices] button

```
Page header:
  Invoices                    [+ Add Invoice]  [⚡ Generate Monthly]

Filter bar (card):
  [Search tenant/invoice#...] [Status ▾] [From date] [To date] [Apply]

Table (table-hover, table-sm):
  ┌──────────────┬─────────────┬───────────┬──────────┬─────────┬────────┐
  │ Invoice #    │ Tenant      │ Period    │ Total    │ Paid    │ Status │
  ├──────────────┼─────────────┼───────────┼──────────┼─────────┼────────┤
  │ INV-202606-01│ John Doe    │ Jun 2026  │ KES 26.5K│ KES 26.5│ ✓ Paid │
  │ INV-202606-02│ Jane Smith  │ Jun 2026  │ KES 20.0K│ KES 10.0│ ⚠ Part │
  │ INV-202606-03│ Peter K.    │ Jun 2026  │ KES 30.0K│ KES 0   │ ⚠ Ovrd │
  └──────────────┴─────────────┴───────────┴──────────┴─────────┴────────┘

Pagination: Showing 1–20 of 87  [ ◀ 1 2 3 4 5 ▶ ]
Footer summary: Total Outstanding: KES 450,000
```

**Row actions (⋮ dropdown):**
- View Invoice
- Add Payment
- Cancel Invoice (admin only)

---

## MK-05: Audit Trail (Auditor View)

**Component:** Dense data table with strong filter panel

```
Page header:
  Audit Trail                                    [↓ Export CSV]

Filter card:
  [From date] [To date] [Action ▾] [Module ▾] [User ▾] [IP Address]
  [Filter]  [Reset]

Table:
  ┌──────────────┬──────────────┬───────┬────────┬────────┬─────────────────┬──────────────┬──┐
  │ Timestamp    │ User         │ Role  │ Action │ Module │ Description     │ IP           │  │
  ├──────────────┼──────────────┼───────┼────────┼────────┼─────────────────┼──────────────┼──┤
  │ Jun 17 09:30 │ Alice M.     │ admin │ UPDATE │ leases │ Updated monthly │ 197.248.x.x  │⊞ │
  │              │ alice@..     │       │        │        │ rent 25K→27.5K  │              │  │
  ├──────────────┼──────────────┼───────┼────────┼────────┼─────────────────┼──────────────┼──┤
  │ Jun 17 09:15 │ John D.      │ tenant│ LOGIN  │ auth   │ Successful login│ 41.90.x.x    │  │
  └──────────────┴──────────────┴───────┴────────┴────────┴─────────────────┴──────────────┴──┘

⊞ = diff button → opens modal with before/after JSON

Action badge colours:
  LOGIN   → bg-success     UPDATE  → bg-info
  LOGOUT  → bg-secondary   DELETE  → bg-danger
  CREATE  → bg-primary     VIEW    → bg-light text-dark
```

---

## MK-06: Privacy & Data (GDPR)

**Layout:** 2-column (col-lg-8 + col-lg-4)

```
Left column:
┌─────────────────────────────────────────────────┐
│ card: Data We Hold About You                    │
│ Two-column list of data categories              │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│ card: Consent Preferences                       │
│                                                 │
│ Terms of Service      [Required] ■──────────○  │
│ Privacy Policy        [Required] ■──────────○  │
│ Marketing Comms       [Optional] ○──────────■  │
│                                                 │
│ [💾 Save Preferences]                           │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│ card: Download Your Data    [GDPR Art. 20]      │
│ [↓ Generate & Download My Data]                 │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│ card border-danger: Right to Erasure            │
│                     [GDPR Art. 17]              │
│ Reason (optional): [textarea]                   │
│ [🗑 Submit Deletion Request]  ← btn-outline-danger│
└─────────────────────────────────────────────────┘

Right column:
┌─────────────────────┐
│ Account Security    │
│ [⚙ Security Settings│
└─────────────────────┘
┌─────────────────────┐
│ Your Rights         │
│ Art 15, 16, 17...   │
└─────────────────────┘
┌─────────────────────┐
│ Data Retention      │
│ Periods list        │
└─────────────────────┘
```

---

## MK-07: Responsive Breakpoints

| Breakpoint | Width | Layout change |
|---|---|---|
| xs | < 576px | Sidebar hidden; hamburger toggle; 1-col KPI cards |
| sm | ≥ 576px | 2-col KPI cards |
| md | ≥ 768px | Sidebar partially visible (icons + text); 3-col KPIs |
| lg | ≥ 992px | Full sidebar; 4-col KPIs; 2-col report layout |
| xl | ≥ 1200px | Wide tables; 3-col card grids |

**Mobile sidebar behaviour:**
- On `xs`/`sm`: sidebar slides in from left on hamburger click, overlays content
- On `md`+: sidebar always visible, content offset by 250px (`margin-left: 250px`)

---

*End of Mockups*
