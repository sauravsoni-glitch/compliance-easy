@echo off
title Compliance app - PHP server
cd /d "%~dp0"

REM Try XAMPP (common on Windows), then PHP on PATH
if exist "C:\xampp\php\php.exe" (
  echo Starting http://localhost:8000 ...
  echo Open: http://localhost:8000
  echo Press Ctrl+C to stop.
  "C:\xampp\php\php.exe" -S localhost:8000 -t public
) else (
  where php >nul 2>&1
  if errorlevel 1 (
    echo PHP not found. Install XAMPP or add PHP to PATH.
    pause
    exit /b 1
  )
  echo Starting http://localhost:8000 ...
  php -S localhost:8000 -t public
)
pause
