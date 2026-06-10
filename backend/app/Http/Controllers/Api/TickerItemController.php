<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\TickerItemResource;
use App\Models\TickerItem;
use App\Observers\TickerItemObserver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class TickerItemController extends Controller
{
    /**
     * GET /api/ticker-items
     *
     * Public endpoint feeding the Neural Ticker in the default layout.
     * Versioned-cache pattern identical to FeaturedSlideController — the
     * resolved resource arrays are cached for 5 minutes under a key that
     * TickerItemObserver orphans on every admin write.
     *
     * An empty result is valid — the frontend renders its hardcoded brand
     * fallback items when the array is empty.
     */
    public function index(): JsonResponse
    {
        $version = (int) Cache::get(TickerItemObserver::CACHE_VERSION_KEY, 0);
        $cacheKey = "ticker_items_public:v{$version}";

        $data = Cache::remember($cacheKey, 300, function () {
            return TickerItem::active()
                ->orderBy('display_order')
                ->orderBy('id')
                ->get()
                ->map(fn (TickerItem $item) => (new TickerItemResource($item))->resolve())
                ->all();
        });

        return $this->successResponse($data);
    }
}
