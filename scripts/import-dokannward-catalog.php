<?php

/**
 * Import Dokan Ward catalog from scrape/dokannward/data/catalog.json into the admin DB.
 *
 * Usage (from project root):
 *   php scripts/import-dokannward-catalog.php
 *
 * Or via artisan:
 *   cd admin && php artisan db:seed --class=Database\\Seeders\\DokanWardCatalogSeeder
 */

passthru(
    'cd '.escapeshellarg(__DIR__.'/../admin').
    ' && php artisan db:seed --class=Database\\\\Seeders\\\\DokanWardCatalogSeeder',
    $code
);
exit($code);
