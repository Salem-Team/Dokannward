# Dokan Ward

Professional home-décor storefront (Next.js + Laravel admin), branded for **Dokan Ward**.

Live catalog source: [dokannward.com](https://dokannward.com)

## Stack

| Layer | Path |
|---|---|
| Storefront | `src/` (Next.js 15) |
| Admin / API | `admin/` (Laravel) |
| Scraped catalog | `scrape/dokannward/` |
| Nginx / PHP deploy | `deploy/` |

## Brand

- Logo: `public/images/dokan-ward-logo.png`
- Palette: cream `#FEFDF9`, bronze `#B49480`, ink brown `#3A2A1A`
- Display: Cormorant Garamond · UI: Outfit

## Local development

```bash
# API (SQLite)
cd admin
composer install
php artisan migrate
php artisan db:seed --class=Database\\Seeders\\DokanWardCatalogSeeder
php artisan serve --host=127.0.0.1 --port=8001

# Storefront
cd ..
cp .env.example .env.local
npm install
npm run dev
```

## Production deploy

Server path: `/var/www/dokannward` · Domain: `dokannward.com` · PM2: `dokannward-storefront`

1. Copy env from examples on the server only (never commit secrets):
   - `.env.production`
   - `admin/.env`
2. Install nginx:

```bash
sudo cp deploy/nginx/dokannward-app.conf /etc/nginx/snippets/dokannward-app.conf
sudo cp deploy/nginx/sites/dokannward.com.conf /etc/nginx/sites-available/dokannward.com
sudo ln -sfn /etc/nginx/sites-available/dokannward.com /etc/nginx/sites-enabled/dokannward.com
sudo nginx -t && sudo systemctl reload nginx
```

3. From a machine with SSH + git remotes:

```bash
# optional overrides: DOKANWARD_SSH_HOST / DOKANWARD_REMOTE_PATH
./scripts/push-deploy.sh
```

Or on the VPS after `git pull`:

```bash
./scripts/deploy.sh
# or
./scripts/rebuild-production.sh
```

## Catalog re-import

```bash
php scripts/import-dokannward-catalog.php
# or
cd admin && php artisan db:seed --class=Database\\Seeders\\DokanWardCatalogSeeder
```

## Contact

Hotline: 01069503631 · Instagram: [@dokan_ward_96](https://www.instagram.com/dokan_ward_96/)
