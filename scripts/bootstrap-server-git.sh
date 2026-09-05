#!/usr/bin/env bash
# One-time: turn /var/www/dokannward into a git checkout while keeping live env + builds.
set -euo pipefail

APP_ROOT="${APP_ROOT:-/var/www/dokannward}"
REPO="${REPO:-git@github.com:Salem-Team/Dokannward.git}"
BRANCH="${DEPLOY_BRANCH:-main}"

cd "$(dirname "$APP_ROOT")"
STAMP="$(date +%Y%m%d%H%M%S)"
BACKUP="${APP_ROOT}.bak-${STAMP}"

echo "==> Backing up ${APP_ROOT} -> ${BACKUP}"
cp -a "$APP_ROOT" "$BACKUP"

ENV_TMP="$(mktemp -d /tmp/dokannward-bootstrap-env.XXXXXX)"
mkdir -p "$ENV_TMP/admin"
cp -a "$APP_ROOT/.env" "$ENV_TMP/.env" 2>/dev/null || true
cp -a "$APP_ROOT/.env.production" "$ENV_TMP/.env.production" 2>/dev/null || true
cp -a "$APP_ROOT/admin/.env" "$ENV_TMP/admin/.env"

# Keep heavy artifacts to avoid a cold rebuild if possible
KEEP_DIRS=(
  node_modules
  .next
  admin/vendor
  admin/node_modules
  admin/public/build
  public/images/brand
)

KEEP_TMP="$(mktemp -d /tmp/dokannward-bootstrap-keep.XXXXXX)"
for d in "${KEEP_DIRS[@]}"; do
  if [[ -e "$APP_ROOT/$d" ]]; then
    mkdir -p "$KEEP_TMP/$(dirname "$d")"
    mv "$APP_ROOT/$d" "$KEEP_TMP/$d"
  fi
done

rm -rf "$APP_ROOT"
git clone --branch "$BRANCH" --single-branch "$REPO" "$APP_ROOT"

for d in "${KEEP_DIRS[@]}"; do
  if [[ -e "$KEEP_TMP/$d" ]]; then
    mkdir -p "$APP_ROOT/$(dirname "$d")"
    rm -rf "$APP_ROOT/$d"
    mv "$KEEP_TMP/$d" "$APP_ROOT/$d"
  fi
done

cp -a "$ENV_TMP/.env" "$APP_ROOT/.env"
cp -a "$ENV_TMP/.env.production" "$APP_ROOT/.env.production"
cp -a "$ENV_TMP/admin/.env" "$APP_ROOT/admin/.env"
rm -f "$APP_ROOT/.env.local"

chmod +x "$APP_ROOT/scripts/"*.sh
chown -R www-data:www-data "$APP_ROOT/admin/storage" "$APP_ROOT/admin/bootstrap/cache" 2>/dev/null || true

rm -rf "$ENV_TMP" "$KEEP_TMP"
echo "==> Git checkout ready. Backup kept at ${BACKUP}"
echo "==> Run: ${APP_ROOT}/scripts/deploy.sh"
