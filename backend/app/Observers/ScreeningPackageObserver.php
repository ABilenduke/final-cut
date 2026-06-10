<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ScreeningPackage;
use Illuminate\Support\Facades\Cache;

/** Versioned-key invalidation for the public packages cache (FeaturedSlide pattern). */
class ScreeningPackageObserver
{
    public const string CACHE_VERSION_KEY = 'screening_packages_public_version';

    public static function bumpVersion(): void
    {
        if (Cache::increment(self::CACHE_VERSION_KEY) === false) {
            Cache::forever(self::CACHE_VERSION_KEY, 1);
        }
    }

    public function saved(ScreeningPackage $package): void
    {
        if ($package->wasRecentlyCreated || $package->wasChanged()) {
            self::bumpVersion();
        }
    }

    public function deleted(ScreeningPackage $package): void
    {
        self::bumpVersion();
    }
}
