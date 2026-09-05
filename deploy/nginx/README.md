# Production nginx for dokannward.com

Shared location blocks: `dokannward-app.conf` (included by the site config).
Full vhost (SSL + www redirect): `sites/dokannward.com.conf`

Apply on the VPS:

```bash
sudo cp deploy/nginx/dokannward-app.conf /etc/nginx/snippets/dokannward-app.conf
sudo cp deploy/nginx/sites/dokannward.com.conf /etc/nginx/sites-available/dokannward.com
sudo ln -sfn /etc/nginx/sites-available/dokannward.com /etc/nginx/sites-enabled/dokannward.com
sudo certbot certonly --webroot -w /var/www/certbot -d dokannward.com -d www.dokannward.com --expand
sudo nginx -t && sudo systemctl reload nginx
```

App path on server: `/var/www/dokannward`
Next.js PM2 process: `dokannward-storefront` on port `3010`
Laravel public: `/var/www/dokannward/admin/public`

Exact Next.js routes under `/api/*` must be listed in `dokannward-app.conf`
(before Laravel's `location ^~ /api/`), otherwise nginx sends them to Laravel.
