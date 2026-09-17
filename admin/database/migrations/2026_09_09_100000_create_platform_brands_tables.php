<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_brands', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name')->comment('Admin label');
            $table->boolean('is_default')->default(false);

            $table->string('company_name')->nullable();
            $table->string('company_name_ar')->nullable();
            $table->string('company_short_name')->nullable();
            $table->string('company_short_name_ar')->nullable();
            $table->string('portal_name')->nullable();
            $table->string('portal_name_ar')->nullable();

            $table->string('logo_light_path')->nullable();
            $table->string('logo_dark_path')->nullable();
            $table->string('logo_mark_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('og_image_path')->nullable();

            $table->string('primary_color', 16)->nullable();
            $table->string('secondary_color', 16)->nullable();
            $table->string('accent_color', 16)->nullable();
            $table->string('primary_hover', 16)->nullable();
            $table->string('theme_color_light', 16)->nullable();
            $table->string('theme_color_dark', 16)->nullable();
            $table->string('mobile_splash_color', 16)->nullable();

            $table->string('website_title')->nullable();
            $table->string('website_title_ar')->nullable();
            $table->text('website_description')->nullable();
            $table->text('website_description_ar')->nullable();
            $table->string('properties_title_suffix')->nullable();
            $table->string('properties_title_suffix_ar')->nullable();

            $table->string('email_from_name')->nullable();
            $table->string('email_from_address')->nullable();
            $table->string('push_title')->nullable();
            $table->string('investor_portal_subject_prefix')->nullable();

            $table->string('pdf_logo_path')->nullable();
            $table->text('pdf_footer')->nullable();
            $table->string('export_prefix')->nullable();

            $table->string('mobile_app_name')->nullable();
            $table->string('mobile_package', 128)->nullable();

            $table->boolean('show_developer_credit')->default(true);

            $table->timestamps();
        });

        Schema::create('platform_brand_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_brand_id')->constrained('platform_brands')->cascadeOnDelete();
            $table->string('host', 255)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_brand_domains');
        Schema::dropIfExists('platform_brands');
    }
};
