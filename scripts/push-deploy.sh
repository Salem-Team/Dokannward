#!/usr/bin/env bash
# Push to GitHub, rsync code to every Dokan Ward tenant on the VPS, rebuild.
# Never overwrites production env files or ROOTK injected .rootk/*.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
export PATH="/usr/bin:/bin:/usr/sbin:/sbin:${PATH}"

HOST="${DOKANWARD_SSH_HOST:-zibra}"
# Live ROOTK tenant first; legacy path kept in sync for parity.
TENANTS=(
  "${DOKANWARD_REMOTE_PATH:-/var/www/dokan-ward}"
  "${DOKANWARD_MIRROR_PATH:-/var/www/dokannward}"
)
PROD_HOST="${PROD_HOST:-dokan-ward.rootk-eg.com}"
RELEASE_VERSION="$(node -p "require('./package.json').version")"

echo "==> Release version ${RELEASE_VERSION}"
echo "==> Push origin/main"
git push origin HEAD:main

rsync_tenant() {
  local REMOTE="$1"
  echo "==> Rsync -> ${HOST}:${REMOTE}"
  # Du=rwx,Dgo=rx keeps directories traversable by nginx (rsync from macOS can
  # otherwise leave admin/ as 700 and break /storage + /api).
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

  # Catalog plates are gitignored under storage/ but required by seeded product photos.
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
  ssh -o BatchMode=yes "$HOST" "chmod +x ${REMOTE}/scripts/*.sh"

  # Stamp ROOTK release version when the tenant is ROOTK-managed.
  ssh -o BatchMode=yes "$HOST" "bash -s" <<EOF
set -euo pipefail
REMOTE='${REMOTE}'
VER='${RELEASE_VERSION}'
if [[ -f "\$REMOTE/.rootk/tenant.env" ]]; then
  python3 - <<'PY'
from pathlib import Path
import re
remote = Path("${REMOTE}")
ver = "${RELEASE_VERSION}"
for rel in [".rootk/tenant.env", ".rootk/deployment.env"]:
    p = remote / rel
    if not p.is_file():
        continue
    text = p.read_text()
    for key in ("ROOTK_RELEASE_VERSION", "NEXT_PUBLIC_ROOTK_RELEASE_VERSION"):
        pat = rf"(?m)^{key}='[^']*'"
        if re.search(pat, text):
            text = re.sub(pat, f"{key}='{ver}'", text)
        else:
            text = text.rstrip() + f"\n{key}='{ver}'\n"
    p.write_text(text)
    print(f"stamped {p} -> {ver}")
PY
fi
# Keep package.json version aligned on the box.
python3 - <<'PY'
import json
from pathlib import Path
p = Path("${REMOTE}") / "package.json"
data = json.loads(p.read_text())
data["version"] = "${RELEASE_VERSION}"
if data.get("name") in ("zibra", "dokannward", None):
    data["name"] = "dokannward"
p.write_text(json.dumps(data, indent=2) + "\n")
print(f"package.json version -> ${RELEASE_VERSION}")
PY
EOF

  if [[ "$REMOTE" == *"/dokan-ward" ]]; then
    echo "==> Rebuild live tenant ${REMOTE}"
    ssh -o BatchMode=yes "$HOST" \
      "APP_ROOT='${REMOTE}' PROD_HOST='${PROD_HOST}' PM2_NAME='dokan-ward-storefront' ${REMOTE}/scripts/rebuild-production.sh"
  else
    echo "==> Mirror synced (no PM2 rebuild): ${REMOTE}"
  fi
}for tenant in "${TENANTS[@]}"; do
  # Skip missing mirror dirs
  if ssh -o BatchMode=yes "$HOST" "[[ -d ${tenant} ]]"; then
    rsync_tenant "$tenant"
  else
    echo "==> Skip missing tenant path ${tenant}"
  fi
done

echo "==> Done — https://${PROD_HOST} (v${RELEASE_VERSION})"
