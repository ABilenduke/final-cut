<?php

namespace App\Models;

use App\Enums\MovieStatus;
use Database\Factories\MovieFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tmdb_id', 'slug', 'title', 'tagline', 'synopsis', 'runtime',
    'rating', 'release_date', 'genres', 'cast', 'poster_url', 'backdrop_url',
    'trailer_key', 'status', 'tmdb_enriched_at',
])]
class Movie extends Model
{
    /** @use HasFactory<MovieFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tmdb_id' => 'integer',
            'genres' => 'array',
            'cast' => 'array',
            'release_date' => 'date',
            'status' => MovieStatus::class,
            'rating' => 'decimal:1',
            'runtime' => 'integer',
            'tmdb_enriched_at' => 'datetime',
        ];
    }

    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class);
    }
}
