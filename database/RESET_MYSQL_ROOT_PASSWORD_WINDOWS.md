# Reset MySQL `root` password on Windows

Use **Administrator** Command Prompt or PowerShell.

Replace `YourNewPasswordHere` everywhere with a strong password you choose (mix of letters, numbers, symbols).

---

## Step 1: Find the MySQL service name

```powershell
Get-Service | Where-Object { $_.DisplayName -like '*mysql*' -or $_.Name -like '*mysql*' }
```

Common names: `MySQL84`, `MySQL80`, `MySQL`. Note the **Name** column.

---

## Step 2: Stop MySQL

```cmd
net stop MySQL84
```

(If that fails, try `MySQL80` or `MySQL` — use the name from Step 1.)

---

## Step 3: Start MySQL **without** password checks

Open a **new** Administrator **Command Prompt** (not PowerShell for this step, to avoid execution policy issues):

```cmd
cd /d "C:\Program Files\MySQL\MySQL Server 8.4\bin"
mysqld --console --skip-grant-tables --shared-memory
```

Leave this window **open** (MySQL is running in the foreground).

---

## Step 4: In a **second** Administrator window — set the new password

```cmd
cd /d "C:\Program Files\MySQL\MySQL Server 8.4\bin"
mysql.exe -u root
```

At the `mysql>` prompt, run (edit the password first):

```sql
FLUSH PRIVILEGES;
ALTER USER 'root'@'localhost' IDENTIFIED BY 'YourNewPasswordHere';
FLUSH PRIVILEGES;
EXIT;
```

If `ALTER USER` fails for `'root'@'localhost'`, try:

```sql
SELECT user, host FROM mysql.user WHERE user='root';
```

Then use the **host** you see (e.g. `%` or `127.0.0.1`):

```sql
ALTER USER 'root'@'%' IDENTIFIED BY 'YourNewPasswordHere';
```

---

## Step 5: Stop the temporary server

In the window where `mysqld --skip-grant-tables` is running, press **Ctrl+C** to stop it.

---

## Step 6: Start MySQL normally

```cmd
net start MySQL84
```

(Again, use your real service name.)

---

## Step 7: Test login

```cmd
"C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe" -u root -p
```

Enter **YourNewPasswordHere**.

---

## Step 8: Match your PHP app (`config/database.php`)

Set the same password there:

```php
'password' => 'YourNewPasswordHere',
```

---

## If `mysql.exe` is not in that folder

Adjust the path to match your install, e.g.:

`C:\Program Files\MySQL\MySQL Server 8.0\bin\`

---

## Alternative: `--init-file` (one shot)

1. Create `C:\mysql-reset-root.sql` containing **only**:

   ```sql
   ALTER USER 'root'@'localhost' IDENTIFIED BY 'YourNewPasswordHere';
   ```

2. Stop the service (`net stop MySQL84`).

3. Run **once** (Administrator CMD):

   ```cmd
   cd /d "C:\Program Files\MySQL\MySQL Server 8.4\bin"
   mysqld --init-file=C:\mysql-reset-root.sql --console
   ```

4. When it finishes starting, stop with **Ctrl+C**, **delete** `C:\mysql-reset-root.sql`, then `net start MySQL84`.

If MySQL complains about init-file, use the **skip-grant-tables** method above instead.
