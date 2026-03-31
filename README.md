# Compliance Management SaaS – Easy Home Finance

PHP + MySQL compliance management platform with role-based access, session auth, and multi-tenant support.

## Requirements

- PHP 7.4+ (with PDO MySQL, session, json, mbstring, **curl** for optional upload webhook forwarding)
- MySQL 5.7+ or MariaDB
- Web server (Apache with mod_rewrite) or PHP built-in server
- **Composer** (for PHPMailer — run `composer install` in the project root after clone)

## Setup

### 1. Database

Create a database and load the schema:

```bash
mysql -u root -p -e "CREATE DATABASE compliance_saas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p compliance_saas < database/schema.sql
```

Update `config/database.php` with your MySQL credentials (host, username, password, database).

**PHP dependencies (invite emails):**

```bash
composer install
```

**Uploads:** User-uploaded files are stored under `public/uploads/upload_history/` (subfolders per module). See `public/uploads/upload_history/README.txt`. Legacy rows that reference files directly under `public/uploads/` still resolve.

**n8n file webhook:** On each successful upload, the app also POSTs the file as multipart form fields `file` and `file_name` to `file_upload_webhook_url` in `config/app.php`. Set `file_upload_webhook_enabled` to `false` to disable (e.g. local dev). Requires the PHP **curl** extension.

**Invite emails (SMTP or Mailgun):** After `composer install`, set environment variables on the server (or in your PHP-FPM/Apache pool). See `config/mail.php` for the full list.

Typical Gmail SMTP setup:

- `MAIL_ENABLED=1`
- `MAIL_HOST=smtp.gmail.com`
- `MAIL_PORT=587`
- `MAIL_ENCRYPTION=tls`
- `MAIL_USERNAME=youraddress@gmail.com`
- `MAIL_PASSWORD=` your **Gmail App Password** (16 characters, with 2-Step Verification enabled)
- `MAIL_FROM=youraddress@gmail.com` (usually same as `MAIL_USERNAME`)
- `MAIL_FROM_NAME=Easy Home Finance`

Mailgun API setup:

- `MAIL_ENABLED=1`
- `MAIL_PROVIDER=mailgun` (or `auto` to try Mailgun then fallback to SMTP)
- `MAIL_FROM=no-reply@your-mailgun-domain`
- `MAIL_FROM_NAME=Easy Home Finance`
- `MAILGUN_DOMAIN=mg.yourdomain.com`
- `MAILGUN_API_KEY=key-xxxxxxxxxxxxxxxx`
- `MAILGUN_ENDPOINT=https://api.mailgun.net`

For local testing without env vars, copy `config/mail.local.example.php` to `config/mail.local.php` (gitignored) and edit; those values override `config/mail.php`.

If `MAIL_ENABLED` is not set, invites are still saved and the join link is shown in the success message (no email sent).

### 2. Application URL

The public base URL is resolved from the **`APP_URL`** environment variable, or falls back to the default in `config/app.php` (used for links and assets).

- **Production:** set `APP_URL=https://compliance.easyhomefinance.in` on the server.
- **Local (PHP built-in server):** default is often `http://localhost:8000`, or set `APP_URL` before starting PHP.
- **Apache subfolder (e.g. XAMPP):** e.g. `APP_URL=http://localhost/compliance/public`

### 3. Run with PHP built-in server

**Windows PowerShell:** from the project folder you must use `.\` (current directory is not on the command search path):

```powershell
cd c:\Users\Saurav.Soni\Desktop\compliance
.\start-server.ps1
```

Same idea for the batch file: `.\start-server.bat`.

**Any shell** (sets `APP_URL` for local links if you use the scripts above; otherwise set it yourself):

```bash
cd c:\Users\Saurav.Soni\Desktop\compliance
php -S localhost:8000 -t public
```

Open **http://localhost:8000** in the browser.

### 4. Run with Apache

- Point document root to the `public` folder.
- Ensure `mod_rewrite` is enabled and `.htaccess` is allowed.
- Adjust `RewriteBase` in `public/.htaccess` if the app is in a subdirectory (e.g. `RewriteBase /compliance/public/`).

## Demo login (RBAC)

After loading the schema, run the demo users seed once so that login passwords match the UI:

```bash
php database/seed_demo_users.php
```

Then use:

| Role     | Email              | Password    |
|----------|--------------------|-------------|
| Admin    | admin@easyhome.com | admin123    |
| Maker    | maker@easyhome.com | maker123    |
| Reviewer | reviewer@demo.com  | Reviewer@123 |
| Approver | approver@demo.com  | Approver@123 |

## Features

- **Auth:** Login, logout, forgot password, create account (after checkout), session (expires when browser closes). Public: pricing (`/pricing`), checkout (`/checkout`), create account (`/create-account`).
- **Dashboard:** Summary cards (Total/Pending/Approved/Overdue/High Risk), recent activity, alerts, calendar, modals for overdue and high-risk.
- **Compliance:** List (filters, pagination), Create, View (tabs: Overview, History, Documents, Activity), Edit, Delete, Submit, Approve/Rework, document upload.
- **Circular Intelligence:** List circulars, view, upload, approve.
- **DOA:** Delegation of Authority list by department.
- **Authority Matrix:** Compliance workflow rules (Maker → Reviewer → Approver).
- **Organization:** Profile, setup steps, invite users.
- **Roles & Permissions:** User list, change role, activate/deactivate.
- **Billing:** Current plan, billing history table.
- **Settings:** Email reminder and escalation settings.
- **Financial Ratios:** Summary cards and category list.
- **Reports / Bulk Upload:** Placeholder pages.

## Theme

- Dark sidebar, red primary `#dc2626`, white cards with soft shadow, rounded buttons, enterprise-style layout (sidebar + top header + content).

## Security

- Passwords hashed with bcrypt.
- Routes protected by role (admin/maker/reviewer/approver).
- Input validation and PDO prepared statements to avoid SQL injection.

## Project structure

```
compliance/
├── app/
│   ├── Core/         (Database, Router, Auth, BaseController)
│   ├── Controllers/
│   └── Views/
├── config/
├── database/
│   └── schema.sql
├── public/
│   ├── assets/
│   ├── uploads/
│   ├── .htaccess
│   └── index.php
└── README.md
```
