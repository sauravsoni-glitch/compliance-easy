@echo off
REM Creates database, loads schema, and seeds demo users. Run: setup_database.bat
cd /d "%~dp0"

REM Step 1: Create database using PHP (no mysql.exe needed)
set PHP_CMD=
if exist "%LOCALAPPDATA%\Programs\Php\php.exe" set PHP_CMD=%LOCALAPPDATA%\Programs\Php\php.exe
if not defined PHP_CMD if exist "C:\xampp\php\php.exe" set PHP_CMD=C:\xampp\php\php.exe
if not defined PHP_CMD if exist "C:\laragon\bin\php\php.exe" set PHP_CMD=C:\laragon\bin\php\php.exe
if not defined PHP_CMD if exist "D:\laragon\bin\php\php.exe" set PHP_CMD=D:\laragon\bin\php\php.exe
if not defined PHP_CMD for /d %%D in ("C:\laragon\bin\php\*") do (
  if exist "%%~D\php.exe" (
    set "PHP_CMD=%%~D\php.exe"
    goto :php_ok
  )
)
if not defined PHP_CMD for /d %%D in ("D:\laragon\bin\php\*") do (
  if exist "%%~D\php.exe" (
    set "PHP_CMD=%%~D\php.exe"
    goto :php_ok
  )
)
if not defined PHP_CMD where php >nul 2>&1 && set PHP_CMD=php
:php_ok
if not defined PHP_CMD (
    echo PHP not found. Install XAMPP or add PHP to PATH.
    pause
    exit /b 1
)

echo [1/3] Creating database...
"%PHP_CMD%" create_database.php
if errorlevel 1 (
    echo Failed to create database.
    pause
    exit /b 1
)

REM Step 2: Load schema using mysql
set MYSQL_CMD=
where mysql >nul 2>&1 && set MYSQL_CMD=mysql
if not defined MYSQL_CMD if exist "C:\xampp\mysql\bin\mysql.exe" set MYSQL_CMD=C:\xampp\mysql\bin\mysql.exe
if not defined MYSQL_CMD if exist "C:\laragon\bin\mysql\mysql.exe" set MYSQL_CMD=C:\laragon\bin\mysql\mysql.exe
if not defined MYSQL_CMD for /d %%D in (C:\laragon\bin\mysql\mysql-*) do if exist "%%D\bin\mysql.exe" set "MYSQL_CMD=%%D\bin\mysql.exe"

if not defined MYSQL_CMD (
    echo.
    echo mysql.exe not found. Database was created. To load schema, run in XAMPP shell or add mysql to PATH:
    echo   mysql -u root compliance_saas ^< database\schema.sql
    echo Then run seed_demo_users.bat
    pause
    exit /b 1
)

echo [2/3] Loading schema...
"%MYSQL_CMD%" -u root compliance_saas < database\schema.sql
if errorlevel 1 (
    echo Failed to load schema. Check that MySQL is running.
    pause
    exit /b 1
)
echo Schema loaded.

REM Step 3: Seed demo users
echo [3/3] Seeding demo users...
"%PHP_CMD%" database/seed_demo_users.php

echo.
echo All set. Run run_server.bat and open http://localhost:8000
pause
