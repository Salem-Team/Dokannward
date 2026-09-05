#!/usr/bin/env bash
# Warm production caches so the first visitor isn't paying remote-DB latency.
set -euo pipefail
export PATH="/usr/bin:/bin:/usr/sbin:/sbin:${PATH:-}"
PROD_ORIGIN="${PROD_ORIGIN:-https://dokannward.com}"
CURL=(curl -sS --max-time 25)

echo "==> Warming ${PROD_ORIGIN}"
for path in \
  / \
  /collections \
  /collections/all \
  /search \
  /checkout \
  /api/catalog-search \
  /api/products?per_page=24 \
  /api/products?featured=1\&per_page=12 \
  /api/categories \
  /api/brands?per_page=50 \
  /api/banners \
  /api/testimonials \
  /api/settings/store \
  /api/settings/currency \
  /api/settings/checkout \
  /admin/login
do
  code="$("${CURL[@]}" -o /dev/null -w '%{http_code}' "${PROD_ORIGIN}${path}" || echo 000)"
  ms="$("${CURL[@]}" -o /dev/null -w '%{time_total}' "${PROD_ORIGIN}${path}" || echo 0)"
  echo "  ${path} → HTTP ${code} (${ms}s)"
  # Tiny pause so deploy warm never trips the public API rate limiter.
  sleep 0.15
done
# Second pass hits warm Laravel + Next caches (HTML + a couple of APIs only).
for path in /api/products?per_page=24 /api/categories /api/catalog-search /; do
  "${CURL[@]}" -o /dev/null "${PROD_ORIGIN}${path}" || true
  sleep 0.1
done
echo "==> Warm complete"
