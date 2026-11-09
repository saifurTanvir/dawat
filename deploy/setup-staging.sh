#!/usr/bin/env bash
# One-time setup of the STAGING copy of Dawat, on the same VPS as production.
# Run as root after setup-server.sh, once the subdomain points at this server:
#
#   curl -fsSL https://raw.githubusercontent.com/saifurTanvir/dawat/main/deploy/setup-staging.sh | sudo bash -s staging.dawat.cloud you@example.com
#
# Staging has its own folder, database, Redis databases, queue worker and
# scheduler; it never sends real SMS, accepts any login code, and sits behind
# a password so search engines and strangers stay out. Deploys come from the
# develop branch (.github/workflows/deploy.yml).
set -euo pipefail

DOMAIN="${1:?Usage: setup-staging.sh <domain> <email>}"
EMAIL="${2:?Usage: setup-staging.sh <domain> <email>}"
DOMAIN="${DOMAIN#http://}"; DOMAIN="${DOMAIN#https://}"; DOMAIN="${DOMAIN%%/*}"
APP_DIR=/var/www/dawat-staging
PROD_ENV=/var/www/dawat/shared/.env
DEPLOY_USER=deploy
PHP=8.3
DB_NAME=dawat_staging
DB_USER=dawat_staging

log() { printf '\n\033[1;32m==> %s\033[0m\n' "$*"; }
[ "$(id -u)" -eq 0 ] || { echo "Run as root (sudo bash ...)" >&2; exit 1; }
id "$DEPLOY_USER" >/dev/null 2>&1 || { echo "Run setup-server.sh first (no '$DEPLOY_USER' user)." >&2; exit 1; }
[ -f "$PROD_ENV" ] || { echo "Missing $PROD_ENV — production must be set up first." >&2; exit 1; }

log "Checking that $DOMAIN points at this server"
HERE="$(curl -fsS4 --max-time 10 https://api.ipify.org || true)"
THERE="$(getent ahostsv4 "$DOMAIN" | awk 'NR==1{print $1}')"
if [ -z "$THERE" ]; then
    echo "$DOMAIN does not resolve yet. Add an A record: staging -> ${HERE:-the server IP}, wait a few minutes, then run this again." >&2
    exit 1
fi
[ -z "$HERE" ] || [ "$HERE" = "$THERE" ] || echo "warning: $DOMAIN points at $THERE but this server is $HERE"

log "Folders"
install -d -o "$DEPLOY_USER" -g www-data -m 775 "$APP_DIR" "$APP_DIR/releases" "$APP_DIR/shared"
for d in app/public app/private framework/cache/data framework/sessions framework/views logs; do
    install -d -o "$DEPLOY_USER" -g www-data -m 775 "$APP_DIR/shared/storage/$d"
done

log "MySQL database and user"
if [ "$(mysql -N -e "SELECT COUNT(*) FROM mysql.user WHERE user='$DB_USER'")" = "0" ]; then
    DB_PASS="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-28)"
    mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
else
    DB_PASS=""
    echo "MySQL user $DB_USER exists; keeping the password in $APP_DIR/shared/.env."
fi

log "Staging .env (a copy of production with its own database, Redis and no real SMS)"
if [ ! -f "$APP_DIR/shared/.env" ]; then
    [ -n "$DB_PASS" ] || { echo "No password for $DB_USER: drop the MySQL user and run again." >&2; exit 1; }
    set_env() { # key value: replace or append
        if grep -q "^$1=" "$APP_DIR/shared/.env"; then sed -i "s|^$1=.*|$1=$2|" "$APP_DIR/shared/.env"; else echo "$1=$2" >> "$APP_DIR/shared/.env"; fi
    }
    cp "$PROD_ENV" "$APP_DIR/shared/.env"
    set_env APP_ENV staging
    set_env APP_KEY "base64:$(openssl rand -base64 32)"
    set_env APP_DEBUG false
    set_env APP_URL "https://$DOMAIN"
    set_env DB_DATABASE "$DB_NAME"
    set_env DB_USERNAME "$DB_USER"
    set_env DB_PASSWORD "$DB_PASS"
    # Production uses Redis databases 0 and 1.
    set_env REDIS_DB 2
    set_env REDIS_CACHE_DB 3
    set_env SESSION_COOKIE dawat_staging_session
    # No real SMS; any 6-digit code logs in (staging only, behind a password).
    set_env OTP_DRIVER log
    set_env OTP_ANY_CODE true
    set_env ORDER_SMS_ENABLED false
    set_env SHARE_CARD_NODE_MODULES "/var/www/dawat/shared/browsershot/node_modules"
    chown "$DEPLOY_USER:www-data" "$APP_DIR/shared/.env"
    chmod 640 "$APP_DIR/shared/.env"
else
    echo "$APP_DIR/shared/.env already exists; not touching it."
fi

log "Queue worker and scheduler"
cat > /etc/supervisor/conf.d/dawat-staging-worker.conf <<CONF
[program:dawat-staging-worker]
command=/usr/bin/php $APP_DIR/current/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
directory=$APP_DIR/current
user=$DEPLOY_USER
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=$APP_DIR/shared/storage/logs/worker.log
CONF
supervisorctl reread >/dev/null && supervisorctl update >/dev/null || true
cat > /etc/cron.d/dawat-staging-scheduler <<CRON
* * * * * $DEPLOY_USER [ -f $APP_DIR/current/artisan ] && cd $APP_DIR/current && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
CRON
chmod 644 /etc/cron.d/dawat-staging-scheduler

log "Let deploys restart the staging worker"
cat > /etc/sudoers.d/dawat-deploy-staging <<SUDO
$DEPLOY_USER ALL=(root) NOPASSWD: /usr/bin/supervisorctl restart dawat-staging-worker
SUDO
chmod 440 /etc/sudoers.d/dawat-deploy-staging
visudo -cf /etc/sudoers.d/dawat-deploy-staging >/dev/null

log "Password for the staging site"
apt-get install -y -qq apache2-utils >/dev/null
STAGE_PASS=""
if [ ! -f /etc/nginx/dawat-staging.htpasswd ]; then
    STAGE_PASS="$(openssl rand -base64 12 | tr -d '/+=' | cut -c1-12)"
    htpasswd -bc /etc/nginx/dawat-staging.htpasswd dawat "$STAGE_PASS" >/dev/null
    chown root:www-data /etc/nginx/dawat-staging.htpasswd
    chmod 640 /etc/nginx/dawat-staging.htpasswd
fi

log "Nginx site for $DOMAIN"
cat > /etc/nginx/sites-available/dawat-staging <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;
    root $APP_DIR/current/public;
    index index.php;
    charset utf-8;
    client_max_body_size 50M;

    auth_basic "Dawat staging";
    auth_basic_user_file /etc/nginx/dawat-staging.htpasswd;
    add_header X-Robots-Tag "noindex, nofollow" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

    location = /sw.js {
        add_header Cache-Control "no-cache, no-store, must-revalidate";
        add_header X-Robots-Tag "noindex, nofollow" always;
        try_files \$uri =404;
    }
    location /build/assets/ {
        add_header Cache-Control "public, max-age=31536000, immutable";
        try_files \$uri =404;
    }
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    location = /favicon.ico { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ ^/index\.php(/|\$) {
        fastcgi_pass unix:/run/php/php$PHP-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX
ln -sfn /etc/nginx/sites-available/dawat-staging /etc/nginx/sites-enabled/dawat-staging
nginx -t
systemctl reload nginx

log "HTTPS certificate"
certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$EMAIL" --redirect \
    || echo "warning: certbot failed; run: sudo certbot --nginx -d $DOMAIN -m $EMAIL --agree-tos --redirect"

log "Done. The next push to the develop branch deploys staging."
if [ -n "$STAGE_PASS" ]; then
    echo
    echo "Staging site password (browser asks once):"
    echo "  user:     dawat"
    echo "  password: $STAGE_PASS"
    echo "Keep it; it is not shown again. Change it with: sudo htpasswd /etc/nginx/dawat-staging.htpasswd dawat"
fi
