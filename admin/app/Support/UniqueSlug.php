<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Generates URL-safe, collision-free slugs for any Eloquent model.
 * Supports plain string columns and JSON locale keys (e.g. slug->en).
 */
final class UniqueSlug
{
    public static function make(
        string $modelClass,
        string $source,
        string $column = 'slug',
        ?string $ignoreId = null,
        ?string $jsonLocale = null,
        string $fallback = 'item',
    ): string {
        /** @var class-string<Model> $modelClass */
        $base = self::normalize($source) ?: $fallback;
        $slug = $base;
        $i = 2;

        while (self::isTaken($modelClass, $slug, $column, $ignoreId, $jsonLocale)) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /**
     * Normalize a candidate into a URL-safe slug (lowercase, hyphenated).
     */
    public static function normalize(string $source): string
    {
        return Str::slug(trim($source));
    }

    /**
     * Whether a slug value is already claimed on the given model/column.
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function isTaken(
        string $modelClass,
        string $slug,
        string $column = 'slug',
        ?string $ignoreId = null,
        ?string $jsonLocale = null,
    ): bool {
        $query = $modelClass::query();

        if ($jsonLocale) {
            $query->where("{$column}->{$jsonLocale}", $slug);
        } else {
            $query->where($column, $slug);
        }

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
