#!/usr/bin/env bash
#
# deploy.sh — production deploy for the Smart Prosthetics ERP
# Target: Ubuntu 24.04 · nginx + php-fpm · PostgreSQL or MySQL · git-based deploy
# DB backup is engine-aware (pg_dump / mysqldump) via `php artisan prosthetics:backup`.
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

# Laravel file cache (rate limiter, sessions) needs writable subdirs under storage/.
ensure_storage_dirs() {
    mkdir -p storage/framework/cache/data
    mkdir -p storage/framework/sessions
    mkdir -p storage/framework/views
    mkdir -p storage/logs
    mkdir -p storage/app/public
    mkdir -p storage/backups
    mkdir -p bootstrap/cache
}

fix_storage_permissions() {
    if ! id "$WEB_USER" >/dev/null 2>&1; then
        warn "User $WEB_USER not found — skipping storage permission fix."
        return
    fi
    log "Fixing storage / cache permissions ($WEB_USER)"
    chown -R "$WEB_USER":"$WEB_USER" storage bootstrap/cache || warn "chown skipped (need root?)."
    chmod -R 775 storage bootstrap/cache || true
}

run_artisan() {
    if id "$WEB_USER" >/dev/null 2>&1 && [ "$(id -u)" -eq 0 ]; then
        sudo -u "$WEB_USER" php artisan "$@"
    else
        php artisan "$@"
    fi
}

[ -f artisan ]  || die "artisan not found — run this from the Laravel app root."
[ -f .env ]     || die ".env not found — configure the environment first."
command -v php   >/dev/null || die "php is not installed / not in PATH."
command -v git   >/dev/null || die "git is not installed / not in PATH."

# ── 1) Maintenance mode ─────────────────────────────────────────────────────
if [ "${SKIP_MAINTENANCE:-0}" != "1" ]; then
    log "Enabling maintenance mode"
    php artisan down --render="errors::503" || php artisan down || true
fi

# لا نُعيد التطبيق للعمل تلقائياً عند الفشل — يبقى في وضع الصيانة حتى ينجح النشر كاملاً.
# يُبقى التطبيق «حياً» فقط بعد نجاح كل الخطوات (DEPLOY_OK=1) في الخطوة 10.
DEPLOY_OK=0
cleanup() {
    if [ "$DEPLOY_OK" = "1" ]; then
        return
    fi
    warn "فشل النشر — التطبيق مُبقىً في وضع الصيانة عمداً. أصلِح المشكلة ثم أعد تشغيل deploy.sh."
    warn "لإعادة التطبيق يدوياً بعد الإصلاح: php artisan up"
}
trap cleanup EXIT

# ── 2) Pull latest code ─────────────────────────────────────────────────────
log "Fetching origin/$BRANCH"
git fetch origin "$BRANCH"
git reset --hard "origin/$BRANCH"
git --no-pager log -1 --oneline

ensure_storage_dirs
fix_storage_permissions

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
# H-9: نسخة احتياطية مستقلة عن المحرك — تعتمد على أمر التطبيق الذي يكتشف السائق
#      تلقائياً (pg_dump لـ PostgreSQL / mysqldump لـ MySQL). يعمل على VPS و LAN.
if [ "${SKIP_DB_BACKUP:-0}" != "1" ]; then
    DB_CONN=$(sed -n 's/^DB_CONNECTION=//p' .env | tr -d '"' | tr -d "'" | head -n1)
    log "Backing up database (engine: ${DB_CONN:-unknown}) via artisan prosthetics:backup"
    if php artisan prosthetics:backup; then
        printf '  backup OK → storage/backups\n'
    else
        # Fallback: direct dump per engine if the artisan command is unavailable.
        DB_NAME=$(sed -n 's/^DB_DATABASE=//p' .env | tr -d '"' | tr -d "'" | head -n1)
        DB_USER=$(sed -n 's/^DB_USERNAME=//p' .env | tr -d '"' | tr -d "'" | head -n1)
        DB_PASS=$(sed -n 's/^DB_PASSWORD=//p' .env | tr -d '"' | tr -d "'" | head -n1)
        DB_HOST=$(sed -n 's/^DB_HOST=//p' .env | tr -d '"' | tr -d "'" | head -n1)
        DB_PORT=$(sed -n 's/^DB_PORT=//p' .env | tr -d '"' | tr -d "'" | head -n1)
        mkdir -p storage/backups
        BACKUP="storage/backups/db-$(date +%F-%H%M%S).sql"
        if [ "$DB_CONN" = "pgsql" ] && command -v pg_dump >/dev/null; then
            log "Fallback pg_dump → $BACKUP"
            PGPASSWORD="$DB_PASS" pg_dump --host="${DB_HOST:-127.0.0.1}" --port="${DB_PORT:-5432}" \
                --username="$DB_USER" --no-owner --no-acl "$DB_NAME" > "$BACKUP" \
                && printf '  backup OK (%s)\n' "$(du -h "$BACKUP" | cut -f1)" \
                || die "pg_dump failed — take a manual backup/snapshot before deploying."
        elif [ "$DB_CONN" = "mysql" ] && command -v mysqldump >/dev/null; then
            log "Fallback mysqldump → $BACKUP"
            MYSQL_PWD="$DB_PASS" mysqldump --host="${DB_HOST:-127.0.0.1}" --port="${DB_PORT:-3306}" \
                -u "$DB_USER" --single-transaction --quick --lock-tables=false "$DB_NAME" > "$BACKUP" \
                && printf '  backup OK (%s)\n' "$(du -h "$BACKUP" | cut -f1)" \
                || die "mysqldump failed — take a manual backup/snapshot before deploying."
        else
            die "No backup mechanism for engine '${DB_CONN:-unknown}'. Take a manual backup/snapshot, or set SKIP_DB_BACKUP=1 to bypass intentionally."
        fi
    fi
fi

# ── 6) Migrate ──────────────────────────────────────────────────────────────
# أي فشل في الهجرات يُوقِف النشر فوراً ويُبقي التطبيق في وضع الصيانة (لا يعود «حياً»).
log "Running migrations"
if ! php artisan migrate --force; then
    die "فشلت الهجرات — التطبيق مُبقىً في وضع الصيانة. راجع الخطأ أعلاه، أصلِح قاعدة البيانات، ثم أعد تشغيل deploy.sh."
fi

# ── 7) Rebuild caches ───────────────────────────────────────────────────────
# Recreate dirs after migrate/backup; rebuild caches as www-data when running as root.
ensure_storage_dirs
fix_storage_permissions
log "Rebuilding caches"
run_artisan config:clear
run_artisan route:clear
run_artisan view:clear
run_artisan config:cache
run_artisan route:cache
run_artisan view:cache
# public disk symlink for legacy assets (safe if it already exists)
run_artisan storage:link 2>/dev/null || true
fix_storage_permissions

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
# نصل هنا فقط بعد نجاح كل الخطوات السابقة (بفضل set -e). عندها فقط نُعيد التطبيق للعمل.
DEPLOY_OK=1
if [ "${SKIP_MAINTENANCE:-0}" != "1" ]; then
    log "Disabling maintenance mode"
    php artisan up
fi
trap - EXIT

fix_storage_permissions

log "Deploy complete — $(git --no-pager log -1 --oneline)"
warn "مركز الوثائق: /admin/documents-hub — بعد النشر شغّل migrate إن لم يُشغَّل تلقائياً."
warn "تحقق من الواجهة: زر المعدلات «إرسال إلى الاعتماد» — رسالة الطبيب «تم التحويل … للتوصيف». حدّث المتصفح Ctrl+F5 بعد النشر."
warn "لا تشغّل php artisan view:cache أو config:cache كـ root يدوياً — يكسر صلاحيات www-data."
warn "بعد أي artisan يدوي كـ root: php artisan prosthetics:fix-storage-permissions"
