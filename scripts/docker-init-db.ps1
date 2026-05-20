# Initialize MariaDB in Docker (schema + migration 019). Run from repo root in PowerShell:
#   .\scripts\docker-init-db.ps1
# Requires: docker compose up -d db   (and db healthy)

$ErrorActionPreference = "Stop"
$Root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
Set-Location $Root

Write-Host "Initializing database compliance_saas..."
docker compose exec -T db mariadb -uroot -e @"
DROP DATABASE IF EXISTS compliance_saas;
CREATE DATABASE compliance_saas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
"@

Get-Content -Raw (Join-Path $Root "database\schema.sql") | docker compose exec -T db mariadb -uroot compliance_saas
Get-Content -Raw (Join-Path $Root "database\migrations\019_deduplicate_authorities.sql") | docker compose exec -T db mariadb -uroot compliance_saas

Write-Host "Done. Database compliance_saas initialized (schema + 019)."
