@echo off
REM No PowerShell — works even when "running scripts is disabled".
REM Starts PHP built-in server for localhost access (IPv4/IPv6 by OS resolution).
title Compliance — local web server
cd /d "%~dp0"

if not exist "public\index.php" (
  echo ERROR: Run this file from the project folder ^(needs public\index.php^).
  pause
  exit /b 1
)

set "PHP_EXE="
REM Same search order intent as start-server.ps1 — prefer PATH, then common installs
where php >nul 2>&1 && set "PHP_EXE=php"
if not defined PHP_EXE if exist "%LOCALAPPDATA%\Programs\Php\php.exe" set "PHP_EXE=%LOCALAPPDATA%\Programs\Php\php.exe"
if not defined PHP_EXE if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"
if not defined PHP_EXE if exist "C:\laragon\bin\php\php.exe" set "PHP_EXE=C:\laragon\bin\php\php.exe"
if not defined PHP_EXE if exist "C:\wamp64\bin\php\php.exe" set "PHP_EXE=C:\wamp64\bin\php\php.exe"
if not defined PHP_EXE if exist "C:\php\php.exe" set "PHP_EXE=C:\php\php.exe"
if not defined PHP_EXE if exist "%ProgramFiles%\PHP\php.exe" set "PHP_EXE=%ProgramFiles%\PHP\php.exe"
if not defined PHP_EXE if exist "%USERPROFILE%\.herd\bin\php.exe" set "PHP_EXE=%USERPROFILE%\.herd\bin\php.exe"
if not defined PHP_EXE if exist "%USERPROFILE%\.herd\bin\php.bat" set "PHP_EXE=%USERPROFILE%\.herd\bin\php.bat"

if not defined PHP_EXE (
  echo ERROR: PHP was not found. Install XAMPP ^(recommended^), Laragon, WAMP,
  echo or add php.exe to your Windows PATH.
  pause
  exit /b 1
)

set "PORT="
if defined PHP_SERVER_PORT set "PORT=%PHP_SERVER_PORT%"
if not defined PORT set PORT=8000
set "BIND=localhost"

echo.
echo  Starting PHP built-in server — keep THIS WINDOW OPEN while you browse the app.
echo.
echo  In your browser use HTTP ^(not HTTPS^)^:
echo    http://localhost:%PORT%/
echo    http://127.0.0.1:%PORT%/
echo.
echo  Listen: %BIND%:%PORT%
echo  Stop: Ctrl+C in this window
echo.

"%PHP_EXE%" -S "%BIND%:%PORT%" -t public public/index.php

echo.
echo --- Server stopped ---
echo If you saw "Address already in use", before running this file again:
echo   set PHP_SERVER_PORT=8010
pause
