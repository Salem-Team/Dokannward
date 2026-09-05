#!/usr/bin/env bash
# Dokan Ward local smoke suite — storefront + API + admin.
# Usage: FRONT=http://127.0.0.1:3001 API=http://127.0.0.1:8001/api ADMIN=http://127.0.0.1:8001 ./scripts/qa-smoke.sh
set -euo pipefail

FRONT="${FRONT:-http://127.0.0.1:3001}"
API="${API:-http://127.0.0.1:8001/api}"
ADMIN="${ADMIN:-http://127.0.0.1:8001}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@dokannward.com}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-Admin123!}"

PASS=0
FAIL=0

ok() { PASS=$((PASS + 1)); echo "PASS  $1 — $2"; }
fail() { FAIL=$((FAIL + 1)); echo "FAIL  $1 — $2"; }

check() {
  local name="$1" url="$2" expect="${3:-200}"
  local code
  local cookie_args=()
  if [[ -f /tmp/dokannward-smoke-cj.txt ]]; then
    cookie_args=(-b /tmp/dokannward-smoke-cj.txt -c /tmp/dokannward-smoke-cj.txt)
  fi
  code=$(curl -sS "${cookie_args[@]}" -o /tmp/dokannward-smoke-body.bin -w "%{http_code}" --max-time 60 "$url" || echo "000")
  if [[ "$code" == "$expect" ]]; then
    ok "$name" "HTTP $code"
  else
    fail "$name" "expected $expect got $code ($url)"
  fi
}

echo "== Storefront =="
for path in / /brands /collections /collections/all /pages/about /pages/contact \
  /policies/privacy-policy /policies/terms-of-service /search?q=bag /checkout; do
  check "GET $path" "$FRONT$path"
done

echo "== API =="
for path in /products?per_page=5 /brands?per_page=5 /categories /collections \
  /banners /testimonials /settings/currency /settings/store /settings/checkout; do
  check "API $path" "$API$path"
done

IMG=$(python3 - <<PY
import json
print(json.load(open("/tmp/dokannward-smoke-body.bin")).get("data",[{}])[0].get("image",""))
PY
)
# Re-fetch products for a reliable image URL
IMG=$(curl -sS "$API/products?per_page=1" | python3 -c "import json,sys; print(json.load(sys.stdin)['data'][0]['image'])")
if [[ "$IMG" == *":8001/"* ]]; then
  ok "Product image host" "$IMG"
else
  fail "Product image host" "expected :8001 in $IMG"
fi
check "Product image fetch" "$IMG"

echo "== Admin =="
rm -f /tmp/dokannward-smoke-cj.txt
curl -sS -c /tmp/dokannward-smoke-cj.txt -b /tmp/dokannward-smoke-cj.txt "$ADMIN/admin/login" -o /tmp/dokannward-smoke-login.html
CSRF=$(python3 - <<'PY'
import re
html=open("/tmp/dokannward-smoke-login.html",encoding="utf-8",errors="ignore").read()
m=re.search(r'name="_token"\s+value="([^"]+)"', html)
print(m.group(1) if m else "")
PY
)
curl -sS -c /tmp/dokannward-smoke-cj.txt -b /tmp/dokannward-smoke-cj.txt \
  -X POST "$ADMIN/admin/login" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data-urlencode "_token=$CSRF" \
  --data-urlencode "email=$ADMIN_EMAIL" \
  --data-urlencode "password=$ADMIN_PASSWORD" \
  -o /dev/null -w "%{http_code}" | grep -Eq '302|200' \
  && ok "Admin login" "$ADMIN_EMAIL" \
  || fail "Admin login" "$ADMIN_EMAIL"

check "Admin dashboard" "$ADMIN/admin/dashboard"
check "Admin orders" "$ADMIN/admin/orders"

ORDER_ID=$(cd "$(dirname "$0")/../admin" && php artisan tinker --execute='echo App\Models\Order::latest("id")->value("id") ?? "";' 2>/dev/null | tail -1)
if [[ -n "$ORDER_ID" ]]; then
  code=$(curl -sS -b /tmp/dokannward-smoke-cj.txt -o /tmp/dokannward-smoke-invoice.pdf -w "%{http_code}" \
    -H "Accept: application/pdf" "$ADMIN/admin/orders/$ORDER_ID/invoice")
  if [[ "$code" == "200" ]] && file /tmp/dokannward-smoke-invoice.pdf | grep -qi PDF; then
    ok "Invoice PDF" "HTTP 200 order=$ORDER_ID"
  else
    fail "Invoice PDF" "HTTP $code order=$ORDER_ID"
  fi
else
  fail "Invoice PDF" "no orders in DB"
fi

echo
echo "SUMMARY PASS=$PASS FAIL=$FAIL"
[[ "$FAIL" -eq 0 ]]
