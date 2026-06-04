#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════════
#   One-command production deployment for the Email Automation cron.
#
#   Pulls the latest code, ensures CRON_SECRET_KEY is set in .env,
#   verifies the /cron/run-automations endpoint, and prints the
#   exact URL to paste into cron-job.org (or n8n).
#
#   USAGE on production server (one-time):
#     cd /home/ubuntu/compliance-easy
#     bash scripts/deploy-cron.sh
# ════════════════════════════════════════════════════════════════════════

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="$ROOT/.env"

echo ""
echo "═══════════════════════════════════════════════════════════════════"
echo "  Easy Home Finance — Cron Deployment Script"
echo "═══════════════════════════════════════════════════════════════════"
echo ""

# ─── 1. Pull latest code ───────────────────────────────────────────────
echo "▶ Step 1/5: Pulling latest code from GitHub ..."
cd "$ROOT"
git pull origin main
echo "  ✓ Code updated."
echo ""

# ─── 2. Ensure CRON_SECRET_KEY exists in .env ──────────────────────────
echo "▶ Step 2/5: Checking CRON_SECRET_KEY in .env ..."
if [ ! -f "$ENV_FILE" ]; then
    echo "  ✗ .env file not found at $ENV_FILE"
    exit 1
fi

if grep -q "^CRON_SECRET_KEY=" "$ENV_FILE"; then
    echo "  ✓ CRON_SECRET_KEY already set."
else
    NEW_KEY=$(openssl rand -hex 32)
    {
        echo ""
        echo "# ─── Cron endpoint secret (auto-generated $(date +'%Y-%m-%d')) ───"
        echo "CRON_SECRET_KEY=${NEW_KEY}"
    } >> "$ENV_FILE"
    echo "  ✓ Generated and added new CRON_SECRET_KEY."
fi

CRON_KEY=$(grep "^CRON_SECRET_KEY=" "$ENV_FILE" | head -1 | cut -d'=' -f2-)
echo ""

# ─── 3. Detect base URL ────────────────────────────────────────────────
echo "▶ Step 3/5: Detecting your app's URL from .env ..."
BASE_URL=$(grep "^APP_URL=" "$ENV_FILE" | head -1 | cut -d'=' -f2- || echo "")
if [ -z "$BASE_URL" ]; then
    echo "  ⚠ APP_URL not set in .env — using https://YOUR-DOMAIN as placeholder."
    BASE_URL="https://YOUR-DOMAIN"
else
    echo "  ✓ Found: $BASE_URL"
fi
echo ""

CRON_URL="${BASE_URL}/cron/run-automations?key=${CRON_KEY}"

# ─── 4. Test the endpoint locally via PHP CLI ──────────────────────────
echo "▶ Step 4/5: Smoke-testing the engine ..."
if [ -f "$ROOT/scripts/run-pre-due-automation.php" ]; then
    php "$ROOT/scripts/run-pre-due-automation.php" > /tmp/_predue.log 2>&1 && \
        echo "  ✓ Pre-Due engine runs OK." || echo "  ⚠ Pre-Due script returned non-zero. Check /tmp/_predue.log"
fi
if [ -f "$ROOT/scripts/run-escalation-automation.php" ]; then
    php "$ROOT/scripts/run-escalation-automation.php" > /tmp/_esc.log 2>&1 && \
        echo "  ✓ Escalation engine runs OK." || echo "  ⚠ Escalation script returned non-zero. Check /tmp/_esc.log"
fi
echo ""

# ─── 5. Optionally install a Linux system cron ─────────────────────────
echo "▶ Step 5/5: Want to install a Linux cron job too? (recommended)"
echo "   This will add a daily 11:00 AM IST job that fires the engines"
echo "   directly via PHP CLI — no external service needed."
echo ""
read -p "   Install Linux cron? [y/N] " yn
if [[ "$yn" =~ ^[Yy]$ ]]; then
    CRON_LINE="0 11 * * * /usr/bin/php $ROOT/scripts/run-pre-due-automation.php >> /var/log/compliance-mail.log 2>&1 && /usr/bin/php $ROOT/scripts/run-escalation-automation.php >> /var/log/compliance-mail.log 2>&1"
    ( crontab -l 2>/dev/null | grep -v "scripts/run-pre-due-automation.php" ; echo "$CRON_LINE" ) | crontab -
    echo "  ✓ Linux cron installed. View with: crontab -l"
else
    echo "  ⏭ Skipped Linux cron (you can install later)."
fi
echo ""

# ─── Done ──────────────────────────────────────────────────────────────
echo "═══════════════════════════════════════════════════════════════════"
echo "  ✅ DEPLOYMENT COMPLETE"
echo "═══════════════════════════════════════════════════════════════════"
echo ""
echo "  Your cron URL (paste this into cron-job.org or n8n):"
echo ""
echo "  $CRON_URL"
echo ""
echo "  To test it now from this server:"
echo ""
echo "    curl '$CRON_URL'"
echo ""
echo "═══════════════════════════════════════════════════════════════════"
