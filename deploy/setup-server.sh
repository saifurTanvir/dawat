#!/usr/bin/env bash
# One-time setup of a fresh Ubuntu 24.04 VPS for Dawat.
#
# Run as root (or with sudo) on the server:
#   curl -fsSL https://raw.githubusercontent.com/saifurTanvir/dawat/main/deploy/setup-server.sh -o setup-server.sh
#   sudo bash setup-server.sh dawat.example.com you@example.com
#
# Arguments:
#   1. domain name pointing at this server (A record)   e.g. dawat.com.bd
#   2. email for Let's Encrypt certificate notices
#
# It installs Nginx, PHP 8.3, MySQL 8, Redis, Supervisor, Certbot; creates the
# "deploy" user, the app folders, the database, the queue worker, the scheduler
# cron and an HTTPS Nginx site. It prints the generated database password once.
set -euo pipefail

DOMAIN="${1:?Usage: setup-server.sh <domain> <email>}"
EMAIL="${2:?Usage: setup-server.sh <domain> <email>}"
# Accept "https://dawat.cloud/" too; keep only the host name.
DOMAIN="${DOMAIN#http://}"; DOMAIN="${DOMAIN#https://}"; DOMAIN="${DOMAIN%%/*}"
DOMAIN="$(printf '%s' "$DOMAIN" | tr 'A-Z' 'a-z')"
if ! printf '%s' "$DOMAIN" | grep -Eq '^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$'; then
    echo "Not a domain name: '$DOMAIN' (example: sudo bash setup-server.sh dawat.cloud you@example.com)" >&2
    exit 1
fi
APP_DIR=/var/www/dawat
DEPLOY_USER=deploy
PHP=8.3
DB_NAME=dawat
DB_USER=dawat

log() { printf '\n\033[1;32m==> %s\033[0m\n' "$*"; }

[ "$(id -u)" -eq 0 ] || { echo "Run as root (sudo bash $0 ...)" >&2; exit 1; }
. /etc/os-release
[ "${VERSION_ID:-}" = "24.04" ] || echo "warning: tested on Ubuntu 24.04, this is ${PRETTY_NAME:-unknown}"

export DEBIAN_FRONTEND=noninteractive

log "Installing packages"
apt-get update -q
apt-get install -y -q \
    nginx mysql-server redis-server supervisor certbot python3-certbot-nginx \
    unzip git curl ufw acl \
    php$PHP-fpm php$PHP-cli php$PHP-mysql php$PHP-mbstring php$PHP-xml php$PHP-curl \
    php$PHP-zip php$PHP-gd php$PHP-intl php$PHP-bcmath php$PHP-redis php$PHP-opcache

log "Share cards (F19): Google Chrome, Node.js 22 and Puppeteer"
if ! command -v google-chrome >/dev/null 2>&1; then
    curl -fsSL https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb -o /tmp/chrome.deb
    apt-get install -y -q /tmp/chrome.deb
    rm -f /tmp/chrome.deb
fi
if ! node --version 2>/dev/null | grep -qE '^v(2[2-9]|[3-9][0-9])'; then
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
    apt-get install -y -q nodejs
fi

log "Firewall: allow SSH, HTTP, HTTPS"
ufw allow OpenSSH >/dev/null
ufw allow 'Nginx Full' >/dev/null
ufw --force enable >/dev/null

log "Creating the $DEPLOY_USER user"
if ! id "$DEPLOY_USER" >/dev/null 2>&1; then
    adduser --disabled-password --gecos "" "$DEPLOY_USER"
fi
usermod -aG www-data "$DEPLOY_USER"
install -d -m 700 -o "$DEPLOY_USER" -g "$DEPLOY_USER" "/home/$DEPLOY_USER/.ssh"
touch "/home/$DEPLOY_USER/.ssh/authorized_keys"
chown "$DEPLOY_USER:$DEPLOY_USER" "/home/$DEPLOY_USER/.ssh/authorized_keys"
chmod 600 "/home/$DEPLOY_USER/.ssh/authorized_keys"

# The deploy user may reload PHP-FPM and restart the queue worker, nothing else as root.
cat > /etc/sudoers.d/dawat-deploy <<EOF
$DEPLOY_USER ALL=(root) NOPASSWD: /usr/bin/systemctl reload php$PHP-fpm, /usr/bin/supervisorctl restart dawat-worker
EOF
chmod 440 /etc/sudoers.d/dawat-deploy
visudo -cf /etc/sudoers.d/dawat-deploy >/dev/null

log "PHP-FPM runs as $DEPLOY_USER so it can read releases and write storage"
sed -i "s/^user = .*/user = $DEPLOY_USER/; s/^group = .*/group = www-data/" /etc/php/$PHP/fpm/pool.d/www.conf
sed -i "s/^listen.owner = .*/listen.owner = www-data/; s/^listen.group = .*/listen.group = www-data/" /etc/php/$PHP/fpm/pool.d/www.conf
cat > /etc/php/$PHP/fpm/conf.d/99-dawat.ini <<'EOF'
upload_max_filesize = 12M
post_max_size = 50M
memory_limit = 256M
expose_php = Off
opcache.enable = 1
opcache.validate_timestamps = 1
opcache.revalidate_freq = 2
EOF
systemctl restart php$PHP-fpm

log "App folders in $APP_DIR"
install -d -o "$DEPLOY_USER" -g www-data -m 775 "$APP_DIR" "$APP_DIR/releases" "$APP_DIR/shared" \
    "$APP_DIR/shared/storage"
for d in app/public app/private framework/cache/data framework/sessions framework/views logs; do
    install -d -o "$DEPLOY_USER" -g www-data -m 775 "$APP_DIR/shared/storage/$d"
done

log "Puppeteer for share cards (uses the system Chrome, outside the app releases)"
install -d -o "$DEPLOY_USER" -g www-data -m 775 "$APP_DIR/shared/browsershot"
sudo -u "$DEPLOY_USER" -H bash -c "cd '$APP_DIR/shared/browsershot' && { [ -f package.json ] || npm init -y >/dev/null; } && PUPPETEER_SKIP_DOWNLOAD=1 npm install --omit=dev puppeteer@25 >/dev/null"

log "MySQL database and user"
DB_PASS=""
if [ "$(mysql -N -e "SELECT COUNT(*) FROM mysql.user WHERE user='$DB_USER'")" = "0" ]; then
    DB_PASS="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-28)"
    mysql <<EOF
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF
else
    echo "MySQL user $DB_USER already exists; leaving its password unchanged."
    [ -f "$APP_DIR/shared/.env" ] || echo "warning: put that user's password in DB_PASSWORD in $APP_DIR/shared/.env"
fi

log "Production .env (only if it does not exist yet)"
if [ ! -f "$APP_DIR/shared/.env" ]; then
    APP_KEY="base64:$(openssl rand -base64 32)"
    cat > "$APP_DIR/shared/.env" <<EOF
APP_NAME=Dawat
APP_ENV=production
APP_KEY=$APP_KEY
APP_DEBUG=false
APP_URL=https://$DOMAIN
APP_LOCALE=bn
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=$DB_NAME
DB_USERNAME=$DB_USER
DB_PASSWORD=$DB_PASS

SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

FILESYSTEM_DISK=local
MEDIA_DISK=public

# SMS: put the key and sender id from the BulkSMSBD panel here.
OTP_DRIVER=bulksmsbd
BULKSMSBD_API_KEY=
BULKSMSBD_SENDER_ID=
OTP_TTL_MINUTES=5
OTP_LENGTH=6
OTP_PER_IP_PER_DAY=30
OTP_GLOBAL_PER_HOUR=300
SANCTUM_TOKEN_EXPIRATION_DAYS=90

# Set when the site goes behind Cloudflare or a load balancer
TRUSTED_PROXIES=

# F19 share cards (headless Chrome)
SHARE_CARDS_ENABLED=true
SHARE_CARD_CHROME_PATH=/usr/bin/google-chrome
SHARE_CARD_NODE_BINARY=/usr/bin/node
SHARE_CARD_NODE_MODULES=$APP_DIR/shared/browsershot/node_modules

# F20 AI suggestions. To turn on: AI_DRIVER=anthropic and put the key here.
AI_DRIVER=none
ANTHROPIC_API_KEY=
AI_MODEL=claude-haiku-5-5
EOF
    chown "$DEPLOY_USER:www-data" "$APP_DIR/shared/.env"
    chmod 640 "$APP_DIR/shared/.env"
else
    echo "$APP_DIR/shared/.env already exists; not touching it."
fi

log "Queue worker (Supervisor)"
cat > /etc/supervisor/conf.d/dawat-worker.conf <<EOF
[program:dawat-worker]
command=/usr/bin/php $APP_DIR/current/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
directory=$APP_DIR/current
user=$DEPLOY_USER
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=$APP_DIR/shared/storage/logs/worker.log
EOF
supervisorctl reread >/dev/null && supervisorctl update >/dev/null || true

log "Scheduler (cron, every minute)"
cat > /etc/cron.d/dawat-scheduler <<EOF
* * * * * $DEPLOY_USER cd $APP_DIR/current && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
EOF
chmod 644 /etc/cron.d/dawat-scheduler

log "Nginx site for $DOMAIN"
cat > /etc/nginx/sites-available/dawat <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;
    root $APP_DIR/current/public;
    index index.php;
    charset utf-8;
    client_max_body_size 50M;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # The service worker must never be cached, or app updates get stuck.
    location = /sw.js {
        add_header Cache-Control "no-cache, no-store, must-revalidate";
        try_files \$uri =404;
    }

    # Vite assets have content hashes in their names: cache for a year.
    location /build/assets/ {
        add_header Cache-Control "public, max-age=31536000, immutable";
        try_files \$uri =404;
    }

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ ^/index\.php(/|$) {
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
EOF
ln -sfn /etc/nginx/sites-available/dawat /etc/nginx/sites-enabled/dawat
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

log "HTTPS certificate from Let's Encrypt"
if certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$EMAIL" --redirect; then
    echo "HTTPS is on."
else
    echo "warning: certbot failed. Check that $DOMAIN points at this server, then run:"
    echo "  sudo certbot --nginx -d $DOMAIN -m $EMAIL --agree-tos --redirect"
fi

log "Done"
cat <<EOF

Next steps (also in README → Deployment):

  1. Add the GitHub Actions public key to /home/$DEPLOY_USER/.ssh/authorized_keys
  2. Add the repository secrets in GitHub (DEPLOY_HOST, DEPLOY_USER=$DEPLOY_USER,
     DEPLOY_SSH_KEY, DEPLOY_PATH=$APP_DIR, DEPLOY_KNOWN_HOSTS)
  3. Merge to main (or run the Deploy workflow by hand)
  4. Put BULKSMSBD_API_KEY and BULKSMSBD_SENDER_ID in $APP_DIR/shared/.env
  5. After the first deploy, create your admin and test SMS:
       sudo -u $DEPLOY_USER php $APP_DIR/current/artisan dawat:make-admin 017XXXXXXXX you@example.com
       sudo -u $DEPLOY_USER php $APP_DIR/current/artisan dawat:sms-test 017XXXXXXXX

EOF
if [ -n "$DB_PASS" ]; then
    echo "MySQL password for '$DB_USER' (already written to $APP_DIR/shared/.env):"
    echo "  $DB_PASS"
    echo "Keep it somewhere safe; it is not shown again."
fi
