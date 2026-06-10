<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\FaqItem;
use Illuminate\Support\Facades\Cache;

/** Versioned-key invalidation for the public FAQ cache (FeaturedSlide pattern). */
class FaqItemObserver
{
    public const string CACHE_VERSION_KEY = 'faq_items_public_version';

    public static function bumpVersion(): void
    {
        if (Cache::increment(self::CACHE_VERSION_KEY) === false) {
            Cache::forever(self::CACHE_VERSION_KEY, 1);
        }
    }

    public function saved(FaqItem $item): void
    {
        if ($item->wasRecentlyCreated || $item->wasChanged()) {
            self::bumpVersion();
        }
    }

    public function deleted(FaqItem $item): void
    {
        self::bumpVersion();
    }
}
