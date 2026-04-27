# Starts the app (PHP built-in server). Run from project folder:  .\start-server.ps1
Set-Location $PSScriptRoot

function Get-PhpExecutable {
    $candidates = @(
        'C:\xampp\php\php.exe',
        'C:\laragon\bin\php\php.exe',
        'C:\wamp64\bin\php\php.exe',
        'C:\php\php.exe'
    )
    foreach ($p in $candidates) {
        if (Test-Path $p) { return $p }
    }
    foreach ($d in (Get-ChildItem -Path 'C:\laragon\bin\php' -Directory -ErrorAction SilentlyContinue)) {
        $exe = Join-Path $d.FullName 'php.exe'
        if (Test-Path $exe) { return $exe }
    }
    foreach ($d in (Get-ChildItem -Path 'C:\wamp64\bin' -Filter 'php*' -Directory -ErrorAction SilentlyContinue)) {
        $exe = Join-Path $d.FullName 'php.exe'
        if (Test-Path $exe) { return $exe }
    }
    if (Get-Command php -ErrorAction SilentlyContinue) { return 'php' }
    return $null
}

function Test-LocalPortAvailable([int] $Port) {
    try {
        $l = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, $Port)
        $l.Start()
        $l.Stop()
        return $true
    } catch {
        return $false
    }
}

$phpExe = Get-PhpExecutable
if (-not $phpExe) {
    Write-Error 'PHP not found. Install XAMPP, Laragon, WAMP, or add PHP to PATH.'
    exit 1
}

$port = $null
foreach ($p in @(8000, 8001, 8002, 8080, 8888)) {
    if (Test-LocalPortAvailable $p) {
        $port = $p
        break
    }
}
if ($null -eq $port) {
    Write-Error 'No free port found (tried 8000, 8001, 8002, 8080, 8888). Close another app using those ports.'
    exit 1
}

# 127.0.0.1 avoids some Windows "localhost" / IPv6 resolution issues; must match APP_URL for redirects.
$baseUrl = "http://127.0.0.1:$port"
$env:APP_URL = $baseUrl

Write-Host "PHP: $phpExe" -ForegroundColor DarkGray
Write-Host "Open: $baseUrl" -ForegroundColor Green
Write-Host "If the browser still fails, try: http://localhost:$port" -ForegroundColor DarkYellow
Write-Host 'Press Ctrl+C to stop.' -ForegroundColor Yellow

& $phpExe -S "127.0.0.1:$port" -t public public/index.php
