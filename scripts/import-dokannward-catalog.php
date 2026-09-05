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

require __DIR__.'/../admin/vendor/autoload.php';
$app = require __DIR__.'/../admin/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$seeder = new Database\Seeders\DokanWardCatalogSeeder;
$seeder->setCommand(new class
{
    public function info($m): void
    {
        echo $m.PHP_EOL;
    }

    public function error($m): void
    {
        fwrite(STDERR, $m.PHP_EOL);
    }

    public function warn($m): void
    {
        echo "WARN: {$m}".PHP_EOL;
    }
});
$seeder->run();
