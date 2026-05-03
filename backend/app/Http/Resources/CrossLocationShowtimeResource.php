<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cross-location showtime resource for GET /api/movies/{slug}/showtimes.
 *
 * Embeds a `location` object (slug, name, latitude, longitude) so the
 * frontend can group showtimes by venue and compute distance captions
 * client-side without a second request.
 *
 * Uses snake_case keys to match the contract in
 * docs/plans/backend/features/2026-05-02-cross-location-content-api.md § Task 2.
 *
 * The existing ShowtimeResource (camelCase, no location embed) is left untouched
 * and continues to serve the per-location booking-flow endpoint.
 */
class CrossLocationShowtimeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Location $location */
        $location = $this->auditorium->location;

        return [
            'id' => $this->id,
            'movie_id' => $this->movie_id,
            'movie_slug' => $this->movie->slug,
            'screen_id' => $this->auditorium_id,
            'screen_name' => $this->auditorium->name,
            'start_time' => $this->start_time->toIso8601String(),
            'end_time' => $this->end_time->toIso8601String(),
            'price_standard' => (int) $this->price_standard,
            'price_premium' => (int) $this->price_premium,
            'price_accessible' => (int) $this->price_accessible,
            'location' => [
                'slug' => $location->slug,
                'name' => $location->name,
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
            ],
        ];
    }
}
