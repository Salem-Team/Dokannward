<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use Illuminate\Support\Str;

class UpdateProductsCommand extends Command
{
    protected $signature = 'products:update-missing-fields';
    protected $description = 'Update products with missing SKU, slug, and other fields';

    public function handle()
    {
        $products = Product::all();
        $this->info('Updating ' . $products->count() . ' products...');
        $count = 0;

        foreach ($products as $product) {
            $updates = [];

            if (empty($product->sku)) {
                $updates['sku'] = 'SKU-' . strtoupper(substr($product->id, 0, 8));
            }

            if (empty($product->slug)) {
                $nameEn = $product->name['en'] ?? 'product';
                $updates['slug'] = Str::slug($nameEn) . '-' . substr($product->id, 0, 4);
            }

            if (empty($product->short_description)) {
                $descEn = $product->description['en'] ?? '';
                $updates['short_description'] = substr($descEn, 0, 150);
            }

            if ($product->price && (empty($product->price_min) || empty($product->price_max))) {
                $updates['price_min'] = $product->price * 0.9;
                $updates['price_max'] = $product->price * 1.1;
            }

            if (empty($product->weight_kg)) {
                $updates['weight_kg'] = round(rand(300, 2500) / 1000, 3);
            }

            if (!empty($updates)) {
                $product->update($updates);
                $count++;
            }
        }

        $this->info('Updated ' . $count . ' products with missing fields!');
        return 0;
    }
}
