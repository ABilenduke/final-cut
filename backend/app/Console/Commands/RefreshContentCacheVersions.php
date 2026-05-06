<?php

namespace App\Console\Commands;

use App\Observers\FeaturedSlideObserver;
use App\Observers\MenuItemObserver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Increments the version keys behind the public content caches so that
 * any time-windowed records (featured slides, menu items) re-resolve
 * against a fresh "now" at least once per day.
 *
 * The observers behind these caches only bump on admin writes — a slide
 * whose `ends_at` passes overnight, or whose `starts_at` arrives at
 * 09:00, would otherwise wait for the 5-minute Cache::remember TTL to
 * expire before the next request rebuilt the entry. That's already
 * tight, but a daily floor caps the worst-case staleness regardless of
 * any future drift in the TTL or observer wiring.
 *
 * Deliberately scoped: only content caches. Idempotency keys
 * (gift_card_idempotency:*, pending_booking:*, pending_gift_card:*)
 * and rate-limit counters must NOT be touched — they protect in-flight
 * transactions. Use `php artisan cache:clear` only when you actually
 * want to nuke everything.
 */
class RefreshContentCacheVersions extends Command
{
    protected $signature = 'cache:refresh-content-versions';

    protected $description = 'Bump content cache version keys (featured slides, food menu) so time-windowed records re-resolve';

    public function handle(): int
    {
        $keys = [
            FeaturedSlideObserver::CACHE_VERSION_KEY,
            MenuItemObserver::CACHE_VERSION_KEY,
        ];

        foreach ($keys as $key) {
            // The database cache store returns false from increment() when
            // the key is absent (only Redis/array seed atomically). Prime
            // missing keys to 1 so the controllers' cached entries flip to
            // a new versioned key on the next read regardless of driver.
            $next = Cache::increment($key);
            if ($next === false) {
                Cache::forever($key, 1);
                $next = 1;
            }
            $this->info("Bumped {$key} → {$next}");
        }

        return self::SUCCESS;
    }
}
