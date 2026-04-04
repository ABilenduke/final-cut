<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovieListResource extends JsonResource
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
            'posterUrl' => $this->poster_url,
            'backdropUrl' => $this->backdrop_url,
            'trailerKey' => $this->trailer_key,
            'status' => $this->status,
        ];
    }
}
