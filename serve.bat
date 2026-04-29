@echo off
setlocal EnableExtensions EnableDelayedExpansion
REM No PowerShell required — works when script execution is disabled.
REM Starts PHP built-in server. Default bind: 127.0.0.1 (most reliable on Windows).
REM   set PHP_SERVER_BIND=localhost   to use localhost instead
REM   set PHP_SERVER_PORT=8010        to force a port (otherwise 8000–8015 is auto-picked)
title Compliance — local web server
cd /d "%~dp0"

if not exist "public\index.php" (
  echo ERROR: Run this file from the project folder ^(needs public\index.php^).
  pause
  exit /b 1
)

set "PHP_EXE="
REM Known installs first — "where php" may hit WindowsApps shim before a real PHP (XAMPP/Laragon).
if not defined PHP_EXE if exist "%LOCALAPPDATA%\Programs\Php\php.exe" set "PHP_EXE=%LOCALAPPDATA%\Programs\Php\php.exe"
if not defined PHP_EXE if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"
if not defined PHP_EXE if exist "C:\wamp64\bin\php\php.exe" set "PHP_EXE=C:\wamp64\bin\php\php.exe"
if not defined PHP_EXE if exist "C:\php\php.exe" set "PHP_EXE=C:\php\php.exe"
if not defined PHP_EXE if exist "%ProgramFiles%\PHP\php.exe" set "PHP_EXE=%ProgramFiles%\PHP\php.exe"
if not defined PHP_EXE if exist "%USERPROFILE%\.herd\bin\php.exe" set "PHP_EXE=%USERPROFILE%\.herd\bin\php.exe"
if not defined PHP_EXE if exist "%USERPROFILE%\.herd\bin\php.bat" set "PHP_EXE=%USERPROFILE%\.herd\bin\php.bat"
REM Laragon: versioned folders under bin\php
if not defined PHP_EXE if exist "C:\laragon\bin\php\php.exe" set "PHP_EXE=C:\laragon\bin\php\php.exe"
if not defined PHP_EXE if exist "D:\laragon\bin\php\php.exe" set "PHP_EXE=D:\laragon\bin\php\php.exe"
if not defined PHP_EXE for /d %%D in ("C:\laragon\bin\php\*") do (
  if exist "%%~D\php.exe" (
    set "PHP_EXE=%%~D\php.exe"
    goto :php_found
  )
)
if not defined PHP_EXE for /d %%D in ("D:\laragon\bin\php\*") do (
  if exist "%%~D\php.exe" (
    set "PHP_EXE=%%~D\php.exe"
    goto :php_found
  )
)
if not defined PHP_EXE where php >nul 2>&1 && set "PHP_EXE=php"
:php_found

if not defined PHP_EXE (
  echo ERROR: PHP was not found. Install PHP ^(XAMPP, Laragon, WAMP^) or add php.exe to PATH.
  pause
  exit /b 1
)

REM Quick sanity check
"%PHP_EXE%" -r "exit(0);" >nul 2>&1
if errorlevel 1 (
  echo ERROR: PHP did not run: "%PHP_EXE%"
  pause
  exit /b 1
)

set "BIND=127.0.0.1"
if defined PHP_SERVER_BIND set "BIND=%PHP_SERVER_BIND%"

set "PORT="
if defined PHP_SERVER_PORT set "PORT=%PHP_SERVER_PORT%"
if defined PORT (
  netstat -an 2>nul | findstr /I /C:":!PORT! " | findstr /I "LISTENING" >nul 2>&1
  if not errorlevel 1 (
    echo ERROR: Port !PORT! is already in use ^(PHP_SERVER_PORT^). Pick a free port, e.g.:
    echo   set PHP_SERVER_PORT=8010
    echo   serve.bat
    pause
    exit /b 1
  )
  set "CHOSEN=!PORT!"
  goto :have_port
)

REM Pick first free port in 8000–8015 (LISTENING lines from netstat)
set "CHOSEN="
for /L %%P in (8000,1,8015) do (
  netstat -an 2>nul | findstr /I /C:":%%P " | findstr /I "LISTENING" >nul 2>&1
  if errorlevel 1 (
    set "CHOSEN=%%P"
    goto :have_port
  )
)
echo ERROR: No free port found between 8000 and 8015. Close another server or run:
echo   set PHP_SERVER_PORT=8020
echo   serve.bat
pause
exit /b 1

:have_port
set "PORT=!CHOSEN!"

echo.
echo  Starting PHP built-in server — keep THIS WINDOW OPEN while you browse.
echo.
echo  Use HTTP ^(not HTTPS^). Try in this order if one does not load:
echo    http://127.0.0.1:!PORT!/
echo    http://localhost:!PORT!/
echo.
echo  Listen: !BIND!:!PORT!
echo  Stop: Ctrl+C in this window
echo.

"%PHP_EXE%" -S "!BIND!:!PORT!" -t public public/index.php

echo.
echo --- Server stopped ---
echo If the browser says the site cannot be reached:
echo   1^) Confirm this window stayed open while testing.
echo   2^) Try http://127.0.0.1:!PORT!/ instead of localhost.
echo   3^) If "Address already in use", run: set PHP_SERVER_PORT=8010
pause
endlocal
