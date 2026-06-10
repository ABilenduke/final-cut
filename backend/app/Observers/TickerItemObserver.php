<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\TickerItem;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates the ticker-items public cache when an item is created,
 * updated, or deleted. Same versioned-key pattern as FeaturedSlideObserver.
 */
class TickerItemObserver
{
    public const string CACHE_VERSION_KEY = 'ticker_items_public_version';

    public static function bumpVersion(): void
    {
        // Database cache store returns false from increment() on a missing
        // key — seed it so the first bump never silently no-ops.
        if (Cache::increment(self::CACHE_VERSION_KEY) === false) {
            Cache::forever(self::CACHE_VERSION_KEY, 1);
        }
    }

    public function saved(TickerItem $item): void
    {
        // Skip no-op saves; wasRecentlyCreated covers fresh inserts where
        // wasChanged() reports nothing.
        if ($item->wasRecentlyCreated || $item->wasChanged()) {
            self::bumpVersion();
        }
    }

    public function deleted(TickerItem $item): void
    {
        self::bumpVersion();
    }
}
