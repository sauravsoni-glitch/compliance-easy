# Starts the app at http://localhost:8000 (matches config/app.php)
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

Write-Host 'Open: http://localhost:8000' -ForegroundColor Green
Write-Host 'Press Ctrl+C to stop.' -ForegroundColor Yellow
& $phpExe -S localhost:8000 -t public
