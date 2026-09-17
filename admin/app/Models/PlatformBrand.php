<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-editable platform brand row.
 *
 * ROOTK's injected `.rootk/.platform-brand-sync.php` historically assigns a
 * simplified attribute set (`display_name`, `logo_light`, `colors`, `domain`…).
 * Those keys are remapped here onto the Sidra schema so sweep/install never
 * throws SQLSTATE[42S22] Unknown column 'display_name'.
 */
class PlatformBrand extends Model
{
    /**
     * ROOTK / generic white-label attribute names → Sidra columns.
     *
     * @var array<string, string>
     */
    public const ROOTK_ATTRIBUTE_MAP = [
        'display_name' => 'company_name',
        'display_name_ar' => 'company_name_ar',
        'short_name' => 'company_short_name',
        'short_name_ar' => 'company_short_name_ar',
        'logo_light' => 'logo_light_path',
        'logo_dark' => 'logo_dark_path',
        'logo_mark' => 'logo_mark_path',
        'favicon' => 'favicon_path',
        'og_image' => 'og_image_path',
        'pdf_logo' => 'pdf_logo_path',
    ];

    /** @var list<string> */
    private const COLOR_COLUMNS = [
        'primary_color',
        'secondary_color',
        'accent_color',
        'primary_hover',
        'theme_color_light',
        'theme_color_dark',
        'mobile_splash_color',
    ];

    protected $fillable = [
        'slug',
        'name',
        'is_default',
        'company_name',
        'company_name_ar',
        'company_short_name',
        'company_short_name_ar',
        'portal_name',
        'portal_name_ar',
        'logo_light_path',
        'logo_dark_path',
        'logo_mark_path',
        'favicon_path',
        'og_image_path',
        'primary_color',
        'secondary_color',
        'accent_color',
        'primary_hover',
        'theme_color_light',
        'theme_color_dark',
        'mobile_splash_color',
        'website_title',
        'website_title_ar',
        'website_description',
        'website_description_ar',
        'properties_title_suffix',
        'properties_title_suffix_ar',
        'email_from_name',
        'email_from_address',
        'push_title',
        'investor_portal_subject_prefix',
        'pdf_logo_path',
        'pdf_footer',
        'export_prefix',
        'mobile_app_name',
        'mobile_package',
        'show_developer_credit',
        // ROOTK alias keys (remapped in setAttribute — never persisted as-is)
        'display_name',
        'display_name_ar',
        'short_name',
        'short_name_ar',
        'logo_light',
        'logo_dark',
        'logo_mark',
        'favicon',
        'og_image',
        'pdf_logo',
        'colors',
        'domain',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'show_developer_credit' => 'boolean',
    ];

    private ?string $pendingRootkDomain = null;

    protected static function booted(): void
    {
        static::saved(function (PlatformBrand $brand): void {
            $brand->persistPendingRootkDomain();
        });
    }

    public function domains(): HasMany
    {
        return $this->hasMany(PlatformBrandDomain::class);
    }

    /**
     * @param  mixed  $value
     */
    public function setAttribute($key, $value)
    {
        if ($key === 'colors') {
            $this->applyRootkColorsPayload($value);

            return $this;
        }

        if ($key === 'domain') {
            $host = is_string($value) ? trim($value) : '';
            $this->pendingRootkDomain = $host !== '' ? $host : null;

            return $this;
        }

        $mappedKey = self::ROOTK_ATTRIBUTE_MAP[$key] ?? $key;

        return parent::setAttribute($mappedKey, $value);
    }

    /**
     * Accept ROOTK colors JSON / associative array without a physical `colors` column.
     *
     * @param  mixed  $value
     */
    private function applyRootkColorsPayload(mixed $value): void
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value)) {
            return;
        }

        $aliases = [
            'primary' => 'primary_color',
            'secondary' => 'secondary_color',
            'accent' => 'accent_color',
            'primary_hover' => 'primary_hover',
            'theme_color_light' => 'theme_color_light',
            'theme_color_dark' => 'theme_color_dark',
            'mobile_splash_color' => 'mobile_splash_color',
            'brand_primary' => 'primary_color',
            'brand_primary_hover' => 'primary_hover',
            'brand_accent' => 'accent_color',
            'theme_color_meta' => 'theme_color_light',
        ];

        foreach ($value as $rawKey => $rawColor) {
            if (! is_string($rawKey) || (! is_string($rawColor) && ! is_numeric($rawColor))) {
                continue;
            }

            $normalizedKey = ltrim(strtolower($rawKey), '-');
            $normalizedKey = str_replace(['--', ' '], ['', '_'], $normalizedKey);
            $column = $aliases[$normalizedKey] ?? (in_array($normalizedKey, self::COLOR_COLUMNS, true) ? $normalizedKey : null);

            if ($column === null) {
                continue;
            }

            parent::setAttribute($column, (string) $rawColor);
        }
    }

    private function persistPendingRootkDomain(): void
    {
        $host = $this->pendingRootkDomain;
        $this->pendingRootkDomain = null;

        if ($host === null || $host === '') {
            return;
        }

        if (! Schema::hasTable('platform_brand_domains')) {
            return;
        }

        PlatformBrandDomain::query()->updateOrCreate(
            ['host' => $host],
            ['platform_brand_id' => $this->id]
        );
    }
}
