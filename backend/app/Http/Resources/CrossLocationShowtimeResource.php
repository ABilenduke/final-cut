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
 * camelCase keys match the frontend Showtime type (frontend/app/types/showtime.ts)
 * and the existing per-location ShowtimeResource. The earlier snake_case
 * draft drifted from those consumers and broke the movie-detail page —
 * see PR #50 review feedback.
 *
 * latitude/longitude preserve null for un-geocoded venues so the frontend's
 * "not geocoded yet" branch keeps working — casting null to float would
 * coerce to 0.0 and place the venue at the prime meridian.
 */
class CrossLocationShowtimeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Location $location */
        $location = $this->auditorium->location;

        return [
            'id' => $this->id,
            'movieId' => $this->movie_id,
            'movieSlug' => $this->movie->slug,
            'screenId' => $this->auditorium_id,
            'screenName' => $this->auditorium->name,
            'startTime' => $this->start_time->toIso8601String(),
            'endTime' => $this->end_time->toIso8601String(),
            'priceStandard' => (int) $this->price_standard,
            'pricePremium' => (int) $this->price_premium,
            'priceAccessible' => (int) $this->price_accessible,
            'location' => [
                'slug' => $location->slug,
                'name' => $location->name,
                'latitude' => $location->latitude !== null ? (float) $location->latitude : null,
                'longitude' => $location->longitude !== null ? (float) $location->longitude : null,
            ],
        ];
    }
}
