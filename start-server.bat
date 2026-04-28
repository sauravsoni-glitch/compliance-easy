@echo off
REM Advanced: finds a free port and picks bind address via PowerShell.
REM NO POWERSHELL / execution-policy issues: use serve.bat instead (recommended for most setups).
title Compliance app - PHP server
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0start-server.ps1"
if errorlevel 1 pause
