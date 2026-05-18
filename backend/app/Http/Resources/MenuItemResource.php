<?php

namespace App\Http\Resources;

use App\Support\AssetUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->pivot?->price_override ?? $this->price,
            'category' => $this->category->value,
            'imageUrl' => AssetUrl::resolve($this->image_url),
            'allergens' => $this->allergens ?? [],
            'dietary' => $this->dietary ?? [],
            'available' => $this->available && is_null($this->pivot?->unavailable_at),
        ];
    }
}
