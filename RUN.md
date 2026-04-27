# How to Run & Visualize the Compliance App

## Prerequisites

- **PHP 7.4+** (with PDO MySQL, session, json, mbstring)
- **MySQL 5.7+** or MariaDB

**If "php is not recognized" on Windows:** PHP is not in your PATH. Either:
1. **Add PHP to PATH:** Search “Environment variables” in Windows → Edit “Path” → Add the folder that contains `php.exe` (e.g. `C:\xampp\php`).
2. **Or use the batch file:** Run **`seed_demo_users.bat`** and **`run_server.bat`** (see below); they try common PHP locations.

---

## Step 1: Create the database

1. Open a terminal (PowerShell or Command Prompt).
2. Create the database and load the schema:

```bash
cd c:\Users\Saurav.Soni\Desktop\compliance
mysql -u root -p -e "CREATE DATABASE compliance_saas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p compliance_saas < database/schema.sql
```

**Existing database:** apply lifecycle updates once:

```bash
mysql -u root -p compliance_saas < database/migrations/002_compliance_lifecycle.sql
```

(Adds `compliance_history.comment`, widens `frequency`, and **Audit** authority. If `comment` already exists, skip that line or ignore the error.)

*(If your MySQL root has no password, omit the `-p` or press Enter when asked.)*

3. Update **`config/database.php`** if needed (host, username, password). Default is:
   - host: `127.0.0.1`
   - user: `root`
   - password: *(empty)*
   - database: `compliance_saas`

---

## Step 2: Seed demo users (for login)

Run this once so the demo credentials work.

**Option A – PHP in PATH:**
```bash
php database/seed_demo_users.php
```

**Option B – PHP not in PATH (Windows):**  
Double‑click **`seed_demo_users.bat`** in the project folder, or in PowerShell:
```powershell
.\seed_demo_users.bat
```
The batch file looks for PHP in XAMPP, Laragon, WAMP, or `C:\php`.

**Option C – Use full path to PHP**, e.g. XAMPP:
```powershell
C:\xampp\php\php.exe database/seed_demo_users.php
```

You should see lines like `Updated: admin@easyhome.com` etc.

---

## Step 3: Base URL

The default in **`config/app.php`** is `http://localhost:8000` (for the PHP built-in server).  
If you use Apache instead, set `'url' => 'http://localhost/compliance/public'` (or your actual URL).

---

## Step 4: Run the app

In the same project folder, start the PHP built-in server:

```bash
cd c:\Users\Saurav.Soni\Desktop\compliance
php -S localhost:8000 -t public public/index.php
```

You should see something like:

```
PHP 8.x Development Server (http://localhost:8000) started
```

---

## Step 5: Open in the browser

1. Open your browser and go to: **http://localhost:8000**
2. You should see the **Login** page (Easy Home Finance).
3. Use any demo account, for example:
   - **Admin:** `admin@easyhome.com` / `admin123`
   - **Maker:** `maker@easyhome.com` / `maker123`
   - **Reviewer:** `reviewer@demo.com` / `Reviewer@123`
   - **Approver:** `approver@demo.com` / `Approver@123`

4. After login you’ll see the **Dashboard** (KPIs, charts, recent activity, alerts).
5. Use the **sidebar** to open: Compliance Management, Circular Intelligence, DOA, Authority Matrix, Organization, Roles & Permissions, Billing & Subscription, Settings.

---

## Optional: Run with XAMPP / Apache

1. Copy the project (e.g. to `htdocs/compliance`).
2. In **`config/app.php`** set:  
   `'url' => 'http://localhost/compliance/public'`
3. In **`config/database.php`** set your MySQL credentials.
4. Create the database and run the schema and seed (Steps 1–2 above).
5. In the browser open: **http://localhost/compliance/public**

---

## Troubleshooting: "Connection refused"

If the seed script or app says **"No connection could be made"** or **"target machine actively refused it"**, MySQL is not running:

- **XAMPP:** Open **XAMPP Control Panel** → click **Start** next to **MySQL**. Wait until it shows green, then run `seed_demo_users.bat` again.
- **Laragon:** Start Laragon and ensure MySQL is running, then run the seed again.

---

## Quick checklist

| Step | Action |
|------|--------|
| 1 | Create DB `compliance_saas` and run `database/schema.sql` |
| 2 | Run `php database/seed_demo_users.php` |
| 3 | Set `config/app.php` → `'url' => 'http://localhost:8000'` |
| 4 | Run `php -S localhost:8000 -t public public/index.php` |
| 5 | Open **http://localhost:8000** and log in with a demo user |

---

## Quick health checks (recommended)

Run these from project root:

```powershell
# Works even when Composer is not installed globally
C:\xampp\php\php.exe scripts\check_system.php
```

If Composer is available on PATH, you can also run:

```bash
composer check
```
