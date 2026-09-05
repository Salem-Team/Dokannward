<?php

namespace App\Support;

/**
 * Social platforms a testimonial can be attributed to.
 * Keys are stored on testimonials.source and rendered with brand colors.
 */
class TestimonialSources
{
    /**
     * @return array<string, array{label: string, icon: string, color: string, color_soft: string}>
     */
    public static function all(): array
    {
        return [
            'instagram' => [
                'label' => 'Instagram',
                'icon' => 'fab fa-instagram',
                'color' => '#E1306C',
                'color_soft' => '#fce7ef',
            ],
            'facebook' => [
                'label' => 'Facebook',
                'icon' => 'fab fa-facebook-f',
                'color' => '#1877F2',
                'color_soft' => '#e8f1fe',
            ],
            'tiktok' => [
                'label' => 'TikTok',
                'icon' => 'fab fa-tiktok',
                'color' => '#111111',
                'color_soft' => '#ececec',
            ],
            'whatsapp' => [
                'label' => 'WhatsApp',
                'icon' => 'fab fa-whatsapp',
                'color' => '#25D366',
                'color_soft' => '#e9f9ef',
            ],
            'google' => [
                'label' => 'Google',
                'icon' => 'fab fa-google',
                'color' => '#EA4335',
                'color_soft' => '#fce8e6',
            ],
            'x' => [
                'label' => 'X',
                'icon' => 'fab fa-x-twitter',
                'color' => '#0a0a0a',
                'color_soft' => '#ececec',
            ],
            'youtube' => [
                'label' => 'YouTube',
                'icon' => 'fab fa-youtube',
                'color' => '#FF0000',
                'color_soft' => '#ffe5e5',
            ],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function isValid(?string $key): bool
    {
        return $key !== null && $key !== '' && isset(self::all()[$key]);
    }

    public static function get(?string $key): ?array
    {
        if (! self::isValid($key)) {
            return null;
        }

        return self::all()[$key];
    }

    public static function label(?string $key): ?string
    {
        return self::get($key)['label'] ?? null;
    }
}
