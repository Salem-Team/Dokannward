<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $names = [
            ['en' => 'Bamboo Tote', 'ar' => 'حقيبة بامبو توت'],
            ['en' => 'GG Marmont Matelassé', 'ar' => 'جي جي ماغمونت مطرز'],
            ['en' => 'Dionysus Shoulder', 'ar' => 'ديونيسوس شوالدر'],
            ['en' => 'Prada Cleo', 'ar' => 'برادا كليو'],
            ['en' => 'LV Speedy', 'ar' => 'لويس فيتون سبيدي'],
            ['en' => 'Fendi Baguette', 'ar' => 'فيندي باغيت'],
            ['en' => 'Mini Chloé', 'ar' => 'كلوي ميني'],
        ];
        $desc = [
            'en' => $this->faker->sentence(14),
            'ar' => 'اختيار فاخر مناسب للعرض اليومي أو المناسبات المميزة.',
        ];

        $luxColors = ['Taupe', 'Navy', 'Ivory', 'Camel', 'Gold', 'Champagne', 'Powder Blue'];
        $luxMaterials = ['Italian Leather', 'Lambskin', 'Canvas', 'Croc Embossed', 'Suede', 'Patent'];

        $selectedName = $this->faker->randomElement($names);
        $basePrice = $this->faker->randomFloat(2, 900, 17500);

        return [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'sku' => 'SKU-' . strtoupper($this->faker->bothify('???-###')),
            'name' => $selectedName,
            'slug' => \Illuminate\Support\Str::slug($selectedName['en']) . '-' . $this->faker->unique()->randomNumber(4),
            'description' => $desc,
            'short_description' => $this->faker->sentence(8),
            'brand_id' => \App\Models\Brand::inRandomOrder()->first()?->id ?? 1,
            'category_id' => \App\Models\Category::inRandomOrder()->first()?->id ?? 1,
            'status' => 'active',
            'visibility' => true,
            'price' => $basePrice,
            'price_min' => $basePrice * 0.9,
            'price_max' => $basePrice * 1.1,
            'color' => $this->faker->randomElement($luxColors),
            'material' => $this->faker->randomElement($luxMaterials),
            'metadata' => [
                'designer' => $this->faker->name(),
                'season' => $this->faker->randomElement(['Spring', 'Summer', 'Fall', 'Winter']) . ' ' . $this->faker->year(),
            ],
            'featured' => $this->faker->boolean(20),
            'in_stock' => $this->faker->boolean(90),
            'is_limited_edition' => $this->faker->boolean(10),
        ];
    }
}
