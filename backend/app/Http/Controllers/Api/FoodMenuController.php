<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\CrossLocationMenuItemResource;
use App\Http\Resources\MenuItemResource;
use App\Models\Location;
use App\Models\MenuItem;
use App\Observers\MenuItemObserver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FoodMenuController extends Controller
{
    /**
     * GET /api/food-menu
     *
     * Cross-location public endpoint. Returns every available menu item across
     * all venues. Each item carries `available_at: string[]` — the slugs of the
     * locations where it is offered. Items with an empty array exist in the
     * catalogue but are not currently stocked at any location; the frontend
     * filters or dims them accordingly.
     *
     * Cached in Redis (array in tests) for 5 minutes. Cache is versioned: the
     * MenuItemObserver and LocationMenuItemPivot model increment a version key
     * on every save/delete so that the old entry is naturally orphaned and the
     * next request gets a fresh query result.
     */
    public function crossLocation(): JsonResponse
    {
        $version = (int) Cache::get(MenuItemObserver::CACHE_VERSION_KEY, 0);
        $cacheKey = "food_menu_public:v{$version}";

        $items = Cache::remember($cacheKey, 300, function () {
            return MenuItem::query()
                ->currentlyAvailable()
                ->with('locations:id,slug')
                ->orderBy('category')
                ->orderBy('name')
                ->get();
        });

        return $this->successResponse(CrossLocationMenuItemResource::collection($items));
    }

    /**
     * GET /api/locations/{location}/food-menu
     *
     * Per-location endpoint used by the booking flow and admin/internal paths.
     * Returns items attached to the given location, grouped by category.
     * Respects both global `unavailable_at` and the pivot-level `unavailable_at`.
     */
    public function index(Location $location, Request $request): JsonResponse
    {
        $query = $location->menuItems()
            ->currentlyAvailable()
            ->wherePivotNull('unavailable_at')
            ->orderBy('menu_items.category')
            ->orderBy('menu_items.name');

        if ($request->filled('category')) {
            $query->where('menu_items.category', $request->input('category'));
        }

        $items = $query->get();

        $grouped = $items
            ->groupBy(fn ($item) => $item->category->value)
            ->map(fn ($group) => MenuItemResource::collection($group));

        return $this->successResponse($grouped);
    }
}
