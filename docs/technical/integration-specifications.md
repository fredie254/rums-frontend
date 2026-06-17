# Integration Specifications
## Rental Unit Management System (RUMS)

**Version:** 1.0  
**Date:** 2026-06-17  

---

## Table of Contents

1. [M-Pesa Daraja API](#1-m-pesa-daraja-api)
2. [SMS — Africa's Talking](#2-sms--africas-talking)
3. [Email — SMTP](#3-email--smtp)
4. [Bank Statement Import (CSV)](#4-bank-statement-import-csv)
5. [Configuration Reference](#5-configuration-reference)

---

## 1. M-Pesa Daraja API

### 1.1 Overview

RUMS integrates with Safaricom's **Daraja API** for real-time M-Pesa payments via the **STK Push (Lipa na M-Pesa Online)** flow. When a tenant initiates payment, a push notification is sent directly to their phone — they enter their M-Pesa PIN and the payment is confirmed automatically via callback.

**Integration type:** C2B STK Push (Customer to Business)  
**Daraja docs:** developer.safaricom.co.ke  

### 1.2 Prerequisites

| Requirement | Details |
|---|---|
| Business Shortcode | Safaricom-assigned Paybill/Till number |
| Passkey | Provided by Safaricom for the shortcode |
| Consumer Key | Daraja app credential |
| Consumer Secret | Daraja app credential |
| Callback URL | `https://{domain}/api/v1/payments/mpesa/callback` (public HTTPS) |

### 1.3 STK Push Flow

```
Tenant initiates payment in portal
    │
    ▼
POST /api/v1/payments/mpesa/stkpush
{tenant_id, invoice_id, phone, amount}
    │
    ▼
API: Generate OAuth token from Daraja
    │
    ▼
POST https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest
{
  BusinessShortCode, Password, Timestamp,
  TransactionType: "CustomerPayBillOnline",
  Amount, PartyA (phone), PartyB (shortcode),
  PhoneNumber, CallBackURL, AccountReference, TransactionDesc
}
    │
    ▼
Daraja responds: {CheckoutRequestID, MerchantRequestID}
API stores mpesa_transaction record (status: pending)
API responds to frontend: {checkout_request_id, message}
    │
    ▼
Safaricom sends STK Push to tenant's phone
Tenant enters M-Pesa PIN
    │
    ▼
Safaricom calls POST {CallBackURL}
{
  Body.stkCallback: {
    MerchantRequestID, CheckoutRequestID, ResultCode,
    ResultDesc,
    CallbackMetadata.Item: [Amount, MpesaReceiptNumber, TransactionDate, PhoneNumber]
  }
}
    │
    ▼
API callback handler:
  ResultCode = 0 → Payment SUCCESS
    - Update mpesa_transaction: status=completed, receipt, transaction_id
    - Create payment record
    - Update invoice amount_paid + status
    - Send notification to tenant
  ResultCode ≠ 0 → Payment FAILED
    - Update mpesa_transaction: status=failed, result_desc
    - Notify tenant of failure
```

### 1.4 Password Generation

```php
$timestamp = date('YmdHis');
$password  = base64_encode($shortcode . $passkey . $timestamp);
```

### 1.5 OAuth Token

```
POST https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials
Authorization: Basic base64(consumer_key:consumer_secret)

Response: {"access_token": "...", "expires_in": "3599"}
```

Token is cached for 3599 seconds. Refresh before expiry.

### 1.6 Environment Configuration

```env
MPESA_CONSUMER_KEY=your_consumer_key
MPESA_CONSUMER_SECRET=your_consumer_secret
MPESA_SHORTCODE=174379
MPESA_PASSKEY=bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919
MPESA_ENV=sandbox          # sandbox | production
MPESA_CALLBACK_URL=https://yourdomain.com/api/v1/payments/mpesa/callback
```

### 1.7 Endpoint URLs

| Environment | Base URL |
|---|---|
| Sandbox | `https://sandbox.safaricom.co.ke` |
| Production | `https://api.safaricom.co.ke` |

### 1.8 Error Handling

| ResultCode | Meaning | Action |
|---|---|---|
| 0 | Success | Create payment record |
| 1 | Insufficient funds | Notify tenant |
| 1032 | Request cancelled by user | Log, notify |
| 1037 | STK Push timeout | Log, allow retry |
| Any other | Generic failure | Log result_desc, notify |

### 1.9 Callback Security

- Restrict callback URL to Safaricom IP ranges (configure at Nginx/firewall level).
- Validate that `CheckoutRequestID` exists in `mpesa_transactions` before processing.
- Idempotency: check `status = 'pending'` before creating a payment record.

---

## 2. SMS — Africa's Talking

### 2.1 Overview

SMS notifications are sent via the **Africa's Talking SMS API** for:
- Payment reminders
- Payment confirmations
- Lease expiry notices
- Maintenance status updates
- Broadcast messages

### 2.2 API Details

```
Endpoint: https://api.africastalking.com/version1/messaging
Method:   POST
Auth:     Header: apiKey: {API_KEY}
          Header: Accept: application/json
```

### 2.3 Request Format

```http
POST https://api.africastalking.com/version1/messaging
Content-Type: application/x-www-form-urlencoded
apiKey: {API_KEY}
Accept: application/json

username={AT_USERNAME}
&to=+254700000000,+254711111111
&message=Dear John, your rent of KES 25,000 is due on 5 Jul 2026.
&from=RUMS
```

### 2.4 Response

```json
{
  "SMSMessageData": {
    "Message": "Sent to 1/1 Total Cost: KES 0.8000",
    "Recipients": [{
      "statusCode": 101,
      "number": "+254700000000",
      "status": "Success",
      "cost": "KES 0.8000",
      "messageId": "ATXid_123456"
    }]
  }
}
```

### 2.5 Status Codes

| Code | Status | Action |
|---|---|---|
| 101 | Success | Mark communication_log as `sent` |
| 102 | Sent | Pending delivery confirmation |
| 401 | Risk Hold | Retry after review |
| 402 | Invalid Phone | Mark as failed; log |
| 403 | Blacklisted | Remove from send list |

### 2.6 Configuration

```env
AT_USERNAME=your_username
AT_API_KEY=your_api_key
AT_SENDER_ID=RUMS
AT_ENV=sandbox    # sandbox | production
```

### 2.7 Template Variable Substitution

Messages are built from `message_templates` with variable replacement:

```php
$message = str_replace(
    ['{{TENANT_NAME}}', '{{AMOUNT_DUE}}', '{{DUE_DATE}}', '{{COMPANY_NAME}}'],
    [$tenant['name'], money($amount), $due_date, $company_name],
    $template['body']
);
```

---

## 3. Email — SMTP

### 3.1 Overview

Transactional and scheduled report emails are sent via SMTP (compatible with Gmail, SendGrid, Mailgun, Postfix).

### 3.2 Configuration

```env
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=no-reply@yourdomain.com
MAIL_PASSWORD=your_app_password
MAIL_ENCRYPTION=tls       # tls | ssl
MAIL_FROM_NAME=RUMS Property Management
MAIL_FROM_ADDRESS=no-reply@yourdomain.com
```

### 3.3 Email Types

| Type | Trigger | Template |
|---|---|---|
| Payment Reminder | Scheduled cron / manual send | `message_templates` category: payment |
| Payment Confirmed | M-Pesa callback success | `message_templates` category: payment |
| Lease Expiry Notice | Cron: 60/30/7 days before end_date | `message_templates` category: lease |
| Welcome Tenant | New lease created | `message_templates` category: general |
| Maintenance Update | Work order status change | `message_templates` category: maintenance |
| Scheduled Report | `report_schedules` cron job | Report attachment (CSV/PDF) |
| Broadcast | Admin/Manager manual send | Custom body or template |

### 3.4 Scheduled Report Email Flow

```
Cron job runs every 5 minutes
    │
    ▼
Check report_schedules WHERE is_active=1 AND next_run_at <= NOW()
    │
    ▼
For each due schedule:
  1. Generate report data (ReportService method)
  2. Build CSV/PDF
  3. Compose email with attachment
  4. Send to all recipients[] via SMTP
  5. Update last_run_at = NOW()
  6. Calculate + update next_run_at
  7. Log in communication_logs (channel: email)
```

### 3.5 HTML Email Template Structure

```html
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif; max-width:600px; margin:0 auto">
  <div style="background:#0d6efd; padding:20px; color:white">
    <h2>RUMS Property Management</h2>
  </div>
  <div style="padding:20px">
    <!-- Template body (HTML from message_templates) -->
    {BODY}
  </div>
  <div style="background:#f8f9fa; padding:15px; font-size:12px; color:#6c757d; text-align:center">
    &copy; {YEAR} {COMPANY_NAME} · <a href="{UNSUBSCRIBE_URL}">Unsubscribe</a>
  </div>
</body>
</html>
```

---

## 4. Bank Statement Import (CSV)

### 4.1 Overview

The accountant imports bank statements as CSV files to reconcile bank credits against recorded payments. The system matches entries by amount + date ± 3 days, creating audit-ready reconciliation records.

### 4.2 Expected CSV Format

```csv
Date,Value Date,Description,Debit,Credit,Balance,Reference
2026-06-05,,MPESA PAYMENT FROM JOHN DOE,,25000.00,342000.00,MPESA REF QGH7823KLM
2026-06-06,,CHEQUE 001234,,50000.00,392000.00,RENT JUN 2026
2026-06-07,,BANK CHARGES,850.00,,391150.00,SERVICE FEE
```

**Required columns:** Date, Credit (or Debit), Description  
**Optional columns:** Value Date, Balance, Reference  

### 4.3 Import Process

```
POST /api/v1/accountant/bank-import (multipart/form-data)
{file: statement.csv, account_name: "KCB Business Account"}
    │
    ▼
Server:
  1. Parse CSV (handle BOM, CRLF, quoted fields)
  2. Validate headers — return 400 on missing required columns
  3. Generate import_batch UUID
  4. Insert rows into bank_statement_entries
  5. Auto-match: find payments WHERE
       amount = credit AND
       payment_date BETWEEN entry_date-3 AND entry_date+3
  6. Return: {imported, auto_matched, unmatched}
```

### 4.4 Manual Matching

```
POST /api/v1/accountant/bank-match
{ entry_id: 42, payment_id: 17 }
    │
    ▼
Update bank_statement_entries SET
  payment_id = 17,
  matched_by = auth_user_id,
  matched_at = NOW()
```

---

## 5. Configuration Reference

### 5.1 Environment Variables (`.env`)

```env
# ── Application ───────────────────────────────────────────────
APP_NAME=RUMS
APP_URL=https://yourdomain.com
APP_ENV=production              # local | sandbox | production
APP_DEBUG=false
APP_ENCRYPTION_KEY=hex_32_bytes # 32-byte hex key for AES-256-GCM
APP_VERSION=1.0.0

# ── Database ──────────────────────────────────────────────────
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=rums
DB_USER=rums_app
DB_PASS=strong_password

# ── M-Pesa ────────────────────────────────────────────────────
MPESA_CONSUMER_KEY=
MPESA_CONSUMER_SECRET=
MPESA_SHORTCODE=
MPESA_PASSKEY=
MPESA_ENV=sandbox
MPESA_CALLBACK_URL=https://yourdomain.com/api/v1/payments/mpesa/callback

# ── Africa's Talking SMS ──────────────────────────────────────
AT_USERNAME=
AT_API_KEY=
AT_SENDER_ID=RUMS
AT_ENV=sandbox

# ── Email / SMTP ──────────────────────────────────────────────
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=RUMS

# ── Company ───────────────────────────────────────────────────
COMPANY_NAME=Your Property Management Co.
COMPANY_PHONE=+254700000000
COMPANY_EMAIL=info@yourdomain.com
CURRENCY_SYMBOL=KES
```

### 5.2 Cron Job Schedule

```cron
# Monthly invoice generation — runs 1st of each month at 00:05
5 0 1 * * php /var/www/rental_api/cron/generate_invoices.php >> /var/log/rums-invoices.log 2>&1

# Scheduled report emails — every 5 minutes
*/5 * * * * php /var/www/rental_api/cron/send_scheduled_reports.php >> /var/log/rums-reports.log 2>&1

# Lease expiry reminders — daily at 07:00
0 7 * * * php /var/www/rental_api/cron/lease_expiry_reminders.php >> /var/log/rums-leases.log 2>&1

# MFA pending token cleanup — hourly
0 * * * * php /var/www/rental_api/cron/cleanup_mfa_pending.php

# Overdue invoice marking — daily at 00:01
1 0 * * * php /var/www/rental_api/cron/mark_overdue_invoices.php >> /var/log/rums-invoices.log 2>&1

# Payment reminder SMS/email — daily at 08:00
0 8 * * * php /var/www/rental_api/cron/payment_reminders.php >> /var/log/rums-reminders.log 2>&1
```

---

*End of Integration Specifications*
