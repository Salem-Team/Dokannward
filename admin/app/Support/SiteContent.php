<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Str;

/**
 * Structured, non-technical website copy stored in site_settings.
 * Keys: content_home, content_about, content_contact, content_nav, content_faq, content_footer.
 */
class SiteContent
{
    public const KEYS = [
        'home' => 'content_home',
        'about' => 'content_about',
        'contact' => 'content_contact',
        'nav' => 'content_nav',
        'faq' => 'content_faq',
        'footer' => 'content_footer',
    ];

    public static function defaults(): array
    {
        return [
            'home' => [
                'hero_image' => '/images/hero-layers/hero-base.jpg',
                'hero_wordmark' => '/images/dokan-ward-logo.png',
                'hero_alt' => 'DOKAN WARD — Home Decor',
                'arrivals_eyebrow' => 'Just dropped',
                'arrivals_title' => 'Fresh finds for your home',
                'arrivals_link_label' => 'View all',
                'testimonials_eyebrow' => 'Client voices',
                'testimonials_title' => 'What people say about Dokan Ward',
            ],
            'about' => [
                'hero_eyebrow' => 'About Dokan Ward',
                'hero_title' => "Bring nature\nindoors",
                'hero_subtitle' => 'Premium home décor pieces — plants, vases, bakhoor, candles, and boho style — curated with care since 2018.',
                'hero_image' => '/images/hero-layers/hero-base.jpg',
                'story_eyebrow' => 'Who we are',
                'story_title' => 'The Story Behind Dokan Ward',
                'story_image' => '/images/dokan-ward-logo.png',
                'story_paragraphs' => [
                    'Dokan Ward was founded to bring a calm, natural atmosphere into Egyptian homes — without compromising on craft or style.',
                    'From artificial greenery and statement vases to bakhoor burners, candle holders, and boho accents, every piece is chosen to feel warm, elegant, and lived-in.',
                    'Whether you are styling a corner, a wall, or a full room, our collections help you build spaces that feel personal and timeless.',
                ],
                'quote' => 'Bring nature indoors with pieces that feel warm, elegant, and full of soul.',
                'quote_attribution' => '— Dokan Ward',
                'pillars_eyebrow' => 'Our principles',
                'pillars_title' => 'What we stand for',
                'pillars' => [
                    ['title' => 'Natural calm', 'text' => 'Greenery, soft materials, and organic forms that make every room feel fresher and more grounded.'],
                    ['title' => 'Thoughtful craft', 'text' => 'We select décor for finish, proportion, and lasting presence — pieces you will keep styling for years.'],
                    ['title' => 'Warm hospitality', 'text' => 'From bakhoor to trays and candlelight, our edit is made for welcoming Egyptian homes.'],
                ],
                'journey_eyebrow' => 'The process',
                'journey_title' => 'From our atelier to your home',
                'journey' => [
                    ['title' => 'Select', 'text' => 'We source décor that balances beauty, durability, and everyday practicality.'],
                    ['title' => 'Style', 'text' => 'Collections are edited into clear categories so you can shop by mood — plants, vases, boho, light, and more.'],
                    ['title' => 'Deliver', 'text' => 'Across Egypt, we ship with care so your new pieces arrive ready to transform the space.'],
                ],
                'edit_eyebrow' => 'The shop',
                'edit_title' => "Home décor.\nEvery corner.",
                'edit_text' => 'Explore wall art & clocks, trees, vases, bakhoor, candle holders, and boho finds — all in one curated home edit.',
                'edit_button_label' => 'Explore collections',
                'edit_button_href' => '/collections',
                'edit_image' => '/images/hero-layers/hero-base.jpg',
                'cta_eyebrow' => 'Visit & connect',
                'cta_primary_label' => 'Contact us',
                'cta_secondary_label' => 'Shop the catalog',
            ],
            'contact' => [
                'eyebrow' => 'Get in touch',
                'title' => 'Contact',
                'lede' => 'Questions about an order, a piece, or styling advice? Message us — we reply with care. Hotline: 01069503631',
                'form_title' => 'Send a message',
                'form_button' => 'Send message',
            ],
            'nav' => [
                'items' => [
                    ['label' => 'Home', 'href' => '/'],
                    ['label' => 'Shop', 'href' => '/collections/all'],
                    ['label' => 'Collections', 'href' => '/collections'],
                    ['label' => 'About', 'href' => '/pages/about'],
                    ['label' => 'Contact', 'href' => '/pages/contact'],
                ],
            ],
            'faq' => [
                'eyebrow' => 'Support',
                'title' => 'Our Policies',
                'items' => [
                    [
                        'q' => 'What is the return policy?',
                        'a' => "Our goal is for every customer to be totally satisfied with their purchase. If this isn't the case, let us know and we'll do our best to work with you to make it right.",
                        'tag' => 'Returns',
                    ],
                    [
                        'q' => 'When will I get my order?',
                        'a' => 'We will work quickly to ship your order as soon as possible. Once your order has shipped, you will receive an email with further information. Delivery times vary depending on your location.',
                        'tag' => 'Delivery',
                    ],
                    [
                        'q' => 'Where are your products manufactured?',
                        'a' => 'Our products are manufactured globally. We carefully select our manufacturing partners to ensure our products are high quality and a fair value.',
                        'tag' => 'Origin',
                    ],
                ],
            ],
            'footer' => [
                'trust' => [
                    'Curated Home Decor',
                    'Delivery Across Egypt',
                    'Secure Payments',
                    'Since 2018',
                ],
                'customer_care' => [
                    ['label' => 'Contact Us', 'href' => '/pages/contact'],
                    ['label' => 'Shipping & Delivery', 'href' => '/policies/shipping-policy'],
                    ['label' => 'Returns & Exchanges', 'href' => '/policies/refund-policy'],
                ],
                'information' => [
                    ['label' => 'About Us', 'href' => '/pages/about'],
                    ['label' => 'Terms & Conditions', 'href' => '/policies/terms-of-service'],
                    ['label' => 'Privacy Policy', 'href' => '/policies/privacy-policy'],
                ],
            ],
        ];
    }

    public static function get(string $section): array
    {
        $defaults = self::defaults();
        if (! isset($defaults[$section], self::KEYS[$section])) {
            return [];
        }

        $stored = SiteSetting::where('key', self::KEYS[$section])->first()?->value ?? [];

        $merged = self::mergeRecursive($defaults[$section], is_array($stored) ? $stored : []);

        // Empty FAQ list should keep the storefront defaults (same as before CMS wipe).
        if ($section === 'faq' && empty($merged['items'])) {
            $merged['items'] = $defaults['faq']['items'];
        }

        return $merged;
    }

    public static function all(): array
    {
        $out = [];
        foreach (array_keys(self::KEYS) as $section) {
            $out[$section] = self::get($section);
        }

        return $out;
    }

    public static function put(string $section, array $value): void
    {
        if (! isset(self::KEYS[$section])) {
            return;
        }

        $setting = SiteSetting::firstOrNew(['key' => self::KEYS[$section]]);
        if (! $setting->exists) {
            $setting->id = (string) Str::uuid();
        }
        $setting->value = $value;
        $setting->save();
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private static function mergeRecursive(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && self::isAssoc($base[$key])) {
                $base[$key] = self::mergeRecursive($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    private static function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /** Friendly destination choices for menu / footer links. */
    public static function linkPresets(): array
    {
        return [
            '/' => 'Home',
            '/brands' => 'Brands',
            '/collections' => 'Collections',
            '/collections/all' => 'Full catalog',
            '/pages/about' => 'About',
            '/pages/contact' => 'Contact',
            '/policies/privacy-policy' => 'Privacy Policy',
            '/policies/terms-of-service' => 'Terms of Service',
            '/policies/refund-policy' => 'Refund & Returns',
            '/policies/shipping-policy' => 'Shipping Policy',
        ];
    }
}
