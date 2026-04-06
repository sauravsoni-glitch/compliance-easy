#!/usr/bin/env bash
# Initialize a fresh DB inside the Docker MariaDB service (schema + authority dedupe).
# Requires: docker compose up -d db
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
docker compose exec -T db mariadb -uroot -e \
  "DROP DATABASE IF EXISTS compliance_saas; CREATE DATABASE compliance_saas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
docker compose exec -T db mariadb -uroot compliance_saas < database/schema.sql
docker compose exec -T db mariadb -uroot compliance_saas < database/migrations/019_deduplicate_authorities.sql
echo "Database compliance_saas initialized (schema + 019)."
