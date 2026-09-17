<?php

namespace Database\Seeders;

use App\Models\PlatformBrand;
use App\Support\Branding;
use Illuminate\Database\Seeder;

class PlatformBrandSeeder extends Seeder
{
    public function run(): void
    {
        $slug = (string) config('branding.slug', 'default');

        PlatformBrand::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => config('branding.company_name', 'Default Company'),
                'is_default' => true,
                'company_name' => config('branding.company_name'),
                'company_name_ar' => config('branding.company_name_ar'),
                'company_short_name' => config('branding.company_short_name'),
                'company_short_name_ar' => config('branding.company_short_name_ar'),
                'portal_name' => config('branding.portal_name'),
                'portal_name_ar' => config('branding.portal_name_ar'),
                'logo_light_path' => config('branding.logo_light'),
                'logo_dark_path' => config('branding.logo_dark'),
                'logo_mark_path' => config('branding.logo_mark'),
                'favicon_path' => config('branding.favicon'),
                'og_image_path' => config('branding.og_image'),
                'primary_color' => config('branding.primary_color'),
                'secondary_color' => config('branding.secondary_color'),
                'accent_color' => config('branding.accent_color'),
                'primary_hover' => config('branding.primary_hover'),
                'theme_color_light' => config('branding.theme_color_light'),
                'theme_color_dark' => config('branding.theme_color_dark'),
                'mobile_splash_color' => config('branding.mobile_splash_color'),
                'website_title' => config('branding.website_title'),
                'website_title_ar' => config('branding.website_title_ar'),
                'website_description' => config('branding.website_description'),
                'website_description_ar' => config('branding.website_description_ar'),
                'properties_title_suffix' => config('branding.properties_title_suffix'),
                'properties_title_suffix_ar' => config('branding.properties_title_suffix_ar'),
                'email_from_name' => config('branding.email_from_name'),
                'email_from_address' => config('branding.email_from_address'),
                'push_title' => config('branding.push_title'),
                'investor_portal_subject_prefix' => config('branding.investor_portal_subject_prefix'),
                'pdf_logo_path' => config('branding.pdf_logo'),
                'pdf_footer' => config('branding.pdf_footer'),
                'export_prefix' => config('branding.export_prefix'),
                'mobile_app_name' => config('branding.mobile_app_name'),
                'mobile_package' => config('branding.mobile_package'),
                'show_developer_credit' => config('branding.show_developer_credit'),
            ]
        );

        Branding::bustCache($slug);
    }
}
