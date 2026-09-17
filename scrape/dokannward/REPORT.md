# Dokan Ward media scrape

**Source:** https://dokannward.com
**Updated:** 2026-09-17

## Products
- **34** products (WooCommerce Store API)
- **14** products on sale
- **71** product images → `public/images/products/`

## Categories
- **11** categories → `public/images/categories/`

## Brand
- Hotline: 01069503631
- Instagram: https://www.instagram.com/dokan_ward_96/
- Logo: `/images/dokan-ward-logo.png` (from live Welcome-card)

## Import
```bash
cd admin && php artisan db:seed --class=Database\\Seeders\\DokanWardCatalogSeeder
# or
php scripts/import-dokannward-catalog.php
```
