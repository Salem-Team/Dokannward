<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cats = [
            [
                'en' => 'Clutch',
                'ar' => 'كلاتش',
            ],[
                'en' => 'Tote',
                'ar' => 'توت',
            ],[
                'en' => 'Crossbody',
                'ar' => 'كروس بودي',
            ],[
                'en' => 'Backpack',
                'ar' => 'حقيبة ظهر',
            ],[
                'en' => 'Mini Bag',
                'ar' => 'حقيبة صغيرة',
            ]
        ];
        $cat = $this->faker->randomElement($cats);
        return [
            'name' => ['en' => $cat['en'], 'ar' => $cat['ar']],
            'slug' => ['en' => strtolower(str_replace(' ', '-', $cat['en'])), 'ar' => strtolower(str_replace(' ', '-', $cat['ar']))],
            'is_featured' => true,
            'position' => 0,
        ];
    }
}
