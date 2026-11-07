#!/usr/bin/env bash
# Runs ON THE SERVER for every deploy (called by .github/workflows/deploy.yml).
#
#   release.sh <app_dir> <release_name>
#
# Expects <app_dir>/releases/<release_name>.tar.gz to be uploaded already.
# Layout:
#   <app_dir>/releases/<name>/   one folder per deploy (last 5 kept)
#   <app_dir>/shared/.env        production settings, created once by you
#   <app_dir>/shared/storage/    uploads, logs, cache — survives deploys
#   <app_dir>/current            symlink to the live release
set -euo pipefail

APP_DIR="${1:?app dir required}"
RELEASE="${2:?release name required}"
KEEP=5
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.3-fpm}"

RELEASES="$APP_DIR/releases"
SHARED="$APP_DIR/shared"
NEW="$RELEASES/$RELEASE"

log() { printf '\n==> %s\n' "$*"; }

[ -f "$SHARED/.env" ] || { echo "Missing $SHARED/.env — create it first (see README)." >&2; exit 1; }

log "Unpacking $RELEASE"
mkdir -p "$NEW"
tar -xzf "$RELEASES/$RELEASE.tar.gz" -C "$NEW"
rm -f "$RELEASES/$RELEASE.tar.gz"

log "Linking shared .env and storage"
mkdir -p "$SHARED/storage"/{app/public,app/private,framework/cache/data,framework/sessions,framework/views,logs}
rm -rf "$NEW/storage"
ln -sfn "$SHARED/storage" "$NEW/storage"
ln -sfn "$SHARED/.env" "$NEW/.env"
mkdir -p "$NEW/bootstrap/cache"

cd "$NEW"

log "Publishing Filament assets"
php artisan filament:assets --no-interaction

log "Running database migrations"
php artisan migrate --force --no-interaction

log "Seeding reference data (roles, tags, areas; safe to repeat)"
php artisan db:seed --force --no-interaction

log "Caching config, routes, views and events"
php artisan optimize --no-interaction
php artisan storage:link --force --no-interaction >/dev/null

log "Switching live traffic to $RELEASE"
ln -sfn "$NEW" "$APP_DIR/current.tmp"
mv -Tf "$APP_DIR/current.tmp" "$APP_DIR/current"

log "Reloading PHP and restarting queue workers"
sudo -n /usr/bin/systemctl reload "$PHP_FPM_SERVICE" || echo "warning: could not reload $PHP_FPM_SERVICE (check sudoers)"
# Restart (or start, on the very first deploy) the Supervisor worker; fall back to a graceful signal.
sudo -n /usr/bin/supervisorctl restart dawat-worker 2>/dev/null || php artisan queue:restart --no-interaction

log "Removing old releases (keeping $KEEP)"
cd "$RELEASES"
ls -1dt -- */ 2>/dev/null | tail -n +$((KEEP + 1)) | xargs -r rm -rf --

log "Deployed $RELEASE"
