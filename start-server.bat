@echo off
REM Run from project folder:  .\start-server.bat
title Compliance app - PHP server
cd /d "%~dp0"

set "PHP_CMD="
if exist "C:\xampp\php\php.exe" set "PHP_CMD=C:\xampp\php\php.exe"
if not defined PHP_CMD if exist "C:\laragon\bin\php\php.exe" set "PHP_CMD=C:\laragon\bin\php\php.exe"
if not defined PHP_CMD for /d %%D in (C:\laragon\bin\php\php-*) do if exist "%%D\php.exe" set "PHP_CMD=%%D\php.exe"
if not defined PHP_CMD if exist "C:\wamp64\bin\php\php.exe" set "PHP_CMD=C:\wamp64\bin\php\php.exe"
if not defined PHP_CMD for /d %%D in ("C:\wamp64\bin\php\php*") do if exist "%%D\php.exe" set "PHP_CMD=%%D\php.exe"
if not defined PHP_CMD if exist "C:\php\php.exe" set "PHP_CMD=C:\php\php.exe"
if not defined PHP_CMD (
  where php >nul 2>&1 && set "PHP_CMD=php"
)
if not defined PHP_CMD (
  echo PHP not found. Install XAMPP, Laragon, WAMP, or add PHP to PATH.
  pause
  exit /b 1
)

set "PORT=8000"
set "APP_URL=http://127.0.0.1:%PORT%"
echo Starting %APP_URL% ...
echo Open: %APP_URL%   (if that fails in browser, try http://localhost:%PORT%)
echo Press Ctrl+C to stop.
"%PHP_CMD%" -S 127.0.0.1:%PORT% -t public public/index.php
pause
