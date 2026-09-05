#!/usr/bin/env bash
# One-shot: dump Hostinger MySQL → local MySQL on the VPS, then point Laravel at 127.0.0.1.
# Run on the production host as root. Never commits secrets.
set -euo pipefail

APP_ROOT="${APP_ROOT:-/var/www/dokannward/admin}"
ENV_FILE="${APP_ROOT}/.env"
LOCAL_DB="${LOCAL_DB:-dokannward}"
LOCAL_USER="${LOCAL_USER:-dokannward}"
PASS_FILE="${PASS_FILE:-/root/.dokannward-mysql-pass}"
DUMP="${DUMP:-/root/dokannward-hostinger-$(date -u +%Y%m%dT%H%M%SZ).sql.gz}"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing $ENV_FILE"
  exit 1
fi

if [[ ! -f "$PASS_FILE" ]]; then
  echo "Missing $PASS_FILE — create local user first"
  exit 1
fi

LOCAL_PASS="$(tr -d '\n' < "$PASS_FILE")"

get_env() {
  local key="$1"
  grep -E "^${key}=" "$ENV_FILE" | tail -1 | cut -d= -f2- | tr -d '"' | tr -d "'"
}

REMOTE_HOST="$(get_env DB_HOST)"
REMOTE_PORT="$(get_env DB_PORT)"
REMOTE_DB="$(get_env DB_DATABASE)"
REMOTE_USER="$(get_env DB_USERNAME)"
REMOTE_PASS="$(get_env DB_PASSWORD)"

if [[ -z "$REMOTE_HOST" || "$REMOTE_HOST" == "127.0.0.1" || "$REMOTE_HOST" == "localhost" ]]; then
  echo "DB_HOST is already local ($REMOTE_HOST) — nothing to migrate"
  exit 0
fi

echo "==> Testing remote MySQL ${REMOTE_HOST}:${REMOTE_PORT}/${REMOTE_DB}"
mysqladmin --connect-timeout=10 -h"$REMOTE_HOST" -P"${REMOTE_PORT:-3306}" -u"$REMOTE_USER" -p"$REMOTE_PASS" ping

echo "==> Dumping remote → ${DUMP}"
mysqldump \
  --single-transaction --quick --routines --triggers \
  --column-statistics=0 \
  -h"$REMOTE_HOST" -P"${REMOTE_PORT:-3306}" -u"$REMOTE_USER" -p"$REMOTE_PASS" \
  "$REMOTE_DB" | gzip -c > "$DUMP"

echo "==> Importing into local ${LOCAL_DB}"
gunzip -c "$DUMP" | mysql -h127.0.0.1 -u"$LOCAL_USER" -p"$LOCAL_PASS" "$LOCAL_DB"

echo "==> Backing up .env and switching DB_* to local"
cp -a "$ENV_FILE" "${ENV_FILE}.bak.hostinger.$(date -u +%Y%m%d%H%M%S)"
python3 - <<PY
from pathlib import Path
env = Path("$ENV_FILE")
text = env.read_text()
replacements = {
    "DB_HOST": "127.0.0.1",
    "DB_PORT": "3306",
    "DB_DATABASE": "$LOCAL_DB",
    "DB_USERNAME": "$LOCAL_USER",
    "DB_PASSWORD": """$LOCAL_PASS""",
    "DB_PERSISTENT": "false",
}
lines = []
seen = set()
for line in text.splitlines():
    if "=" in line and not line.strip().startswith("#"):
        k, _, _ = line.partition("=")
        if k in replacements:
            lines.append(f"{k}={replacements[k]}")
            seen.add(k)
            continue
    lines.append(line)
for k, v in replacements.items():
    if k not in seen:
        lines.append(f"{k}={v}")
env.write_text("\n".join(lines) + "\n")
PY

cd "$APP_ROOT"
php artisan config:clear
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Verifying local"
php artisan tinker --execute="echo 'reviews='.App\\Models\\ProductReview::count().PHP_EOL.'products='.App\\Models\\Product::count();"

echo "==> Done. Dump kept at ${DUMP}"
echo "    Restart not required; next request uses local MySQL."
