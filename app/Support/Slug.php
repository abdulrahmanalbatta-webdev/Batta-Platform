<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * URL slugs for courses and articles, built like the dashboard's preview: Latin letters and digits only,
 * so "Next.js من الصفر" becomes "nextjs". A title with no Latin letters gets "<fallback>-xxxxxx".
 */
class Slug
{
    /**
     * @param  class-string<Model>  $model
     */
    public static function unique(string $model, string $source, string $fallback, ?int $ignoreId = null): string
    {
        $base = Str::slug(preg_replace('/[^A-Za-z0-9\s-]/', '', $source));

        if ($base === '') {
            $base = $fallback.'-'.Str::lower(Str::random(6));
        }

        $base = Str::limit($base, 80, '');
        $slug = $base;

        for ($suffix = 2; self::taken($model, $slug, $ignoreId); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private static function taken(string $model, string $slug, ?int $ignoreId): bool
    {
        return $model::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
