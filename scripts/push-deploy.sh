#!/usr/bin/env bash
# Push to GitHub, rsync code to the VPS (never env files), rebuild production.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
export PATH="/usr/bin:/bin:/usr/sbin:/sbin:${PATH}"

HOST="${DOKANWARD_SSH_HOST:-mtec}"
REMOTE="${DOKANWARD_REMOTE_PATH:-/var/www/dokannward}"
PROD_HOST="${PROD_HOST:-dokannward.com}"

echo "==> Push origin/main"
git push origin HEAD:main

echo "==> Rsync application code (production env files stay on the server)"
# Du=rwx,Dgo=rx keeps directories traversable by nginx (rsync from macOS can
# otherwise leave admin/ as 700 and break /storage + /api).
# `protect` keeps upload/storage trees safe from --delete even when excluded.
rsync -az --delete \
  --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r \
  --exclude '.git/' \
  --exclude 'node_modules/' \
  --exclude 'admin/node_modules/' \
  --exclude 'admin/vendor/' \
  --exclude '.next/' \
  --exclude 'admin/public/build/' \
  --exclude 'admin/public/hot' \
  --exclude 'admin/public/storage' \
  --exclude 'admin/storage/app/public/' \
  --exclude 'admin/storage/logs/' \
  --exclude 'admin/storage/framework/cache/' \
  --exclude 'admin/storage/framework/sessions/' \
  --exclude 'admin/storage/framework/views/' \
  --filter 'protect admin/storage/app/public/' \
  --filter 'protect admin/storage/logs/' \
  --filter 'protect admin/storage/framework/cache/' \
  --filter 'protect admin/storage/framework/sessions/' \
  --filter 'protect admin/storage/framework/views/' \
  --filter 'protect admin/public/storage' \
  --filter 'protect admin/bootstrap/cache/' \
  --exclude '.env' \
  --exclude '.env.local' \
  --exclude '.env.production' \
  --exclude 'admin/.env' \
  --exclude '.DS_Store' \
  -e 'ssh -o BatchMode=yes' \
  ./ "${HOST}:${REMOTE}/"

# Catalog plates are gitignored under storage/ but required by seeded product photos.
if [[ -d admin/storage/app/public/products/dokannward ]]; then
  echo "==> Sync Dokan Ward product images"
  ssh -o BatchMode=yes "$HOST" "mkdir -p ${REMOTE}/admin/storage/app/public/products/dokannward"
  rsync -az --chmod=Fu=rw,Fgo=r \
    -e 'ssh -o BatchMode=yes' \
    admin/storage/app/public/products/dokannward/ \
    "${HOST}:${REMOTE}/admin/storage/app/public/products/dokannward/"
  ssh -o BatchMode=yes "$HOST" \
    "chown -R www-data:www-data ${REMOTE}/admin/storage/app/public/products/dokannward"
fi

rsync -az scripts/ "${HOST}:${REMOTE}/scripts/"
ssh -o BatchMode=yes "$HOST" \
  "chmod +x ${REMOTE}/scripts/*.sh && PROD_HOST='${PROD_HOST}' ${REMOTE}/scripts/rebuild-production.sh"

echo "==> Done — https://${PROD_HOST}"
