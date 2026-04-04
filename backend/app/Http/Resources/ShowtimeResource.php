<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShowtimeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'movieId' => $this->movie_id,
            'movieSlug' => $this->movie->slug,
            'movieTitle' => $this->movie->title,
            'screenId' => $this->auditorium_id,
            'screenName' => $this->auditorium->name,
            'startTime' => $this->start_time->toIso8601String(),
            'endTime' => $this->end_time->toIso8601String(),
            'priceStandard' => $this->price_standard,
            'pricePremium' => $this->price_premium,
            'priceAccessible' => $this->price_accessible,
        ];
    }
}
