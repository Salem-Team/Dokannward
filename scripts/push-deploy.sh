#!/usr/bin/env bash
# Push to GitHub, rsync code to every Dokan Ward tenant on the VPS, rebuild.
# Never overwrites production env files or ROOTK injected .rootk/*.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
export PATH="/usr/bin:/bin:/usr/sbin:/sbin:${PATH}"

HOST="${DOKANWARD_SSH_HOST:-zibra}"
TENANTS=(
  "${DOKANWARD_REMOTE_PATH:-/var/www/dokan-ward}"
  "${DOKANWARD_MIRROR_PATH:-/var/www/dokannward}"
)
PROD_HOST="${PROD_HOST:-dokan-ward.rootk-eg.com}"
RELEASE_VERSION="$(node -p "require('./package.json').version")"

stamp_tenant_version() {
  local REMOTE="$1"
  local VER="$2"
  ssh -o BatchMode=yes "$HOST" \
    "python3 ${REMOTE}/scripts/stamp-release-version.py '${REMOTE}' '${VER}'"
}

rsync_tenant() {
  local REMOTE="$1"
  echo "==> Rsync -> ${HOST}:${REMOTE}"
  rsync -az --delete \
    --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r \
    --exclude '.git/' \
    --exclude 'node_modules/' \
    --exclude 'admin/node_modules/' \
    --exclude 'admin/vendor/' \
    --exclude '.next/' \
    --exclude '.next-build/' \
    --exclude '.rootk/' \
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
    --filter 'protect .rootk/' \
    --exclude '.env' \
    --exclude '.env.local' \
    --exclude '.env.production' \
    --exclude 'admin/.env' \
    --exclude '.DS_Store' \
    -e 'ssh -o BatchMode=yes' \
    ./ "${HOST}:${REMOTE}/"

  if [[ -d admin/storage/app/public/products/dokannward ]]; then
    echo "==> Sync product images -> ${REMOTE}"
    ssh -o BatchMode=yes "$HOST" "mkdir -p ${REMOTE}/admin/storage/app/public/products/dokannward"
    rsync -az --chmod=Fu=rw,Fgo=r \
      -e 'ssh -o BatchMode=yes' \
      admin/storage/app/public/products/dokannward/ \
      "${HOST}:${REMOTE}/admin/storage/app/public/products/dokannward/"
    ssh -o BatchMode=yes "$HOST" \
      "chown -R www-data:www-data ${REMOTE}/admin/storage/app/public/products/dokannward"
  elif [[ -d public/images/products ]]; then
    echo "==> Sync public product images -> ${REMOTE}"
    ssh -o BatchMode=yes "$HOST" "mkdir -p ${REMOTE}/public/images/products ${REMOTE}/admin/storage/app/public/products/dokannward"
    rsync -az --chmod=Fu=rw,Fgo=r \
      -e 'ssh -o BatchMode=yes' \
      public/images/products/ \
      "${HOST}:${REMOTE}/public/images/products/"
    rsync -az --chmod=Fu=rw,Fgo=r \
      -e 'ssh -o BatchMode=yes' \
      public/images/products/ \
      "${HOST}:${REMOTE}/admin/storage/app/public/products/dokannward/"
    ssh -o BatchMode=yes "$HOST" \
      "chown -R www-data:www-data ${REMOTE}/admin/storage/app/public/products/dokannward ${REMOTE}/public/images/products"
  fi

  rsync -az scripts/ "${HOST}:${REMOTE}/scripts/"
  ssh -o BatchMode=yes "$HOST" "chmod +x ${REMOTE}/scripts/*.sh ${REMOTE}/scripts/*.py 2>/dev/null || chmod +x ${REMOTE}/scripts/*.sh"
  stamp_tenant_version "$REMOTE" "$RELEASE_VERSION"

  if [[ "$REMOTE" == *"/dokan-ward" ]]; then
    echo "==> Rebuild live tenant ${REMOTE}"
    ssh -o BatchMode=yes "$HOST" \
      "APP_ROOT='${REMOTE}' PROD_HOST='${PROD_HOST}' PM2_NAME='dokan-ward-storefront' ${REMOTE}/scripts/rebuild-production.sh"
  else
    echo "==> Mirror synced (no PM2 rebuild): ${REMOTE}"
  fi
}

echo "==> Release version ${RELEASE_VERSION}"
echo "==> Push origin/main"
git push origin HEAD:main

for tenant in "${TENANTS[@]}"; do
  if ssh -o BatchMode=yes "$HOST" "[[ -d ${tenant} ]]"; then
    rsync_tenant "$tenant"
  else
    echo "==> Skip missing tenant path ${tenant}"
  fi
done

echo "==> Done - https://${PROD_HOST} (v${RELEASE_VERSION})"
