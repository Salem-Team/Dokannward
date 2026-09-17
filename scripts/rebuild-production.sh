#!/usr/bin/env bash
# Rebuild production in place WITHOUT touching env files.
# Used after rsync/git update of application code.
set -euo pipefail

APP_ROOT="${APP_ROOT:-/var/www/dokan-ward}"
PROD_HOST="${PROD_HOST:-dokan-ward.rootk-eg.com}"
PROD_ORIGIN="https://${PROD_HOST}"
# Optional override when edge nginx is not the host systemd unit (ROOTK docker edge).
NGINX_SNIPPET_DIR="${NGINX_SNIPPET_DIR:-/etc/nginx/snippets}"
NGINX_DOCKER="${NGINX_DOCKER:-rootk-prod-nginx}"
# Live ROOTK tenant process name; legacy path keeps the older name.
if [[ "$APP_ROOT" == *"/dokan-ward" ]]; then
  PM2_NAME="${PM2_NAME:-dokan-ward-storefront}"
else
  PM2_NAME="${PM2_NAME:-dokannward-storefront}"
fi
SKIP_PM2="${SKIP_PM2:-0}"

reload_nginx() {
  if systemctl is-active --quiet nginx 2>/dev/null; then
    systemctl reload nginx
    echo "==> Reloaded systemd nginx"
    return 0
  fi
  if command -v docker >/dev/null 2>&1 && docker ps --format '{{.Names}}' 2>/dev/null | grep -qx "$NGINX_DOCKER"; then
    if docker exec "$NGINX_DOCKER" nginx -t 2>/tmp/dokannward-docker-nginx-test.log; then
      docker exec "$NGINX_DOCKER" nginx -s reload
      echo "==> Reloaded docker nginx ($NGINX_DOCKER)"
      return 0
    fi
    echo "WARN: docker nginx -t failed — left previous config"
    cat /tmp/dokannward-docker-nginx-test.log >&2 || true
    return 1
  fi
  echo "WARN: no active nginx service/container to reload — continuing deploy"
  return 0
}

cd "$APP_ROOT"

# rsync from macOS can leave dirs as 700 (owner-only). Nginx/php-fpm need
# execute-bit on every parent path to serve /storage and /api — do this
# before any nginx reload or traffic hits the box mid-deploy.
chmod 755 "$APP_ROOT" "$APP_ROOT/admin" "$APP_ROOT/admin/public" 2>/dev/null || true
find "$APP_ROOT/admin/public" -type d -exec chmod 755 {} \; 2>/dev/null || true
find "$APP_ROOT/admin/public" -type f -exec chmod 644 {} \; 2>/dev/null || true
if [[ -L "$APP_ROOT/admin/public/storage" ]]; then
  chmod 755 "$APP_ROOT/admin/public/storage" 2>/dev/null || true
fi

# Rotate bloated Laravel logs (deploy errors can stack thousands of lines).
LOG_FILE="$APP_ROOT/admin/storage/logs/laravel.log"
if [[ -f "$LOG_FILE" ]]; then
  log_bytes="$(wc -c < "$LOG_FILE" | tr -d ' ')"
  if [[ "$log_bytes" -gt 5242880 ]]; then
    rotated="${LOG_FILE}.$(date +%Y%m%d-%H%M%S).bak"
    cp "$LOG_FILE" "$rotated"
    : > "$LOG_FILE"
    echo "==> Rotated laravel.log ($(numfmt --to=iec "$log_bytes" 2>/dev/null || echo "${log_bytes} bytes")) -> $(basename "$rotated")"
  fi
fi

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

echo "==> Assert production URLs"
assert_prod_value "$APP_ROOT/admin/.env" APP_ENV production
assert_prod_value "$APP_ROOT/admin/.env" APP_DEBUG false
assert_prod_value "$APP_ROOT/admin/.env" APP_URL "$PROD_ORIGIN"
assert_prod_value "$APP_ROOT/admin/.env" FRONTEND_URL "$PROD_ORIGIN"
assert_prod_value "$APP_ROOT/admin/.env" STOREFRONT_PUBLIC_URL "$PROD_ORIGIN"
assert_prod_value "$APP_ROOT/.env.production" NEXT_PUBLIC_API_URL "${PROD_ORIGIN}/api"
assert_app_key "$APP_ROOT/admin/.env"

# Keep nginx snippet in sync so admin static paths (/build, /sw-admin.js,
# /storage) never silently fall through to Next.js after a deploy.
if [[ -f "$APP_ROOT/deploy/nginx/dokannward-app.conf" && -d /etc/nginx/snippets ]]; then
  echo "==> Sync nginx dokannward-app.conf snippet"
  snippet_target=/etc/nginx/snippets/dokannward-app.conf
  snippet_backup="$(mktemp)"
  if [[ -f "$snippet_target" ]]; then
    cp "$snippet_target" "$snippet_backup"
  else
    rm -f "$snippet_backup"
    snippet_backup=""
  fi
  cp "$APP_ROOT/deploy/nginx/dokannward-app.conf" "$snippet_target"
  if nginx -t 2>/tmp/dokannward-nginx-test.log; then
    reload_nginx || true
  else
    echo "WARN: nginx -t failed after syncing dokannward-app.conf — restoring previous snippet and continuing deploy"
    cat /tmp/dokannward-nginx-test.log >&2 || true
    if [[ -n "$snippet_backup" && -f "$snippet_backup" ]]; then
      cp "$snippet_backup" "$snippet_target"
    fi
  fi
  [[ -n "$snippet_backup" ]] && rm -f "$snippet_backup"
fi

# Raise PHP upload ceilings so high-res product photos are not silently dropped.
if [[ -f "$APP_ROOT/deploy/php/99-dokannward-uploads.ini" ]]; then
  for conf_dir in /etc/php/*/fpm/conf.d /etc/php/*/cli/conf.d; do
    if [[ -d "$conf_dir" ]]; then
      cp "$APP_ROOT/deploy/php/99-dokannward-uploads.ini" "$conf_dir/99-dokannward-uploads.ini"
      echo "==> Synced PHP upload limits -> $conf_dir"
    fi
  done

  # php_admin_value in the pool cannot be overridden by a bad php.ini default.
  for pool in /etc/php/*/fpm/pool.d/www.conf; do
    if [[ -f "$pool" ]]; then
      tmp="$(mktemp)"
      awk '
        BEGIN { skip=0 }
        /^; dokannward-upload-limits$/ { skip=1; next }
        skip==1 && /^php_admin_value\[/ { next }
        skip==1 && /^request_terminate_timeout[[:space:]]*=/ { next }
        skip==1 { skip=0 }
        { print }
      ' "$pool" > "$tmp"
      cat >> "$tmp" <<'CONF'

; dokannward-upload-limits
php_admin_value[upload_max_filesize] = 2048M
php_admin_value[post_max_size] = 4096M
php_admin_value[memory_limit] = 1024M
php_admin_value[max_execution_time] = 1800
php_admin_value[max_input_time] = 1800
request_terminate_timeout = 1800s
CONF
      mv "$tmp" "$pool"
      echo "==> Locked PHP-FPM pool upload limits -> $pool"
    fi
  done
fi

# Keep site body-size / upload timeouts aligned with product photo uploads when
# the packaged vhost is present on this host.
if [[ -f "$APP_ROOT/deploy/nginx/sites/dokannward.com.conf" && -f /etc/nginx/sites-available/dokannward.com ]]; then
  if grep -q 'client_max_body_size' /etc/nginx/sites-available/dokannward.com; then
    sed -i 's/client_max_body_size[[:space:]]*[0-9.]\+[kmgt]\?;/client_max_body_size 4g;/' \
      /etc/nginx/sites-available/dokannward.com
    if grep -q 'client_body_timeout' /etc/nginx/sites-available/dokannward.com; then
      sed -i 's/client_body_timeout[[:space:]]*[0-9.]\+[sm]\?;/client_body_timeout 1800s;/' \
        /etc/nginx/sites-available/dokannward.com
    else
      sed -i '/client_max_body_size 4g;/a\    client_body_timeout 1800s;' \
        /etc/nginx/sites-available/dokannward.com
    fi
    if nginx -t 2>/tmp/dokannward-nginx-body.log; then
      reload_nginx || true
      echo "==> nginx client_max_body_size -> 4g (body timeout 1800s)"
    else
      echo "WARN: nginx -t failed after body-size tweak; left previous config"
      cat /tmp/dokannward-nginx-body.log >&2 || true
    fi
  fi
fi

# Belt-and-suspenders: strip any accidental local public URLs if someone edited env
python3 - <<PY
from pathlib import Path
prod = "${PROD_ORIGIN}"
app_root = Path("${APP_ROOT}")
replacements = {
    "APP_URL": prod,
    "ASSET_URL": prod,
    "FRONTEND_URL": prod,
    "STOREFRONT_URL": prod,
    "STOREFRONT_PUBLIC_URL": prod,
    "CORS_ALLOWED_ORIGINS": prod,
    "CACHE_STORE": "redis",
    "SESSION_DRIVER": "redis",
}
admin = app_root / "admin" / ".env"
text = admin.read_text()
lines = []
seen = set()
for line in text.splitlines():
    if "=" in line and not line.strip().startswith("#"):
        k, _, v = line.partition("=")
        if k in replacements:
            lines.append(f"{k}={replacements[k]}")
            seen.add(k)
            continue
    lines.append(line)
for k, v in replacements.items():
    if k not in seen:
        lines.append(f"{k}={v}")
admin.write_text("\n".join(lines) + "\n")

sf = app_root / ".env.production"
existing = sf.read_text() if sf.exists() else ""
sf.write_text(
    f"NEXT_PUBLIC_API_URL={prod}/api\n"
    + "\n".join(
        l for l in existing.splitlines()
        if l and not l.startswith("NEXT_PUBLIC_API_URL=")
    )
    + "\n"
)
# Never keep a .env.local on production
(app_root / ".env.local").unlink(missing_ok=True)
(app_root / ".env").write_text(sf.read_text())
print("production URLs enforced ->", prod)
PY

cd "$APP_ROOT/admin"
# Never serve Vite HMR in production — a leftover public/hot makes @vite
# point at http://[::1]:5173 and the admin UI loads with zero CSS/JS.
rm -f public/hot
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --no-audit --no-fund
npm run build
rm -f public/hot
php artisan migrate --force
# Orders that already had credit notes before refunded-status auto-mark.
php artisan orders:sync-refunded-status 2>/dev/null || true
# Do not auto-seed mock homepage banners — Admin → Banners is the only source.
# Never run optimize:clear here — it deletes config.php and briefly bricks
# every admin POST (MissingAppKeyException) until config:cache finishes.
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache 2>/dev/null || true
php artisan cache:clear
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
# Drop PHP OPcache so newly compiled Blade views are served immediately.
# Without this, admin pages can keep rendering the previous deploy's HTML.
if systemctl is-active --quiet php8.4-fpm 2>/dev/null; then
  systemctl reload php8.4-fpm
  echo "==> Reloaded php8.4-fpm (OPcache)"
elif systemctl is-active --quiet php8.3-fpm 2>/dev/null; then
  systemctl reload php8.3-fpm
  echo "==> Reloaded php8.3-fpm (OPcache)"
elif systemctl is-active --quiet php-fpm 2>/dev/null; then
  systemctl reload php-fpm
  echo "==> Reloaded php-fpm (OPcache)"
fi

# Keep public uploads writable + symlink reachable by nginx
mkdir -p storage/app/public
php artisan storage:link 2>/dev/null || true
chown -R www-data:www-data storage/app/public
chmod -R ug+rwX storage/app/public

cd "$APP_ROOT"
rm -f .env.local
cp -a .env.production .env
# Refuse to SSG an empty catalog (broken Laravel/API would ship blank pages).
api_code="$(curl -sk -o /tmp/dokannward-prebuild-brands.json -w '%{http_code}' "https://${PROD_HOST}/api/brands?per_page=5" || echo 000)"
api_count="$(python3 -c 'import json;print(len(json.load(open("/tmp/dokannward-prebuild-brands.json")).get("data") or []))' 2>/dev/null || echo 0)"
if [[ "$api_code" != "200" || "$api_count" -lt 1 ]]; then
  echo "ERROR: storefront API unhealthy before Next build (HTTP ${api_code}, brands=${api_count})"
  echo "       Fix Laravel/APP_KEY/config cache, then rebuild."
  exit 1
fi
echo "==> Pre-build API OK (brands=${api_count})"
# Next 15 can hit a WasmHash crash on incremental/corrupt .next caches after rsync.
rm -rf .next
npm ci --no-audit --no-fund
NODE_OPTIONS='--max-old-space-size=4096' npm run build

if [[ "$SKIP_PM2" == "1" ]]; then
  echo "==> SKIP_PM2=1 — built ${APP_ROOT} without touching PM2"
else
  # Bind 0.0.0.0 so ROOTK docker-edge nginx (bridge gateway) can reach Next.
  # Retire the other process name if it still owns this cwd/port.
  for legacy in dokannward-storefront dokan-ward-storefront; do
    if [[ "$legacy" != "$PM2_NAME" ]] && pm2 describe "$legacy" >/dev/null 2>&1; then
      legacy_cwd="$(pm2 show "$legacy" 2>/dev/null | awk -F'│' '/exec cwd/ {gsub(/ /,"",$2); print $2}' | head -1 || true)"
      if [[ -z "$legacy_cwd" || "$legacy_cwd" == "$APP_ROOT" ]]; then
        pm2 delete "$legacy" >/dev/null 2>&1 || true
      fi
    fi
  done
  if pm2 describe "$PM2_NAME" >/dev/null 2>&1; then
    pm2 delete "$PM2_NAME" >/dev/null 2>&1 || true
  fi
  pm2 start npm --name "$PM2_NAME" --cwd "$APP_ROOT" -- start -- --hostname 0.0.0.0 -p 3010
  pm2 save
fi

sleep 2
login_html="$(curl -fsS "https://${PROD_HOST}/admin/login")"
if echo "$login_html" | grep -Eiq 'localhost|127\.0\.0\.1|:3000|:8000'; then
  echo "ERROR: login page still references local URLs"
  exit 1
fi
if ! echo "$login_html" | grep -Fq "href=\"${PROD_ORIGIN}/\""; then
  echo "ERROR: back-to-website is not ${PROD_ORIGIN}/"
  exit 1
fi

# Admin Vite bundle + SW must come from Laravel public, not Next.js.
sw_code="$(curl -sS -o /tmp/dokannward-sw-admin.js -w '%{http_code}' "https://${PROD_HOST}/sw-admin.js")"
if [[ "$sw_code" != "200" ]]; then
  echo "ERROR: /sw-admin.js returned HTTP $sw_code (expected 200 from Laravel public)"
  exit 1
fi
if ! grep -Fq 'dokannward-admin-static' /tmp/dokannward-sw-admin.js; then
  echo "ERROR: /sw-admin.js is not the admin service worker"
  exit 1
fi
if ! echo "$login_html" | grep -Eq '/build/assets/admin-[^"]+\.js'; then
  echo "ERROR: admin login is not loading a Vite admin-*.js bundle"
  exit 1
fi
admin_js="$(echo "$login_html" | grep -oE '/build/assets/admin-[^"]+\.js' | head -1)"
# Grep the downloaded file — piping a large JS blob through echo can false-fail.
curl -fsS "https://${PROD_HOST}${admin_js}" -o /tmp/dokannward-admin-js-check.js
if ! grep -Eq 'window\.dialog|admin-dialog' /tmp/dokannward-admin-js-check.js; then
  echo "ERROR: admin JS (${admin_js}) missing branded dialog API — stale bundle?"
  exit 1
fi
echo "==> Admin bundle OK (${admin_js})"

# Smoke-test that /storage serves an uploaded product image
sample="$(find "$APP_ROOT/admin/storage/app/public/products" -type f \( -name '*.jpg' -o -name '*.png' -o -name '*.webp' \) 2>/dev/null | head -1 || true)"
if [[ -n "$sample" ]]; then
  rel="${sample#${APP_ROOT}/admin/storage/app/public/}"
  code="$(curl -s -o /dev/null -w '%{http_code}' "${PROD_ORIGIN}/storage/${rel}")"
  if [[ "$code" != "200" ]]; then
    echo "ERROR: /storage/${rel} returned HTTP ${code}"
    exit 1
  fi
  echo "==> Storage OK — /storage/${rel} → ${code}"
fi

# Site-wide next/image + storage health (catches quality allow-list regressions).
if [[ -x "$APP_ROOT/scripts/assert-images.sh" ]]; then
  PROD_ORIGIN="$PROD_ORIGIN" "$APP_ROOT/scripts/assert-images.sh"
fi

# Warm hot paths so the first real visitor hits Redis/file cache, not remote DB.
if [[ -x "$APP_ROOT/scripts/warm-production.sh" ]]; then
  PROD_ORIGIN="$PROD_ORIGIN" "$APP_ROOT/scripts/warm-production.sh" || true
fi

echo "==> Production rebuild OK — URLs locked to ${PROD_ORIGIN}"
