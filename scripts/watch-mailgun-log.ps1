param(
    [string]$Path = ""
)

if ([string]::IsNullOrWhiteSpace($Path)) {
    $projectRoot = Split-Path -Parent $PSScriptRoot
    $Path = Join-Path $projectRoot "storage\logs\mailgun.log"
}

$fullPath = Resolve-Path -LiteralPath $Path -ErrorAction SilentlyContinue
if (-not $fullPath) {
    $dir = Split-Path -Parent $Path
    if ($dir -and -not (Test-Path -LiteralPath $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }
    if (-not (Test-Path -LiteralPath $Path)) {
        New-Item -ItemType File -Path $Path -Force | Out-Null
    }
    $fullPath = Resolve-Path -LiteralPath $Path
}

Write-Host "Watching Mailgun log: $($fullPath.Path)"
Get-Content -LiteralPath $fullPath.Path -Wait
