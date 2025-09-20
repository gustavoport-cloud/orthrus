#!/usr/bin/env bash
set -euo pipefail

# Laravel Forge deploy script for Symfony

echo "[deploy] Installing dependencies"
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader

echo "[deploy] Preparing shared keys directory"
SHARED_DIR="$HOME/shared"
KEYS_DIR="$SHARED_DIR/keys"
mkdir -p "$KEYS_DIR" "var" "var/cache" "var/log"
if [ ! -f "$KEYS_DIR/private.pem" ]; then
  openssl genrsa -out "$KEYS_DIR/private.pem" 4096
  chmod 600 "$KEYS_DIR/private.pem"
  openssl rsa -in "$KEYS_DIR/private.pem" -pubout -out "$KEYS_DIR/public.pem"
fi
if [ ! -f "$KEYS_DIR/kid" ]; then
  if command -v uuidgen >/dev/null 2>&1; then
    uuidgen > "$KEYS_DIR/kid"
  else
    php -r 'echo strtoupper(implode("-", str_split(bin2hex(random_bytes(16)), 4)));' > "$KEYS_DIR/kid"
  fi
fi
ln -snf "$KEYS_DIR" var/keys

echo "[deploy] Writing .env.local values"
touch .env.local
grep -q '^APP_ENV=prod' .env.local || echo 'APP_ENV=prod' >> .env.local
grep -q '^APP_DEBUG=0' .env.local || echo 'APP_DEBUG=0' >> .env.local
KID=$(cat "$KEYS_DIR/kid")
grep -q '^JWT_KID=' .env.local || echo "JWT_KID=$KID" >> .env.local

echo "[deploy] Running migrations and cache warmup"
export APP_ENV=prod APP_DEBUG=0 JWT_KID="$KID"
php bin/console doctrine:migrations:migrate -n --env=prod
php bin/console cache:clear --env=prod --no-debug

echo "[deploy] OPCache reset (if enabled)"
php -r 'if (function_exists("opcache_reset")) { opcache_reset(); echo "OPCache reset\n"; }'

echo "[deploy] Done"

