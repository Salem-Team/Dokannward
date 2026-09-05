#!/usr/bin/env bash
# Smoke-test every admin sidebar GET route on production (authenticated).
# Usage: ADMIN_EMAIL=... ADMIN_PASSWORD=... ./scripts/smoke-admin-links.sh
set -euo pipefail

BASE="${BASE_URL:-https://dokannward.com}"
EMAIL="${ADMIN_EMAIL:-admin@dokannward.com}"
PASS="${ADMIN_PASSWORD:-Admin123!}"
COOKIE_JAR="$(mktemp)"
trap 'rm -f "$COOKIE_JAR"' EXIT

fail=0
ok=0

echo "==> Login ${EMAIL} @ ${BASE}"
LOGIN_HTML="$(curl -sS -c "$COOKIE_JAR" -b "$COOKIE_JAR" "${BASE}/admin/login")"
TOKEN="$(printf '%s' "$LOGIN_HTML" | python3 -c "import sys,re; m=re.search(r'name=\"_token\" value=\"([^\"]+)\"', sys.stdin.read()); print(m.group(1) if m else '')")"
if [[ -z "$TOKEN" ]]; then
  echo "FAIL: could not parse CSRF token from login page"
  exit 1
fi

LOC="$(curl -sS -o /tmp/admin-login-post.html -w '%{http_code} %{redirect_url}' \
  -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
  -X POST "${BASE}/admin/login" \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode "_token=${TOKEN}" \
  --data-urlencode "email=${EMAIL}" \
  --data-urlencode "password=${PASS}" \
  --data-urlencode "remember=1")"
echo "login => ${LOC}"

PATHS=(
  /admin/dashboard
  /admin/desk-search?q=test
  /admin/products
  /admin/products/create
  /admin/categories
  /admin/categories/create
  /admin/orders
  /admin/customers
  /admin/customers/create
  /admin/brands
  /admin/brands/create
  /admin/testimonials
  /admin/testimonials/create
  /admin/pages
  /admin/pages/create
  /admin/banners
  /admin/banners/create
  /admin/collections
  /admin/collections/create
  /admin/website
  /admin/policies
  /admin/reviews
  /admin/reviews?status=pending
  /admin/reviews?status=approved
  /admin/reviews?status=all
  /admin/contact-messages
  /admin/inventory
  /admin/settings
  /admin/profile
)

echo "==> Checking ${#PATHS[@]} admin pages"
for path in "${PATHS[@]}"; do
  code="$(curl -sS -o /tmp/admin-smoke-body.html -w '%{http_code}' \
    -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
    -L --max-redirs 3 \
    "${BASE}${path}")"
  final_url="$(curl -sS -o /dev/null -w '%{url_effective}' \
    -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
    -L --max-redirs 3 \
    "${BASE}${path}")"
  body="$(head -c 200000 /tmp/admin-smoke-body.html)"
  bad=0
  reason=""
  if [[ "$code" != "200" ]]; then
    bad=1
    reason="http ${code}"
  elif echo "$final_url" | grep -q '/admin/login'; then
    bad=1
    reason="redirected to login"
  elif echo "$body" | grep -qiE 'Server Error|SQLSTATE|max_connections|Whoops|Connection refused'; then
    bad=1
    reason="error page content"
  fi
  if [[ "$bad" -eq 1 ]]; then
    echo "FAIL  ${path}  (${reason})"
    fail=$((fail + 1))
  else
    echo "OK    ${path}"
    ok=$((ok + 1))
  fi
done

echo "==> Summary: ${ok} ok, ${fail} fail"
[[ "$fail" -eq 0 ]]
