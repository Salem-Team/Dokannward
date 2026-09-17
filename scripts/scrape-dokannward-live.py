#!/usr/bin/env python3
"""Refresh Dokan Ward catalog + brand assets from https://dokannward.com."""

from __future__ import annotations

import json
import re
import ssl
import time
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timezone
from html import unescape
from pathlib import Path

BASE = "https://dokannward.com"
ROOT = Path(__file__).resolve().parents[1]
SCRAPE = ROOT / "scrape" / "dokannward"
RAW = SCRAPE / "raw"
DATA = SCRAPE / "data"
ASSETS_PRODUCTS = SCRAPE / "assets" / "products"
ASSETS_CATS = SCRAPE / "assets" / "categories"
PUBLIC_PRODUCTS = ROOT / "public" / "images" / "products"
PUBLIC_CATS = ROOT / "public" / "images" / "categories"
PUBLIC_IMAGES = ROOT / "public" / "images"
UA = "Mozilla/5.0 DokanWardScraper/1.1"

# Live WooCommerce slug → internal catalog slug used by the seeder/UI.
SLUG_ALIASES = {
    "artificial-plants": "small-artificial-plants",
}

CATEGORY_AR = {
    "bakhoor-burners": "بخور ومباخر",
    "boho-style": "ستايل بوهيمي",
    "candle-holder": "حامل شموع",
    "decoration-products": "منتجات ديكور",
    "diffusers": "فواحات",
    "flowers": "ورود",
    "lamps": "إضاءة",
    "ramadan-products": "منتجات رمضان",
    "sculpture": "منحوتات",
    "small-artificial-plants": "نباتات صناعية",
    "tissue-boxes": "علب مناديل",
    "trays": "صواني",
    "trees": "أشجار",
    "vases": "فازات",
    "wall-art-clocks": "لوحات وساعات",
}

CTX = ssl.create_default_context()


def fetch(url: str, retries: int = 3) -> bytes:
    last: Exception | None = None
    for attempt in range(retries):
        try:
            req = urllib.request.Request(url, headers={"User-Agent": UA, "Accept": "*/*"})
            with urllib.request.urlopen(req, context=CTX, timeout=60) as resp:
                return resp.read()
        except Exception as exc:  # noqa: BLE001
            last = exc
            time.sleep(1.5 * (attempt + 1))
    raise RuntimeError(f"Failed to fetch {url}: {last}")


def fetch_json(url: str):
    return json.loads(fetch(url).decode("utf-8"))


def strip_html(value: str) -> str:
    text = re.sub(r"<br\s*/?>", "\n", value or "", flags=re.I)
    text = re.sub(r"</p\s*>", "\n\n", text, flags=re.I)
    text = re.sub(r"<[^>]+>", "", text)
    text = unescape(text)
    text = re.sub(r"[ \t]+\n", "\n", text)
    text = re.sub(r"\n{3,}", "\n\n", text)
    return text.strip()


def money(prices: dict) -> tuple[float, float, float, bool]:
    minor = int(prices.get("currency_minor_unit") or 2)
    div = 10**minor

    def num(key: str) -> float:
        raw = prices.get(key) or "0"
        try:
            return float(raw) / div
        except (TypeError, ValueError):
            return 0.0

    regular = num("regular_price")
    sale = num("sale_price")
    price = num("price")
    on_sale = bool(sale and regular and sale < regular)
    compare = regular if on_sale else None
    return price or sale or regular, compare or 0.0, regular, on_sale


def ext_from_url(url: str) -> str:
    path = urllib.parse.urlparse(url).path
    suffix = Path(path).suffix.lower()
    if suffix in {".jpg", ".jpeg", ".png", ".webp", ".gif", ".svg"}:
        return suffix
    return ".jpg"


def download(url: str, dest: Path) -> bool:
    if not url:
        return False
    dest.parent.mkdir(parents=True, exist_ok=True)
    try:
        data = fetch(url)
    except Exception as exc:  # noqa: BLE001
        print(f"  WARN image fail {url}: {exc}")
        return False
    dest.write_bytes(data)
    return True


def paginate(endpoint: str, per_page: int = 100) -> list:
    items: list = []
    page = 1
    while True:
        url = f"{BASE}/wp-json/wc/store/v1/{endpoint}?per_page={per_page}&page={page}"
        batch = fetch_json(url)
        if not isinstance(batch, list) or not batch:
            break
        items.extend(batch)
        if len(batch) < per_page:
            break
        page += 1
    return items


def normalize_category_slug(slug: str) -> str:
    slug = (slug or "").strip().lower()
    return SLUG_ALIASES.get(slug, slug)


def main() -> None:
    for path in (RAW, DATA, ASSETS_PRODUCTS, ASSETS_CATS, PUBLIC_PRODUCTS, PUBLIC_CATS, PUBLIC_IMAGES):
        path.mkdir(parents=True, exist_ok=True)

    print("Fetching categories…")
    categories_raw = paginate("products/categories")
    (RAW / "categories.json").write_text(
        json.dumps(categories_raw, ensure_ascii=False, indent=2), encoding="utf-8"
    )

    print("Fetching products…")
    products_raw = paginate("products")
    (RAW / "products-store.json").write_text(
        json.dumps(products_raw, ensure_ascii=False, indent=2), encoding="utf-8"
    )

    # Brand / logo from homepage
    print("Fetching homepage for brand assets…")
    home_html = fetch(f"{BASE}/").decode("utf-8", errors="replace")
    (RAW / "homepage.html").write_text(home_html, encoding="utf-8")

    logo_candidates = [
        f"{BASE}/wp-content/uploads/2025/07/Welcome-card-.png",
        f"{BASE}/wp-content/uploads/2025/07/Welcome-card-1-2.png",
    ]
    for m in re.findall(
        r"https://dokannward\.com/wp-content/uploads/[^\"'\s]+Welcome-card-\.png",
        home_html,
    ):
        if m not in logo_candidates:
            logo_candidates.insert(0, m)

    logo_ok = False
    for logo_url in logo_candidates:
        dest = PUBLIC_IMAGES / "dokan-ward-logo.png"
        if download(logo_url, dest):
            # transparent copy for dark backgrounds when source has alpha
            (PUBLIC_IMAGES / "dokan-ward-logo-transparent.png").write_bytes(dest.read_bytes())
            print(f"Logo saved from {logo_url}")
            logo_ok = True
            break
    if not logo_ok:
        print("WARN: could not download live logo")

    favicon_url = f"{BASE}/wp-content/uploads/2025/07/cropped-Welcome-card--192x192.png"
    download(favicon_url, ROOT / "public" / "apple-icon.png")
    download(favicon_url, ROOT / "public" / "icon-192.png")
    download(
        f"{BASE}/wp-content/uploads/2025/07/cropped-Welcome-card--32x32.png",
        ROOT / "public" / "favicon-32x32.png",
    )

    hotline_match = re.search(r"01\d{8,10}", home_html)
    hotline = hotline_match.group(0) if hotline_match else "01069503631"
    ig = "https://www.instagram.com/dokan_ward_96/"
    if "instagram.com/dokan_ward_96" not in home_html:
        m = re.search(r"https?://(?:www\.)?instagram\.com/[^\s\"']+", home_html)
        if m:
            ig = m.group(0).rstrip("/") + "/"

    categories = []
    for cat in categories_raw:
        src_slug = (cat.get("slug") or "").strip()
        if not src_slug or src_slug in {"uncategorized"}:
            continue
        slug = normalize_category_slug(src_slug)
        name = unescape(strip_html(cat.get("name") or slug))
        image_url = ((cat.get("image") or {}) or {}).get("src") or ""
        local_image = ""
        if image_url:
            ext = ext_from_url(image_url)
            fname = f"{slug}{ext}"
            asset = ASSETS_CATS / fname
            public = PUBLIC_CATS / fname
            if download(image_url, asset):
                public.write_bytes(asset.read_bytes())
                local_image = f"/images/categories/{fname}"
        categories.append(
            {
                "id": cat.get("id"),
                "name": name,
                "slug": slug,
                "source_slug": src_slug,
                "parent": cat.get("parent") or 0,
                "count": cat.get("count") or 0,
                "description": strip_html(cat.get("description") or ""),
                "image": local_image,
                "name_ar": CATEGORY_AR.get(slug, name),
            }
        )

    products = []
    for item in products_raw:
        slug = (item.get("slug") or "").strip()
        if not slug:
            continue
        prices = item.get("prices") or {}
        price, compare_at, regular, on_sale = money(prices)
        short = strip_html(item.get("short_description") or "")
        long = strip_html(item.get("description") or "")
        cats = []
        for c in item.get("categories") or []:
            cslug = normalize_category_slug(c.get("slug") or "")
            if not cslug:
                continue
            cats.append(
                {
                    "id": c.get("id"),
                    "name": unescape(strip_html(c.get("name") or cslug)),
                    "slug": cslug,
                    "source_slug": (c.get("slug") or "").strip(),
                }
            )

        images_local = []
        for idx, img in enumerate(item.get("images") or []):
            src = img.get("src") or ""
            if not src:
                continue
            ext = ext_from_url(src)
            fname = f"{item.get('id')}-{idx}{ext}"
            asset = ASSETS_PRODUCTS / fname
            public = PUBLIC_PRODUCTS / fname
            if download(src, asset):
                public.write_bytes(asset.read_bytes())
                images_local.append(f"/images/products/{fname}")

        products.append(
            {
                "id": item.get("id"),
                "name": unescape(item.get("name") or slug),
                "slug": slug,
                "sku": (item.get("sku") or "").strip() or f"DW-{item.get('id')}",
                "type": item.get("type") or "simple",
                "permalink": item.get("permalink") or "",
                "short_description": short,
                "description": long or short,
                "price": price,
                "regular_price": regular,
                "compare_at_price": compare_at if on_sale else None,
                "on_sale": on_sale,
                "categories": cats,
                "images": images_local,
                "in_stock": bool(item.get("is_in_stock", True)),
                "image": images_local[0] if images_local else "",
                "average_rating": item.get("average_rating") or "0",
                "review_count": item.get("review_count") or 0,
            }
        )

    catalog = {
        "source": BASE,
        "scraped_at": datetime.now(timezone.utc).isoformat(),
        "brand": {
            "name": "Dokan Ward",
            "name_ar": "دكان ورد",
            "url": BASE,
            "hotline": hotline,
            "phone": f"+2{hotline}" if hotline.startswith("0") else hotline,
            "instagram": ig,
            "logo": "/images/dokan-ward-logo.png",
            "colors": {
                "primary": "#532904",
                "secondary": "#debcad",
                "ink": "#333333",
                "muted": "#777777",
                "surface": "#f7f7f7",
            },
        },
        "categories": categories,
        "products": products,
    }
    out = DATA / "catalog.json"
    out.write_text(json.dumps(catalog, ensure_ascii=False, indent=2), encoding="utf-8")

    report = f"""# Dokan Ward media scrape

**Source:** {BASE}
**Updated:** {catalog['scraped_at'][:10]}

## Products
- **{len(products)}** products (WooCommerce Store API)
- **{sum(1 for p in products if p.get('on_sale'))}** products on sale
- **{sum(len(p['images']) for p in products)}** product images → `public/images/products/`

## Categories
- **{len(categories)}** categories → `public/images/categories/`

## Brand
- Hotline: {hotline}
- Instagram: {ig}
- Logo: `/images/dokan-ward-logo.png` (from live Welcome-card)

## Import
```bash
cd admin && php artisan db:seed --class=Database\\\\Seeders\\\\DokanWardCatalogSeeder
# or
php scripts/import-dokannward-catalog.php
```
"""
    (SCRAPE / "REPORT.md").write_text(report, encoding="utf-8")
    print(
        f"Done: {len(products)} products, {len(categories)} categories → {out}"
    )


if __name__ == "__main__":
    main()
