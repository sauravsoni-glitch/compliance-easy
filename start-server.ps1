# Starts the app at http://localhost:8000 (matches config/app.php)
# Run from project folder:  .\start-server.ps1   (.\ required in PowerShell)
Set-Location $PSScriptRoot

$phpExe = $null
if (Test-Path 'C:\xampp\php\php.exe') {
    $phpExe = 'C:\xampp\php\php.exe'
} elseif (Get-Command php -ErrorAction SilentlyContinue) {
    $phpExe = 'php'
}

if (-not $phpExe) {
    Write-Error 'PHP not found. Install XAMPP (C:\xampp) or add PHP to your PATH.'
    exit 1
}

$env:APP_URL = 'http://localhost:8000'
Write-Host 'Open: http://localhost:8000' -ForegroundColor Green
Write-Host 'Press Ctrl+C to stop.' -ForegroundColor Yellow
# Router script required so paths like /login and /doa hit the app (not 404)
& $phpExe -S localhost:8000 -t public public/index.php
