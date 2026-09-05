<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Builds absolute, production-safe URLs for files on the public disk.
 * Never returns localhost / :8000 links once APP_URL is the live domain.
 */
class PublicUrl
{
    public static function storage(string $path): string
    {
        $path = ltrim($path, '/');
        $url = Storage::disk('public')->url($path);

        if (self::isLocal($url)) {
            $base = rtrim((string) (config('app.asset_url') ?: config('app.url')), '/');
            $url = $base.'/storage/'.$path;
        }

        return $url;
    }

    /**
     * Bust browser + /_next/image caches after media replaces.
     * Next.js marks optimizer 404s as `immutable` for a year — a new query
     * string is the only reliable recovery without asking shoppers to hard-refresh.
     */
    public static function versioned(?string $url, mixed $version = null): ?string
    {
        $url = is_string($url) ? trim($url) : '';
        if ($url === '') {
            return null;
        }

        $stamp = null;
        if ($version instanceof \DateTimeInterface) {
            $stamp = (string) $version->getTimestamp();
        } elseif (is_numeric($version)) {
            $stamp = (string) (int) $version;
        } elseif (is_string($version) && $version !== '') {
            $stamp = (string) (strtotime($version) ?: crc32($version));
        }

        if ($stamp === null || $stamp === '' || $stamp === '0') {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'v='.$stamp;
    }

    public static function isLocal(?string $url): bool
    {
        $url = strtolower((string) $url);

        return $url === ''
            || str_contains($url, 'localhost')
            || str_contains($url, '127.0.0.1')
            || str_contains($url, ':8000')
            || str_contains($url, ':8001')
            || str_contains($url, ':3000');
    }
}
