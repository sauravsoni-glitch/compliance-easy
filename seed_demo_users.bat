@echo off
setlocal EnableExtensions
REM Try common PHP locations (PATH last — avoids WindowsApps php shim when XAMPP/Laragon exists).
set "PHP_CMD="
if exist "%LOCALAPPDATA%\Programs\Php\php.exe" set "PHP_CMD=%LOCALAPPDATA%\Programs\Php\php.exe"
if not defined PHP_CMD if exist "C:\xampp\php\php.exe" set "PHP_CMD=C:\xampp\php\php.exe"
if not defined PHP_CMD if exist "C:\laragon\bin\php\php.exe" set "PHP_CMD=C:\laragon\bin\php\php.exe"
if not defined PHP_CMD if exist "D:\laragon\bin\php\php.exe" set "PHP_CMD=D:\laragon\bin\php\php.exe"
if not defined PHP_CMD for /d %%D in ("C:\laragon\bin\php\*") do (
  if exist "%%~D\php.exe" (
    set "PHP_CMD=%%~D\php.exe"
    goto :have_php
  )
)
if not defined PHP_CMD for /d %%D in ("D:\laragon\bin\php\*") do (
  if exist "%%~D\php.exe" (
    set "PHP_CMD=%%~D\php.exe"
    goto :have_php
  )
)
if not defined PHP_CMD if exist "C:\wamp64\bin\php\php.exe" set "PHP_CMD=C:\wamp64\bin\php\php.exe"
if not defined PHP_CMD for /d %%D in ("C:\wamp64\bin\php\php*") do (
  if exist "%%~D\php.exe" (
    set "PHP_CMD=%%~D\php.exe"
    goto :have_php
  )
)
if not defined PHP_CMD if exist "C:\php\php.exe" set "PHP_CMD=C:\php\php.exe"
if not defined PHP_CMD if exist "%ProgramFiles%\PHP\php.exe" set "PHP_CMD=%ProgramFiles%\PHP\php.exe"
if not defined PHP_CMD if exist "%USERPROFILE%\.herd\bin\php.exe" set "PHP_CMD=%USERPROFILE%\.herd\bin\php.exe"
if not defined PHP_CMD where php >nul 2>&1 && set "PHP_CMD=php"
:have_php
if not defined PHP_CMD (
    echo PHP not found. Please do one of the following:
    echo 1. Install XAMPP from https://www.apachefriends.org/ then run this again.
    echo 2. Or add PHP to PATH: add the folder containing php.exe to your system PATH.
    echo 3. Or run manually: "C:\path\to\php.exe" database\seed_demo_users.php
    pause
    exit /b 1
)
echo Using: %PHP_CMD%
"%PHP_CMD%" database/seed_demo_users.php
pause
endlocal
