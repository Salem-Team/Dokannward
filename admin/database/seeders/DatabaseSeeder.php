<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PlatformBrandSeeder::class);

        $adminEmail = strtolower((string) env('ADMIN_SEED_EMAIL', 'admin@example.com'));
        $adminPassword = (string) env('ADMIN_SEED_PASSWORD', '');

        if ($adminPassword === '' || strlen($adminPassword) < 12) {
            // Safe local default that still meets Password::defaults(); override in .env for real deploys.
            $adminPassword = 'ChangeMe!Admin#2026';
        }

        User::query()->updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => env('ADMIN_SEED_NAME', 'Store Admin'),
                'normalized_email' => $adminEmail,
                'password' => $adminPassword,
                'is_admin' => true,
                'is_active' => true,
                'is_guest' => false,
                'email_verified_at' => now(),
            ]
        );

        // Ensure is_admin sticks even if fillable omits it.
        User::query()->where('email', $adminEmail)->update(['is_admin' => true]);

        $brandsData = [
            [
                'name' => ['en' => 'Gucci', 'ar' => 'غوتشي'],
                'description' => ['en' => 'Italian luxury brand known for exceptional craftsmanship.', 'ar' => 'علامة إيطالية فاخرة معروفة بالحرفية الاستثنائية.'],
                'country' => 'Italy',
                'logo_url' => 'https://logo.clearbit.com/gucci.com',
            ], [
                'name' => ['en' => 'Prada', 'ar' => 'برادا'],
                'description' => ['en' => 'Modern elegance with classic inspiration.', 'ar' => 'أناقة عصرية بإلهام كلاسيكي.'],
                'country' => 'Italy',
                'logo_url' => 'https://logo.clearbit.com/prada.com',
            ], [
                'name' => ['en' => 'Louis Vuitton', 'ar' => 'لويس فيتون'],
                'description' => ['en' => 'Signature monogram and elite designs.', 'ar' => 'تصاميم راقية وتوقيع المونوغرام.'],
                'country' => 'France',
                'logo_url' => 'https://logo.clearbit.com/louisvuitton.com',
            ], [
                'name' => ['en' => 'Chloé', 'ar' => 'كلوي'],
                'description' => ['en' => 'Refined Parisian femininity.', 'ar' => 'أنوثة باريسية راقية.'],
                'country' => 'France',
                'logo_url' => 'https://logo.clearbit.com/chloe.com',
            ], [
                'name' => ['en' => 'Fendi', 'ar' => 'فيندي'],
                'description' => ['en' => 'Famous for baguette and exceptional leather.', 'ar' => 'مشهور بحقائب باغيت والجلد الفاخر.'],
                'country' => 'Italy',
                'logo_url' => 'https://logo.clearbit.com/fendi.com',
            ],
        ];
        $brands = [];
        foreach ($brandsData as $b) {
            $b['id'] = (string) Str::uuid();
            $brands[] = Brand::create($b);
        }

        $categoriesData = [
            ['name' => ['en' => 'Clutch', 'ar' => 'كلاتش'], 'slug' => ['en' => 'clutch', 'ar' => 'كلاتش']],
            ['name' => ['en' => 'Tote', 'ar' => 'توت'], 'slug' => ['en' => 'tote', 'ar' => 'توت']],
            ['name' => ['en' => 'Crossbody', 'ar' => 'كروس بودي'], 'slug' => ['en' => 'crossbody', 'ar' => 'كروس-بودي']],
            ['name' => ['en' => 'Backpack', 'ar' => 'حقيبة ظهر'], 'slug' => ['en' => 'backpack', 'ar' => 'حقيبة-ظهر']],
            ['name' => ['en' => 'Mini Bag', 'ar' => 'حقيبة صغيرة'], 'slug' => ['en' => 'mini-bag', 'ar' => 'حقيبة-صغيرة']],
        ];
        foreach ($categoriesData as $c) {
            $c['id'] = (string) Str::uuid();
            Category::create($c);
        }
    }
}
