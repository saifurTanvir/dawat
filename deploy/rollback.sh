#!/usr/bin/env bash
# Run ON THE SERVER to go back to the previous release:
#   bash /var/www/dawat/current/deploy/rollback.sh /var/www/dawat
# Database migrations are NOT rolled back automatically.
set -euo pipefail

APP_DIR="${1:-/var/www/dawat}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.3-fpm}"
CURRENT="$(readlink -f "$APP_DIR/current")"
PREVIOUS="$(ls -1dt "$APP_DIR"/releases/*/ | sed 's:/$::' | grep -vx "$CURRENT" | head -n 1 || true)"

[ -n "$PREVIOUS" ] || { echo "No previous release to roll back to." >&2; exit 1; }

echo "Rolling back: $(basename "$CURRENT") -> $(basename "$PREVIOUS")"
ln -sfn "$PREVIOUS" "$APP_DIR/current.tmp"
mv -Tf "$APP_DIR/current.tmp" "$APP_DIR/current"
sudo -n /usr/bin/systemctl reload "$PHP_FPM_SERVICE" || true
sudo -n /usr/bin/supervisorctl restart dawat-worker 2>/dev/null || (cd "$APP_DIR/current" && php artisan queue:restart --no-interaction)
# Remove the bad release so a second rollback goes further back instead of returning to it.
rm -rf -- "$CURRENT"
echo "Done. Now live: $(basename "$PREVIOUS")"
