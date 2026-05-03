<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\FeaturedSlide;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates the featured-slides public cache when a slide is created,
 * updated, or deleted.
 *
 * The cache version key is a simple integer stored under CACHE_VERSION_KEY.
 * The controller builds its remember-key by combining the base key with the
 * current version number. Incrementing the version effectively orphans the
 * old cache entry and forces a fresh DB query on the next request.
 */
class FeaturedSlideObserver
{
    public const string CACHE_VERSION_KEY = 'featured_slides_public_version';

    public static function bumpVersion(): void
    {
        Cache::increment(self::CACHE_VERSION_KEY);
    }

    public function saved(FeaturedSlide $slide): void
    {
        // Skip no-op saves (touch() or save() with no dirty columns) — they
        // would orphan a still-valid cache entry without changing payload.
        // wasRecentlyCreated covers fresh inserts, where wasChanged() reports
        // no changed columns even though the row is brand new.
        if ($slide->wasRecentlyCreated || $slide->wasChanged()) {
            self::bumpVersion();
        }
    }

    public function deleted(FeaturedSlide $slide): void
    {
        self::bumpVersion();
    }
}
