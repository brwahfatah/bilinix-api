#!/usr/bin/env bash
# =============================================================================
# Beeliin Hosting — Laravel API Deployment Script
# Server: Ubuntu 24.04 · PHP 8.3 · Redis queue
# Usage:  bash /var/www/beeliin-api/deploy.sh
# =============================================================================

set -euo pipefail   # exit on error, unset variable, or pipe failure

# ── Configuration ─────────────────────────────────────────────────────────────
APP_DIR="/var/www/beeliin-api"
PHP="/usr/bin/php8.3"
COMPOSER="/usr/local/bin/composer"
BRANCH="master"
SUPERVISOR_GROUP="beeliin-worker"

# ── Colour helpers ─────────────────────────────────────────────────────────────
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'   # no colour

step()  { echo -e "\n${GREEN}▶ $1${NC}"; }
warn()  { echo -e "${YELLOW}⚠  $1${NC}"; }
abort() { echo -e "${RED}✗  $1${NC}"; exit 1; }

# ── Pre-flight checks ──────────────────────────────────────────────────────────
step "Pre-flight checks"

[[ -d "$APP_DIR" ]]        || abort "App directory not found: $APP_DIR"
[[ -f "$APP_DIR/.env" ]]   || abort ".env file missing — copy .env.production.example to .env and fill it in"
[[ -f "$PHP" ]]            || abort "PHP binary not found: $PHP"
[[ -f "$COMPOSER" ]]       || abort "Composer not found: $COMPOSER"
command -v supervisorctl &>/dev/null || warn "supervisorctl not found — queue:restart step will be skipped"

cd "$APP_DIR"

# Confirm we are on the right branch
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
[[ "$CURRENT_BRANCH" == "$BRANCH" ]] || abort "Expected branch '$BRANCH', currently on '$CURRENT_BRANCH'"

echo "  Directory : $APP_DIR"
echo "  Branch    : $CURRENT_BRANCH"
echo "  PHP       : $($PHP -r 'echo PHP_VERSION;')"
echo "  Composer  : $($COMPOSER --version --no-ansi 2>&1 | head -1)"
echo "  Git remote: $(git remote get-url origin)"

# ── 1. Pull latest code ────────────────────────────────────────────────────────
step "1/7  git pull origin $BRANCH"

git pull origin "$BRANCH"

COMMIT=$(git log -1 --format="%h  %s  (%cr)")
echo "  Latest commit: $COMMIT"

# ── 2. Install dependencies ────────────────────────────────────────────────────
step "2/7  composer install --no-dev --optimize-autoloader"

$COMPOSER install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist \
    --ansi

# ── 3. Run database migrations ─────────────────────────────────────────────────
step "3/7  php artisan migrate --force"

$PHP artisan migrate --force

# ── 4. Cache configuration ─────────────────────────────────────────────────────
step "4/7  php artisan config:cache"

$PHP artisan config:cache

# ── 5. Cache routes ────────────────────────────────────────────────────────────
step "5/7  php artisan route:cache"

$PHP artisan route:cache

# ── 6. Restart queue workers ───────────────────────────────────────────────────
step "6/7  php artisan queue:restart"

# queue:restart sets a flag in the cache; Supervisor re-launches workers automatically.
$PHP artisan queue:restart

if command -v supervisorctl &>/dev/null; then
    supervisorctl restart "${SUPERVISOR_GROUP}:*"
    echo "  Supervisor workers restarted."
else
    warn "supervisorctl not available — workers will restart on their next cycle"
fi

# ── 7. Optimize ────────────────────────────────────────────────────────────────
step "7/7  php artisan optimize"

$PHP artisan optimize

# ── Reload PHP-FPM ─────────────────────────────────────────────────────────────
step "Reloading PHP-FPM (graceful)"

if systemctl is-active --quiet php8.3-fpm; then
    systemctl reload php8.3-fpm
    echo "  php8.3-fpm reloaded."
else
    warn "php8.3-fpm is not running — skipping reload"
fi

# ── Health check ───────────────────────────────────────────────────────────────
step "Health check"

APP_URL=$($PHP artisan tinker --execute="echo config('app.url');" 2>/dev/null | tail -1)

if [[ -n "$APP_URL" ]]; then
    HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$APP_URL/api/health" --max-time 5 || true)
    if [[ "$HTTP_STATUS" == "200" ]]; then
        echo "  GET /api/health → HTTP $HTTP_STATUS  ✓"
    else
        warn "GET /api/health returned HTTP $HTTP_STATUS — check storage/logs/laravel.log"
    fi
else
    warn "Could not determine APP_URL — skipping health check"
fi

# ── Summary ────────────────────────────────────────────────────────────────────
echo -e "\n${GREEN}============================================${NC}"
echo -e "${GREEN}  Deploy complete${NC}"
echo -e "${GREEN}============================================${NC}"
echo "  Commit  : $(git log -1 --format='%h')"
echo "  Branch  : $(git rev-parse --abbrev-ref HEAD)"
echo "  Time    : $(date '+%Y-%m-%d %H:%M:%S %Z')"
echo ""
