<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Ensures the homepage CTA plates exist in Admin → Banners.
 * Idempotent: only inserts when the banners table is empty.
 */
class HomepageBannerSeeder extends Seeder
{
    public function run(): void
    {
        if (Banner::query()->exists()) {
            return;
        }

        $plates = [
            [
                'title' => 'curated essentials.',
                'subtitle' => 'Each bag and shoe embodies balance—functional beauty crafted for your rhythm.',
                'button_text' => 'Shop now',
                'button_url' => '/collections/all',
                'image_url' => '/images/handbags-and-accessories-natural-3.jpg',
                'position' => 0,
            ],
            [
                'title' => 'timeless design',
                'subtitle' => 'crafted to transcend fleeting trends',
                'button_text' => 'Shop now',
                'button_url' => '/collections/all',
                'image_url' => '/images/handbags-and-accessories-natural-2.jpg',
                'position' => 1,
            ],
        ];

        foreach ($plates as $plate) {
            Banner::create([
                'id' => (string) Str::uuid(),
                'title' => $plate['title'],
                'subtitle' => $plate['subtitle'],
                'button_text' => $plate['button_text'],
                'button_url' => $plate['button_url'],
                'image_url' => $plate['image_url'],
                'is_active' => true,
                'position' => $plate['position'],
            ]);
        }
    }
}
