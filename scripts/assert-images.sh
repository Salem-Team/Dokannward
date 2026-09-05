#!/usr/bin/env bash
# Site-wide image health check for production.
# Fails if sampled /_next/image or /storage URLs return non-2xx / tiny bodies.
set -euo pipefail

PROD_ORIGIN="${PROD_ORIGIN:-https://dokannward.com}"
export PROD_ORIGIN

echo "==> Image assert @ ${PROD_ORIGIN%/}"

python3 - <<'PY'
import json, re, sys, urllib.parse, urllib.request, ssl, os

origin = os.environ.get("PROD_ORIGIN", "https://dokannward.com").rstrip("/")
ctx = ssl.create_default_context()
pages = ["/", "/brands", "/collections", "/collections/all", "/search"]

def get_json(path):
    try:
        req = urllib.request.Request(
            origin + path,
            headers={"User-Agent": "dokannward-image-assert", "Accept": "application/json"},
        )
        with urllib.request.urlopen(req, context=ctx, timeout=20) as r:
            return json.loads(r.read().decode())
    except Exception as e:
        print(f"WARN api {path}: {e}")
        return {}

for row in (get_json("/api/products?per_page=6").get("data") or [])[:6]:
    slug = row.get("slug")
    if slug:
        pages.append(f"/products/{slug}")
for row in (get_json("/api/brands?per_page=8").get("data") or [])[:8]:
    slug = row.get("slug")
    if slug:
        pages.append(f"/brands/{slug}")

urls = []
families = set()

def add(raw: str):
    u = raw.replace("&amp;", "&").replace("\\/", "/").strip().rstrip("\\")
    if not u:
        return
    if "/_next/image" in u:
        full = u if u.startswith("http") else origin + u
        q = urllib.parse.urlsplit(full)
        qs = urllib.parse.parse_qs(q.query)
        key = (qs.get("url", [""])[0], qs.get("q", ["75"])[0])
        w = qs.get("w", [""])[0]
        if key in families:
            return
        if w and w not in {"640", "750", "828", "1080", "1200"}:
            return
        families.add(key)
        urls.append(full)
        return
    if u.startswith("http"):
        urls.append(u)
    elif u.startswith("/"):
        urls.append(origin + u)

for path in pages:
    try:
        req = urllib.request.Request(origin + path, headers={"User-Agent": "dokannward-image-assert"})
        with urllib.request.urlopen(req, context=ctx, timeout=25) as r:
            html = r.read().decode("utf-8", "ignore")
    except Exception as e:
        print(f"WARN page {path}: {e}")
        continue
    for m in re.findall(r'(?:src|srcSet|srcset)="([^"]+)"', html):
        for part in m.split(","):
            cand = part.strip().split(" ")[0]
            if "/_next/image" in cand or "/storage/" in cand or cand.startswith("/images/"):
                add(cand)
    for m in re.findall(r"https://dokannward.com/(storage/[A-Za-z0-9_./-]+)", html):
        add("/" + m)

dedup = []
seen = set()
for u in urls:
    if u in seen:
        continue
    seen.add(u)
    dedup.append(u)
urls = dedup[:80]

if not urls:
    print("ERROR: no image URLs discovered on sampled pages")
    sys.exit(1)

print(f"==> Checking {len(urls)} image URLs across {len(pages)} pages")
fail = 0
ok = 0
for url in urls:
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "dokannward-image-assert"})
        with urllib.request.urlopen(req, context=ctx, timeout=20) as r:
            body = r.read()
            st = r.status
    except Exception as e:
        st = getattr(e, "code", None) or 0
        body = b""
        print(f"FAIL {st or e} {url[:160]}")
        fail += 1
        continue
    if st < 200 or st >= 300 or len(body) < 200:
        print(f"FAIL {st} bytes={len(body)} {url[:160]}")
        fail += 1
    else:
        ok += 1

print(f"==> ok={ok} fail={fail}")
if fail:
    sys.exit(1)
print("Image assert OK")
PY
