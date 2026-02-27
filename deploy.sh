#!/bin/bash
set -e

# ── CONFIG ──────────────────────────────────────────────
REPO="https://github.com/chisomgbata/arya.git"
APP_DIR="$HOME/public_html"   # Hostinger public_html
# ────────────────────────────────────────────────────────

echo "==> Checking PHP & Composer"
php -v
composer --version

echo "==> Cloning / updating repo"
if [ -d "$APP_DIR/.git" ]; then
    cd "$APP_DIR"
    git pull origin main
else
    # Back up existing public_html if needed
    if [ -d "$APP_DIR" ] && [ "$(ls -A $APP_DIR)" ]; then
        mv "$APP_DIR" "${APP_DIR}_backup_$(date +%s)"
    fi
    git clone "$REPO" "$APP_DIR"
    cd "$APP_DIR"
fi

echo "==> Writing .env"
cat > .env << 'ENVEOF'
APP_NAME=Arya
APP_ENV=production
APP_KEY=base64:+4BZ+FliGG7+MzL7Ilz1DbhfAMhoR8TjpesAHCYzePI=
APP_DEBUG=false
APP_URL=https://scrapeguru.com

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u262763368_scrapeguru
DB_USERNAME=u262763368_scrapeguru
DB_PASSWORD=2O&dA:jVvga

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@scrapeguru.com"
MAIL_FROM_NAME="Arya"

VITE_APP_NAME="Arya"
ENVEOF

echo "==> Installing Composer dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Installing NPM dependencies & building assets"
if command -v npm &>/dev/null; then
    npm ci --prefer-offline 2>/dev/null || npm install
    npm run build
else
    echo "WARN: npm not found, skipping asset build (upload pre-built public/build if needed)"
fi

echo "==> Running migrations"
php artisan migrate --force --no-interaction

echo "==> Seeding (optional — comment out if not needed)"
# php artisan db:seed --force --no-interaction

echo "==> Caching config / routes / views"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> Storage link"
php artisan storage:link --force 2>/dev/null || true

echo "==> Fixing permissions"
chmod -R 755 storage bootstrap/cache
chown -R $(whoami):$(whoami) storage bootstrap/cache 2>/dev/null || true

echo ""
echo "✅  Deploy complete! Visit https://scrapeguru.com"
