#!/usr/bin/env bash
# Deploy Dokan Ward to production without ever overwriting production env files.
# Usage (on the VPS):  /var/www/dokannward/scripts/deploy.sh
set -euo pipefail

APP_ROOT="${APP_ROOT:-/var/www/dokannward}"
PROD_HOST="${PROD_HOST:-dokannward.com}"
PROD_ORIGIN="https://${PROD_HOST}"
BRANCH="${DEPLOY_BRANCH:-main}"

cd "$APP_ROOT"

echo "==> Dokan Ward deploy @ $(date -u +%Y-%m-%dT%H:%M:%SZ)"

# ---------------------------------------------------------------------------
# Guard: production env files must exist and must NOT contain local URLs
# ---------------------------------------------------------------------------
assert_no_local_urls() {
  local file="$1"
  if [[ ! -f "$file" ]]; then
    echo "ERROR: missing $file — refusing to deploy without production env."
    exit 1
  fi
  # Only flag URL-ish settings — Redis/Memcached on 127.0.0.1 is fine on the VPS.
  if grep -Ei '^(APP_URL|FRONTEND_URL|STOREFRONT_URL|STOREFRONT_PUBLIC_URL|CORS_ALLOWED_ORIGINS|NEXT_PUBLIC_API_URL|ASSET_URL|MAIL_FROM_ADDRESS)=' "$file" \
    | grep -Eiq 'localhost|127\.0\.0\.1|:3000|:8000|http://'; then
    echo "ERROR: $file has local/non-https public URLs. Fix before deploy:"
    grep -Ei '^(APP_URL|FRONTEND_URL|STOREFRONT_URL|STOREFRONT_PUBLIC_URL|CORS_ALLOWED_ORIGINS|NEXT_PUBLIC_API_URL)=' "$file" || true
    exit 1
  fi
}

assert_prod_value() {
  local file="$1" key="$2" expected="$3"
  local actual
  actual="$(grep -E "^${key}=" "$file" | tail -1 | cut -d= -f2- | tr -d '"' | tr -d "'")"
  if [[ "$actual" != "$expected" ]]; then
    echo "ERROR: $file $key must be '$expected' (got '$actual')"
    exit 1
  fi
}

assert_app_key() {
  local file="$1"
  if ! grep -qE '^APP_KEY=base64:[A-Za-z0-9+/]{20,}={0,2}$' "$file"; then
    echo "ERROR: $file missing a valid APP_KEY — refusing to cache config (would brick sessions)."
    exit 1
  fi
}

# Never let git overwrite these
ENV_KEEP=(
  ".env"
  ".env.production"
  "admin/.env"
)

for f in "${ENV_KEEP[@]}"; do
  assert_no_local_urls "$APP_ROOT/$f"
done

assert_prod_value "$APP_ROOT/admin/.env" APP_ENV production
assert_prod_value "$APP_ROOT/admin/.env" APP_DEBUG false
assert_prod_value "$APP_ROOT/admin/.env" APP_URL "$PROD_ORIGIN"
assert_prod_value "$APP_ROOT/admin/.env" FRONTEND_URL "$PROD_ORIGIN"
assert_prod_value "$APP_ROOT/admin/.env" STOREFRONT_PUBLIC_URL "$PROD_ORIGIN"
assert_prod_value "$APP_ROOT/.env.production" NEXT_PUBLIC_API_URL "${PROD_ORIGIN}/api"
assert_app_key "$APP_ROOT/admin/.env"

# Snapshot env so a bad checkout can never leave us without them
BACKUP_DIR="$(mktemp -d /tmp/dokannward-env.XXXXXX)"
for f in "${ENV_KEEP[@]}"; do
  mkdir -p "$BACKUP_DIR/$(dirname "$f")"
  cp -a "$APP_ROOT/$f" "$BACKUP_DIR/$f"
done
restore_env() {
  for f in "${ENV_KEEP[@]}"; do
    cp -a "$BACKUP_DIR/$f" "$APP_ROOT/$f"
  done
}
trap 'restore_env; rm -rf "$BACKUP_DIR"' EXIT

# ---------------------------------------------------------------------------
# Pull latest code (env files are gitignored — still restore from backup)
# ---------------------------------------------------------------------------
if [[ ! -d .git ]]; then
  echo "ERROR: $APP_ROOT is not a git checkout. Bootstrap with scripts/bootstrap-server-git.sh first."
  exit 1
fi

git fetch --prune origin
git checkout "$BRANCH"
git reset --hard "origin/$BRANCH"

restore_env
echo "==> Restored production env files (never taken from git)"

# ---------------------------------------------------------------------------
# Admin (Laravel)
# ---------------------------------------------------------------------------
cd "$APP_ROOT/admin"
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --no-audit --no-fund
npm run build
php artisan migrate --force
php artisan storage:link 2>/dev/null || true
php artisan optimize:clear
# Prefer Redis when the extension + daemon are available (rebuild-production
# already forces this; keep deploy aligned so cache hits stay in memory).
if php -r 'exit(extension_loaded("redis") ? 0 : 1);' 2>/dev/null \
  && redis-cli ping 2>/dev/null | grep -qi pong; then
  grep -q '^CACHE_STORE=' .env && sed -i 's/^CACHE_STORE=.*/CACHE_STORE=redis/' .env \
    || echo 'CACHE_STORE=redis' >> .env
  grep -q '^SESSION_DRIVER=' .env && sed -i 's/^SESSION_DRIVER=.*/SESSION_DRIVER=redis/' .env \
    || echo 'SESSION_DRIVER=redis' >> .env
  echo "==> Redis available — CACHE_STORE/SESSION_DRIVER set to redis"
fi
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

# ---------------------------------------------------------------------------
# Storefront (Next.js) — rebuild so NEXT_PUBLIC_* comes from .env.production
# ---------------------------------------------------------------------------
cd "$APP_ROOT"
# Ensure Next reads production API URL (not a leftover .env.local)
rm -f .env.local
cp -a .env.production .env
npm ci --no-audit --no-fund
NODE_OPTIONS='--max-old-space-size=4096' npm run build
if pm2 describe dokannward-storefront >/dev/null 2>&1; then
  pm2 restart dokannward-storefront --update-env
else
  pm2 start npm --name dokannward-storefront --cwd "$APP_ROOT" -- start -- -p 3010
fi
pm2 save

# ---------------------------------------------------------------------------
# Post-deploy verification
# ---------------------------------------------------------------------------
sleep 2
fail=0
for path in / /admin/login /api/products /api/catalog-search; do
  code="$(curl -s -o /dev/null -w '%{http_code}' "https://${PROD_HOST}${path}" || echo 000)"
  echo "check ${path} -> ${code}"
  [[ "$code" =~ ^2 ]] || fail=1
done

login_html="$(curl -fsS "https://${PROD_HOST}/admin/login")"
if echo "$login_html" | grep -Eiq 'localhost|127\.0\.0\.1|:3000|:8000'; then
  echo "ERROR: login page still references local URLs"
  fail=1
fi
if ! echo "$login_html" | grep -Fq "href=\"${PROD_ORIGIN}/\""; then
  echo "ERROR: login 'back to website' is not ${PROD_ORIGIN}/"
  fail=1
fi

if [[ "$fail" -ne 0 ]]; then
  echo "Deploy verification FAILED"
  exit 1
fi

rm -rf "$BACKUP_DIR"
trap - EXIT
echo "==> Deploy OK — production URLs intact @ ${PROD_ORIGIN}"
