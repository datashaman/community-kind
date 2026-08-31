<?php

namespace App\Concerns;

use App\Models\OrganisationSlug;
use Illuminate\Support\Str;

trait GeneratesUniqueOrganisationSlugs
{
    private const int MAXIMUM_SLUG_LENGTH = 63;

    /**
     * Generate a unique slug for the organisation.
     */
    protected static function generateUniqueOrganisationSlug(string $name, ?int $excludeId = null): string
    {
        $baseSlug = rtrim(Str::substr(Str::slug($name), 0, self::MAXIMUM_SLUG_LENGTH), '-');
        $searchPrefix = Str::substr($baseSlug, 0, min(strlen($baseSlug), 31));

        $existingSlugs = static::withTrashed()
            ->where('slug', 'like', $searchPrefix.'%')
            ->when($excludeId !== null, fn ($query) => $query->whereKeyNot($excludeId))
            ->pluck('slug')
            ->merge(OrganisationSlug::query()
                ->where('slug', 'like', $searchPrefix.'%')
                ->where('quarantined_until', '>', now())
                ->pluck('slug'))
            ->unique();

        $maxSuffix = $existingSlugs
            ->map(function (string $slug) use ($baseSlug): ?int {
                if ($slug === $baseSlug) {
                    return 0;
                }

                if (preg_match('/-(\d+)$/', $slug, $matches) !== 1) {
                    return null;
                }

                $suffixNumber = (int) $matches[1];

                return self::slugWithSuffix($baseSlug, $suffixNumber) === $slug
                    ? $suffixNumber
                    : null;
            })
            ->filter(fn (?int $suffix) => $suffix !== null)
            ->max();

        return $maxSuffix === null
            ? $baseSlug
            : self::slugWithSuffix($baseSlug, $maxSuffix + 1);
    }

    private static function slugWithSuffix(string $baseSlug, int $suffixNumber): string
    {
        $suffix = '-'.$suffixNumber;
        $prefix = rtrim(Str::substr(
            $baseSlug,
            0,
            self::MAXIMUM_SLUG_LENGTH - strlen($suffix),
        ), '-');

        return $prefix.$suffix;
    }
}
