<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Single resolver for public asset URLs returned in API responses.
 *
 * Image columns across the app store one of three shapes:
 *   - null                              → no asset
 *   - absolute URL ("https://...")      → seeded references (concession CDN photos,
 *                                         TMDB poster URLs) — passed through unchanged
 *   - relative path ("menu-items/x.png")→ Filament uploads on the `public` disk —
 *                                         resolved via Storage::disk('public')->url($path),
 *                                         which honours whatever the disk is bound to
 *                                         (local in dev, DO Spaces CDN in prod)
 */
final class AssetUrl
{
    public static function resolve(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return Storage::disk('public')->url($value);
    }
}
