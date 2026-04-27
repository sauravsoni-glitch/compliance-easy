@echo off
REM Start the Compliance app (PHP built-in server). Run: run_server.bat
set PHP_CMD=
where php >nul 2>&1 && set PHP_CMD=php
if defined PHP_CMD goto :run
if exist "C:\xampp\php\php.exe" set PHP_CMD=C:\xampp\php\php.exe
if exist "C:\laragon\bin\php\php.exe" set PHP_CMD=C:\laragon\bin\php\php.exe
if not defined PHP_CMD for /d %%D in (C:\laragon\bin\php\php-*) do if exist "%%D\php.exe" set "PHP_CMD=%%D\php.exe"
if exist "C:\wamp64\bin\php\php.exe" set PHP_CMD=C:\wamp64\bin\php\php.exe
if not defined PHP_CMD for /d %%D in (C:\wamp64\bin\php\php*) do if exist "%%D\php.exe" set "PHP_CMD=%%D\php.exe"
if exist "C:\php\php.exe" set PHP_CMD=C:\php\php.exe
if not defined PHP_CMD (
    echo PHP not found. Add PHP to PATH or install XAMPP/Laragon.
    pause
    exit /b 1
)
cd /d "%~dp0"
set "APP_URL=http://127.0.0.1:8000"
echo Starting server at %APP_URL%
echo If the browser fails, try http://localhost:8000
echo Press Ctrl+C to stop.
"%PHP_CMD%" -S 127.0.0.1:8000 -t public public/index.php
pause
