<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/** Versioned-key invalidation for the public site-content cache (FeaturedSlide pattern). */
class SiteSettingObserver
{
    public const string CACHE_VERSION_KEY = 'site_settings_public_version';

    public static function bumpVersion(): void
    {
        if (Cache::increment(self::CACHE_VERSION_KEY) === false) {
            Cache::forever(self::CACHE_VERSION_KEY, 1);
        }
    }

    public function saved(SiteSetting $setting): void
    {
        if ($setting->wasRecentlyCreated || $setting->wasChanged()) {
            self::bumpVersion();
        }
    }

    public function deleted(SiteSetting $setting): void
    {
        self::bumpVersion();
    }
}
