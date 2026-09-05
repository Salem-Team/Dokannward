<?php

namespace App\Support;

use App\Models\Page;
use Illuminate\Support\Str;

/**
 * Canonical storefront legal policies edited from Admin → Policies.
 *
 * Each policy maps to a published `pages` row at /policies/{slug}.
 * Starter HTML lives in resources/policies/*.html.
 */
class StorePolicies
{
    /**
     * @return array<string, array{slug: string, title: string, description: string, icon: string, tone: string, position: int, meta_description: string}>
     */
    public static function catalog(): array
    {
        return [
            'privacy-policy' => [
                'slug' => 'privacy-policy',
                'title' => 'Privacy Policy',
                'description' => 'How you collect, use, and protect customer data.',
                'icon' => 'fas fa-shield-halved',
                'tone' => 'cool',
                'position' => 10,
                'meta_description' => 'How Dokan Ward collects, uses, and protects your personal information.',
            ],
            'terms-of-service' => [
                'slug' => 'terms-of-service',
                'title' => 'Terms & Conditions',
                'description' => 'Rules for browsing, ordering, and using the store.',
                'icon' => 'fas fa-file-contract',
                'tone' => 'ink',
                'position' => 20,
                'meta_description' => 'Terms that govern your use of the Dokan Ward website and purchases.',
            ],
            'refund-policy' => [
                'slug' => 'refund-policy',
                'title' => 'Refund & Returns',
                'description' => 'Return windows, exchanges, and how refunds work.',
                'icon' => 'fas fa-rotate-left',
                'tone' => 'warm',
                'position' => 30,
                'meta_description' => 'Dokan Ward return eligibility, exchanges, and refund timelines.',
            ],
            'shipping-policy' => [
                'slug' => 'shipping-policy',
                'title' => 'Shipping Policy',
                'description' => 'Processing times, delivery windows, and tracking.',
                'icon' => 'fas fa-truck-fast',
                'tone' => 'cool',
                'position' => 40,
                'meta_description' => 'How Dokan Ward processes, ships, and delivers your orders.',
            ],
        ];
    }

    public static function isPolicySlug(string $slug): bool
    {
        return isset(self::catalog()[$slug]);
    }

    public static function definition(string $slug): ?array
    {
        return self::catalog()[$slug] ?? null;
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys(self::catalog());
    }

    /** @return list<string> */
    public static function storefrontPaths(): array
    {
        return array_map(
            fn (string $slug) => "/policies/{$slug}",
            self::slugs(),
        );
    }

    public static function starterHtml(string $slug): string
    {
        $path = resource_path("policies/{$slug}.html");
        if (! is_file($path)) {
            return '<p>Write your policy content here.</p>';
        }

        return (string) file_get_contents($path);
    }

    /**
     * Ensure the policy page exists (create with starter HTML when missing).
     * Does not overwrite content that an admin already saved.
     */
    public static function ensure(string $slug): Page
    {
        $def = self::definition($slug);
        abort_unless($def, 404);

        $page = Page::where('slug', $slug)->first();
        if ($page) {
            return $page;
        }

        return Page::create([
            'id' => (string) Str::uuid(),
            'slug' => $slug,
            'title' => $def['title'],
            'content' => HtmlSanitizer::clean(self::starterHtml($slug)),
            'meta_title' => $def['title'],
            'meta_description' => $def['meta_description'],
            'position' => $def['position'],
            'published' => true,
        ]);
    }

    /**
     * Ensure all canonical policies exist. Safe to call repeatedly.
     *
     * @return array<string, Page>
     */
    public static function ensureAll(): array
    {
        $out = [];
        foreach (self::slugs() as $slug) {
            $out[$slug] = self::ensure($slug);
        }

        return $out;
    }

    /**
     * Hub cards: definition + live page status.
     *
     * @return list<array<string, mixed>>
     */
    public static function hubCards(): array
    {
        $pages = self::ensureAll();
        $cards = [];

        foreach (self::catalog() as $slug => $def) {
            $page = $pages[$slug];
            $wordCount = str_word_count(strip_tags((string) $page->content));
            $cards[] = [
                ...$def,
                'page' => $page,
                'published' => (bool) $page->published,
                'has_content' => trim(strip_tags((string) $page->content)) !== '',
                'word_count' => $wordCount,
                'updated_at' => $page->updated_at,
                'path' => "/policies/{$slug}",
            ];
        }

        return $cards;
    }
}
