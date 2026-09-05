# Dokan Ward media scrape

**Source:** https://dokannward.com  
**Updated:** 2026-09-04  

## Products
- **34** products (full WooCommerce Store API sync)
- Descriptions keep paragraph breaks from the live short description
- **14** products on sale with `compare_at_price`
- Original store SKUs preserved (e.g. `0659`)
- **71** product images → `public/images/products/` + admin storage `products/dokannward/`
- All gallery frames downloaded (23 products have 2+ images)
- **5** products have no description on the live site either

## Categories
- **15** categories (including empty shop shelves) → `public/images/categories/`
- Live slug `artificial-plants` mapped to `small-artificial-plants`

## Import
```bash
cd admin && php artisan db:seed --class=Database\\Seeders\\DokanWardCatalogSeeder
```
