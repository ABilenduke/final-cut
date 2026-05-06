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

            // Single-line display address — kept stable for the existing
            // customer frontend contract (app/types/location.ts `address` field).
            'address' => $this->formatAddress(),

            // Structured address parts — required by /locations and /locations/:slug
            // frontend pages for LocalBusiness JSON-LD and the location detail panel.
            'street' => $this->street,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,

            // Contact
            'phone' => $this->phone,
            'email' => $this->email,

            // Geolocation — exposed for client-side distance computation
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,

            // Timezone — required to interpret hours and display local showtimes
            'timezone' => $this->timezone,

            // Business hours keyed by lowercase day name.
            // Shape: { "monday": { "open": "HH:MM", "close": "HH:MM" } | null, ... }
            // Null day value = closed. Hours are local to `timezone`.
            'hours' => $this->hours,
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
