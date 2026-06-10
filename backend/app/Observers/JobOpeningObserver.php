<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\JobOpening;
use Illuminate\Support\Facades\Cache;

/** Versioned-key invalidation for the public job-openings cache (FeaturedSlide pattern). */
class JobOpeningObserver
{
    public const string CACHE_VERSION_KEY = 'job_openings_public_version';

    public static function bumpVersion(): void
    {
        if (Cache::increment(self::CACHE_VERSION_KEY) === false) {
            Cache::forever(self::CACHE_VERSION_KEY, 1);
        }
    }

    public function saved(JobOpening $opening): void
    {
        if ($opening->wasRecentlyCreated || $opening->wasChanged()) {
            self::bumpVersion();
        }
    }

    public function deleted(JobOpening $opening): void
    {
        self::bumpVersion();
    }
}
