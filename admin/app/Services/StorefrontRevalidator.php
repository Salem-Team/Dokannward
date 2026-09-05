<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asks the Next.js storefront to drop ISR caches so admin edits appear
 * without waiting for the fetch revalidate window (60–300s).
 *
 * No-ops when REVALIDATE_SECRET is unset (local/dev convenience).
 *
 * IMPORTANT: never await Next.js inside the admin request lifecycle.
 * Redis sessions stay locked until PHP terminates — a sync HTTP call
 * (even in afterResponse) blocks Turbo's redirect follow-up and makes
 * "Save" feel stuck behind the brand loader.
 */
class StorefrontRevalidator
{
    /**
     * @param  list<string>  $paths
     * @param  list<string>  $tags
     */
    public static function purge(array $paths = ['/'], array $tags = []): void
    {
        $secret = config('app.revalidate_secret');
        $base = rtrim((string) config('app.frontend_url'), '/');

        if (! $secret || $base === '') {
            return;
        }

        $payload = [
            'secret' => $secret,
            'paths' => array_values(array_unique($paths)),
            'tags' => array_values(array_unique($tags)),
        ];

        $url = "{$base}/api/revalidate";

        if (self::fireAndForget($url, $payload)) {
            return;
        }

        // Last-resort fallback (e.g. Windows / disabled exec): keep it short.
        dispatch(function () use ($url, $payload) {
            try {
                Http::connectTimeout(0.4)
                    ->timeout(1.2)
                    ->acceptJson()
                    ->asJson()
                    ->post($url, $payload);
            } catch (\Throwable $e) {
                Log::debug('Storefront revalidate skipped: '.$e->getMessage());
            }
        })->afterResponse();
    }

    /**
     * Background curl — returns immediately so admin redirects stay snappy.
     *
     * @param  array{secret:string,paths:list<string>,tags:list<string>}  $payload
     */
    private static function fireAndForget(string $url, array $payload): bool
    {
        if (PHP_OS_FAMILY === 'Windows' || ! function_exists('exec')) {
            return false;
        }

        if (! is_dir('/bin') && ! is_dir('/usr/bin')) {
            return false;
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        $curl = self::curlBinary();
        if ($curl === null) {
            return false;
        }

        // Brief delay so freshly stored /storage files are visible to nginx
        // before Next.js regenerates pages that reference them.
        $cmd = '(sleep 2; '.$curl
            .' -sS -m 3'
            .' -X POST '.escapeshellarg($url)
            .' -H '.escapeshellarg('Content-Type: application/json')
            .' -H '.escapeshellarg('Accept: application/json')
            .' --data-binary '.escapeshellarg($json)
            .' >/dev/null 2>&1) >/dev/null 2>&1 &';

        try {
            exec($cmd);

            return true;
        } catch (\Throwable $e) {
            Log::debug('Storefront revalidate background spawn failed: '.$e->getMessage());

            return false;
        }
    }

    private static function curlBinary(): ?string
    {
        foreach (['/usr/bin/curl', '/bin/curl', 'curl'] as $candidate) {
            if ($candidate === 'curl') {
                return 'curl';
            }
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public static function purgeCatalog(): void
    {
        self::purge(
            self::catalogPaths(),
            [
                'catalog',
                'products',
                'brands',
                'categories',
                'collections',
                'banners',
            ],
        );
    }

    /**
     * Drop ISR for a single product PDP plus the catalog surfaces that list it.
     */
    public static function purgeProduct(?\App\Models\Product $product): void
    {
        if (! $product) {
            self::purgeCatalog();

            return;
        }

        $paths = self::catalogPaths();
        $slug = is_string($product->slug) ? $product->slug : null;
        if ($slug) {
            $paths[] = '/products/'.$slug;
        }

        self::purge(
            $paths,
            [
                'catalog',
                'products',
                'product:'.($slug ?: $product->id),
            ],
        );
    }

    /**
     * Index paths plus every live collection / category handle so individual
     * /collections/{slug} pages refresh immediately after admin edits.
     *
     * @return list<string>
     */
    private static function catalogPaths(): array
    {
        $paths = [
            '/',
            '/brands',
            '/collections',
            '/collections/all',
            '/search',
        ];

        try {
            foreach (Collection::query()->get(['slug']) as $collection) {
                $slug = is_array($collection->slug)
                    ? ($collection->slug['en'] ?? null)
                    : $collection->slug;
                if (is_string($slug) && $slug !== '') {
                    $paths[] = '/collections/'.$slug;
                }
            }

            foreach (Category::query()->get(['slug']) as $category) {
                $slug = is_array($category->slug)
                    ? ($category->slug['en'] ?? null)
                    : $category->slug;
                if (is_string($slug) && $slug !== '') {
                    $paths[] = '/collections/'.$slug;
                }
            }

            foreach (Brand::query()->get(['slug']) as $brand) {
                $slug = is_array($brand->slug)
                    ? ($brand->slug['en'] ?? null)
                    : $brand->slug;
                if (is_string($slug) && $slug !== '') {
                    $paths[] = '/brands/'.$slug;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Storefront catalog path expand skipped: '.$e->getMessage());
        }

        return array_values(array_unique($paths));
    }

    /** Currency / store / shipping / tax — refresh chrome, checkout and PDPs. */
    public static function purgeChrome(): void
    {
        self::purge(
            [
                '/',
                '/checkout',
                '/brands',
                '/collections',
                '/collections/all',
                '/search',
            ],
            [
                'storefront-settings',
                'checkout-settings',
                'store-settings',
                'currency-settings',
            ],
        );
    }

    /** Website content hub — nav, about, contact, FAQ, footer, homepage copy. */
    public static function purgeContent(): void
    {
        self::purge(
            [
                '/',
                '/pages/about',
                '/pages/contact',
                '/brands',
                '/collections',
                '/collections/all',
                '/checkout',
            ],
            [
                'site-content',
                'storefront-settings',
            ],
        );
    }
}
