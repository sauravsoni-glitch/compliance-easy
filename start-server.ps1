# Starts the app (PHP built-in server).
# If "running scripts is disabled": double-click start-server.bat, or from this folder run:
#   powershell -NoProfile -ExecutionPolicy Bypass -File ".\start-server.ps1"
# Or once (current user): Set-ExecutionPolicy -Scope CurrentUser RemoteSigned
Set-Location $PSScriptRoot

function Get-PhpExecutable {
    $candidates = @(
        'C:\xampp\php\php.exe',
        'C:\laragon\bin\php\php.exe',
        'C:\wamp64\bin\php\php.exe',
        'C:\php\php.exe',
        "$env:LOCALAPPDATA\Programs\Php\php.exe",
        'C:\Program Files\PHP\php.exe',
        "$env:USERPROFILE\.herd\bin\php.exe",
        "$env:USERPROFILE\.herd\bin\php.bat"
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
    $scoopPhp = Get-ChildItem -Path "$env:USERPROFILE\scoop\apps\php" -Recurse -Filter 'php.exe' -ErrorAction SilentlyContinue |
        Select-Object -First 1 -ExpandProperty FullName
    if ($scoopPhp) { return $scoopPhp }
    if (Get-Command php -ErrorAction SilentlyContinue) { return 'php' }
    return $null
}

function Test-PortAvailable([int] $Port) {
    # Do not require BOTH IPv4 and IPv6 loopback — if IPv6 is disabled, the v6 probe always fails
    # and this script would think every port is busy, so nothing ever listens ("site can't be reached").
    foreach ($addr in @(
            [System.Net.IPAddress]::Loopback,
            [System.Net.IPAddress]::IPv6Loopback
        )) {
        try {
            $l = [System.Net.Sockets.TcpListener]::new($addr, $Port)
            $l.Start()
            $l.Stop()
            return $true
        } catch {
            continue
        }
    }
    try {
        $l = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Any, $Port)
        $l.Start()
        $l.Stop()
        return $true
    } catch {
        return $false
    }
}

function Resolve-BindSpec([int] $Port) {
    if ($env:PHP_SERVER_BIND -and $env:PHP_SERVER_BIND.Trim() -ne '') {
        return @{ Host = $env:PHP_SERVER_BIND.Trim(); Label = $env:PHP_SERVER_BIND.Trim() }
    }
    # Prefer 0.0.0.0 first: listens on all IPv4 interfaces so http://127.0.0.1 and http://localhost (v4) work.
    foreach ($pair in @(
            @{ Host = '0.0.0.0'; Label = '0.0.0.0 (IPv4 all)' },
            @{ Host = '[::]'; Label = '[::] (IPv6)' },
            @{ Host = 'localhost'; Label = 'localhost' },
            @{ Host = '127.0.0.1'; Label = '127.0.0.1' }
        )) {
        $h = $pair.Host
        try {
            $ip = switch ($h) {
                '[::]' { [System.Net.IPAddress]::IPv6Any }
                '0.0.0.0' { [System.Net.IPAddress]::Any }
                'localhost' { $null }
                default { [System.Net.IPAddress]::Parse($h) }
            }
            if ($null -eq $ip) {
                $addrs = [System.Net.Dns]::GetHostAddresses('localhost')
                $ok = $true
                foreach ($a in $addrs) {
                    try {
                        $l = [System.Net.Sockets.TcpListener]::new($a, $Port)
                        $l.Start()
                        $l.Stop()
                    } catch {
                        $ok = $false
                        break
                    }
                }
                if (-not $ok) { continue }
            } else {
                $l = [System.Net.Sockets.TcpListener]::new($ip, $Port)
                $l.Start()
                $l.Stop()
            }
            return @{ Host = $h; Label = $pair.Label }
        } catch {
            continue
        }
    }
    return @{ Host = '127.0.0.1'; Label = '127.0.0.1' }
}

$phpExe = Get-PhpExecutable
if (-not $phpExe) {
    Write-Error 'PHP not found. Install XAMPP, Laragon, WAMP, Scoop php, Herd, or add PHP to PATH.'
    exit 1
}

$indexPhp = Join-Path $PSScriptRoot 'public\index.php'
if (-not (Test-Path $indexPhp)) {
    Write-Error "Missing public\index.php — run this script from the project root (folder that contains public\)."
    exit 1
}

$phpProbe = Start-Process -FilePath $phpExe -ArgumentList '-r', 'exit(0);' -Wait -PassThru -NoNewWindow
if ([int]$phpProbe.ExitCode -ne 0) {
    Write-Error "PHP did not run correctly (exit $($phpProbe.ExitCode)). Path: $phpExe"
    exit 1
}

$port = $null
$portList = @(8000, 8001, 8002, 8003, 8004, 8005, 8080, 8888, 9000)
$usedExplicitPort = $false
if ($env:PHP_SERVER_PORT -match '^\d+$') {
    $tryP = [int]$env:PHP_SERVER_PORT
    if ($tryP -ge 1 -and $tryP -le 65535) {
        $portList = @($tryP)
        $usedExplicitPort = $true
    }
}
foreach ($p in $portList) {
    if (Test-PortAvailable $p) {
        $port = $p
        break
    }
}
if ($null -eq $port) {
    Write-Error "No free port found among: $($portList -join ', '). Close other apps or set env PHP_SERVER_PORT to a free port (e.g. 8010)."
    exit 1
}
if ($usedExplicitPort) {
    Write-Host "Port: $port (from PHP_SERVER_PORT)" -ForegroundColor DarkGray
}

$bind = Resolve-BindSpec $port
$bindHost = $bind.Host

Write-Host "PHP: $phpExe" -ForegroundColor DarkGray
Write-Host "Listen: ${bindHost}:$port  ($($bind.Label))" -ForegroundColor DarkGray
$u1 = "http://127.0.0.1:$port/"
$u2 = "http://localhost:$port/"
Write-Host ""
Write-Host "Use HTTP (not HTTPS). Copy one of these into the address bar:" -ForegroundColor Cyan
Write-Host "  $u1" -ForegroundColor Green
Write-Host "  $u2" -ForegroundColor Green
Write-Host ""
Write-Host 'Keep this window open while you use the app. Press Ctrl+C to stop.' -ForegroundColor Yellow
Write-Host 'Built-in server: base URL follows your browser (see config/app.php).' -ForegroundColor DarkGray

if ($env:OPEN_DEV_BROWSER -eq '1') {
    Start-Process $u1
}

# Do not set $env:APP_URL here — let config infer from HTTP_HOST on cli-server.
& $phpExe -S "${bindHost}:$port" -t public public/index.php
