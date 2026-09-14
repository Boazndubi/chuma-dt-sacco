# Chuna DT Sacco — Loan Payment Portal

A loan repayment system for **Chuna DT Sacco Ltd** ("The University Sacco"). Members get a personalized payment link by SMS or WhatsApp, tap it, and pay their loan balance by M-Pesa. Admins manage the whole thing from a dashboard.

## What it does

**Member side** (`public/pay.php`)
- Member opens a link like `https://yourdomain/pay.php?token=...`
- Sees their name, loan number, outstanding balance, and due date
- Enters their M-Pesa number and pays via an STK push (Lipa Na M-Pesa Online)
- Page polls for the result and shows a receipt once Safaricom confirms

**Admin side** (`admin/`)
- Login-protected dashboard listing all outstanding loans
- Search by name, loan number, member number, or phone
- Filter by status (active/overdue/cleared) and whether a reminder was ever sent
- Sort any column, paginate through results
- Send a payment link to one member via **SMS**, **WhatsApp**, or **both** — or select multiple members and send in bulk
- Every send is logged (channel, timestamp, which admin sent it)

## Stack

- Plain PHP (no framework) — PDO for all database access
- Postgres (built and tested against [Neon](https://neon.tech))
- [Africa's Talking](https://africastalking.com) for SMS
- [Meta WhatsApp Cloud API](https://developers.facebook.com/docs/whatsapp) for WhatsApp
- [Safaricom Daraja](https://developer.safaricom.co.ke) for M-Pesa STK push
- No build step, no JS framework — vanilla JS + CSS for both the member page and admin dashboard

## Setup

1. **Clone and configure**
   ```bash
   git clone https://github.com/Boazndubi/chuma-dt-sacco.git
   cd chuma-dt-sacco
   cp .env.example .env
   ```
   Fill in `.env` with your database, M-Pesa, Africa's Talking, and WhatsApp credentials. `.env` is gitignored — never commit it.

2. **Create the database**

   Run these against your Postgres database (e.g. via Neon's SQL Editor, or `psql`):
   ```bash
   sql/schema.sql
   sql/migration_admin.sql
   ```
   Each loan must have its three-character `product_code` populated from the
   Sacco loan register (for example, `NO1` or `SA2`). The member's Paybill
   account reference is generated as `product_code + member_no`.

3. **Create your first admin login**
   ```bash
   php -r "echo password_hash('your-password-here', PASSWORD_DEFAULT), PHP_EOL;"
   ```
   Then insert it:
   ```sql
   INSERT INTO admins (username, password_hash, full_name)
   VALUES ('admin', 'PASTE_THE_HASH_HERE', 'Your Name');
   ```

4. **Run it locally**
   ```bash
   php -S localhost:8000
   ```
   - Admin: `http://localhost:8000/admin/login.php`
   - Member page (needs a valid token): `http://localhost:8000/public/pay.php?token=...`

5. **Deploy**

   Point your web server's document root at `public/`. The `public/api/` bridge files expose only the required API endpoints while the application code remains outside the web root. Never expose `.env`, `config/`, `includes/`, or the database files.

   Set these production values in `.env`:

   ```env
   APP_BASE_URL=https://yourdomain.com
   MPESA_ENV=production
   MPESA_CALLBACK_URL=https://yourdomain.com/api/mpesa-callback.php
   ```

   The live server must have a valid TLS certificate, PHP cURL enabled, PHP PostgreSQL support enabled, and inbound HTTPS access. Safaricom must be able to reach the callback URL from the public internet.

## Provider accounts and credentials

The developer supplies the application code. The Sacco or its authorized account owner must open and verify these provider accounts, complete business/KYC checks, and provide the production credentials:

### Safaricom Daraja (M-Pesa STK Push)

- Production Daraja app consumer key and consumer secret
- Registered M-Pesa Paybill or shortcode
- STK Passkey for that shortcode
- Confirmation that the shortcode is enabled for Lipa na M-Pesa Online
- Public HTTPS callback URL: `https://yourdomain.com/api/mpesa-callback.php`
- A Safaricom test line and permission to test real deductions

Use the Daraja sandbox first. Replace the sandbox values only after the callback and payment flow have been tested.

### Africa's Talking (SMS)

- Verified Africa's Talking account
- Live username and API key
- SMS credit
- Approved sender ID, if a branded sender name is required

The sender ID is optional in code, but approval and availability depend on the mobile networks.

### Meta WhatsApp Cloud API

- Meta Business account with business verification where required
- WhatsApp Business account and registered business phone number
- Phone Number ID, not the visible phone number
- Permanent system-user access token with WhatsApp messaging permissions
- An approved template named `loan_payment_reminder` (or the configured template name)
- Template language and body variables matching the application

### Database and operations

- Production PostgreSQL/Neon database and connection credentials
- Initial schema and admin migration applied
- First admin account created with a strong password
- Member loan data loaded with valid product codes, phone numbers, balances, and due dates
- A backup and log-monitoring plan

Do not send credentials by email or commit them to Git. Put them only in the server's `.env` file, restrict that file's permissions, and rotate any credential that has been exposed.

## Project structure

```
chuma-dt-sacco/
├── .env.example          # copy to .env and fill in real credentials
├── config/                # env loader, DB connection, M-Pesa/SMS/WhatsApp config
├── includes/              # shared PHP helpers (auth, loans, reminders, sms, whatsapp)
├── public/                # member-facing payment page — point your web root here
│   ├── pay.php
│   └── assets/
├── api/                   # M-Pesa STK push, callback, and status-check endpoints
├── admin/                 # login, dashboard, single + bulk send endpoints
│   └── assets/
└── sql/
    ├── schema.sql         # core tables
    └── migration_admin.sql # admin login + send-tracking columns
```

## Notes

- Payment links expire after 14 days by default and are reused (not regenerated) while still valid, so re-sending a reminder doesn't invalidate a link the member may have already opened.
- WhatsApp requires an approved message template for the first message to a member (see `config/whatsapp.php` for details) — free-text only works once they've replied to you.
- The bulk-send endpoint caps a single batch at 200 recipients.
