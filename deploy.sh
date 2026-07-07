#!/usr/bin/env bash
#
# deploy.sh — production deploy for the Smart Prosthetics ERP
# Target: Ubuntu 24.04 · nginx + php-fpm · MySQL · git-based deploy
#
# Usage (run from the app directory on the VPS, as root or a sudo user):
#   bash deploy.sh
#
# Optional environment overrides:
#   DEPLOY_BRANCH=master   git branch to deploy (default: master)
#   SKIP_DB_BACKUP=1       skip the mysqldump before migrating
#   SKIP_MAINTENANCE=1     do not toggle php artisan down/up
#
set -euo pipefail

# ── Resolve paths ───────────────────────────────────────────────────────────
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$APP_DIR"

BRANCH="${DEPLOY_BRANCH:-master}"
WEB_USER="www-data"
export COMPOSER_ALLOW_SUPERUSER=1

log()  { printf '\n\033[1;36m▶ %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m⚠ %s\033[0m\n' "$*"; }
die()  { printf '\033[1;31m✖ %s\033[0m\n' "$*" >&2; exit 1; }

[ -f artisan ]  || die "artisan not found — run this from the Laravel app root."
[ -f .env ]     || die ".env not found — configure the environment first."
command -v php   >/dev/null || die "php is not installed / not in PATH."
command -v git   >/dev/null || die "git is not installed / not in PATH."

# ── 1) Maintenance mode ─────────────────────────────────────────────────────
if [ "${SKIP_MAINTENANCE:-0}" != "1" ]; then
    log "Enabling maintenance mode"
    php artisan down --render="errors::503" || php artisan down || true
fi

restore_up() { [ "${SKIP_MAINTENANCE:-0}" != "1" ] && php artisan up || true; }
trap restore_up EXIT

# ── 2) Pull latest code ─────────────────────────────────────────────────────
log "Fetching origin/$BRANCH"
git fetch origin "$BRANCH"
git reset --hard "origin/$BRANCH"
git --no-pager log -1 --oneline

# ── 3) Composer (production) ────────────────────────────────────────────────
log "Installing production dependencies"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# ── 4) Ensure the zip extension (Excel export/import) ───────────────────────
if ! php -m | grep -qi '^zip$'; then
    warn "php 'zip' extension missing — attempting to install php8.3-zip"
    if command -v apt-get >/dev/null; then
        apt-get update -y && apt-get install -y php8.3-zip || warn "Could not auto-install php8.3-zip; install it manually."
    else
        warn "Install the php zip extension manually (Excel export/import needs it)."
    fi
fi

# ── 5) DB backup before migrating ───────────────────────────────────────────
if [ "${SKIP_DB_BACKUP:-0}" != "1" ]; then
    DB_CONN=$(sed -n 's/^DB_CONNECTION=//p' .env | tr -d '"' | tr -d "'" | head -n1)
    if [ "${DB_CONN:-mysql}" = "mysql" ] && command -v mysqldump >/dev/null; then
        DB_NAME=$(sed -n 's/^DB_DATABASE=//p' .env | tr -d '"' | tr -d "'" | head -n1)
        DB_USER=$(sed -n 's/^DB_USERNAME=//p' .env | tr -d '"' | tr -d "'" | head -n1)
        DB_PASS=$(sed -n 's/^DB_PASSWORD=//p' .env | tr -d '"' | tr -d "'" | head -n1)
        mkdir -p storage/app/backups
        BACKUP="storage/app/backups/db-$(date +%F-%H%M%S).sql"
        log "Backing up MySQL database '$DB_NAME' → $BACKUP"
        MYSQL_PWD="$DB_PASS" mysqldump -u "$DB_USER" "$DB_NAME" > "$BACKUP" \
            && printf '  backup OK (%s)\n' "$(du -h "$BACKUP" | cut -f1)" \
            || warn "mysqldump failed — review credentials in .env. Continuing (a snapshot is recommended)."
    else
        warn "Skipping DB backup (non-MySQL or mysqldump unavailable). Take a VPS snapshot first."
    fi
fi

# ── 6) Migrate ──────────────────────────────────────────────────────────────
log "Running migrations"
php artisan migrate --force

# ── 7) Rebuild caches ───────────────────────────────────────────────────────
log "Rebuilding caches"
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
# public disk symlink for legacy assets (safe if it already exists)
php artisan storage:link 2>/dev/null || true

# ── 8) Permissions ──────────────────────────────────────────────────────────
if id "$WEB_USER" >/dev/null 2>&1; then
    log "Fixing storage / cache permissions ($WEB_USER)"
    chown -R "$WEB_USER":"$WEB_USER" storage bootstrap/cache || warn "chown skipped (need root?)."
    chmod -R 775 storage bootstrap/cache || true
fi

# ── 9) Reload PHP-FPM ───────────────────────────────────────────────────────
if command -v systemctl >/dev/null; then
    FPM_SVC=$(systemctl list-units --type=service --no-legend 2>/dev/null \
        | grep -oE 'php[0-9.]*-fpm\.service' | head -n1 | sed 's/\.service$//')
    FPM_SVC="${FPM_SVC:-php8.3-fpm}"
    log "Reloading $FPM_SVC"
    systemctl reload "$FPM_SVC" 2>/dev/null || systemctl restart "$FPM_SVC" 2>/dev/null \
        || warn "Could not reload $FPM_SVC — reload PHP-FPM manually."
fi

# ── 10) Back online ─────────────────────────────────────────────────────────
if [ "${SKIP_MAINTENANCE:-0}" != "1" ]; then
    log "Disabling maintenance mode"
    php artisan up
fi
trap - EXIT

log "Deploy complete — $(git --no-pager log -1 --oneline)"
