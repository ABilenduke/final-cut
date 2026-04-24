<?php

namespace App\Http\Resources;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Location */
class LocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            // Single-line display address derived from the structured parts.
            // The customer frontend contract (app/types/location.ts) ships a
            // single `address` string; keep it stable while the admin panel
            // now stores the parts in dedicated columns.
            'address' => $this->formatAddress(),
        ];
    }

    private function formatAddress(): string
    {
        $line = array_filter([
            $this->street,
            $this->city,
            trim(($this->state ?? '').' '.($this->postal_code ?? '')),
        ]);

        return implode(', ', array_map('trim', $line));
    }
}
