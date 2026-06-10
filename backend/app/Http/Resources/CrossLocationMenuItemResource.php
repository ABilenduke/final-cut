<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MenuItem;
use App\Support\AssetUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for the cross-location food menu endpoint (GET /api/food-menu).
 *
 * Distinct from MenuItemResource (which exposes per-location pivot data for the
 * location-scoped endpoint). This resource includes `available_at: string[]`
 * (array of location slugs) and uses the item's base price only.
 */
/** @mixin MenuItem */
class CrossLocationMenuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (int) $this->price,
            'category' => $this->category->value,
            'imageUrl' => AssetUrl::resolve($this->image_url),
            'allergens' => $this->allergens ?? [],
            'dietary' => $this->dietary ?? [],
            'featuredOnHomeAt' => $this->featured_on_home_at?->toIso8601String(),
            'available_at' => $this->whenLoaded(
                'locations',
                fn () => $this->locations->pluck('slug')->all(),
                [],
            ),
        ];
    }
}
