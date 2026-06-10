<?php

namespace App\Http\Resources;

use App\Models\Movie;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Movie */
class MovieResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'tagline' => $this->tagline,
            'synopsis' => $this->synopsis,
            'runtime' => $this->runtime,
            'rating' => $this->rating,
            'releaseDate' => $this->release_date instanceof \DateTimeInterface
                ? $this->release_date->format('Y-m-d')
                : $this->release_date,
            'genres' => $this->genres ?? [],
            'cast' => $this->cast ?? [],
            'posterUrl' => $this->poster_url,
            'backdropUrl' => $this->backdrop_url,
            'trailerKey' => $this->trailer_key,
            'status' => $this->status->value,
            'homeFeaturedAt' => $this->home_featured_at?->toIso8601String(),
            'credits' => $this->credits,
            'pressQuotes' => $this->press_quotes ?? [],
            'clips' => $this->clips ?? [],
        ];
    }
}
