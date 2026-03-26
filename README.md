# Compliance Management SaaS – Easy Home Finance

PHP + MySQL compliance management platform with role-based access, session auth, and multi-tenant support.

## Requirements

- PHP 7.4+ (with PDO MySQL, session, json, mbstring, **curl** for optional upload webhook forwarding)
- MySQL 5.7+ or MariaDB
- Web server (Apache with mod_rewrite) or PHP built-in server

## Setup

### 1. Database

Create a database and load the schema:

```bash
mysql -u root -p -e "CREATE DATABASE compliance_saas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p compliance_saas < database/schema.sql
```

Update `config/database.php` with your MySQL credentials (host, username, password, database).

**Uploads:** User-uploaded files are stored under `public/uploads/upload_history/` (subfolders per module). See `public/uploads/upload_history/README.txt`. Legacy rows that reference files directly under `public/uploads/` still resolve.

**n8n file webhook:** On each successful upload, the app also POSTs the file as multipart form fields `file` and `file_name` to `file_upload_webhook_url` in `config/app.php`. Set `file_upload_webhook_enabled` to `false` to disable (e.g. local dev). Requires the PHP **curl** extension.

### 2. Application URL

The public base URL is resolved from the **`APP_URL`** environment variable, or defaults to **`https://compliance.easyhomefinance.in`** in `config/app.php` (used for links and assets).

- **Production:** Set `APP_URL=https://compliance.easyhomefinance.in` (or rely on the default in `config/app.php`).
- **Local (PHP built-in server):** `APP_URL=http://localhost:8000` before starting the server, or adjust the default in `config/app.php`.
- **Apache subfolder (e.g. XAMPP):** e.g. `APP_URL=http://localhost/compliance/public`

### 3. Run with PHP built-in server

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
