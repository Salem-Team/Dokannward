<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Brand>
 */
class BrandFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $luxBrands = [
            [
                'en' => 'Gucci',
                'ar' => 'غوتشي',
                'desc_en' => 'Italian luxury brand known for exceptional craftsmanship.',
                'desc_ar' => 'علامة إيطالية فاخرة معروفة بالحرفية الاستثنائية.',
                'country' => 'Italy',
                'logo' => 'https://logo.clearbit.com/gucci.com',
            ],[
                'en' => 'Prada',
                'ar' => 'برادا',
                'desc_en' => 'Modern elegance with classic inspiration.',
                'desc_ar' => 'أناقة عصرية بإلهام كلاسيكي.',
                'country' => 'Italy',
                'logo' => 'https://logo.clearbit.com/prada.com',
            ],[
                'en' => 'Louis Vuitton',
                'ar' => 'لويس فيتون',
                'desc_en' => 'Signature monogram and elite designs.',
                'desc_ar' => 'تصاميم راقية وتوقيع المونوغرام.',
                'country' => 'France',
                'logo' => 'https://logo.clearbit.com/louisvuitton.com',
            ],[
                'en' => 'Chloé',
                'ar' => 'كلوي',
                'desc_en' => 'Refined Parisian femininity.',
                'desc_ar' => 'أنوثة باريسية راقية.',
                'country' => 'France',
                'logo' => 'https://logo.clearbit.com/chloe.com',
            ],[
                'en' => 'Fendi',
                'ar' => 'فيندي',
                'desc_en' => 'Famous for baguette and exceptional leather.',
                'desc_ar' => 'مشهور بحقائب باغيت والجلد الفاخر.',
                'country' => 'Italy',
                'logo' => 'https://logo.clearbit.com/fendi.com',
            ],
        ];
        $luxBrand = $this->faker->randomElement($luxBrands);
        return [
            'name' => ['en' => $luxBrand['en'], 'ar' => $luxBrand['ar']],
            'description' => ['en' => $luxBrand['desc_en'], 'ar' => $luxBrand['desc_ar']],
            'country' => $luxBrand['country'],
            'logo_url' => $luxBrand['logo'],
        ];
    }
}
