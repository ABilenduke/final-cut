<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\FeaturedSlideResource;
use App\Models\FeaturedSlide;
use App\Observers\FeaturedSlideObserver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class FeaturedSlideController extends Controller
{
    /**
     * GET /api/featured-slides
     *
     * Public endpoint. Returns currently-active home hero carousel slides
     * ordered by display_order ASC, id ASC.
     *
     * A slide is active when:
     *   - published_at IS NOT NULL and published_at <= now()
     *   - starts_at IS NULL OR starts_at <= now()
     *   - ends_at   IS NULL OR ends_at   >= now()
     *
     * Cached in Redis (array driver in tests) for 5 minutes. Cache is versioned:
     * FeaturedSlideObserver increments a version key on every save/delete so the
     * old entry is naturally orphaned and the next request gets a fresh result.
     *
     * An empty result is a valid response — the frontend renders a hardcoded
     * brand fallback slide when the array is empty.
     */
    public function index(): JsonResponse
    {
        $version = (int) Cache::get(FeaturedSlideObserver::CACHE_VERSION_KEY, 0);
        $cacheKey = "featured_slides_public:v{$version}";

        // Cache the resolved resource array, not the Eloquent Collection.
        // Laravel 13's `cache.serializable_classes => false` default refuses
        // to deserialize any class out of the cache (gadget-chain protection),
        // so storing models would round-trip as __PHP_Incomplete_Class.
        $data = Cache::remember($cacheKey, 300, function () {
            return FeaturedSlide::active()
                ->orderBy('display_order')
                ->orderBy('id')
                ->get()
                ->map(fn (FeaturedSlide $slide) => (new FeaturedSlideResource($slide))->resolve())
                ->all();
        });

        return $this->successResponse($data);
    }
}
