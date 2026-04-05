<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\MenuItemResource;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FoodMenuController extends Controller
{
    public function index(Location $location, Request $request): JsonResponse
    {
        $query = $location->menuItems()
            ->currentlyAvailable()
            ->locationAvailable()
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
